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
)

type Version struct {
	id       int
	name     string
	command  string
	isHelper bool
	released time.Time
}

type Input struct {
	id           int
	short        string
	uniqueOutput map[string]bool
	penalty      int
	created      time.Time
	run          int
	runArchived  bool
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

func exitError(format string, v ...interface{}) {
	fmt.Fprintf(os.Stderr, format+"\n", v...)
	os.Exit(1)
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p
	stats["penalty"] += p

	if p < 1 {
		return
	}
}

func (this *Input) setBusy(newRun bool) {
	incRun := 0
	if newRun {
		incRun = 1
	}

	if r, err := db.Exec(`UPDATE input SET run = run + $2, state = 'busy' WHERE short = $1`, this.short, incRun); err != nil {
		exitError("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		exitError("Input: failed to update run+state; %d rows affected, %s", a, err)
	}

	if err := db.QueryRow(`SELECT id, run FROM input WHERE short = $1`, this.short).Scan(&this.id, &this.run); err != nil {
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

	stats["inputs"]++
	if this.penalty > 128 {
		fmt.Printf("[%s] state = %s penalty = %d\n", this.short, state, this.penalty)
	}

	os.RemoveAll("/tmp/")
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

		stats["outputs"]++
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

	if !(v.isHelper && exitCode == 255) {
		r.store()
	}

	stats["results"]++
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

func (this *Input) execute(v *Version) *Result {
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
		output := make([]byte, 0)
		buffer := make([]byte, 1024)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				fmt.Printf("While reading output: %s\n", err)
				break
			}

			buffer = buffer[:n]

			// 32 KiB is the length of phpinfo() output
			if len(output) < 32*1024 || (v.isHelper && len(output) < 256*1024) {
				output = append(output, buffer...)
				continue
			}

			if err := cmd.Process.Kill(); err != nil && err.Error() != "os: process already finished" && err.Error() != "no such process" {
				this.penalize("Didn't stop: "+err.Error(), 256)
			}
		}

		if !v.isHelper {
			this.penalize("Excessive output", len(output)/10240)
		}

		procOut <- string(output)
	}(cmd, cmdR)

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
	case <-time.After(2500 * time.Millisecond):
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

	return newResult(this, v, output, state)
}

func refreshVersions() {
	newVersions := []*Version{}

	rs, err := db.Query(`SELECT id, name, command, COALESCE(released, '1900-01-01'), "isHelper" FROM version ORDER BY "order" DESC`)

	if err != nil {
		exitError("Could not populate versions: %s", err)
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.command, &v.released, &v.isHelper); err != nil {
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

	data := make([]byte, 64)
	if c, err := file.Read(data); err != nil || c < 8 {
		return false, err
	}

	loadAvg := strings.Split(string(data), " ")

	if l, err := strconv.ParseFloat(loadAvg[0], 32); doSleep && err == nil {
		time.Sleep(time.Duration(int(l*100)/runtime.NumCPU()) * time.Millisecond)
	}

	if l, err := strconv.ParseFloat(loadAvg[0], 32); err != nil {
		return false, err
	} else if int(l) > runtime.NumCPU()/2 {
		fmt.Printf("Load1 [%.1f] seems high (for %d cpus), sleeping...\n", l, runtime.NumCPU())
		time.Sleep(time.Duration(3 * l) * time.Second)
	}

	if l, err := strconv.ParseFloat(loadAvg[1], 32); err != nil {
		return false, err
	} else if int(l) > runtime.NumCPU()/2 {
		fmt.Printf("Load5 [%.1f] seems high (for %d cpus), skipping batch\n", l, runtime.NumCPU())
		return false, nil
	}

	return true, nil
}

func batchScheduleNewVersions(target *Version) {
	found := 1
	for found > 0 {
		rs, err := db.Query(`
			SELECT id, short, i.run, created, "runArchived"
			FROM input i
			LEFT JOIN result r ON (r.input=i.id AND r.run=i.run AND r.version=$1)
			WHERE state = 'done' AND version ISNULL
			LIMIT 999;`, target.id)
		if err != nil {
			exitError("doBatch: error in SELECT query: %s", err)
		}

		found = 0
		for rs.Next() {
			found++
			input := &Input{uniqueOutput: map[string]bool{}}
			if err := rs.Scan(&input.id, &input.short, &input.run, &input.created, &input.runArchived); err != nil {
				exitError("doBatch: error fetching work: %s", err)
			}

			for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
				if err != nil {
					exitError("Unable to check load: %s\n", err)
				}
			}

			y, m, d := input.created.Date()
			minArchDate := time.Date(y-3, m, d, 0, 0, 0, 0, time.UTC)
			if input.runArchived || target.isHelper || target.released.After(minArchDate) {
				input.execute(target)
			}
		}
	}
}

func batchRefreshRandomScripts() {
	for {
		rs, err := db.Query(`
			SELECT id, short, created, "runArchived"
			FROM input
			WHERE penalty < 50 AND NOW() - created > '1 month'::interval
			ORDER BY random()
			LIMIT 999`)
		if err != nil {
			exitError("doBatch: error in SELECT query: %s", err)
		}

		for rs.Next() {
			input := &Input{uniqueOutput: map[string]bool{}}
			if err := rs.Scan(&input.id, &input.short, &input.created, &input.runArchived); err != nil {
				exitError("doBatch: error fetching work: %s", err)
			}

			for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
				if err != nil {
					exitError("Unable to check load: %s\n", err)
				}
			}

			if _, err := db.Exec(`DELETE FROM result WHERE input = $1`, input.id); err != nil {
				exitError("doBatch: could not delete existing results: %s", err)
			}
			if _, err := db.Exec(`UPDATE input SET run = 0 WHERE id = $1`, input.id); err != nil {
				exitError("doBatch: could not reset input.run: %s", err)
			}

			input.setBusy(true)

			y, m, d := input.created.Date()
			minArchDate := time.Date(y-3, m, d, 0, 0, 0, 0, time.UTC)
			for _, v := range versions {
				if input.runArchived || v.isHelper || v.released.After(minArchDate) {
					input.execute(v)
				}
			}

			input.setDone()
		}
	}
}

