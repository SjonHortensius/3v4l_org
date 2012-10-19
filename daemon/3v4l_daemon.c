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


#define OUTBASE "/tmp/"
#define EXIT_FAILURE 1

char *versionOutputPath;
FILE *versionOutput;
int child, outputBusy;

void checkOutputDirectory(char *script)
{
	DIR *dp;
	char *dir;
	sprintf(dir, OUTBASE "/%s", script);
	dp = opendir(dir);
	struct stat outStat;

	if (!stat(script, NULL))
	{
		perror("Script doesn't exist");
		exit(EXIT_FAILURE);
	}

	if (!dp)
		mkdir(dir, 0750);
	else
	{
		if (!stat(dir, &outStat))
		{
			perror("Couldn't check output directory");
			exit(EXIT_FAILURE);
		}

		if (outStat.st_mode != 0750)
		{
			fprintf(stderr, "Invalid mode for output-directory: %d", outStat.st_mode);
			exit(EXIT_FAILURE);
		}

		chmod(dir, 0750);
	}
}

void killChild(int sig)
{
	kill(child, SIGTERM);
	usleep(500000);
	kill(child, SIGKILL);
}

void storeOutput(int sig)
{
	FILE *fp;
	char buffer[8096];

	if (outputBusy)
		return;

	nice(-5);

	outputBusy = 1;
	fp = fopen(versionOutputPath, "w");
	while (fgets(buffer, sizeof(buffer), versionOutput))
		fwrite(buffer, 8096, sizeof(buffer), fp);

	if (!feof(versionOutput))
	{
		perror("Could not completely store output");
		exit(EXIT_FAILURE);
	}

	fclose(fp);
	pclose(versionOutput);
	outputBusy = 0;
}

int executeVersion(char *script[], char *version)
{
	char *command;
	sprintf(command, "%s -c /etc/ -q '/in/%s' 2>&1", version, script);

	if (!seteuid(99))
	{
		perror("Error setting effective uid");
		exit(EXIT_FAILURE);
	}

	nice(5);

	signal(SIGTERM, storeOutput);
	sprintf(versionOutputPath, OUTBASE "/%s-%s", *script, strrchr(version, '-'));
	versionOutput = popen(command, "r");

	if (!versionOutput)
	{
		perror("Error executing command");
		exit(EXIT_FAILURE);
	}

	if (!seteuid(0))
	{
		perror("Error resetting effective uid");
		exit(EXIT_FAILURE);
	}

	// Store output
	raise(SIGTERM);
}

int main(int argc, char *argv[])
{
	if (1 == argc)
	{
		printf("Supply path to input-script as first argument");
		return 3;
	}

	glob_t paths;
	char **p;
	char *script = argv[1];
	int pid;

	checkOutputDirectory(script);

	if (!glob("/bin/c*", 0, NULL, &paths))
	{
		perror("Didn't find any binaries to execute");
		return 2;
	}

	for (p=paths.gl_pathv; *p != NULL; ++p)
	{
		pid = fork();

		switch (pid)
		{
			case -1:
				perror("Error while forking");
				return 3;
			break;

			case 0:
				printf("\n[%d] Executing version %s", getpid(), *p);

				if (!executeVersion(&script, *p))
					return 4;

				return 0;
			break;

			default:
				printf("\n[%d] Spawned a child for version %s, childPid = %d", getpid(), *p, pid);

				child = pid;
				signal(SIGALRM, killChild);
				alarm(3);

				waitpid(pid, NULL, 0);

				alarm(0);
			break;
		}
	}

	globfree(&paths);

	return 0;
}
