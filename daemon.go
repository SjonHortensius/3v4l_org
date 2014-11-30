package main

import (
	"crypto/sha1"
	"database/sql"
	"encoding/base64"
	"github.com/lib/pq"
	"io"
	"log"
	"os"
	"os/exec"
	"strings"
	"syscall"
	"time"
)

type Version struct {
	id int
	name string
	command string
	isHelper bool
}

type Input struct {
	id int
	short string
	uniqueOutput map [string]bool
	penalty	int
	run int
}

type Output struct {
	id int64
	raw string
}

type Work struct {
	input string
	version string
}

type Result struct {
	input		*Input
	output		*Output
	version		*Version
	exitCode	int
	created		time.Time
	userTime	float64
	systemTime	float64
	maxMemory	int64
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p

	if p < 1 {
		return;
	}

	if _, err := db.Exec("UPDATE input SET penalty = (penalty * (run-1) + $1) / run WHERE short = $2", this.penalty, this.short); err != nil {
		log.Fatalf("Input: failed to set penalty to `%d`: %s", p, err)
	}

	log.Printf("Penalized %d for: %s", p, r)

	if this.penalty > 256 {
		if _, err := db.Exec("UPDATE input SET state = 'abusive' WHERE short = $1 AND state = 'busy'", this.short); err != nil {
			log.Printf("Input: failed to update state to `abusive`: %s", err)
		}

		log.Fatalf("Penalty limit reached: aborting")
	}
}

func (this *Input) setBusy(newRun bool) {
	log.SetPrefix("[" + this.short + "] ")

	incRun := 0
	if newRun {
		incRun = 1
	}

	if r, err := db.Exec("UPDATE input SET run = run + $2, state = 'busy' WHERE short = $1", this.short, incRun); err != nil {
		log.Fatalf("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Input: failed to update run+state; %d rows affected, %s", a, err)
	}

	if err := db.QueryRow("SELECT id, run FROM input WHERE short = $1", this.short).Scan(&this.id, &this.run); err != nil {
		log.Fatalf("Input: failed to fetch run: %s", err)
	}
}

func (this *Input) setDone() {
	log.SetPrefix("")

	if _, err := db.Exec("UPDATE input SET state = 'done' WHERE short = $1 AND state = 'busy'", this.short); err != nil {
		log.Fatalf("Input: failed to update state to `done`: %s", err)
	}

	os.RemoveAll("/tmp/")
}

func (this *Output) process(result *Result) bool {
	this.raw = strings.Replace(this.raw, "\x06", "\\\x06", -1)
	this.raw = strings.Replace(this.raw, "\x07", "\\\x07", -1)
	this.raw = strings.Replace(this.raw, result.version.name, "\x06", -1)
	this.raw = strings.Replace(this.raw, result.input.short, "\x07", -1)

	return true
}

func (this *Output) getHash() string {
	h := sha1.New()
	io.WriteString(h, this.raw)
	return base64.StdEncoding.EncodeToString(h.Sum(nil))
}

//FIXME: implement newResult instead
func (this *Result) store() {
	this.output.process(this)

	hash := this.output.getHash()

	if false == this.input.uniqueOutput[hash] {
		this.input.uniqueOutput[hash] = true;

		if !this.version.isHelper {
			this.input.penalize("Excessive total output", len(this.output.raw)/2048)
		}
	}

	if rs, err := db.Exec("INSERT INTO output VALUES ($1, $2)", hash, this.output.raw); err != nil {
		log.Fatalf("Output: failed to store: %s", err)
	} else {
		this.output.id, _ = rs.LastInsertId()
	}

	r, err := db.Exec("INSERT INTO result VALUES( $1, $2, $3, $4, $5, $6, $7, $8, $9)",
		this.input.id, this.output.id, this.version.id, this.exitCode, this.created,
		this.userTime, this.systemTime, this.maxMemory, this.input.run,
	)

	if err != nil {
		log.Fatalf("Result: failed to store: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Result: failed to store: %d, %s", a, err)
	}
}

