#define _GNU_SOURCE
#include <dlfcn.h>
#include <sys/time.h>
#include <stdlib.h>
#include <bits/time.h>
#include <sys/timeb.h>
#include <stdio.h>
#include <sys/utsname.h>
#include <string.h>
#include <stdbool.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <sys/resource.h>

struct timeval diff;
int offset = 0;

int (*org_gettimeofday)(struct timeval *tp, void *tzp);
time_t (*org_time)(time_t *tloc);
int (*org_clock_gettime)(clockid_t clk_id, struct timespec *tp);
struct tm *(*org_localtime_r)(const time_t *timep, struct tm *result);
int (*org__xstat)(int __ver, const char *__filename, struct stat *__stat_buf);


void _setrlimit(int resource, int max) {
	struct rlimit limits;
	limits.rlim_cur = max;
	limits.rlim_max = max;

	if (setrlimit(resource, &limits)) {
		perror("setlimit failed");
		exit(205);
	}
}

__attribute__((constructor))
static void preload_init(void) {
	org_gettimeofday =	dlsym(RTLD_NEXT, "gettimeofday");
	org_time =			dlsym(RTLD_NEXT, "time");
	org_clock_gettime =	dlsym(RTLD_NEXT, "clock_gettime");
	org_localtime_r =	dlsym(RTLD_NEXT, "localtime_r");
	org__xstat =		dlsym(RTLD_NEXT, "__xstat");

	if (getenv("TIME") != NULL)
		offset = atoi(getenv("TIME"));

	if (0 == offset) {
		fprintf(stderr, "\nSomeone set us up the bomb, please report to root@3v4l.org: %s\n", getenv("TIME"));
		exit(2);
	}

	// don't change usec, this makes us start at offset.0
	org_gettimeofday(&diff, NULL);
	diff.tv_sec -= offset;

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");

	// this doesn't strictly belong here, but go doesn't allow setting this on os.Exec-ed threads
	_setrlimit(RLIMIT_CPU, 2);
	_setrlimit(RLIMIT_DATA, 128 * 1024 * 1024);
	_setrlimit(RLIMIT_AS, 192 * 1024 * 1024);
	_setrlimit(RLIMIT_FSIZE, 64 * 1024);
	_setrlimit(RLIMIT_NPROC, 64);
	_setrlimit(RLIMIT_NOFILE, 2048);
	_setrlimit(RLIMIT_CORE, 0);
	nice(5);
}

int gettimeofday(struct timeval *__restrict tp, void *__restrict tzp) {
	static bool setsServerRequestTime = true;

	if (setsServerRequestTime) {
		tp->tv_sec = offset;
		tp->tv_usec = 100;
		setsServerRequestTime = false;

		return 0;
	}
	else
		org_gettimeofday(tp, tzp);

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_usec < diff.tv_usec) {
		tp->tv_sec--;
		tp->tv_usec += 1000*1000;
	}
	tp->tv_usec -= diff.tv_usec;
//fprintf(stderr, "\n%s using offset: %ld.%06ld, returning %ld.%06ld\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, tp->tv_sec, tp->tv_usec);

	return 0;
}

time_t time(time_t *t) {
	// cannot use org_time here, diff.tv_usec possibly causes decrease of tv_sec
	struct timeval a;
	// don't use gettimeofday as it triggers setsServerRequestTime. PHP 5.4.4+ perform an extra time() call too
	org_gettimeofday(&a, NULL);

	time_t r = (time_t)a.tv_sec;

	r -= diff.tv_sec;
	if (a.tv_usec < diff.tv_usec)
		r--;

	if (t)
		*t = r;

//fprintf(stderr, "\n%s using offset: %ld.%06ld, returning %ld\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, r);
	return r;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	int r = org_clock_gettime(clk_id, tp);

	if (0 != r || (CLOCK_REALTIME != clk_id && CLOCK_REALTIME_COARSE != clk_id))
		return r;

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_nsec < (1000*diff.tv_usec)) {
		tp->tv_sec--;
		tp->tv_nsec += 1000*1000*1000;
	}
	tp->tv_nsec -= 1000*diff.tv_usec;

//fprintf(stderr, "\n%s using offset: %ld.%06ld, returning %ld.%06ld\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, tp->tv_sec, tp->tv_nsec);
	return 0;
}

struct tm *localtime_r(time_t *timep, struct tm *result) {
	if (!timep) {
		struct timeval a;
		gettimeofday(&a, NULL);

		*timep = (time_t)a.tv_sec;
	}

	return org_localtime_r(timep, result);
}

int ftime(struct timeb *tp) {
	ftime(tp);

	tp->time -= diff.tv_sec;
	if (tp->millitm < diff.tv_usec) {
		tp->time--;
		tp->millitm += 1000*1000;
	}
	tp->millitm -= diff.tv_usec;

//fprintf(stderr, "\n%s using offset: %ld.%06ld, returning %ld.%d\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, tp->time, tp->millitm);
	return 0;
}

// misc other overloads
int uname(struct utsname *buf) {
	strcpy(buf->sysname, "Linux");
	strcpy(buf->nodename,"php_shell");
	strcpy(buf->release, "4.8.6-1-ARCH");
	strcpy(buf->version, "#1 SMP PREEMPT Mon Oct 31 18:51:30 CET 2016");
	strcpy(buf->machine, "x86_64");
	strcpy(buf->domainname, "");

	return 0;
}

// f/l/stat is provided by __xstat - see https://stackoverflow.com/q/5478780
int statCtr = 0;
int __xstat (int __ver, const char *__filename, struct stat *__stat_buf) {
	int s = org__xstat(__ver, __filename, __stat_buf);

	__stat_buf->st_dev = ++statCtr;
	__stat_buf->st_ino = ++statCtr;
	__stat_buf->st_atim.tv_sec = offset;
	__stat_buf->st_mtim.tv_sec = offset;
	__stat_buf->st_ctim.tv_sec = offset;

//fprintf(stderr, "\n%s for %s returning st_ino: %ld\n", __FUNCTION__, __filename, __stat_buf->st_ino);

	return s;
}

pid_t getpid(void) {
	return 2;
}

// noop these
void syslog(int priority, const char *format, ...) {}
void openlog(const char *ident, int option, int facility) {}
void closelog(void) {}

bool forkPrintedMsg = false;
pid_t fork(void) {
	if (!forkPrintedMsg) {
		fprintf(stderr, "\n%s has been disabled for security reasons\n", __FUNCTION__);
		forkPrintedMsg=true;
	}

	return 0;
}
