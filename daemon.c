#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <glob.h>
#include <string.h>
#include <dirent.h>
#include <sys/resource.h>

#define OUTBASE "/out"
#define INBASE "/in"

/*
This code has been made to replace this bash script:

#!/bin/bash
ulimit -f 64 -m 64 -t 2 -u 128

[[ ! -d /out/$1/ ]] && mkdir /out/$1/ || chmod u+w /out/$1/

for bin in /bin/php-*
do
	echo $bin - $1
	nice -n 15 sudo -u nobody $bin -c /etc/ -q "/in/$1" &>/out/$1/${bin##*-} & PID=$!
	( sleep 3.1; kill -9 $PID 2>/dev/null ) &
	wait $PID
	ex=$?

	sf=/out/$1/${bin##*-}-exit
	[[ $ex -eq 0 && -f $sf ]] && rm $sf
	[[ $ex -ne 0 ]] && echo -n $ex > $sf
done

chmod u-w /out/$1/
*/

pid_t child;

void checkOutputDirectory(char *script)
{
	char dir[35], file[35];
	sprintf(dir, OUTBASE "/%s", script);
	sprintf(file, INBASE "/%s", script);
	DIR *dp = opendir(dir);
	struct stat outStat;

	if (access(file, R_OK))
	{
		perror("Input cannot be read");
		exit(1);
	}

	if (!dp)
		mkdir(dir, 0755);
	else
	{
		if (stat(dir, &outStat))
		{
			perror("Couldn't check output directory");
			exit(1);
		}

		if (outStat.st_mode != 16749 && outStat.st_mode != 16877)
		{
			fprintf(stderr, "Invalid mode for output-directory: %d", outStat.st_mode);
			exit(1);
		}

		chmod(dir, strtol("0755", 0, 8));
	}
}

int _setrlimit(int resource, int max)
{
	struct rlimit limits;
	limits.rlim_cur = max;
	limits.rlim_max = max;
	return setrlimit(resource, &limits);
}

void file_put_contents(char *path, char *output)
{
	FILE *fp = fopen(path, "w");
	fwrite(output, 1, strlen(output), fp);
	fclose(fp);
}

void _killChild()
{
	// Remove any alarm that wants to kill this child too
	alarm(0);

	if (kill(child, SIGTERM))
		perror("Error terminating child");
	usleep(500000);
	if (kill(child, SIGKILL))
	{
		perror("Error killing child");
		exit(1);
	}
}

void killChild(int sig)
{
	printf("[%d] Timeout, killing child %d\n", getpid(), child);

	_killChild();
}

int executeVersion(char *binary, char *script)
{
	pid_t pid;
	int pipefd[2], status, size, totalSize = 0, exitCode;
	char buffer[256], file[35], outFile[35], outFileExit[35 + 4], outFileTime[35 + 7];
	FILE *output, *fp;
	struct rusage r_start, r_end;
	char* env[] = {
		"TERM=xterm",
		"PATH=/usr/bin:/bin",
		"LANG=C",
		"SHELL=/bin/bash",
		"MAIN=/var/mail/nobody",
		"LOGNAME=nobody",
		"USER=nobody",
		"USERNAME=nobody",
		"HOME=/",
		NULL
	};

	sprintf(file, INBASE "/%s", script);
	sprintf(outFile, OUTBASE "/%s/%s", script, basename(binary)+4);

	setbuf(stdout, NULL);
	pipe(pipefd);
	getrusage(RUSAGE_CHILDREN, &r_start);
	pid = fork();

	if (-1 == pid)
	{
		perror("Could not fork");
		return 1;
	}
	else if (pid == 0)
	{
		printf("[%d] Version %-6s script %s: ", getpid(), basename(binary)+4, script);

		if (setuid(99))
		{
			perror("Error setting userid");
			exit(1);
		}

		close(pipefd[0]);
		dup2(pipefd[1], STDOUT_FILENO);
		dup2(pipefd[1], STDERR_FILENO);

		nice(5);
		_setrlimit(RLIMIT_CPU, 2);
		_setrlimit(RLIMIT_DATA, 64 * 1024 * 1024);
		_setrlimit(RLIMIT_FSIZE, 64 * 1024);
		_setrlimit(RLIMIT_NPROC, 64);
		_setrlimit(RLIMIT_NOFILE, 4096);

		execle(binary, "php", "-c", "/etc/", "-q", file, (char*) NULL, env);
		exit(1);
	}

	close(pipefd[1]);
	output = fdopen(pipefd[0], "r");
	fp = fopen(outFile, "w");

	child = pid;
	signal(SIGALRM, killChild);
	alarm(3);

	while ((size = fread(buffer, 1, sizeof buffer, output)) != 0)
	{
		fwrite(buffer, 1, size, fp);

		totalSize += size;

		if (totalSize > 65536)
		{
			printf("child %d has generated too much output, killing it\n", child);
			_killChild();
			break;
		}
	}

	fclose(fp);

	printf("stored %d bytes...", totalSize);

	waitpid(child, &status, 0);
	alarm(0);
	getrusage(RUSAGE_CHILDREN, &r_end);

	if (WIFEXITED(status))
		exitCode = WEXITSTATUS(status);
	else if WIFSIGNALED(status)
		exitCode = 128 + WTERMSIG(status);

	if (0 != exitCode)
	{
		memset(&buffer[0], 0, sizeof(buffer));
		sprintf(outFileExit, "%s-exit", outFile);
		sprintf(buffer, "%d", exitCode);
		file_put_contents(outFileExit, buffer);
	}
	else
		unlink(outFileExit);

	memset(&buffer[0], 0, sizeof(buffer));
	sprintf(outFileTime, "%s-timing", outFile);

	sprintf(buffer, "%f:%f:%f",
			(r_end.ru_utime.tv_sec + r_end.ru_utime.tv_usec / 1000000.0) - (r_start.ru_utime.tv_sec + r_start.ru_utime.tv_usec / 1000000.0),
			(r_end.ru_stime.tv_sec + r_end.ru_stime.tv_usec / 1000000.0) - (r_start.ru_stime.tv_sec + r_start.ru_stime.tv_usec / 1000000.0),
			r_end.ru_maxrss - r_start.ru_maxrss
	);
	file_put_contents(outFileTime, buffer);

	printf("done, exit-code: %d\n", exitCode);
}

int main(int argc, char *argv[])
{
	if (1 == argc)
	{
		printf("Supply path to input-script as first argument\n");
		return 3;
	}

	glob_t paths;
	char **p;
	char *script = argv[1];
	char dir[35];
	sprintf(dir, OUTBASE "/%s", script);

	checkOutputDirectory(script);

	if (glob("/bin/php-*", 0, NULL, &paths))
	{
		perror("Found no binaries to execute");
		return 2;
	}

	for (p=paths.gl_pathv; *p != NULL; ++p)
		executeVersion(*p, script);

	globfree(&paths);
	chmod(dir, strtol("0555", 0, 8));

	return 0;
}
