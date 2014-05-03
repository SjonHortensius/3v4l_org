#!/bin/bash
set -e
cd `dirname $0`

updateScript() {
    cat /var/lxc/php_shell/in/$1 | php -r 'print trim(str_replace(array("\r\n", "\r"), "\n", file_get_contents("php://stdin")));' >/var/lxc/php_shell/in/$2
}

fileHash() {
    php -r 'echo gmp_strval(gmp_init(sha1(trim(str_replace(array("\r\n", "\r"), "\n", file_get_contents("/var/lxc/php_shell/in/". $argv[1])))), 16), 58)."\n";' $*
}

updateDb() {
    OLD=$1 NEW=$2
    [[ ! -f /var/lxc/php_shell/in/$OLD ]] && exit 1

    cd / ; sudo -u postgres psql -h 127.0.0.1 phpshell -tc "
        UPDATE input SET alias = '$OLD' WHERE short = '$NEW'; \
        UPDATE submit SET input = '$NEW' WHERE input = '$OLD' AND ip NOT IN(SELECT ip FROM submit WHERE input ='$NEW'); \
        UPDATE submit s SET \"count\" = \"count\" +(SELECT \"count\" FROM submit WHERE ip = s.ip AND input = '$OLD') WHERE input = '$NEW'; \
        UPDATE submit s SET created = COALESCE((SELECT created FROM submit WHERE ip = s.ip AND input = '$OLD'), created) WHERE input = '$NEW'; \
        UPDATE submit s SET updated = COALESCE(updated, (SELECT updated FROM submit WHERE ip = s.ip AND input = '$OLD')) WHERE input = '$NEW'; \
        DELETE FROM submit WHERE input = '$OLD'; \
        UPDATE input SET source = '$NEW' WHERE source = '$OLD'; \
        UPDATE input SET source = null WHERE short = '$NEW' AND source = '$OLD'; \
        DELETE FROM input where short='$OLD'; \
        " && mv -v /var/lxc/php_shell/in/$OLD /var/lxc/php_shell/in/ALIASED/
}

cat all-hashes.txt | while read f h
do
    [[ $h == *$f ]] && { echo UP_TO_DATE ; continue ; }

    l=${#h} ; n=${h:$l-5:5}
    [[ ! -f /var/lxc/php_shell/in/$n ]] && { echo COPY $f $n ; updateScript $f $n ; }

    echo ALIAS:$f:$n
done
