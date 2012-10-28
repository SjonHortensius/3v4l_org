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


#define OUTBASE "/tmp"
#define INBASE "/in"
#define EXIT_FAILURE 1

char *childOutputPath;
FILE *childOutput;
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
		exit(EXIT_FAILURE);
	}

	if (!dp)
		mkdir(dir, 0750);
	else
	{
		if (stat(dir, &outStat))
		{
			perror("Couldn't check output directory");
			exit(EXIT_FAILURE);
		}

		if (outStat.st_mode != 16872)
		{
			fprintf(stderr, "Invalid mode for output-directory: %d", outStat.st_mode);
			exit(EXIT_FAILURE);
		}
	}
}

void killChild(int sig)
{
	printf("[%d] Timeout, killing child %d\n", getpid(), child);

	kill(child, SIGTERM);
	usleep(500000);
	kill(child, SIGKILL);
}

int executeVersion(char *binary, char *script)
{
	pid_t pid;
	int pipefd[2], status, size;
	char buffer[256], file[35], outFile[35];
	FILE *output, *fp;

	sprintf(file, INBASE "/%s", script);
	sprintf(outFile, OUTBASE "/%s/%s", script, basename(binary));

	pipe(pipefd);
	pid = fork();

	if (-1 == pid)
	{
		perror("Could not fork");
		return 1;
	}
	else if (pid == 0)
	{
		printf("[%d] Running binary %s, script = %s\n", getpid(), binary, script);

		close(pipefd[0]);
		dup2(pipefd[1], STDOUT_FILENO);
		dup2(pipefd[1], STDERR_FILENO);

		execl(binary, "php", "-c", "/etc/", "-q", file, (char*) NULL);
		exit(1);
	}

//	printf("[%d] Spawned a child, childPid = %d - outFile = %s\n", getpid(), pid, outFile);

	close(pipefd[1]);
	output = fdopen(pipefd[0], "r");
	fp = fopen(outFile, "w");

	child = pid;
	signal(SIGALRM, killChild);
	alarm(3);

	while ((size = fread(buffer, 1, sizeof buffer, output)) != 0)
	{
		fwrite(buffer, 1, size, fp);
		printf("[%d] Stored %d bytes: %s\n", getpid(), size, buffer);
	}

	//wait for the child process to terminate
	waitpid(pid, &status, 0);

	printf("Done, exit-code was %d\n", status);

	fclose(fp);
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
	pid_t pid;
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
