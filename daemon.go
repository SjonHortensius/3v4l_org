package main

import (
	"crypto/sha1"
	"database/sql"
	"encoding/base64"
	"fmt"
	"github.com/lib/pq"
	"io"
	"os"
	"os/exec"
	"os/signal"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
)

type Version struct {
	id       int
	name     string
	command  string
	isHelper bool
	released time.Time
	order    int
	eol      time.Time
}

type Input struct {
	sync.Mutex
	id            int
	short         string
	uniqueOutput  map[string]bool
	penalty       int
	penaltyDetail map[string]int
	created       time.Time
	runArchived   bool
	lastSubmit    time.Time
	mutations     int
}

type Output struct {
	id   int
	raw  string
	hash string
}

type Result struct {
	input      *Input
	output     *Output
	version    *Version
	exitCode   int
	created    time.Time
	userTime   float64
	systemTime float64
	maxMemory  int64
}

type ResourceLimit struct {
	packets int
	runtime int
	output  int
}

// Limits # of parallel execs by using a channel with a limited buffer
type SizedWaitGroup struct {
	current chan bool
	*sync.WaitGroup
}

func newSizedWaitGroup(limit int) SizedWaitGroup {
	return SizedWaitGroup{make(chan bool, limit), &sync.WaitGroup{}}
}
func (s *SizedWaitGroup) Add()  { s.current <- true; s.WaitGroup.Add(1) }
func (s *SizedWaitGroup) Done() { <-s.current; s.WaitGroup.Done() }

func newInput() Input {
	return Input{uniqueOutput: map[string]bool{}, penaltyDetail: map[string]int{}}
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p
	stats.Lock(); stats.c["penalty"] += p; stats.Unlock()

	if p > 1 {
		this.Lock(); this.penaltyDetail[r] = this.penaltyDetail[r] + p; this.Unlock()
	}
}

//FIXME maybe batches shouldn't set state=busy so checkPending won't attempt to run them
func (this *Input) prepare() {
	inputSrc.Lock()
	inputSrc.srcUse[this.short]++
	if 1 == inputSrc.srcUse[this.short] {
		if f, err := os.Create("/in/" + this.short); err != nil {
			panic("prepare: could not create file: "+ err.Error())
		} else {
			var raw []byte
			if err := db.QueryRow(`SELECT raw FROM input_src WHERE input = $1`, this.id).Scan(&raw); err != nil {
				panic("prepare: could not retrieve source: "+ err.Error())
			} else {
				f.Write(raw)
			}

			f.Close()
		}
	}
	inputSrc.Unlock()

	if err := db.QueryRow(`SELECT COALESCE(SUM(mutations), 0) FROM result WHERE input = $1`, this.id).Scan(&this.mutations); err != nil {
		panic("prepare: could not get original mutation count: "+ err.Error())
	}

	if r, err := db.Exec(`UPDATE input SET state = 'busy' WHERE id = $1`, this.id); err != nil {
		panic("Input: failed to update state: "+ err.Error())
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		panic(fmt.Sprintf("Input: failed to update state; %d rows affected, %s", a, err))
	}

	// Perform date fixation
	if this.lastSubmit.IsZero() {
		if err := db.QueryRow(`SELECT MAX(COALESCE(updated, created)) FROM submit WHERE input = $1`, this.id).Scan(&this.lastSubmit); err != nil {
			fmt.Printf("Warning; failed to find any submit of %s, not fixating\n", this.short)
		}
	}
}

func (this *Input) complete() {
	state := "done"
	if this.penalty > 256 {
		state = "abusive"
	}

	var mutations int
	if err := db.QueryRow(`SELECT SUM(mutations) - $1 FROM result WHERE input = $2`, this.mutations, this.id).Scan(&mutations); err != nil {
		panic("complete: could not get new mutation count: "+ err.Error())
	}

	if _, err := db.Exec(`UPDATE input
		SET penalty = penalty + $2, state = $3, "lastResultChange" = (CASE WHEN $4>0 THEN TIMEZONE('UTC'::text, NOW()) ELSE "lastResultChange" END)
		WHERE short = $1 AND state = 'busy'`, this.short, this.penalty, state, mutations); err != nil {
		panic("Input: failed to update: "+ err.Error())
	}

	stats.Lock(); stats.c["mutations"] += mutations; stats.Unlock()

	inputSrc.Lock(); inputSrc.srcUse[this.short]--
	if 0 == inputSrc.srcUse[this.short] {
		if err := os.Remove("/in/" + this.short); err != nil {
			fmt.Fprintf(os.Stderr, "[%s] failed to remove source: %s\n", this.short, err)
		}

		delete(inputSrc.srcUse, this.short)
	}
	inputSrc.Unlock()

	stats.Lock(); stats.c["inputs"]++; stats.Unlock()
	if this.penalty > 128 {
		fmt.Printf("[%s] state = %s | penalty = %d | %v\n", this.short, state, this.penalty, this.penaltyDetail)
	}
}

