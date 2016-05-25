#define _GNU_SOURCE
#include <dlfcn.h>
#include <stdio.h>
#include <time.h>
#include <sys/time.h>
#include <stdlib.h>
#include <sys/timeb.h>

int diff;

struct tm *(*org_localtime)(const time_t *timep, struct tm *result);
time_t (*org_time)(time_t *tloc);

int gettimeofday(struct timeval *tv, struct timezone *tz) {
	tv->tv_sec = org_time(NULL) - diff;

	return 0;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	if (tp == NULL)
		return -1;

	tp->tv_sec = org_time(NULL) - diff;

	return 0;
}

time_t time(time_t *t) {
	int t_ = org_time(NULL) - diff;

	if (t)
		*t = t_;

	return t_;
}

int ftime(struct timeb *tp) {
	tp->time = org_time(NULL) - diff;

	return 0;
}

struct tm *localtime_r(const time_t *timep, struct tm *result)
{
	time_t t = (time_t)(org_time(NULL) - diff);
	return org_localtime(&t,result);
}

void
_init(void)
{
	org_localtime = dlsym(RTLD_NEXT, "localtime_r");
	org_time = dlsym(RTLD_NEXT, "time");

	int offset = 0;
	if (getenv("TIME") != NULL) {
		offset = atoi(getenv("TIME"));
	}

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");

	diff = time(NULL) - offset;
}
