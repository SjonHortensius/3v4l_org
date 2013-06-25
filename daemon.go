package main

import (
//	"syscall"
	"os"
	"path/filepath"
//	"errors"
	"log"
	"os/exec"
	"io"
	"syscall"
	"time"
)


//FIXME: globally execute setuid/setgid and SETRLIMIT and tag script as type 'misbehaving' when limits are reached, stopping execution of all versions

type Version struct {
	binary string
}

func (this *Version) execute(script string) error {
	log.Printf("Executing %s version %s", script, this.binary)

	cmd := exec.Command(this.binary, "-c", "/etc", "-q", "/tmp/"+script);
	cmd.Args[0] = "php";
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
	};
	cmd.SysProcAttr.Credential.Uid = 99
	cmd.SysProcAttr.Credential.Gid = 99

	stdout, _ := cmd.StdoutPipe()
	stderr, _ := cmd.StderrPipe()
	r := io.MultiReader(stdout, stderr)
	this.startSafe(cmd)

	output := make([]byte, 0)
	buffer := make([]byte, 256)
	for _, err := r.Read(buffer); err != io.EOF; _, err = r.Read(buffer) {
		if err != nil {
			log.Printf("while reading output: %s", err)
			break
		}

		output = append(output, buffer...)

		if len(output) >= 65535 {
			log.Println("script has generated too much output, killing it")

			if err := cmd.Process.Kill(); err != nil {
				log.Fatalf("failed to kill child `%s`, aborting", err)
			}

			break
		}
	}

	/*
	 * Channels are meant to communicate between routines. We create a channel
	 * that transports a ProcessState, which we return from Process.Wait. The
	 * '<-' * syntax indicates us sending / receiving data from the channel.
	 *
	 * Refs: http://stackoverflow.com/questions/11886531
	 */

	procDone := make(chan *os.ProcessState)

	go func() {
		state, _ := cmd.Process.Wait()
		procDone <- state
	}()

	select {
		case <-time.After(3 * time.Second):
			if err := cmd.Process.Kill(); err != nil {
				log.Fatalf("failed to kill child `%s`, aborting", err)
			}
			<-procDone // allow goroutine to exit
			log.Println("timeout, process killed")
		case state := <-procDone:
			waitStatus := state.Sys().(syscall.WaitStatus)
			log.Printf("DONE ", waitStatus.ExitStatus(), state.SysUsage());
	}

	return nil;
}

func (v *Version) startSafe(this *exec.Cmd) error {
	var limits = map [int]int {
		syscall.RLIMIT_CPU:		2,
		syscall.RLIMIT_DATA:	64 * 1024 * 1024,
		syscall.RLIMIT_FSIZE:	64 * 1024,
		syscall.RLIMIT_CORE:	0,
		syscall.RLIMIT_NOFILE:	4096,
	}

	for key, value := range limits {
		if err := syscall.Setrlimit(key, &syscall.Rlimit{uint64(value), uint64(value)}); err != nil {
			log.Fatalf("Failed to set resourcelimit: %d to %d: %s", key, value, err)
		}
	}
/*
	if err := syscall.Setgid(99); err != nil {
		log.Fatalf("Failed to setgid: %v", err)
	}

	if err := syscall.Setuid(99); err != nil {
		log.Fatalf("failed to setuid: %v", err)
	}
*/
	return this.Start()
}

func main() {
	if len(os.Args) < 2 {
		log.Fatal("Missing path for script to execute")
	}
	log.Print(os.Args[1])

	file, err := os.Stat(os.Args[1])
	if err != nil || file.IsDir() {
		log.Fatalf("First argument is not a valid file: %s", err)
	}

	if versions, err := filepath.Glob("/usr/bin/php-*"); err != nil {
		log.Fatal("No binaries found to run this script: %s", err);
	} else {
		for _, version := range versions {
			version := Version{version}
			version.execute(file.Name())
		}
	}
}