func newOutput(raw string, i *Input, v *Version) *Output {
	raw = strings.Replace(raw, "\x06", "\\\x06", -1)
	raw = strings.Replace(raw, "\x07", "\\\x07", -1)
	raw = strings.Replace(raw, v.name, "\x06", -1)
	raw = strings.Replace(raw, i.short, "\x07", -1)

	h := sha1.New()
	io.WriteString(h, raw)

	o := &Output{0, raw, base64.StdEncoding.EncodeToString(h.Sum(nil))}

	if err := db.QueryRow(`SELECT id FROM output WHERE hash = $1`, o.hash).Scan(&o.id); err != nil {
		if _, err := db.Exec(`INSERT INTO output VALUES ($1, $2) ON CONFLICT (hash) DO NOTHING`, o.hash, o.raw); err != nil {
			panic("Output: failed to store: "+ err.Error())
		}

		// LastInsertId doesn't work
		if err := db.QueryRow(`SELECT id FROM output WHERE hash = $1`, o.hash).Scan(&o.id); err != nil {
			panic("Output: failed to retrieve after storing: "+ err.Error())
		}

		stats.Lock(); stats.c["outputs"]++; stats.Unlock()
	}

	i.Lock()
	if false == i.uniqueOutput[o.hash] {
		i.uniqueOutput[o.hash] = true

		i.Unlock()

		if !v.isHelper {
			i.penalize("Excessive total output", len(o.raw)/2048)
		}
	} else {
		i.Unlock()
	}

	return o
}

func newResult(i *Input, v *Version, raw string, s *os.ProcessState) *Result {
	waitStatus := s.Sys().(syscall.WaitStatus)
	usage := s.SysUsage().(*syscall.Rusage)

	var exitCode int
	if waitStatus.Exited() {
		exitCode = waitStatus.ExitStatus()
	} else {
		exitCode = 128 + int(waitStatus.Signal())
	}

	r := &Result{
		input:      i,
		output:     newOutput(raw, i, v),
		version:    v,
		exitCode:   exitCode,
		userTime:   float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime: float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:  usage.Maxrss,
	}

	i.penalize("Total runtime", int(usage.Utime.Sec)+int(usage.Stime.Sec))

	switch v.name {
		case "vld":				if exitCode == 0 {  r.store(); }
		case "segfault":		if exitCode == 139{ r.store(); }

		default:
			r.store()
	}

	stats.Lock(); stats.c["results"]++; stats.Unlock()
	return r
}