func doWork() {
	rs, err := db.Query(`DELETE FROM queue RETURNING input, version, "untilVersion"`)

	if err != nil {
		exitError("doWork: error in DELETE query: %s", err)
	}

	var version sql.NullString
	var isUntil bool

	for rs.Next() {
		input := &Input{uniqueOutput: map[string]bool{}}

		if err := rs.Scan(&input.short, &version, &isUntil); err != nil {
			exitError("doWork: error fetching work: %s", err)
		}

		if isUntil && !version.Valid {
			fmt.Printf("Consistency error, cannot have untilVersion=true with version=null, skipping\n")
			continue
		}

		if err := db.QueryRow(`SELECT id FROM input WHERE short = $1`, input.short).Scan(&input.id); err != nil {
			exitError("doWork: error verifying input: %s", err)
		}

		input.setBusy(!version.Valid || isUntil)

		untilReached := false
		for _, v := range versions {
			if !version.Valid || (isUntil && !untilReached) || (!isUntil && version.String == v.name) {
				input.execute(v)

				if isUntil && version.String == v.name {
					untilReached = true
				}
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
			fmt.Printf("Stats %v\n", stats)
			stats = make(map[string]int)
		}
	}()

	if !isBatch {
		return
	}

	go func() {
		for _, v := range versions {
			// exitCode=255 won't be stored, this'd result in ~500K useless execs
			if !v.isHelper {
				batchScheduleNewVersions(v)
			}
		}

		batchRefreshRandomScripts()
	}()
}

var (
	db       *sql.DB
	l        *pq.Listener
	versions []*Version
	stats    map[string]int
	isBatch  bool
)

const (
	WORK_BREAK   = 150 * time.Millisecond
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

	l = pq.NewListener(DSN, 1*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			fmt.Printf("While creating listener: %s\n", err)
		}
	})

	if !isBatch {
		if err := l.Listen("daemon"); err != nil {
			exitError("Could not setup Listener %s", err)
		}
	}

	var limits = map[int]int{
//		syscall.RLIMIT_CPU:    2,
		syscall.RLIMIT_DATA:   256 * 1024 * 1024,
//		syscall.RLIMIT_FSIZE:  64 * 1024,
		syscall.RLIMIT_FSIZE:  16 * 1024 * 1024,
		syscall.RLIMIT_CORE:   0,
		syscall.RLIMIT_NOFILE: 2048,
		RLIMIT_NPROC:          64,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{Cur: uint64(value), Max: uint64(float64(value) * 1.25)}); err != nil {
			exitError("Failed to set resourceLimit: %d to %d: %s", key, value, err)
		}
	}

	stats = make(map[string]int)
	refreshVersions()
}

func main() {
	go background()

	fmt.Printf("Daemon ready\n")

	// Process pending work
	doWork()

	for {
		select {
		case <-l.Notify:
			go doWork()
		}
	}
}