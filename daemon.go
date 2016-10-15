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
	"runtime"
	"strconv"
	"strings"
	"syscall"
	"time"
	"sync"
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
	id           int
	short        string
	uniqueOutput map[string]bool
	penalty      int
	created      time.Time
	run          int
	runArchived  bool
	lastSubmit   time.Time
	src struct{
		sync.RWMutex
		inUse int
	}
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

func exitError(format string, v ...interface{}) {
	fmt.Fprintf(os.Stderr, format+"\n", v...)
	os.Exit(1)
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p
	stats.Lock(); stats.c["penalty"] += p; stats.Unlock()

	if p < 1 {
		return
	}
}

func (this *Input) setBusy(newRun bool) {
	incRun := 0
	if newRun {
		incRun = 1
	}

	this.src.Lock(); this.src.inUse++
	if 1 == this.src.inUse {
		if f, err := os.Create("/in/"+ this.short); err != nil {
			exitError("setBusy: could not create file: %s", err)
		} else {
			var raw []byte
			if err := db.QueryRow(`SELECT raw FROM input_src WHERE input = $1`, this.id).Scan(&raw); err != nil {
				exitError("setBusy: could not retrieve source: %s", err)
			} else {
				f.Write(raw)
			}

			f.Close()
		}
	}
	this.src.Unlock()

	if r, err := db.Exec(`UPDATE input SET run = run + $2, state = 'busy' WHERE id = $1`, this.id, incRun); err != nil {
		exitError("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		exitError("Input: failed to update run+state; %d rows affected, %s", a, err)
	}

	if err := db.QueryRow(`SELECT run FROM input WHERE short = $1`, this.short).Scan(&this.run); err != nil {
		exitError("Input: failed to fetch run: %s", err)
	}
}

func (this *Input) setDone() {
	state := "done"
	if this.penalty > 256 {
		state = "abusive"
	}

	if _, err := db.Exec(`UPDATE input
		SET penalty = (penalty * (run-1) + $2) / GREATEST(run, 1), state = $3
		WHERE short = $1 AND state = 'busy'`, this.short, this.penalty, state); err != nil {
		exitError("Input: failed to update: %s", err)
	}

	this.src.Lock(); this.src.inUse--
	if 0 == this.src.inUse {
		if err := os.Remove("/in/"+ this.short); err != nil {
			fmt.Fprintf(os.Stderr, "[%s] failed to remove source: %s\n", this.short, err)
		}
	}
	this.src.Unlock()

	stats.Lock(); stats.c["inputs"]++; stats.Unlock()
	if this.penalty > 128 {
		fmt.Printf("[%s] state = %s penalty = %d\n", this.short, state, this.penalty)
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
		var duplicateKey = "pq: duplicate key value violates unique constraint \"output_hash_key\""
		if _, err := db.Exec(`INSERT INTO output VALUES ($1, $2)`, o.hash, o.raw); err != nil && err.Error() != duplicateKey {
			exitError("Output: failed to store: %s", err)
		}

		// LastInsertId doesn't work
		if err := db.QueryRow(`SELECT id FROM output WHERE hash = $1`, o.hash).Scan(&o.id); err != nil {
			exitError("Output: failed to retrieve after storing: %s", err)
		}

		stats.Lock(); stats.c["outputs"]++; stats.Unlock()
	}

	if false == i.uniqueOutput[o.hash] {
		i.uniqueOutput[o.hash] = true

		if !v.isHelper {
			i.penalize("Excessive total output", len(o.raw)/2048)
		}
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
		created:    time.Now(),
		userTime:   float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime: float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:  usage.Maxrss,
	}

	i.penalize("Total runtime", int(usage.Utime.Sec)+int(usage.Stime.Sec))

	switch v.name {
		case "vld":				if exitCode == 0 {  r.store(); }
		case "hhvm-bytecode":	if exitCode == 0 {  r.store(); }
		case "segfault":		if exitCode == 139{ r.store(); }

		default:
			r.store()
	}

	stats.Lock(); stats.c["results"]++; stats.Unlock()
	return r
}

func (this *Result) store() {
	_, err := db.Exec(`INSERT INTO result VALUES( $1, $2, $3, $4, $5, $6, $7, $8, $9)`,
		this.input.id, this.output.id, this.version.id, this.exitCode, this.created,
		this.userTime, this.systemTime, this.maxMemory, this.input.run,
	)

	if err != nil {
		fmt.Printf("Result: failed to store input=%s,version=%s,run=%d: %s\n", this.input.short, this.version.name, this.input.run, err)
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
		"MAIN=/var/mail/nobody",
		"LOGNAME=nobody",
		"USER=nobody",
		"USERNAME=nobody",
		"HOME=/",
	}
	cmd.SysProcAttr = &syscall.SysProcAttr{Credential: &syscall.Credential{Uid: 99, Gid: 99, Groups: []uint32{}}}

	// Perform date fixation
	if this.lastSubmit.IsZero() {
		if err := db.QueryRow(`SELECT MAX(COALESCE(updated, created)) FROM submit WHERE input = $1`, this.id).Scan(&this.lastSubmit); err != nil {
			fmt.Printf("Warning; failed to find any submit of %s, not fixating\n", this.short);
		}
	}

	if !this.lastSubmit.IsZero() {
		cmd.Env = append(cmd.Env, []string{
			"TIME="+ strconv.FormatInt(this.lastSubmit.Unix(), 10),
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
		fmt.Printf("While starting: %s\n", err)
		return &Result{}
	}

	go func(c *exec.Cmd, r io.Reader) {
		limit := l.output
		if v.isHelper {
			limit = 256*1024
		}

		output := make([]byte, 0)
		buffer := make([]byte, 1024)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				fmt.Printf("While reading output: %s\n", err)
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
			fmt.Printf("While waiting for process: %s\n", err)
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

	rs, err := db.Query(`SELECT id, name, COALESCE(released, '1900-01-01'), COALESCE(eol, '2999-12-31'), COALESCE("order", 0), command, "isHelper" FROM version ORDER BY "released" DESC`)

	if err != nil {
		exitError("Could not populate versions: %s", err)
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.released, &v.eol, &v.order, &v.command, &v.isHelper); err != nil {
			exitError("Error fetching version: %s", err)
		}

		newVersions = append(newVersions, &v)
	}

/*	if len(newVersions) > len(versions) {
		go batchScheduleNewVersions(newVersions[len(newVersions)-1])
	}
*/
	versions = newVersions
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
		time.Sleep(time.Duration(3 * l) * time.Second)
	}

	if l, err := strconv.ParseFloat(loadAvg[1], 32); err != nil {
		return false, err
	} else if int(l) > runtime.NumCPU() {
		fmt.Printf("Load5 [%.1f] seems high (for %d cpus), skipping batch\n", l, runtime.NumCPU())
		time.Sleep(time.Duration(30 * l) * time.Second)
		return false, nil
	}

	return true, nil
}

func batchScheduleNewVersions(target *Version) {
	stats.Lock(); stats.c["batchVersion"] = target.order; stats.Unlock()

	//fixme
	l := ResourceLimit{0, 2500, 32768}

	found := 1
	for found > 0 {
		rs, err := db.Query(`
			SELECT id, short, i.run, created
			FROM input i
			WHERE
				state = 'done'
				AND (i."runArchived" OR i.created < $2::date)
				AND id NOT IN (SELECT DISTINCT input FROM result WHERE version = $1)
			LIMIT 999;`, target.id, target.eol.Format("2006-01-02"))
		if err != nil {
			exitError("doBatch: error in SELECT query: %s", err)
		}

		found = 0
		for rs.Next() {
			found++
			input := &Input{uniqueOutput: map[string]bool{}}
			if err := rs.Scan(&input.id, &input.short, &input.run, &input.created); err != nil {
				exitError("doBatch: error fetching work: %s", err)
			}

			for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
				if err != nil {
					exitError("Unable to check load: %s\n", err)
				}
			}

			input.setBusy(false)
			input.execute(target, &l)
			input.setDone()
		}
	}
}

func batchSingleFix() {
	return

	rs, err := db.Query(`
		SELECT id, short, created, "runArchived"
		FROM input
		WHERE id IN (SELECT DISTINCT input FROM result_current WHERE "exitCode" > 0 AND "exitCode" < 255)
		ORDER BY RANDOM()`)
	if err != nil {
		exitError("doBatch: error in SELECT query: %s", err)
	}

	for rs.Next() {
		input := &Input{uniqueOutput: map[string]bool{}}
		if err := rs.Scan(&input.id, &input.short, &input.created, &input.runArchived); err != nil {
			exitError("doBatch: error fetching work: %s", err)
		}

		_batchResetHard(input)
	}

	stats.RLock(); fmt.Printf("batchSingleFix: completed @ %v\n", stats.c); stats.RUnlock()
}

func batchRefreshRandomScripts() {
	for {
		rs, err := db.Query(`
			SELECT id, short, created, "runArchived"
			FROM input
			WHERE penalty < 50 AND created < (SELECT MAX(released) FROM version) AND (run>1 OR NOW()-created>'1 year')
			AND "operationCount">2
			ORDER BY run DESC, RANDOM()
			LIMIT 999`)
		if err != nil {
			exitError("doBatch: error in SELECT query: %s", err)
		}

		for rs.Next() {
			input := &Input{uniqueOutput: map[string]bool{}}
			if err := rs.Scan(&input.id, &input.short, &input.created, &input.runArchived); err != nil {
				exitError("doBatch: error fetching work: %s", err)
			}

			_batchResetHard(input)
		}
	}
}

func _batchResetHard(input *Input){
	//fixme
	l := ResourceLimit{0, 2500, 32768}

	var resCount int
	if err := db.QueryRow(`SELECT COUNT(*) FROM result WHERE input = $1`, input.id).Scan(&resCount); err == nil {
		stats.Lock(); stats.c["resultsDeleted"] += resCount; stats.Unlock()
	}
	if _, err := db.Exec(`DELETE FROM result WHERE input = $1`, input.id); err != nil {
		exitError("doBatch: could not delete existing results: %s", err)
	}
	if _, err := db.Exec(`UPDATE input SET run = 0 WHERE id = $1`, input.id); err != nil {
		exitError("doBatch: could not reset input.run: %s", err)
	}

	input.setBusy(true)

	for _, v := range versions {
		for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
			if err != nil {
				exitError("Unable to check load: %s\n", err)
			}
		}

		if input.runArchived || v.isHelper || v.eol.After(input.created) {
			input.execute(v, &l)
		}
	}

	input.setDone()
}