func (this *Result) store() {
	_, err := db.Exec(`
		INSERT INTO result VALUES ($1, $2, $3, $4, $5, $6, $7)
		ON CONFLICT (input, version) DO UPDATE SET
			output = excluded.output, "exitCode" = excluded."exitCode",
			"userTime" =   ((result.runs * result."userTime"  + excluded."userTime")  / (result.runs+1)),
			"systemTime" = ((result.runs * result."systemTime"+ excluded."systemTime")/ (result.runs+1)),
			"maxMemory" =  ((result.runs * result."maxMemory" + excluded."maxMemory") / (result.runs+1)),
			runs = result.runs + 1, mutations = result.mutations + (CASE WHEN (result.output!=excluded.output OR result."exitCode"!=excluded."exitCode") THEN 1 ELSE 0 END)`,
		this.input.id, this.version.id, this.output.id, this.exitCode,
		this.userTime, this.systemTime, this.maxMemory,
	)

	if err != nil {
		fmt.Printf("Result: failed to store result: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)
	}
}

func (this *Input) execute(v *Version, l *ResourceLimit) *Result {
	cmdArgs := strings.Split(v.command, " ")

	cmdArgs = append(cmdArgs, "/in/"+this.short)
	cmd := exec.Command(cmdArgs[0], cmdArgs[1:]...)
	cmd.Env = []string{
		"TERM=xterm",
		"PATH=/usr/bin:/bin",
		"LANG=C",
		"SHELL=/bin/sh",
		"MAIL=/var/mail/nobody",
		"LOGNAME=nobody",
		"USER=nobody",
		"HOME=/tmp",
	}
	cmd.SysProcAttr = &syscall.SysProcAttr{Credential: &syscall.Credential{Uid: 99, Gid: 99, Groups: []uint32{}}}

	if !this.lastSubmit.IsZero() {
		cmd.Env = append(cmd.Env, []string{
			"TIME=" + strconv.FormatInt(this.lastSubmit.Unix(), 10),
			"LD_PRELOAD=/usr/bin/daemon-preload.so",
		}...)
	}

	/*
	 * Channels are meant to communicate between routines. We create a channel
	 * that transports a ProcessState, which we return from Process.Wait. The
	 * '<-' * syntax indicates us sending / receiving data from the channel.
	 *
	 * Refs: http://stackoverflow.com/questions/11886531
	 */

	procOut := make(chan string)
	procDone := make(chan *os.ProcessState)

	stdout, _ := cmd.StdoutPipe()
	stderr, _ := cmd.StderrPipe()
	cmdR := io.MultiReader(stdout, stderr)

	if err := cmd.Start(); err != nil {
		fmt.Fprintf(os.Stderr, "While starting: %s\n", err)
		return &Result{}
	}

	go func(c *exec.Cmd, r io.Reader) {
		limit := l.output
		if v.isHelper {
			limit = 256 * 1024
		}

		output := make([]byte, 0)
		buffer := make([]byte, 1024)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				fmt.Fprintf(os.Stderr, "While reading output: %s\n", err)
				break
			}

			buffer = buffer[:n]

			if len(output) < limit {
				output = append(output, buffer...)
				continue
			}

			if err := cmd.Process.Kill(); err != nil && err.Error() != "os: process already finished" && err.Error() != "no such process" {
				this.penalize("Didn't stop: "+err.Error(), 256)
			}
		}

		// Make sure all output is exactly the same length
		if len(output) > limit {
			output = output[:limit]
		}

		if !v.isHelper {
			this.penalize("Excessive output", len(output)/10240)
		}

		procOut <- string(output)
	}(cmd, cmdR)

	// We want ProcessState after successful exit too
	go func(c *exec.Cmd) {
		state, err := c.Process.Wait()

		if err != nil {
			fmt.Fprintf(os.Stderr, "While waiting for process: %s\n", err)
		}

		procDone <- state
	}(cmd)

	var state *os.ProcessState
	var output string

	select {
	case <-time.After(time.Duration(l.runtime) * time.Millisecond):
		if err := cmd.Process.Kill(); err != nil {
			fmt.Printf("Kill after timeout resulted in : %s\n", err)

			if err.Error() != "os: process already finished" && err.Error() != "no such process" {
				this.penalize("Failed to kill after timeout", 256)
			}
		}

		state = <-procDone
		output = <-procOut

		this.penalize("Process timed out", 64)
	case state = <-procDone:
		output = <-procOut
	}

	// Required to close stdout/err descriptors
	cmd.Wait()

	os.RemoveAll("/tmp/")

	return newResult(this, v, output, state)
}

func refreshVersions() {
	newVersions := []*Version{}

	rs, err := db.Query(`
		SELECT id, name, COALESCE(released, '1900-01-01'), COALESCE(eol, '2999-12-31'), COALESCE("order", 0), command, "isHelper"
		FROM version
		ORDER BY "released" DESC, "order" DESC`)

	if err != nil {
		panic("Could not populate versions: "+ err.Error())
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.released, &v.eol, &v.order, &v.command, &v.isHelper); err != nil {
			panic("Error fetching version: "+ err.Error())
		}

		newVersions = append(newVersions, &v)
	}

	versions = newVersions
}

func checkPendingInputs() {
	rs, err := db.Query(`
		SELECT id, short, created, "runArchived", "runQuick", state FROM input
		WHERE
			state IN('new', 'busy')
			AND NOW() - created > '5 minutes'
		ORDER BY created DESC`)

	if err != nil {
		panic("checkPendingInputs - could not SELECT: "+ err.Error())
	}

	l := &ResourceLimit{0, 2500, 32768}

	var version sql.NullInt64
	var state string
	for rs.Next() {
		input := newInput()

		if err := rs.Scan(&input.id, &input.short, &input.created, &input.runArchived, &version, &state); err != nil {
			panic("checkPendingInputs: error fetching work: "+ err.Error())
		}

		fmt.Printf("checkPendingInputs - scheduling [%s] %s\n", state, input.short)
		input.prepare()

		for _, v := range versions {
			if (version.Valid && int(version.Int64) == v.id) || (!version.Valid && (input.runArchived || v.eol.After(input.created))) {
				input.execute(v, l)
			}

			if input.penalty > 512 {
				break
			}
		}

		input.complete()
	}
}

