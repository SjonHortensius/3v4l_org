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

struct timeval diff;
bool initDone = false;

int (*org_gettimeofday)(struct timeval *tp, void *tzp);
time_t (*org_time)(time_t *tloc);
int (*org_clock_gettime)(clockid_t clk_id, struct timespec *tp);
struct tm *(*org_localtime_r)(const time_t *timep, struct tm *result);

// This could be called _init and we wouldn't need if(0==diff) checks; but that segfaults on hhvm because of pthreads
// also: don't add _init(){ _initLib }; that breaks functionality in hhvm
void
_initLib(void) {
	org_gettimeofday =  dlsym(RTLD_NEXT, "gettimeofday");
	org_time =          dlsym(RTLD_NEXT, "time");
	org_clock_gettime = dlsym(RTLD_NEXT, "clock_gettime");
	org_localtime_r =   dlsym(RTLD_NEXT, "localtime_r");

	int offset = 0;
	if (getenv("TIME") != NULL) {
		offset = atoi(getenv("TIME"));
	}

	org_gettimeofday(&diff, NULL);
	if (offset != 0) {
		diff.tv_sec -= offset;
	} else {
		diff.tv_sec = 0;
//		fprintf(stderr, "\nSomeone set us up the bomb, please report to root@3v4l.org: %s\n", getenv("TIME"));
	}

//fprintf(stderr, "\n%s has set a custom offset: %d, diff.tv_sec=%ld diff.tv_usec=%ld\n", __FUNCTION__, offset, diff.tv_sec, diff.tv_usec);

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");

	initDone = true;
}

int gettimeofday(struct timeval *restrict tp, struct timezone *restrict tzp) {
	if (!initDone)
		_initLib();

	org_gettimeofday(tp, tzp);

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_usec < diff.tv_usec) {
		tp->tv_sec--;
		tp->tv_usec = 1000*1000 + tp->tv_usec;
//fprintf(stderr, "\n%s correcting overflow for %ld < %ld\n", __FUNCTION__, tp->tv_usec, diff.tv_usec);
	}
	tp->tv_usec -= diff.tv_usec;

//fprintf(stderr, "\n%s using offset: %ld.%ld, returning %ld.%ld\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, tp->tv_sec, tp->tv_usec);

	return 0;
}

time_t time(time_t *t) {
	if (!initDone)
		_initLib();

	time_t r = org_time(t);

	r -= diff.tv_sec;

	if (t)
		*t = r;

	return r;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	if (!initDone)
		_initLib();

	int r = org_clock_gettime(clk_id, tp);

	if (0 != r || (CLOCK_REALTIME != clk_id && CLOCK_REALTIME_COARSE != clk_id))
		return r;

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_nsec < (1000*diff.tv_usec)) {
		tp->tv_sec--;
		tp->tv_nsec = 1000*1000*1000 + tp->tv_nsec;
	}
	tp->tv_nsec -= 1000*diff.tv_usec;

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
	if (!initDone)
		_initLib();

	ftime(tp);

	tp->time -= diff.tv_sec;
	if (tp->millitm < diff.tv_usec) {
		tp->millitm--;
		tp->millitm = 1000*1000 + tp->millitm;
	}
	tp->millitm -= diff.tv_usec;

	return 0;
}

// misc other overloads
int uname(struct utsname *buf) {
	if (!initDone)
		_initLib();

	strcpy(buf->sysname, "Linux");
	strcpy(buf->nodename,"php_shell");
	strcpy(buf->release, "4.8.6-1-ARCH");
	strcpy(buf->version, "#1 SMP PREEMPT Mon Oct 31 18:51:30 CET 2016");
	strcpy(buf->machine, "x86_64");

	return 0;
}

bool forkPrintedMsg = false;
pid_t fork(void) {
	if (!forkPrintedMsg) {
		fprintf(stderr, "\n%s has been disabled for security reasons\n", __FUNCTION__);
		forkPrintedMsg=true;
	}
    return 0;
}
