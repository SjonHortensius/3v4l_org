#!/bin/bash
set -e

[[ ! -d ./files ]] && { echo Execute in correct directory >&2; exit 1; }
[[ ! -d $1/output/target ]] && { echo Pass path to buildroot directory as first argument >&2; exit 2; }

ROOT=$1/output/target
# increase this for new releases
ROOT_ID=74
ROOT_TOC=files/$(printf '%016d' $ROOT_ID)

chmod 1777 $ROOT/tmp
rm -vf $ROOT/THIS_IS_NOT_YOUR_ROOT_FILESYSTEM

rm -f ./files/*

cp -v ${0%/*}/php.ini $ROOT/etc/php.ini

# echo -e "bin/init lib/ld-2.28.so lib/libc-2.28.so usr/bin/php lib/libcrypt-2.28.so /lib/libdl-2.28.so lib/libstdc++.so.6.0.24 lib/libreadline.so.7.0\n\netc/hosts" >$ROOT/.preload

echo -e "Version: 1\n" >$ROOT_TOC

p=1
while IFS=$'\t' read d e
do
	if [[ $d -gt $p ]]; then
		n=$(($p + 1))

		[[ $d -ne $n ]] && echo error: $e - $d is greater than 1 + $p >>.2
		[[ $pe != 04* ]] && echo error: $pe - depth increases without a directory >>.2
	else
		[[ $pe == 04* ]] && echo .

		if [[ $d -lt $p ]]; then
			n=$(($p + 1))

			while n=$(($n - 1)); [[ $n -gt $d ]]
			do
				echo .
			done
		fi
	fi

	# for tmp directory - %#4m doesn't work
	[[ $e == 0401777* ]] && e=${e/0401777/041777}

	# instead of inodes - use content hash | BASHISMS FTW
	[[ $e == 10* ]] && e=${e/$'\t'HASH*/ $(md5sum ${e##*$'\t'} | cut -c1-8)}

	echo $e

	# artifically insert a dev/console so we don't need root permissions to build
	[[ $d == 1 && $e == *dev ]] && echo '020622 0 0 5 1 1499510372 console'

	 p=$d
	pe=$e
# we fakeroot by replacing '%U %G' with '0 0'
done < <(find $ROOT/ -mindepth 1 -printf '%d\t' \
		-type d -printf '04%#m 0 0 %C@ %f\n' -o \
		-type f -printf '10%#m 0 0 %s %C@ %f\tHASH\t%p\n' -o \
		-type l -printf '12%#m 0 0 %C@ %f %l\n' -o \
		-type c -printf '02%#m 0 0 ' -exec stat {} --printf '%t %T' \; -printf ' %C@ %f\n' -o \
		-printf 'ERROR: Unrecognized type %y - %P\n') >>$ROOT_TOC

FILE_COUNT=$(grep -E ^1 $ROOT_TOC|wc -l)
FILE_SIZE=$(du -sb $ROOT|cut -f1)

cat >head <<EOT
Version: 1
Revision: 1
NextFileID: 2ca8
FSFileCount: $FILE_COUNT
FSSize: $FILE_SIZE
FSMaxSize: 1073741824
Key:
RootID: $ROOT_ID

EOT

while read p; do
	f=files/00000000$(md5sum $p | cut -c1-8)
	cp -vp $p $f
	cat $f|gzip -9>$f.gz
done < <(find $ROOT/ -mindepth 1 -type f)