func canBatch(doSleep bool) (bool, error) {
	file, err := os.Open("/proc/loadavg")
	if err != nil {
		return false, err
	}
	defer file.Close()

	data := make([]byte, 64)
	if c, err := file.Read(data); err != nil || c < 8 {
		return false, err
	}

	loadAvg := strings.Split(string(data), " ")

	if l, err := strconv.ParseFloat(loadAvg[0], 32); doSleep && err == nil {
		time.Sleep(time.Duration(int(l*10)/runtime.NumCPU()) * time.Millisecond)
	}

	if l, err := strconv.ParseFloat(loadAvg[0], 32); err != nil {
		return false, err
	} else if int(l) > runtime.NumCPU() {
		fmt.Printf("Load1 [%.1f] seems high (for %d cpus), sleeping...\n", l, runtime.NumCPU())
		time.Sleep(time.Duration(3*l) * time.Second)
	}

	if l, err := strconv.ParseFloat(loadAvg[1], 32); err != nil {
		return false, err
	} else if int(l) > runtime.NumCPU() {
		fmt.Printf("Load5 [%.1f] seems high (for %d cpus), skipping batch\n", l, runtime.NumCPU())
		time.Sleep(time.Duration(30*l) * time.Second)
		return false, nil
	}

	return true, nil
}

func batchScheduleNewVersions() {
	batchNewComplete := 0

	for _, v := range versions {
		// ignore helpers, they don't store all results
		if v.isHelper {
			continue
		}

		fmt.Printf("batchScheduleNewVersions: searching for %s\n", v.name)
		stats.RLock(); pre := stats.c["results"]; stats.RUnlock()
		_batchScheduleNewVersions(v)
		stats.RLock(); post := stats.c["results"]; stats.RUnlock()

		if post-pre < 99 {
			batchNewComplete++
		}

		if batchNewComplete > 3 {
			fmt.Printf("batchScheduleNewVersions: stopping; batchNewComplete > 3\n")
			break
		}
	}
}

func _batchScheduleNewVersions(target *Version) {
	stats.Lock(); stats.c["batchVersion"] = target.order; stats.Unlock()

	wg := newSizedWaitGroup(3)

	found := 1
	for found > 0 {
		rs, err := dbBatch.Query(`
			SELECT id, short
			FROM input
			LEFT JOIN result ON (version = $1 AND input=id)
			WHERE
				input IS NULL
				AND ("runArchived" OR created < $2::date)
				AND state = 'done'
				AND NOT "operationCount" IS NULL
				AND NOT "bughuntIgnore"
				AND "runQuick" IS NULL;`,
			target.id, target.eol.Format("2006-01-02"))
		if err != nil {
			panic("doBatch: error in SELECT query: "+ err.Error())
		}

		found = 0
		for rs.Next() {
			found++
			input := newInput()
			if err := rs.Scan(&input.id, &input.short); err != nil {
				panic("doBatch: error fetching work: "+ err.Error())
			}

			for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
				if err != nil {
					panic("Unable to check load: "+ err.Error())
				}
			}

			wg.Add()
			go func(i *Input, v *Version) {
				i.prepare()
				i.execute(v, &ResourceLimit{0, 2500, 32768})
				i.complete()

				wg.Done()
			}(&input, target)
		}

		wg.Wait()
	}
}

func batchRefreshRandomScripts() {
	wg := newSizedWaitGroup(3)

	for {
		rs, err := dbBatch.Query(`
			SELECT id, short, "runArchived", created
			FROM input
			WHERE
				state = 'done'
				AND penalty < 50
				AND NOW()-created>'1 year'
				AND "runQuick" IS NULL
				AND "operationCount">2
			ORDER BY RANDOM()
			LIMIT 999`)
		if err != nil {
			panic("batchRefreshRandomScripts: error in SELECT query: "+ err.Error())
		}

		for rs.Next() {
			input := newInput()
			if err := rs.Scan(&input.id, &input.short, &input.runArchived, &input.created); err != nil {
				panic("batchRefreshRandomScripts: error fetching work: "+ err.Error())
			}

			input.prepare()

			for _, v := range versions {
				for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
					if err != nil {
						panic("Unable to check load: "+ err.Error())
					}
				}

				if !input.runArchived && v.eol.Before(input.created) {
					continue
				}

				wg.Add()

				go func(v Version) {
					input.execute(&v, &ResourceLimit{0, 2500, 32768})
					wg.Done()
				}(*v)
			}

			wg.Wait()
			input.complete()
		}
	}
}