func (this *Input) execute(v *Version) {
	cmdArgs := strings.Split(v.command, " ")

	log.SetPrefix("[" + this.short + ":" + v.name + "] ")

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
	cmd.SysProcAttr = &syscall.SysProcAttr{ Credential: &syscall.Credential{ 99, 99, []uint32{} } }

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
		log.Printf("While starting: %s", err)
		return
	}

	go func(c *exec.Cmd, r io.Reader) {
		output := make([]byte, 0)
		buffer := make([]byte, 1024)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				log.Printf("While reading output: %s", err)
				break
			}

			buffer = buffer[:n]

			// 32 KiB is the length of phpinfo() output
			if len(output) < 32 * 1024 || (v.isHelper && len(output) < 256*1024) {
				output = append(output, buffer...)
				continue
			}

			if err := cmd.Process.Kill(); (err != nil && err.Error() != "os: process already finished" && err.Error() != "no such process") {
				this.penalize("Didn't stop: "+ err.Error(), 256)
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
			log.Printf("While waiting for process: %s", err)
		}

		procDone <- state
	}(cmd)

	var state *os.ProcessState
	var output string

	select {
	case <-time.After(2500 * time.Millisecond):
		if err := cmd.Process.Kill(); err != nil {
			log.Printf("FYI kill after timeout resulted in : %s", err)

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

	waitStatus := state.Sys().(syscall.WaitStatus)
	usage := state.SysUsage().(*syscall.Rusage)

	var exitCode int
	if waitStatus.Exited() {
		exitCode = waitStatus.ExitStatus()
	} else {
		exitCode = 128 + int(waitStatus.Signal())
	}

	r := Result{
		input:		this,
		output:		&Output{0, output},
		version:	v,
		exitCode:	exitCode,
		created:	time.Now(),
		userTime:	float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime:	float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:	usage.Maxrss,
	}
	r.store()
}

func refreshVersions() {
	log.Printf("Refreshing versions")

	versions = []*Version{}
	versionIndex = map[string]int{}

	rs, err := db.Query("SELECT id, name, command, \"isHelper\" FROM version ORDER BY \"order\" DESC")

	if err != nil {
		log.Fatalf("Could not populate versions: %s", err)
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.command, &v.isHelper); err != nil {
			log.Fatalf("Error fetching version: %s", err)
		}

		versionIndex[v.name] = len(versions)
		versions = append(versions, &v)
	}
}

func doWork() {
	rs, err := db.Query("DELETE FROM queue RETURNING input, version")

	if err != nil {
		log.Fatalf("doWork: error in DELETE query: %s", err)
	}

	for rs.Next() {
		input := &Input{}
		input.uniqueOutput = map[string]bool{}
		var v sql.NullString

		if err := rs.Scan(&input.short, &v); err != nil {
			log.Fatalf("doWork: error fetching work: %s", err)
		}

		if err := db.QueryRow("SELECT id FROM input WHERE short = $1", input.short).Scan(&input.id); err != nil {
			log.Fatalf("doWork: error verifying input: %s", err)
		}

		input.setBusy(!v.Valid)

		if v.Valid {
			input.execute(versions[ versionIndex[v.String] ])
		} else {
			for _, v := range versions {
				input.execute(v)
			}
		}

		input.setDone()
	}
}

func errorReport(ev pq.ListenerEventType, err error) {
	if err != nil {
		log.Printf("Daemon.report: %s", err)
	}
}

var (
	db *sql.DB
	l *pq.Listener
	versions []*Version
	versionIndex map[string]int
)

const (
	RLIMIT_NPROC = 0x6
	DSN = "user=daemon password=password host=/run/postgresql/ dbname=phpshell sslmode=disable"
)

func init() {
	var err error
	db, err = sql.Open("postgres", DSN)

	if err != nil {
		log.Fatalf("Failed connect to db: %s", err)
	}

	if err := db.Ping(); err != nil {
		log.Fatalf("Failed to ping db: %s", err)
	}

	l = pq.NewListener(DSN, 1 * time.Second, time.Minute, errorReport)

	if err := l.Listen("daemon"); err != nil {
		log.Fatalf("Could not setup Listener %s", err)
	}

	refreshVersions()

	var limits = map[int]int{
//		syscall.RLIMIT_CPU:		2,
		syscall.RLIMIT_DATA:	256 * 1024 * 1024,
//		syscall.RLIMIT_FSIZE:	64 * 1024,
		syscall.RLIMIT_FSIZE:	16 * 1024 * 1024,
		syscall.RLIMIT_CORE:	0,
		syscall.RLIMIT_NOFILE:	2048,
		RLIMIT_NPROC:			64,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{uint64(value), uint64(float64(value)*1.25)}); err != nil {
			log.Fatalf("Failed to set resourceLimit: %d to %d: %s", key, value, err)
		}
	}
}

func main() {
	// Process pending work first
	doWork()

	for {
		select {
			case <-l.Notify:
				log.Printf("Notification received, checking for work")
				doWork()

			case <-time.After(5 * time.Minute):
				refreshVersions()

			case <-time.After(90 * time.Second):
				log.Printf("Proactively finding work")
				doWork()
		}
	}
}