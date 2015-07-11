package main

import (
	"crypto/sha1"
	"database/sql"
	"encoding/base64"
	"github.com/lib/pq"
	"io"
	"fmt"
	"os"
	"os/exec"
	"net"
	"strings"
	"syscall"
	"time"
)

type Version struct {
	id			int
	name		string
	command		string
	isHelper	bool
}

type Input struct {
	id				int
	short			string
	uniqueOutput	map[string]bool
	penalty			int
	run				int
}

type Output struct {
	id			int
	raw			string
	hash		string
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

func SdNotify(state string) error {
	socketAddr := &net.UnixAddr{
		Name: os.Getenv("NOTIFY_SOCKET"),
		Net:  "unixgram",
	}

	if socketAddr.Name == "" {
		return fmt.Errorf("Systemd notification socket not found")
	}

	conn, err := net.DialUnix(socketAddr.Net, nil, socketAddr)
	if err != nil {
		return err
	}

	_, err = conn.Write([]byte(state))
	if err != nil {
		return err
	}

	return nil
}
func notifyReady() error { return SdNotify("READY=1")}
func notifyStatus(status string) error { return SdNotify(fmt.Sprintf("STATUS=%s", status))}
func notifyErrno(errno uint) error { return SdNotify(fmt.Sprintf("ERRNO=%d", errno))}
func notifyWatchdog() error { return SdNotify("WATCHDOG=1")}

func exitError(format string, v ...interface{}){
	fmt.Fprintf(os.Stderr, format, v...)
	os.Exit(1)
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p

	if p < 1 {
		return
	}

	if this.penalty > 256 {
		fmt.Printf("Penalized %d for: %s", p, r)
	}
}

func (this *Input) setBusy(newRun bool) {
	notifyStatus("executing "+ this.short)

	incRun := 0
	if newRun {
		incRun = 1
	}

	if r, err := db.Exec("UPDATE input SET run = run + $2, state = 'busy' WHERE short = $1", this.short, incRun); err != nil {
		exitError("Input: failed to update run+state: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		exitError("Input: failed to update run+state; %d rows affected, %s", a, err)
	}

	if err := db.QueryRow("SELECT id, run FROM input WHERE short = $1", this.short).Scan(&this.id, &this.run); err != nil {
		exitError("Input: failed to fetch run: %s", err)
	}
}

func (this *Input) setDone() {
	state := "done"
	if this.penalty > 256 {
		state = "abusive"
	}

	if _, err := db.Exec("UPDATE input SET penalty = (penalty * (run-1) + $2) / GREATEST(run, 1), state = $3 WHERE short = $1 AND state = 'busy'", this.short, this.penalty, state); err != nil {
		exitError("Input: failed to update: %s", err)
	}

	fmt.Printf("state = %s penalty = %d", state, this.penalty)

	notifyStatus("completed "+ this.short)
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

	if err := db.QueryRow("SELECT id FROM output WHERE hash = $1", o.hash).Scan(&o.id); err != nil {
		var duplicateKey = "pq: duplicate key value violates unique constraint \"output_hash_key\""
		if _, err := db.Exec("INSERT INTO output VALUES ($1, $2)", o.hash, o.raw); err != nil && err.Error() != duplicateKey {
			exitError("Output: failed to store: %s", err)
		}

		// LastInsertId doesn't work
		if err := db.QueryRow("SELECT id FROM output WHERE hash = $1", o.hash).Scan(&o.id); err != nil {
			exitError("Output: failed to retrieve after storing: %s", err)
		}
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
		input:		i,
		output:		newOutput(raw, i, v),
		version:	v,
		exitCode:	exitCode,
		created:	time.Now(),
		userTime:	float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime:	float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:	usage.Maxrss,
	}

	i.penalize("Total runtime", int(usage.Utime.Sec) + int(usage.Stime.Sec))

	r.store()

	return r
}

func (this *Result) store() {
	r, err := db.Exec("INSERT INTO result VALUES( $1, $2, $3, $4, $5, $6, $7, $8, $9)",
		this.input.id, this.output.id, this.version.id, this.exitCode, this.created,
		this.userTime, this.systemTime, this.maxMemory, this.input.run,
	)

	if err != nil {
		exitError("Result: failed to store: %s", err)
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		exitError("Result: failed to store: %d, %s", a, err)
	}
}

func (this *Input) execute(v *Version) *Result {
	cmdArgs := strings.Split(v.command, " ")

	notifyStatus("executing "+ this.short +": "+ v.name)

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
	cmd.SysProcAttr = &syscall.SysProcAttr{Credential: &syscall.Credential{99, 99, []uint32{}}}

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
		fmt.Printf("While starting: %s", err)
		return &Result{}
	}

	go func(c *exec.Cmd, r io.Reader) {
		output := make([]byte, 0)
		buffer := make([]byte, 1024)
		for n, err := r.Read(buffer); err != io.EOF; n, err = r.Read(buffer) {
			if err != nil {
				fmt.Printf("While reading output: %s", err)
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
			fmt.Printf("While waiting for process: %s", err)
		}

		procDone <- state
	}(cmd)

	var state *os.ProcessState
	var output string

	select {
	case <-time.After(2500 * time.Millisecond):
		if err := cmd.Process.Kill(); err != nil {
			fmt.Printf("Kill after timeout resulted in : %s", err)

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

	rs, err := db.Query("SELECT id, name, command, \"isHelper\" FROM version ORDER BY \"order\" DESC")

	if err != nil {
		exitError("Could not populate versions: %s", err)
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.command, &v.isHelper); err != nil {
			exitError("Error fetching version: %s", err)
		}

		newVersions = append(newVersions, &v)
	}

	versions = newVersions
}

func doWork() {
	rs, err := db.Query("DELETE FROM queue RETURNING input, version")

	if err != nil {
		exitError("doWork: error in DELETE query: %s", err)
	}

	var input Input;
	for rs.Next() {
		if input.short != "" {
			time.Sleep(250 * time.Millisecond)
		}

		input.uniqueOutput = map[string]bool{}
		var qVersion sql.NullString

		if err := rs.Scan(&input.short, &qVersion); err != nil {
			exitError("doWork: error fetching work: %s", err)
		}

		if err := db.QueryRow("SELECT id FROM input WHERE short = $1", input.short).Scan(&input.id); err != nil {
			exitError("doWork: error verifying input: %s", err)
		}

		input.setBusy(!qVersion.Valid)

		for _, v := range versions {
			if !qVersion.Valid || qVersion.String == v.name {
				input.execute(v)
			}
		}

		input.setDone()
	}
}

var (
	db *sql.DB
	l *pq.Listener
	versions []*Version
)

const (
	RLIMIT_NPROC = 0x6
	DSN = "user=daemon password=password host=/run/postgresql/ dbname=phpshell sslmode=disable"
)

func init() {
	var err error
	db, err = sql.Open("postgres", DSN)

	if err != nil {
		exitError("Failed connect to db: %s", err)
	}

	if err := db.Ping(); err != nil {
		exitError("Failed to ping db: %s", err)
	}

	l = pq.NewListener(DSN, 1*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			fmt.Printf("Daemon.report: %s", err)
		}
	})

	if err := l.Listen("daemon"); err != nil {
		exitError("Could not setup Listener %s", err)
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
		if err := syscall.Setrlimit(key, &syscall.Rlimit{uint64(value), uint64(float64(value) * 1.25)}); err != nil {
			exitError("Failed to set resourceLimit: %d to %d: %s", key, value, err)
		}
	}
}

func main() {
	fmt.Printf("Daemon started")
	notifyReady()

	// Process pending work first
	doWork()

	for {
		select {
		case <-l.Notify:
			doWork()

		//FIXME: doesn't run every 5mins, but only after 5mins of inactivity
		case <-time.After(5 * time.Minute):
			notifyStatus("refreshed versions")
			refreshVersions()

		}
	}
}