func doWork() {
	rs, err := db.Query(`DELETE FROM queue WHERE "maxPackets" = 0 RETURNING *`)

	if err != nil {
		panic("doWork: error in DELETE query: "+ err.Error())
	}

	var version sql.NullString

	for rs.Next() {
		input := newInput()
		rMax := &ResourceLimit{}

		if err := rs.Scan(&input.short, &version, &rMax.packets, &rMax.runtime, &rMax.output); err != nil {
			panic("doWork: error fetching work: "+ err.Error())
		}

		if err := db.QueryRow(`SELECT id, created, "runArchived" FROM input WHERE short = $1`, input.short).Scan(&input.id, &input.created, &input.runArchived); err != nil {
			panic("doWork: error verifying input: "+ err.Error())
		}

		input.prepare()

		for _, v := range versions {
			if (version.Valid && version.String == v.name) || (!version.Valid && (input.runArchived || v.eol.After(input.created))) {
				input.execute(v, rMax)

				if input.penalty > 512 {
					break
				}
			}
		}

		input.complete()
	}
}

var (
	db       *sql.DB
	dbBatch  *sql.DB
	versions []*Version
	batch    string
	inputSrc   struct {
		sync.Mutex
		srcUse map[string]int
	}
	stats    struct {
		sync.RWMutex
		c map[string]int
	}
)

const (
	RLIMIT_NPROC = 0x6
	DSN          = "user=daemon password=password host=/run/postgresql/ dbname=phpshell sslmode=disable"
)

func init() {
	var err error
	if len(os.Args) > 1 && os.Args[1][:7] == "--batch" {
		batch = os.Args[1][8:]
		db, err = sql.Open("postgres", DSN+" port=5434")

		if err != nil {
			panic("init - failed to connect to db: "+ err.Error())
		}

		dbBatch, err = sql.Open("postgres", DSN)
	} else {
		db, err = sql.Open("postgres", DSN)
	}
	db.SetMaxOpenConns(16)

	if err != nil {
		panic("init - failed to connect to db: "+ err.Error())
	}

	if err := db.Ping(); err != nil {
		panic("init - failed to ping db: "+ err.Error())
	}

	stats.c = make(map[string]int)
	inputSrc.srcUse = make(map[string]int)

	var limits = map[int]int{
//		syscall.RLIMIT_CPU:    2,
		syscall.RLIMIT_DATA:   256 * 1024 * 1024,
//		syscall.RLIMIT_FSIZE:  64 * 1024,
		syscall.RLIMIT_FSIZE:  16 * 1024 * 1024,
		syscall.RLIMIT_CORE:   0,
		syscall.RLIMIT_NOFILE: 2048,
		//FIXME https://github.com/facebook/hhvm/issues/7381
//		syscall.RLIMIT_AS:     512 * 1024 * 1024, // also, causes lots of scripts to 137 ?
		RLIMIT_NPROC:          64,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{Cur: uint64(value), Max: uint64(float64(value) * 1.25)}); err != nil {
			panic(fmt.Sprintf("init - failed to set resourceLimit: %d to %d: %s", key, value, err))
		}
	}

	refreshVersions()
}

func main() {
	l := pq.NewListener(DSN, 1*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			panic("While creating listener: "+ err.Error())
		}
	})

	if batch == "" {
		if err := l.Listen("daemon"); err != nil {
			panic("Could not setup Listener "+ err.Error())
		}

		fmt.Printf("daemon ready\n")
	}

	doPrintStats      := time.NewTicker( 1 * time.Hour)
	doCheckPending    := time.NewTicker(45 * time.Minute)
	doShutdown        := make(chan os.Signal, 1)
	signal.Notify(doShutdown, os.Interrupt)

	if batch == "refreshRandomScripts" {
		doCheckPending.Stop()

		go batchRefreshRandomScripts()
	} else if batch == "scheduleNewVersions" {
		doCheckPending.Stop()

		go batchScheduleNewVersions()
	} else {

		// Process pending work immediately
		go checkPendingInputs()
	}

LOOP:
	for {
		select {
		case <-doCheckPending.C:
			go checkPendingInputs()

		case <-doPrintStats.C:
			stats.Lock()
			fmt.Printf("Stats %v\n", stats.c)
			stats.c = make(map[string]int)
			stats.Unlock()

		case n := <-l.Notify:
			switch n.Extra {
				case "version": go refreshVersions()
				case "queue":   go doWork()
			}

		case <-doShutdown:
			l.Close()
			break LOOP
		}
	}
}
