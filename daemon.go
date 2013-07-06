package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"log"
	"io"
	"syscall"
	"time"
	_ "github.com/lib/pq"
	"database/sql"
	"crypto/sha1"
	"encoding/base64"
)

type Input struct {
	short string
}

type Output struct {
	raw string
}

type Result struct {
	input *Input
	output *Output
	version string
	exitCode int
	created time.Time
	userTime float64
	systemTime float64
	maxMemory int64
}

func (this *Input) setState(s string) {
	if r, err := db.Exec("UPDATE input SET state = $1 WHERE short = $2", s, this.short); err != nil {
		log.Fatalf("Input: failed to update state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Input: failed to update state: %d, %s", a, err)
	}
}

func (this *Input) newRun() int {
	if r, err := db.Exec("UPDATE input SET run = run + 1, state = $1 WHERE short = $2", "busy", this.short); err != nil {
		log.Fatalf("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Input: failed to update run+state: %d, %s", a, err)
	}

	var run int
	if err := db.QueryRow("SELECT run FROM input WHERE short = $1", this.short).Scan(&run); err != nil {
		log.Fatalf("Input: failed to query run: %s", err)
	}
	return run;
}

func (this *Output) getHash() string {
	h := sha1.New()
	io.WriteString(h, this.raw)
	return base64.StdEncoding.EncodeToString(h.Sum(nil))
}

func (this *Result) store() {
	if _, err := db.Exec("INSERT INTO output VALUES ($1, $2)", this.output.getHash(), this.output.raw); err != nil {
		log.Fatalf("Output: failed to store: %s", err)
	}

	r, err := db.Exec("INSERT INTO result VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9)",
		this.input.short, this.output.getHash(), this.version, this.exitCode,
		this.created, this.userTime, this.systemTime, this.maxMemory, run,
	)

	if err != nil {
		log.Fatalf("Result: failed to store: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		log.Fatalf("Result: failed to store: %d, %s", a, err)
	}
}

func (this *Input) execute(binary string) {
	log.SetPrefix("["+this.short+":"+binary[len("/usr/bin/php-"):]+"] ")

	cmd := exec.Command(binary, "-c", "/etc", "-q", "/in/"+this.short)
	cmd.Args[0] = "php"
	cmd.Env = []string {
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

	go func() {
		stdout, _ := cmd.StdoutPipe()
		stderr, _ := cmd.StderrPipe()
		r := io.MultiReader(stdout, stderr)

		cmd.Start()

		go func() {
			state, _ := cmd.Process.Wait()
			procDone <- state
		}()

		output := make([]byte, 0)
		buffer := make([]byte, 256)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				log.Printf("While reading output: %s", err)
				break
			}

			buffer = buffer[:n]
			output = append(output, buffer...)

			if len(output) >= 131070 {
				this.setState("verbose")
				log.Println("Output excessive: killing")

				if err := cmd.Process.Kill(); err != nil {
					this.setState("abusive")
					log.Fatalf("Failed to kill child `%s`, aborting", err)
				}

				break
			}
		}

		procOut <- string(output)
	}()

	var state *os.ProcessState
	var output string

	select {
		case <-time.After(2250 * time.Millisecond):
			if err := cmd.Process.Kill(); err != nil {
				this.setState("abusive")
				log.Fatalf("Timeout: Failed to kill child `%s`, aborting", err)
			}

			state = <-procDone
			output = <-procOut

			this.setState("misbehaving")
			log.Println("Timeout: killed")
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
		version:	binary[len("/usr/bin/php-"):],
		exitCode:	exitCode,
		created:	time.Now(),
		userTime:	float64(usage.Utime.Sec) + float64(usage.Utime.Usec) / 1000000.0,
		systemTime:	float64(usage.Stime.Sec) + float64(usage.Stime.Usec) / 1000000.0,
		maxMemory:	usage.Maxrss,
	}
	r.store()
	log.Printf("Completed with status %d", r.exitCode)
}

var (
	db *sql.DB
	input *Input
	run int
)

func init() {
	if len(os.Args) < 2 {
		log.Fatal("Missing input: script")
	}

	if script, err := os.Stat(os.Args[1]); err != nil || script.IsDir() {
		log.Fatalf("First argument is not a valid file: %s", err)
	} else {
		input = &Input{script.Name()}
	}

	var limits = map [int]int {
		syscall.RLIMIT_CPU:		2,
		syscall.RLIMIT_DATA:	64 * 1024 * 1024,
		syscall.RLIMIT_FSIZE:	64 * 1024,
		syscall.RLIMIT_CORE:	0,
		syscall.RLIMIT_NOFILE:	2048,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{uint64(value), uint64(value)}); err != nil {
			log.Fatalf("Failed to set resourcelimit: %d to %d: %s", key, value, err)
		}
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

	if err := syscall.Setgid(99); err != nil {
		log.Fatalf("Failed to setgid: %v", err)
	}

	if err := syscall.Setuid(99); err != nil {
		log.Fatalf("Failed to setuid: %v", err)
	}
}

func main() {
	log.SetPrefix("["+input.short+"] ")
	run = input.newRun()

	versions, err := filepath.Glob("/usr/bin/php-*")

	if  err != nil {
		log.Fatal("No binaries found to execute: %s", err)
	}

	for _, version := range versions {
		input.execute(version)
	}

	input.setState("done")
	db.Close()
}