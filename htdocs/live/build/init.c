#include <unistd.h>
#include <stdio.h>
#include <sys/wait.h>

int main(void)
{
	char *argv[] = { "/usr/bin/php", "-v", 0 };
	char *envp[] =
	{
		"HOME=/",
		"PATH=/usr/bin",
		"USER=nobody",
		"TERM=vt100",
		0
	};

	if (setgid(1000) || setuid(1000))
	{
		perror("Error setting userid");
		return -1;
	}

	// show banner
	if (!fork())
	{
		execve(argv[0], &argv[0], envp);
		return -1;
	}

	waitpid(-1, NULL, 0);
	argv[1] = "-a";

	while (1) {
		if (!fork())
			execve(argv[0], &argv[0], envp);
		else
			waitpid(-1, NULL, 0);
	}

	return -1;
}
