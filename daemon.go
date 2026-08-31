package main

import (
	"crypto/sha1"
	"database/sql"
	"encoding/base64"
	"flag"
	"fmt"
	"github.com/lib/pq"
	"io"
	"io/fs"
	"net"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"syscall"
	"time"
)

type Version struct {
	id       int
	name     string
	command  string
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
}

type Output struct {
	id   int
	raw  string
	hash string
}

type Result struct {
	input      *Input
	output     Output
	version    Version
	exitCode   int
	userTime   float64
	systemTime float64
	maxMemory  int64
}

type ResourceLimit struct {
	packets int
	runtime int
	output  int
}

type SizedWaitGroup struct {
	limit   int
	current chan bool
	*sync.WaitGroup
}

type Stats struct {
	inputs  atomic.Int64
	outputs atomic.Int64
	results atomic.Int64
	penalty atomic.Int64
}

func newSizedWaitGroup(limit int) SizedWaitGroup {
	return SizedWaitGroup{limit, make(chan bool, limit), &sync.WaitGroup{}}
}
func (s *SizedWaitGroup) Add()  { s.current <- true; s.WaitGroup.Add(1) }
func (s *SizedWaitGroup) Done() { <-s.current; s.WaitGroup.Done() }

func (this *Stats) Increase(field string, n int) {
	switch field {
	case "inputs":
		this.inputs.Add(int64(n))
	case "outputs":
		this.outputs.Add(int64(n))
	case "results":
		this.results.Add(int64(n))
	case "penalty":
		this.penalty.Add(int64(n))
	}
}

func (s *Stats) ResetReturn() string {
	defer func(){ s.inputs.Store(0); s.outputs.Store(0); s.results.Store(0); s.penalty.Store(0) }()

	return fmt.Sprintf("inputs=%d outputs=%d results=%d penalty=%d",
		s.inputs.Load(), s.outputs.Load(), s.results.Load(), s.penalty.Load())
}

func (this *Input) penalize(r string, p int) {
	this.penalty += p
	stats.Increase("penalty", p)

	if p > 1 {
		this.Lock()
		this.penaltyDetail[r] += p
		this.Unlock()
	}
}

func (this *Input) prepareSource() {
	inputSrc.Lock()
	inputSrc.srcUse[this.short]++
	if 1 == inputSrc.srcUse[this.short] {
		if f, err := os.Create(inPath + this.short); err != nil {
			panic("Input: could not create file: " + err.Error())
		} else {
			var raw []byte
			if err := db.QueryRow(`SELECT raw FROM input_src WHERE input = $1`, this.id).Scan(&raw); err != nil {
				panic("Input: could not retrieve source: " + err.Error())
			} else {
				f.Write(raw)
			}

			f.Close()
		}
	}
	inputSrc.Unlock()

	if this.lastSubmit.IsZero() {
		db.QueryRow(`SELECT COALESCE(
				(SELECT MAX(COALESCE(updated, created)) FROM submit WHERE input = $1 AND NOT "isQuick"),
				(SELECT MAX(COALESCE(updated, created)) FROM submit WHERE input = $1)
			)`, this.id).Scan(&this.lastSubmit)
	}

	this.penaltyDetail = make(map[string]int)
}

func (this *Input) prepare() {
	this.uniqueOutput = make(map[string]bool)

	this.prepareSource()

	if dryRun {
		return
	}

	if r, err := db.Exec(`UPDATE input SET state = 'busy' WHERE id = $1`, this.id); err != nil {
		panic("Input: failed to update state: " + err.Error())
	} else if a, err := r.RowsAffected(); a != 1 || err != nil {
		panic(fmt.Sprintf("Input: failed to update state; %d rows affected, %s", a, err))
	}
}

func (this *Input) removeSource() {
	inputSrc.Lock()
	inputSrc.srcUse[this.short]--
	if 0 == inputSrc.srcUse[this.short] {
		if err := os.Remove(inPath + this.short); err != nil {
			fmt.Fprintf(os.Stderr, "[%s] failed to remove source: %s\n", this.short, err)
		}

		delete(inputSrc.srcUse, this.short)
	}
	inputSrc.Unlock()

	stats.Increase("inputs", 1)
}

