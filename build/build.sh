#!/bin/bash
set -e
cd `dirname $0`/in/

version=$1

ISTEMP=0
[[ $version == *a* || $version == *RC* ]] && ISTEMP=1

echo -ne "Downloading...\r"
[[ ! -f php-$version.tar.bz2 ]] && curl -OsS http://nl3.php.net/distributions/php-$version.tar.bz2
[[ `du php-$version.tar.bz2|cut -f1` -lt 999 ]] && rm php-$version.tar.bz2
[[ ! -f php-$version.tar.bz2 ]] && curl -O# http://museum.php.net/php5/php-$version.tar.bz2

echo -ne "Extracting...\r"
tar xjf php-$version.tar.bz2 -C ../root/ || rm -v php-$version.tar.bz2
cd ../root/php-$version/

confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-intl --enable-bcmath --enable-calendar --enable-mbstring --with-zlib --with-gettext --disable-cgi --with-gmp --with-mcrypt"

[[ `vercmp $version 5.4.0`  -gt 0 && `vercmp $version 5.4.7` -lt 0  ]] && patch -p0 <../../php-with-libxml2-29plus.patch
[[ `vercmp $version 5.4.7`  -gt 0 && `vercmp $version 5.4.15` -lt 0 ]] && confFlags="$confFlags --without-openssl";
[[ `vercmp $version 5.4.14` -gt 0 ]] && confFlags="$confFlags --with-openssl"

if [[ $ISTEMP -eq 1 ]]; then
	EXTENSION_DIR=/usr/lib/php/${version:0:3}/modules; export EXTENSION_DIR
else
	EXTENSION_DIR=/usr/lib/php/$version/modules; export EXTENSION_DIR
	for ext in intl bcmath; do confFlags="$confFlags --enable-$ext=shared"; done
	for ext in curl gmp iconv mcrypt; do confFlags="$confFlags --with-$ext=shared"; done
fi

echo -ne "Configuring...\r"
./configure $confFlags &>build-configure.log

echo -ne "Making...     \r"
# remove directories but leave logs
make -j10 &>build-make.log || { rm -R */; exit 1; }

# verify correct build
./sapi/cli/php -i >/dev/null

strip sapi/cli/php modules/*.so && upx -qq ./sapi/cli/php
mv -v sapi/cli/php ../../out/php-$version
mkdir -p ../../out/exts/$version/modules && mv modules/*.so ../../out/exts/$version/modules/
cd ../..
rm -R root/php-$version/

echo -e "Done...       \r"
echo -n "Publish? [Yn]"; read p

[[ $p == "N" || $p == "n"  ]] && exit 0

scp out/php-$version root@3v4l.org:/srv/http/3v4l.org/bin/
[[ $ISTEMP -eq 0 ]] && rsync -xva out/exts/ root@3v4l.org:/srv/http/3v4l.org/usr_lib_php/
