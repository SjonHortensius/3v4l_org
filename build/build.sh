#!/bin/bash
set -e
cd `dirname $0`/in/

version=$1

echo -ne "Downloading...\r"
[[ ! -f php-$version.tar.bz2 ]] && curl -OsS http://nl3.php.net/distributions/php-$version.tar.bz2
[[ `du php-$version.tar.bz2|cut -f1` -lt 999 ]] && rm php-$version.tar.bz2
[[ ! -f php-$version.tar.bz2 ]] && curl -O# http://museum.php.net/php5/php-$version.tar.bz2

echo -ne "Extracting...\r"
tar xjf php-$version.tar.bz2 -C ../root/
cd ../root/php-$version/

confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-intl --enable-bcmath --enable-calendar --enable-mbstring --with-zlib --with-gettext --disable-cgi --with-gmp --with-mcrypt"

[[ `vercmp $version 5.4.0`  -gt 0 && `vercmp $version 5.4.7` -lt 0  ]] && patch -p0 <../../php-with-libxml2-29plus.patch
[[ `vercmp $version 5.4.7`  -gt 0 && `vercmp $version 5.4.15` -lt 0 ]] && confFlags="$confFlags --without-openssl";
[[ `vercmp $version 5.4.14` -gt 0 ]] && confFlags="$confFlags --with-openssl"

echo -ne "Configuring...\r"
./configure $confFlags &>build-configure.log

echo -ne "Making...     \r"
make -j10 &>build-make.log

# verify correct build
./sapi/cli/php -i >/dev/null

strip ./sapi/cli/php && upx -qq ./sapi/cli/php
mv -v ./sapi/cli/php ../../out/php-$version
cd ../..
rm -R root/php-$version/

echo -e "Done...      \r"