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
#include <sys/times.h>
#include <time.h>
#include <sys/resource.h>

#define OUTBASE "/tmp"
#define INBASE "/in"

pid_t child;
int fileSizeExceeded = 0;

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
		mkdir(dir, 0750);
	else
	{
		if (stat(dir, &outStat))
		{
			perror("Couldn't check output directory");
			exit(1);
		}

		if (outStat.st_mode != 16872)
		{
			fprintf(stderr, "Invalid mode for output-directory: %d", outStat.st_mode);
			exit(1);
		}
	}
}

void killChild(int sig)
{
	printf("[%d] Timeout, killing child %d\n", getpid(), child);

	if (kill(child, SIGTERM))
		perror("Error terminating child");
	usleep(500000);
	if (kill(child, SIGKILL))
	{
		perror("Error killing child");
		exit(1);
	}
}

void _fileSizeExceeded(int sig)
{
	fileSizeExceeded = 1;
}

int executeVersion(char *binary, char *script)
{
	pid_t pid;
	int pipefd[2], status, size, totalSize = 0;
	char buffer[256], file[35], outFile[35];
	FILE *output, *fp;
	static clock_t st_time;
	static clock_t en_time;
	static struct tms st_cpu;
	static struct tms en_cpu;
	struct rusage usage;

	sprintf(file, INBASE "/%s", script);
	sprintf(outFile, OUTBASE "/%s/%s", script, basename(binary));

	// Since the parent is writing the output, limit it here. Limit child too
	_setrlimit(RLIMIT_FSIZE, 64 * 1000);
	signal(SIGXFSZ, _fileSizeExceeded);
	fileSizeExceeded = 0;

	pipe(pipefd);
	st_time = times(&st_cpu);
	pid = fork();

	if (-1 == pid)
	{
		perror("Could not fork");
		return 1;
	}
	else if (pid == 0)
	{
		printf("[%d] Running binary %s, script = %s\n", getpid(), binary, script);

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
		_setrlimit(RLIMIT_DATA, 64 * 1000 * 1000);
//		_setrlimit(RLIMIT_NOFILE, 8192);

		execl(binary, "php", "-c", "/etc/", "-q", file, (char*) NULL);
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
		if (fileSizeExceeded)
			break;

		fwrite(buffer, 1, size, fp);

		totalSize += size;
	}

	printf("[%d] Stored %d bytes\n", getpid(), totalSize);

	waitpid(pid, &status, 0);
	en_time = times(&en_cpu);

	getrusage(RUSAGE_SELF, &usage);
	printf("[%d] ru_idrss %d, ru_ixrss %d, ru_isrss %d\n", usage.ru_idrss, usage.ru_ixrss, usage.ru_isrss);

	printf("Done, exit-code was %d\n", WEXITSTATUS(status));

	printf("Real Time: %jd, User Time %jd, System Time %jd\n",
		(en_time - st_time),
		(en_cpu.tms_cutime - st_cpu.tms_cutime),
		(en_cpu.tms_cstime - st_cpu.tms_cstime));

	fclose(fp);
}

int _setrlimit(int resource, int max)
{
	struct rlimit limits;
	limits.rlim_cur = max;
	limits.rlim_max = max;
	return setrlimit(resource, &limits);
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
	chmod(dir, strtol("0550", 0, 8));

	return 0;
}
