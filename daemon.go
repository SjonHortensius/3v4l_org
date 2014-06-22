package main

import (
	"crypto/sha1"
	"database/sql"
	"encoding/base64"
	_ "github.com/lib/pq"
	"io"
	"log"
	"os"
	"os/exec"
	"os/signal"
	"strings"
	"syscall"
	"time"
)

type Version struct {
	name string
	command string
	isHelper bool
}

type Input struct {
	short string
	uniqueOutput map [string]bool
	penalty	int
}

type Output struct {
	raw string
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
		if _, err := db.Exec("UPDATE input SET state = 'abusive' WHERE short = $1 AND state = 'busy'", input.short); err != nil {
			log.Printf("Input: failed to update state to `abusive`: %s", err)
		}

		log.Fatalf("Penalty limit reached: aborting")
	}
}

func (this *Input) newRun() int {
	if r, err := db.Exec("UPDATE input SET run = run + 1, state = $1 WHERE short = $2", "busy", this.short); err != nil {
		log.Fatalf("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Input: failed to update run+state; %d rows affected, %s", a, err)
	}

	var run int
	if err := db.QueryRow("SELECT run FROM input WHERE short = $1", this.short).Scan(&run); err != nil {
		log.Fatalf("Input: failed to query run: %s", err)
	}
	return run
}

func (this *Output) process(result *Result) bool {
	this.raw = strings.Replace(this.raw, result.version.name, "\x06", -1)
	this.raw = strings.Replace(this.raw, result.input.short, "\x07", -1)

	return true
}

func (this *Output) getHash() string {
	h := sha1.New()
	io.WriteString(h, this.raw)
	return base64.StdEncoding.EncodeToString(h.Sum(nil))
}

func (this *Result) store() {
	this.output.process(this)

	hash := this.output.getHash()

	if false == this.input.uniqueOutput[hash] {
		this.input.uniqueOutput[hash] = true;

		if !this.version.isHelper {
			this.input.penalize("Excessive total output", len(this.output.raw)/2048)
		}
	}

	if _, err := db.Exec("INSERT INTO output VALUES ($1, $2)", this.output.getHash(), this.output.raw); err != nil {
		log.Fatalf("Output: failed to store: %s", err)
	}

	r, err := db.Exec("INSERT INTO result VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9)",
		this.input.short, this.output.getHash(), this.version.name, this.exitCode,
		this.created, this.userTime, this.systemTime, this.maxMemory, run,
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

	// FIXME Should not be necessary
	if err := syscall.Setgid(99); err != nil {
		log.Fatalf("Failed to setgid: %v", err)
	}

	if err := syscall.Setuid(99); err != nil {
		log.Fatalf("Failed to setuid: %v", err)
	}

	cmdArgs = append(cmdArgs, "/in/"+this.short)
	cmd := exec.Command(cmdArgs[0], cmdArgs[1:]...)
//	cmd.Args[0] = "php" # hhvm doesn't like this...
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
		output:		&Output{output},
		version:	v,
		exitCode:	exitCode,
		created:	time.Now(),
		userTime:	float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime:	float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:	usage.Maxrss,
	}
	r.store()
}

var (
	db *sql.DB
	input *Input
	run int
)

const RLIMIT_NPROC = 0x6

func init() {
	if len(os.Args) < 2 {
		log.Fatal("Missing input: script")
	}

	if script, err := os.Stat(os.Args[1]); err != nil || script.IsDir() {
		log.Fatalf("First argument is not a valid file: %s", err)
	} else {
		input = &Input{script.Name(), make(map[string]bool), 0}
	}

	var err error
	db, err = sql.Open("postgres", "user=daemon password=password host=/run/postgresql/ dbname=phpshell sslmode=disable")

	if err != nil {
		log.Fatalf("Failed connect to db: %s", err)
	}

	// Ping to establish connection so we can drop privileges
	if err := db.Ping(); err != nil {
		log.Fatalf("Failed ping db: %s", err)
	}

	var limits = map[int]int{
		syscall.RLIMIT_CPU:		2,
		syscall.RLIMIT_DATA:	256 * 1024 * 1024,
//		syscall.RLIMIT_FSIZE:	64 * 1024,
		syscall.RLIMIT_FSIZE:	16 * 1024 * 1024,
		syscall.RLIMIT_CORE:	0,
		syscall.RLIMIT_NOFILE:	2048,
		RLIMIT_NPROC:			64,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{uint64(value), uint64(float64(value)*1.25)}); err != nil {
			log.Fatalf("Failed to set resourcelimit: %d to %d: %s", key, value, err)
		}
	}

	if err := syscall.Setgid(99); err != nil {
		log.Fatalf("Failed to setgid: %v", err)
	}

	if err := syscall.Setuid(99); err != nil {
		log.Fatalf("Failed to setuid: %v", err)
	}
}

func main() {
	log.SetPrefix("[" + input.short + "] ")
	run = input.newRun()

	defer func() {
		syscall.Setgid(99);
		syscall.Setuid(99);

		os.RemoveAll("/tmp/")

		// Sometimes we lose /tmp. Test if we cause this by recreating it
		syscall.Umask(0)
		os.Mkdir("/tmp/", 0777|os.ModeSticky)
	}()

	rs, err := db.Query("SELECT name, command, \"isHelper\" FROM version ORDER BY \"order\" DESC")

	if err != nil {
		log.Fatal("No versions found to execute: %s", err)
	}

	var versions []Version
	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.name, &v.command, &v.isHelper); err != nil {
			log.Fatal("Error fetching version: %s", err)
		}

		versions = append(versions, v)
	}

	c := make(chan os.Signal, 1)
	signal.Notify(c)

	go func() {
		for {
			s := <-c
			if s.String() != "child exited" {
				log.Println("Got signal:", s)
			}
		}
	}()

	for _, v := range versions {
		input.execute(&v)
	}

	if _, err := db.Exec("UPDATE input SET state = 'done' WHERE short = $1 AND state = 'busy'", input.short); err != nil {
		log.Printf("Input: failed to update state to `done`: %s", err)
	}

	db.Close()
}