func doWork() {
	rs, err := db.Query(`DELETE FROM queue WHERE "maxPackets" = 0 RETURNING *`)

	if err != nil {
		exitError("doWork: error in DELETE query: %s", err)
	}

	var version sql.NullString

	for rs.Next() {
		input := &Input{uniqueOutput: map[string]bool{}}
		rMax := &ResourceLimit{}

		if err := rs.Scan(&input.short, &version, &rMax.packets, &rMax.runtime, &rMax.output); err != nil {
			exitError("doWork: error fetching work: %s", err)
		}

		if err := db.QueryRow(`SELECT id, created, "runArchived" FROM input WHERE short = $1`, input.short).Scan(&input.id, &input.created, &input.runArchived); err != nil {
			exitError("doWork: error verifying input: %s", err)
		}

		input.setBusy(!version.Valid)

		for _, v := range versions {
			if (version.Valid && version.String == v.name) || (!version.Valid && (v.isHelper || input.runArchived || v.eol.After(input.created))) {
				input.execute(v, rMax)
			}
		}

		input.setDone()
	}
}

func background() {
	go func() {
		ticker := time.NewTicker(5 * time.Minute)
		for range ticker.C {
			refreshVersions()
		}
	}()

	go func() {
		ticker := time.NewTicker(1 * time.Hour)
		for range ticker.C {
			stats.Lock();
			fmt.Printf("Stats %v\n", stats.c)
			stats.c = make(map[string]int);
			stats.Unlock()
		}
	}()
}

