#define _GNU_SOURCE
#include <dlfcn.h>
#include <sys/time.h>
#include <stdlib.h>
#include <bits/time.h>
#include <sys/timeb.h>
#include <stdio.h>

struct timeval diff;

int (*org_gettimeofday)(struct timeval *tp, void *tzp);
time_t (*org_time)(time_t *tloc);
int (*org_clock_gettime)(clockid_t clk_id, struct timespec *tp);
struct tm *(*org_localtime_r)(const time_t *timep, struct tm *result);

void
_initLib(void)
{
	org_gettimeofday =  dlsym(RTLD_NEXT, "gettimeofday");
	org_time =          dlsym(RTLD_NEXT, "time");
	org_clock_gettime = dlsym(RTLD_NEXT, "clock_gettime");
	org_localtime_r =   dlsym(RTLD_NEXT, "localtime_r");

	int offset = 0;
	if (getenv("TIME") != NULL) {
		offset = atoi(getenv("TIME"));
	}

	org_gettimeofday(&diff, NULL);
	diff.tv_sec -= offset;

	// This shouldn't happen
	if (offset == 0) {
		diff.tv_sec = 1;
//fprintf(stderr, "\nSomeone set us up the bomb, please report to root@3v4l.org: %s\n", getenv("TIME"));
	}

//fprintf(stderr, "\n%s has set a custom offset: %d, diff.tv_sec=%ld diff.tv_usec=%ld\n", __FUNCTION__, offset, diff.tv_sec, diff.tv_usec);

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");
}

int gettimeofday(struct timeval *restrict tp, struct timezone *restrict tzp) {
	if (0 == diff.tv_sec)
		_initLib();

	org_gettimeofday(tp, tzp);

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_usec < diff.tv_usec) {
		tp->tv_sec--;
		tp->tv_usec -= 1000*1000 - diff.tv_usec;
	} else
		tp->tv_usec -= diff.tv_usec;

//fprintf(stderr, "\n%s using offset: %ld.%ld, returning %ld.%ld\n", __FUNCTION__, diff.tv_sec, diff.tv_usec, tp->tv_sec, tp->tv_usec);

	return 0;
}

time_t time(time_t *t) {
	struct timeval a;
	gettimeofday(&a, NULL);
	time_t r = a.tv_sec;

	if (t)
		*t = r;

	return r;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	if (0 == diff.tv_sec)
		_initLib();

	int r = org_clock_gettime(clk_id, tp);

	if (0 != r || (CLOCK_REALTIME != clk_id && CLOCK_REALTIME_COARSE != clk_id))
		return r;

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_nsec < (1000*diff.tv_usec)) {
		tp->tv_sec--;
		tp->tv_nsec = tp->tv_nsec + 1000*1000*1000 - (1000*diff.tv_usec);
	} else
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
	if (0 == diff.tv_sec)
		_initLib();

	ftime(tp);

	tp->time -= diff.tv_sec;
	if (tp->millitm < diff.tv_usec) {
		tp->millitm--;
		tp->millitm = tp->millitm + 1000*1000 - diff.tv_usec;
	} else
		tp->millitm -= diff.tv_usec;

	return 0;
}
