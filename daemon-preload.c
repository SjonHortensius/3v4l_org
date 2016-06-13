#define _GNU_SOURCE
#include <dlfcn.h>
#include <sys/time.h>
#include <stdlib.h>
#include <sys/timeb.h>

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

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");

	org_gettimeofday(&diff, NULL);
	diff.tv_sec -= offset;
}

int gettimeofday(struct timeval *restrict tp, struct timezone *restrict tzp) {
	if (0 == diff.tv_sec)
		_initLib();

	org_gettimeofday(tp, tzp);

	tp->tv_sec -= diff.tv_sec;
	if (tp->tv_usec < diff.tv_usec) {
		tp->tv_sec--;
		tp->tv_usec = tp->tv_usec + 1000000 - diff.tv_usec;
	} else
		tp->tv_usec -= diff.tv_usec;

	return 0;
}

time_t time(time_t *t) {
	if (0 == diff.tv_sec)
		_initLib();

	time_t r = org_time(t) - diff.tv_sec;

	if (t)
		*t = r;

	return r;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	if (0 == diff.tv_sec)
		_initLib();

	int r = org_clock_gettime(clk_id, tp);

	if (0 == r)
		tp->tv_sec -= diff.tv_sec;

	return r;
}

struct tm *localtime_r(time_t *timep, struct tm *result) {
	if (0 == diff.tv_sec)
		_initLib();

	if (!timep)
		*timep = (time_t)(org_time(NULL) - diff.tv_sec);

	return org_localtime_r(timep, result);
}

int ftime(struct timeb *tp) {
	if (0 == diff.tv_sec)
		_initLib();

	ftime(tp);

	tp->time -= diff.tv_sec;
	if (tp->millitm < diff.tv_usec) {
		tp->millitm--;
		tp->millitm = tp->millitm + 1000000 - diff.tv_usec;
	} else
		tp->millitm -= diff.tv_usec;

	return 0;
}