var (
	db       *sql.DB
	l        *pq.Listener
	versions []*Version
	isBatch  bool
	stats    struct{
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
	isBatch = len(os.Args) > 1 && os.Args[1] == "--batch"

	if isBatch {
		db, err = sql.Open("postgres", DSN+" port=5434")
	} else {
		db, err = sql.Open("postgres", DSN)
	}
	db.SetMaxOpenConns(16)

	if err != nil {
		exitError("Failed connect to db: %s", err)
	}

	if err := db.Ping(); err != nil {
		exitError("Failed to ping db: %s", err)
	}

	stats.c = make(map[string]int);

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
			exitError("Failed to set resourceLimit: %d to %d: %s", key, value, err)
		}
	}

	refreshVersions()

	if isBatch {
		return
	}

	l = pq.NewListener(DSN, 1*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			fmt.Printf("While creating listener: %s\n", err)
		}
	})

	if err := l.Listen("daemon"); err != nil {
		exitError("Could not setup Listener %s", err)
	}
}

func main() {
	go background()

	fmt.Printf("Daemon ready\n")

	if isBatch {
//		batchSingleFix()
		batchRefreshRandomScripts()

		for _, v := range versions {
			// ignore helpers, they don't store all results
			if v.isHelper {
				continue
			}

			fmt.Printf("batchScheduleNewVersions: searching for %s\n", v.name)
			stats.RLock(); pre := stats.c["results"]; stats.RUnlock()
			batchScheduleNewVersions(v)
			stats.RLock(); post := stats.c["results"]; stats.RUnlock()

			if post-pre < 9999 {
				break
			}
		}

		os.Exit(0)
	}

	// Process pending work
	doWork()

	for {
		select {
		case <-l.Notify:
			go doWork()
		}
	}
}
