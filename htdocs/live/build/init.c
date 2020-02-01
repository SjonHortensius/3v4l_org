#include <unistd.h>
#include <stdio.h>
#include <sys/wait.h>

char *cmd[] = { "/usr/bin/php", "/tmp/preview", 0 };
char *envp[] =
{
	"HOME=/",
	"PATH=/usr/bin",
	"USER=nobody",
	"TERM=vt100",
	0
};

int main(int argc, char *argv[])
{
	if (setgid(1000) || setuid(1000))
	{
		perror("Error setting userid");
		return -1;
	}

//	if (strcmp(argv[0], "/sbin/preview") == 0)
//		return preview();

	while (1) {
		// wait until a new file appears
		while (access("/tmp/preview", F_OK) == -1) {
			usleep(200000);
		}

		// clear the screen
		printf("\n%c%cH%c%cJ", 27, 91, 27, 91);

		if (!fork())
			execve(cmd[0], &cmd[0], envp);
		else
		{
			waitpid(-1, NULL, 0);

			// remove file - it prevents caching issue
			unlink("/tmp/preview");
		}
	}

	return -1;
}