func (this *Input) complete() {
	state := "done"
	if this.penalty > 256 {
		state = "abusive"
	}

	this.removeSource()

	if this.penalty > 128 {
		fmt.Printf("[%s] state = %s | penalty = %d | %v\n", this.short, state, this.penalty, this.penaltyDetail)
	}

	if _, err := db.Exec(`UPDATE input SET penalty = LEAST($2, 32767), state = $3 WHERE short = $1`, this.short, this.penalty, state); err != nil {
		panic(fmt.Sprintf("Input: failed to update: %s | %+v", err.Error(), this))
	}
}

func newOutput(raw string, i *Input, v Version) Output {
	raw = strings.ReplaceAll(raw, "\x06", "\\\x06")
	raw = strings.ReplaceAll(raw, "\x07", "\\\x07")
	raw = strings.ReplaceAll(raw, v.name, "\x06")
	raw = strings.ReplaceAll(raw, i.short, "\x07")

	h := sha1.Sum([]byte(raw))
	o := Output{0, raw, base64.StdEncoding.EncodeToString(h[:])}

	if err := db.QueryRow(`INSERT INTO output VALUES ($1, $2) ON CONFLICT (hash) DO NOTHING RETURNING id`, o.hash, o.raw).Scan(&o.id); err == sql.ErrNoRows {
		// ON CONFLICT does not RETURN id so fetch that
		db.QueryRow(`SELECT id FROM output WHERE hash = $1`, o.hash).Scan(&o.id)
	} else if err != nil {
		panic("Output: failed to store: " + err.Error())
	} else {
		stats.Increase("outputs", 1)
	}

	i.Lock()
	if !i.uniqueOutput[o.hash] {
		i.uniqueOutput[o.hash] = true
		i.Unlock()
		i.penalize("Excessive total output", len(o.raw)/2048)
	} else {
		i.Unlock()
	}

	return o
}

