#!/bin/bash
set -e
cd `dirname $0`

updateScript() {
	php -r 'file_put_contents($argv[2], trim(str_replace(array("\r\n", "\r"), "\n", file_get_contents($argv[1]))));' /var/lxc/php_shell/in/$1 /var/lxc/php_shell/in/$2
}

fileHash() {
	php -r 'echo gmp_strval(gmp_init(sha1(trim(str_replace(array("\r\n", "\r"), "\n", file_get_contents($argv[1])))), 16), 58)."\n";' /var/lxc/php_shell/in/$1
}

updateDb() {
	OLD=$1 NEW=$2 FULL_HASH=$3
#	UPDATE submit s SET \"count\" = \"count\" +(SELECT \"count\" FROM submit WHERE ip = s.ip AND input = '$OLD') WHERE input = '$NEW'; \
#	UPDATE submit s SET created = COALESCE((SELECT created FROM submit WHERE ip = s.ip AND input = '$OLD'), created) WHERE input = '$NEW'; \
#	UPDATE submit s SET updated = COALESCE(updated, (SELECT updated FROM submit WHERE ip = s.ip AND input = '$OLD')) WHERE input = '$NEW'; \

	cd / ; sudo -u postgres psql -h 127.0.0.1 phpshell -tc "
		INSERT INTO input (SELECT '$NEW', source, type, '$FULL_HASH', state, run, \"operationCount\", alias FROM input WHERE short = '$OLD' AND 0 = (SELECT COUNT(*) FROM input WHERE short = '$NEW')); \
		UPDATE submit SET input = '$NEW' WHERE input = '$OLD' AND ip NOT IN(SELECT ip FROM submit WHERE input ='$NEW'); \
		UPDATE input SET alias = '$OLD' WHERE short = '$NEW'; \
		UPDATE input SET source = null WHERE short = '$NEW' AND source = '$OLD'; \
		UPDATE input SET source = '$NEW' WHERE source = '$OLD'; \
		UPDATE result SET input = '$NEW' WHERE input = '$OLD' AND run > COALESCE((SELECT max(run) FROM result WHERE input = '$NEW'), 0); \
		DELETE FROM input WHERE short='$OLD' AND 0 = (SELECT COUNT(*) FROM submit WHERE input = '$OLD'); \
		SELECT input,ip FROM submit WHERE input = '$OLD'; \
	"

	mv -v /var/lxc/php_shell/in/$OLD /var/lxc/php_shell/in/ALIASED/
}

cat all-hashes.txt | while read f h
do
	[[ ! -f /var/lxc/php_shell/in/$f ]] && { echo ERROR:$f ; exit 1; }
	[[ $h == *$f ]] && { continue ; }

	l=${#h} ; n=${h:$l-5:5}
	[[ ! -f /var/lxc/php_shell/in/$n ]] && { echo -n COPY_ ; updateScript $f $n ; }

	echo ALIAS:$f:$n
	updateDb $f $n $h
done
