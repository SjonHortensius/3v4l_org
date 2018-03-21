#!/bin/bash
#
# v=3.18.1; curl http://dl.hhvm.com/debian/pool/main/h/hhvm/hhvm_$v~jessie_amd64.deb -O; \
#   ar p hhvm_$v~jessie_amd64.deb data.tar.xz|tar xJv ./usr/bin/hhvm; \
#   strip usr/bin/hhvm; mv usr/bin/hhvm /srv/http/3v4l.org/bin/hhvm-$v

# for debian:
#
# apt-get install libxml2-dev libssl-dev pkg-config zlib1g-dev libcurl4-openssl-dev curl-devel libcurl4-gnutls-dev libgmp-dev libmcrypt-dev
# ln -s x86_64-linux-gnu/curl /usr/include/curl
# ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h

set -e
cd `dirname $0`/in/

version=$1

ISTEMP=0
[[ $version == *a* || $version == *RC* ]] && ISTEMP=1

echo -ne "Downloading...\r"
[[ ! -f php-$version.tar.bz2 ]] && curl -OsS http://nl1.php.net/distributions/php-$version.tar.bz2
[[ `du php-$version.tar.bz2|cut -f1` -lt 999 ]] && rm php-$version.tar.bz2
[[ ! -f php-$version.tar.bz2 ]] && curl -O# http://museum.php.net/php5/php-$version.tar.bz2

ls php-$version.tar.bz2 >/dev/null

echo -ne "Extracting...\r"
[[ -d ../root/$version/ ]] && rm -R ../root/$version/
tar xjf php-$version.tar.bz2 -C ../root/ || rm -v php-$version.tar.bz2
cd ../root/php-$version/

confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-intl --enable-bcmath --enable-calendar --enable-mbstring --with-zlib --with-gettext --disable-cgi --with-gmp --with-mcrypt"
confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-mbstring --with-zlib --disable-cgi"

vers=${version//./}
  if [[  $ISTEMP -eq 1 ]]; then vers=${vers:0:2}0
elif [[ ${#vers} -eq 3 ]]; then vers=${vers:0:2}0${vers:2}; fi
[[ $vers -gt 5209 && $vers -lt 5407 ]] && patch -p0 <../../php-with-libxml2-29plus.patch
[[ $vers -gt 5209 && $vers -lt 5400  ]] && patch -p0 <../../php-with-newer-gmp.patch
[[ $vers -gt 5407 && $vers -lt 5415 ]] && confFlags="$confFlags --without-openssl";
[[ $vers -gt 5414 ]] && confFlags="$confFlags --with-openssl"
[[ $vers -gt 7000 ]] && confFlags="$confFlags --with-password-argon2"
[[ $vers -gt 7200-1 ]] && confFlags="$confFlags --with-sodium=shared"

if [[ $ISTEMP -eq 1 ]]; then
	EXTENSION_DIR=/usr/lib/php/${version:0:3}/modules; export EXTENSION_DIR
else
	EXTENSION_DIR=/usr/lib/php/$version/modules; export EXTENSION_DIR
	for ext in intl bcmath; do confFlags="$confFlags --enable-$ext=shared"; done
	for ext in curl gmp iconv mcrypt; do confFlags="$confFlags --with-$ext=shared"; done
fi

echo -ne "Configuring...\r"
./configure $confFlags &>build.log || { rm -R */; tail build.log; exit 1; }

echo -ne "Making...     \r"
# remove directories but leave logs
make -j10 &>build.log || { rm -R */; tail build.log; exit 1; }

# verify correct build
./sapi/cli/php -i >/dev/null

strip sapi/cli/php modules/*.so # && upx -qq ./sapi/cli/php
mv -v sapi/cli/php ../../out/php-$version
mkdir -p ../../out/exts/$version/modules && mv modules/*.so ../../out/exts/$version/modules/
cd ../..
rm -R root/php-$version/

echo -e "Done...       \r"
echo -n "Publish? [Yn]"; read p

[[ $p == "N" || $p == "n"  ]] && exit 0

scp out/php-$version root@3v4l.org:/srv/http/3v4l.org/bin/
[[ $ISTEMP -eq 0 ]] && rsync -xva out/exts/ root@3v4l.org:/srv/http/3v4l.org/usr_lib_php/
