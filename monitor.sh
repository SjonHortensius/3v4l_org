#!/bin/bash
shopt -s nullglob

inotifywait -qme attrib -e create --format %f /var/lxc/php_shell/out/ | while read d
do
	[[ ! -d /var/lxc/php_shell/out/$d ]] && continue;

	echo $d - Waiting for output

	inotifywait -qme close_write --format %f --timeout 4 /var/lxc/php_shell/out/$d/ | while read f
	do
		# prevent multiple events on same script
		[[ $f != *-timing ]] && continue

		/srv/http/3v4l.org/import.php $d ${f%%-*}
	done

	echo $d - Checking for left-overs
	for f in /var/lxc/php_shell/out/$d/*-timing
	do
		f=`basename $f`
		/srv/http/3v4l.org/import.php $d ${f%%-*}
	done

	rmdir -v /var/lxc/php_shell/out/$d/
done &>/root/monitor.log