func (this *Result) store() {
	old := &Result{}
	tx, err := db.Begin()
	if err != nil {
		fmt.Printf("Result: failed to start tx: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)

		return
	}

	if err := tx.QueryRow(
		`SELECT output, "exitCode" FROM result WHERE input = $1 AND version = $2 FOR UPDATE`,
		this.input.id, this.version.id).Scan(&old.output.id, &old.exitCode); err == sql.ErrNoRows {

		// Instead of locking the whole results table, use the `input` as lock target, this allows concurrent calls but only on other inputs
		if _, err := tx.Exec(`SELECT * FROM input WHERE id = $1 FOR UPDATE`, this.input.id); err != nil {
			fmt.Printf("Result: failed to lock input: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)

			return
		}

		if _, err := tx.Exec(`INSERT INTO result VALUES ($1, $2, $3, $4, $5, $6, $7)`,
			this.input.id, this.version.id, this.output.id, this.exitCode,
			this.userTime, this.systemTime, this.maxMemory); err != nil {
			fmt.Printf("Result: failed to create: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)
		}
	} else if err == nil {
		mutated := 0

		if old.output.id != this.output.id || old.exitCode != this.exitCode {
			mutated = 1
		}

		if _, err := tx.Exec(`
			UPDATE result
			SET
				output = $3, "exitCode" = $4,
				"userTime" =   ((runs * "userTime"  + $5) / (result.runs+1)),
				"systemTime" = ((runs * "systemTime"+ $6) / (result.runs+1)),
				"maxMemory" =  ((runs * "maxMemory" + $7) / (result.runs+1)),
				runs = result.runs + 1, mutations = result.mutations + $8
			WHERE
				input = $1 AND version = $2`,
			this.input.id, this.version.id,
			this.output.id, this.exitCode,
			this.userTime, this.systemTime, this.maxMemory, mutated); err != nil {
			fmt.Printf("Result: failed to update: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)
		}
	} else if err != nil {
		fmt.Printf("Result: failed to select-for-update: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)
	}

	if err := tx.Commit(); err != nil {
		fmt.Printf("Result: failed to commit: input=%s,version=%s,output=%d: %s\n", this.input.short, this.version.name, this.output.id, err)
	}
}

func (this *Result) delete() {
	if _, err := db.Exec(`DELETE FROM result WHERE input=$1 AND version=$2`, this.input.id, this.version.id); err != nil {
		fmt.Printf("Result: failed to delete: input=%s,version=%s: %s\n", this.input.short, this.version.name, err)
	}
}

func (this *Input) execute(cmdArgs []string, l ResourceLimit) (string, *os.ProcessState) {
	discardStdout := cmdArgs[len(cmdArgs)-1] == "2>/dev/null"
	if discardStdout {
		cmdArgs = cmdArgs[:len(cmdArgs)-1]
	}

	cmdArgs = append(cmdArgs, inPath+this.short)
	cmd := exec.Command(cmdArgs[0], cmdArgs[1:]...)
	cmd.Env = []string{
		"LD_PRELOAD=/usr/bin/daemon-preload.so",
		"TERM=xterm",
		"PATH=/usr/bin:/bin",
		"LANG=C",
		"SHELL=/bin/sh",
		"MAIL=/var/mail/nobody",
		"LOGNAME=nobody",
		"USER=nobody",
		"HOME=/tmp",
	}

	if !dryRun {
		cmd.SysProcAttr = &syscall.SysProcAttr{Credential: &syscall.Credential{Uid: 99, Gid: 99}}
	}

	if !this.lastSubmit.IsZero() {
		cmd.Env = append(cmd.Env, "TIME="+strconv.FormatInt(this.lastSubmit.Unix(), 10))
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

	if discardStdout {
		cmdR = stderr
	}

	if err := cmd.Start(); err != nil {
		fmt.Fprintf(os.Stderr, "While starting: %s\n", err)
		return "", nil
	}

	go func(c *exec.Cmd, r io.Reader) {
		limited := &io.LimitedReader{R: r, N: int64(l.output + 1)}
		data, err := io.ReadAll(limited)

		if err != nil && err != io.EOF {
			fmt.Fprintf(os.Stderr, "While reading output: %s\n", err)
		}

		if limited.N == 0 {
			cmd.Process.Kill()
		}

		if len(data) > l.output {
			data = data[:l.output]
		}

		this.penalize("Excessive output", len(data)/10240)

		procOut <- string(data)
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

	if err := cleanTmpDirectory(); err != nil {
		fmt.Fprintf(os.Stderr, "cleanTmpDirectory - %s\n", err)
	}

	return output, state
}

func (this *Input) storeResult(v Version, raw string, s *os.ProcessState) {
	if dryRun {
		fmt.Printf("\033[1mstoreResult: input=%s | version=%s | output:\033[0m %s\n", this.short, v.name, raw)
		return
	}

	waitStatus := s.Sys().(syscall.WaitStatus)
	usage := s.SysUsage().(*syscall.Rusage)

	var exitCode int
	if waitStatus.Exited() {
		exitCode = waitStatus.ExitStatus()
	} else {
		exitCode = 128 + int(waitStatus.Signal())
	}

	r := Result{
		input:      this,
		output:     newOutput(raw, this, v),
		version:    v,
		exitCode:   exitCode,
		userTime:   float64(usage.Utime.Sec) + float64(usage.Utime.Usec)/1000000.0,
		systemTime: float64(usage.Stime.Sec) + float64(usage.Stime.Usec)/1000000.0,
		maxMemory:  usage.Maxrss,
	}

	this.penalize("Total runtime", int(usage.Utime.Sec)+int(usage.Stime.Sec))
	r.store()

	stats.Increase("results", 1)

	return
}

func (this *Input) storeVldOutput(raw string, s *os.ProcessState) {
	if dryRun {
		fmt.Printf("\033[1mstoreVldHelperOutput: input=%s\033[0m %s\n", this.short, raw)
		return
	}

	waitStatus := s.Sys().(syscall.WaitStatus)
	if !waitStatus.Exited() || waitStatus.ExitStatus() != 0 {
		fmt.Fprintf(os.Stderr, "storeVldOutput input=%s; helper exited with status %d\n", this.short, waitStatus.ExitStatus())
		return
	}

	if _, err := db.Exec(`INSERT INTO helper_output VALUES ($1, $2, $3)`, this.id, "vld", raw); err != nil {
		panic("Input: failed to store helper_output: " + err.Error())
	}

	return
}

func cleanTmpDirectory() error {
	filepath.WalkDir("/tmp", func(p string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		if len(p) > 5 {
			// we have CAP_FOWNER - use it to give the script runner write access
			os.Chmod(p, 0007)
		}
		return nil
	})

	entries, err := os.ReadDir("/tmp")
	if err != nil {
		return err
	}

	for _, e := range entries {
		os.RemoveAll(filepath.Join("/tmp", e.Name()))
	}

	return nil
}

func refreshVersions() {
	var newVersions []Version

	rs, err := db.Query(`
		SELECT id, name, COALESCE(released, '1900-01-01'), COALESCE(eol, '2999-12-31'), COALESCE("order", 0), command
		FROM version
		ORDER BY "order" DESC`)

	if err != nil {
		panic("daemon: could not SELECT: " + err.Error())
	}

	for rs.Next() {
		v := Version{}

		if err := rs.Scan(&v.id, &v.name, &v.released, &v.eol, &v.order, &v.command); err != nil {
			panic("daemon: error Scanning: " + err.Error())
		}

		newVersions = append(newVersions, v)
	}

	versions = newVersions
}

func checkPendingInputs() {
	// we ignore isQuick but this rarely gets executed so that's not a problem
	rs, err := db.Query(`
		SELECT id, short, created, penalty, "runArchived", state FROM input
		WHERE
			state IN('new', 'busy')
			AND created < NOW() - INTERVAL '5 minutes'
		ORDER BY created DESC`)

	if err != nil {
		panic("checkPendingInputs: could not SELECT: " + err.Error())
	}

	l := ResourceLimit{0, 2500, 32768}

	var state string
	for rs.Next() {
		var input Input
		if err := rs.Scan(&input.id, &input.short, &input.created, &input.penalty, &input.runArchived, &state); err != nil {
			panic("checkPendingInputs: error Scanning: " + err.Error())
		}

		fmt.Printf("checkPendingInputs - scheduling [%s] %s\n", state, input.short)
		input.prepare()

		for _, v := range versions {
			if input.runArchived || v.eol.After(input.created) {
				o, s := input.execute(strings.Split(v.command, " "), l)
				input.storeResult(v, o, s)
			}

			if input.penalty > 512 {
				break
			}
		}

		input.complete()
	}
}

func canBatch(doSleep bool) (bool, error) {
	var i syscall.Sysinfo_t
	if err := syscall.Sysinfo(&i); err != nil {
		return false, err
	}

	scale := 65536.0 // magic
	l1 := float64(i.Loads[0]) / scale
	l5 := float64(i.Loads[1]) / scale

	if doSleep {
		time.Sleep(time.Duration(int(10*l1)/runtime.NumCPU()) * time.Millisecond)
	}

	if int(l5) > runtime.NumCPU() {
		fmt.Printf("Load5 [%.1f] seems high (for %d cpus), skipping batch\n", l5, runtime.NumCPU())
		time.Sleep(time.Duration(30*l5) * time.Second)
		return false, nil
	}

	if int(l1) > runtime.NumCPU() {
		fmt.Printf("Load1 [%.1f] seems high (for %d cpus), sleeping...\n", l1, runtime.NumCPU())
		time.Sleep(time.Duration(3*l1) * time.Second)
	}

	return true, nil
}

func batchScheduleNewVersions() {
	if batchSnv {
		return
	} else {
		defer func() { batchSnv = false }()
		batchSnv = true
	}

	// with batching disabled, skip heavy SELECT (only Add blocks)
	if 0 == batch.limit {
		return
	}

	batch.Wait()

	for _, v := range versions {
		if err := db.QueryRow(`SELECT id FROM "version_forBughunt" WHERE id = $1`, v.id).Scan(&v.id); err != nil {
			continue
		}

		fmt.Printf("batchScheduleNewVersions: %s - searching for missing scripts\n", v.name)

		rs, err := db.Query(`
			SELECT id, short, "runArchived", created, penalty
			FROM input
			LEFT JOIN result ON (version = $1 AND input=id)
			WHERE
				input IS NULL
				AND ("runArchived" OR created < $2::date)
				AND state = 'done'
				AND "operationCount" > 0
				AND NOT "bughuntIgnore";`,
			v.id, v.eol.Format("2006-01-02"))
		if err != nil {
			panic("batchScheduleNewVersions: could not SELECT: " + err.Error())
		}

		fmt.Printf("batchScheduleNewVersions: %s - executing\n", v.name)

		found := 0
		for rs.Next() {
			found++

			var input Input
			if err := rs.Scan(&input.id, &input.short, &input.runArchived, &input.created, &input.penalty); err != nil {
				panic("batchScheduleNewVersions: error Scanning: " + err.Error())
			}

			for c, err := canBatch(true); err != nil || !c; c, err = canBatch(true) {
				if err != nil {
					panic("batchScheduleNewVersions: unable to check load: " + err.Error())
				}
			}

			batch.Add()
			go func(i *Input) {
				i.prepare()
				o, s := i.execute(strings.Split(v.command, " "), ResourceLimit{0, 2500, 32768})
				i.storeResult(v, o, s)
				i.complete()

				batch.Done()
			}(&input)

			if found%1e5 == 0 {
				fmt.Printf("batchScheduleNewVersions: %s - completed %.3f M scripts\n", v.name, float64(found)/1e6)
			}
		}

		batch.Wait()
		if found > 0 {
			fmt.Printf("batchScheduleNewVersions: %s - completed %d scripts\n", v.name, found)
		}
	}
}

func doWork() {
	rs, err := db.Query(`DELETE FROM queue WHERE "maxPackets" = 0 RETURNING *`)

	if err != nil {
		panic("doWork: could not DELETE: " + err.Error())
	}

	for rs.Next() {
		var version sql.NullString
		var input Input
		var rMax ResourceLimit

		if err := rs.Scan(&input.short, &version, &rMax.packets, &rMax.runtime, &rMax.output); err != nil {
			panic("doWork: error Scanning: " + err.Error())
		}

		if err := db.QueryRow(`SELECT id, created, penalty, "runArchived" FROM input WHERE short = $1`, input.short).Scan(&input.id, &input.created, &input.penalty, &input.runArchived); err != nil {
			panic("doWork: error verifying input: " + err.Error())
		}

		input.prepare()
		sdNotify(fmt.Sprintf("STATUS=executing %s", input.short))

		for _, v := range versions {
			if (version.Valid && version.String == v.name) || (!version.Valid && (input.runArchived || v.eol.After(input.created))) {
				o, s := input.execute(strings.Split(v.command, " "), rMax)
				input.storeResult(v, o, s)
			}

			if input.penalty > 512 {
				break
			}
		}

		if !input.runArchived && !version.Valid {
			if _, err := db.Exec(`DELETE FROM result WHERE input = $1 AND version IN (SELECT id FROM version WHERE eol < $2)`, input.id, input.created); err != nil {
				fmt.Printf("doWork: failed to clean: input=%d,eol=%s: %s\n", input.id, input.created, err)
			}
		}

		sdNotify(fmt.Sprintf("STATUS=completed %s", input.short))
		input.complete()
	}
}

func doVldHelper(short string) {
	input := &Input{short: short}

	if err := db.QueryRow(`SELECT id, created FROM input WHERE short = $1`, short).Scan(&input.id, &input.created); err != nil {
		panic("doVldHelper: error verifying input: `" + short + "`: " + err.Error())
	}

	sdNotify(fmt.Sprintf("STATUS=executing helper for %s", input.short))

	input.prepareSource()
	defer input.removeSource()

	o, s := input.execute([]string{"/bin/php-8.5.0", "-dextension=vld-0.19.1.so", "-dvld.active=1", "-dvld.execute=0", "2>/dev/null"}, ResourceLimit{0, 2500, 256 * 1024})
	input.storeVldOutput(o, s)
}

var (
	dbDsn    string
	db       *sql.DB
	batch    SizedWaitGroup
	batchSnv bool
	stats    Stats
	versions []Version
	dryRun   bool
	inPath   string
	sdSocket *net.UnixConn
	inputSrc struct {
		sync.Mutex
		srcUse map[string]int
	}
)

const (
	RLIMIT_NPROC = 0x6
)

func init() {
	inPath = "/in/"
	batchThreads := 0

	flag.BoolVar(&dryRun, "test", false, "perform a quick sanity check on internal operations")
	flag.IntVar(&batchThreads, "batch", 0, "perform background batching, specify number of threads")
	flag.StringVar(&dbDsn, "dsn", "", "DNS for the PostgreSQL backend")

	flag.Parse()

	if dryRun {
		inPath = "/tmp/"
	} else if batchThreads > 0 {
		batch = newSizedWaitGroup(batchThreads)
	}

	if c, err := sql.Open("postgres", dbDsn); err != nil {
		panic("init: failed to connect to db: " + err.Error())
	} else {
		db = c
	}

	db.SetMaxOpenConns(32)

	if err := db.Ping(); err != nil {
		panic("init: failed to ping db: " + err.Error())
	}

	inputSrc.srcUse = make(map[string]int)

	refreshVersions()
}

func main() {
	if dryRun {
		// run a predefined set of scripts so we don't trash someones homedir
		fmt.Printf("running tests\n")

		v := Version{0, "local php binary", "/usr/bin/php -n -q", time.Now(), 0, time.Now()}

		rs, err := db.Query(`SELECT id, short, "runArchived", created FROM input WHERE short IN ('J7G8C','7rZMO')`)
		if err != nil {
			panic("daemon: could not SELECT: " + err.Error())
		}

		var i Input
		for rs.Next() {
			if err := rs.Scan(&i.id, &i.short, &i.runArchived, &i.created); err != nil {
				panic("daemon: error Scanning: " + err.Error())
			}

			i.prepare()
			i.execute(strings.Split(v.command, " "), ResourceLimit{0, 2500, 32768})
			// we can skip complete since /tmp is already cleared by the daemon
		}

		os.Exit(0)
	}

	l := pq.NewListener(dbDsn, 1*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			panic("daemon: error creating Listener: " + err.Error())
		}
	})

	if err := l.Listen("daemon"); err != nil {
		panic("daemon: error Listening: " + err.Error())
	}

	sdNotify("READY=1")

	var (
		doPrintStats   = time.NewTicker(1 * time.Hour)
		doCheckPending = time.NewTicker(45 * time.Minute)
		doShutdown     = make(chan os.Signal, 1)
	)
	signal.Notify(doShutdown, os.Interrupt)

	// do some bg work when we're idle
	go batchScheduleNewVersions()

	// Process pending work immediately
	go checkPendingInputs()

LOOP:
	for {
		select {
		case <-doCheckPending.C:
			go checkPendingInputs()

		case <-doPrintStats.C:
			sdNotify(fmt.Sprintf("STATUS=%s", stats.ResetReturn()))

		case n := <-l.Notify:
			switch n.Extra {
			case "version":
				refreshVersions()
				go batchScheduleNewVersions()
			case "queue":
				go doWork()
			default:
				if strings.HasPrefix(n.Extra, "vld:") {
					go doVldHelper(n.Extra[4:])
				}
			}

		case <-doShutdown:
			sdNotify("STOPPING=1")

			l.Close()
			break LOOP
		}
	}
}

// Notify systemd about our state, see https://freedesktop.org/software/systemd/man/sd_notify.html
func sdNotify(state string) {
	if sdSocket == nil {
		if len(os.Getenv("NOTIFY_SOCKET")) == 0 {
			return
		}

		socketAddr := &net.UnixAddr{
			Name: os.Getenv("NOTIFY_SOCKET"),
			Net:  "unixgram",
		}

		if conn, err := net.DialUnix(socketAddr.Net, nil, socketAddr); err != nil {
			panic("sdNotify: error connecting")
		} else {
			sdSocket = conn
		}
	}

	if _, err := sdSocket.Write([]byte(state + "\n")); err != nil {
		panic("sdNotify: error writing: " + err.Error())
	}
}
