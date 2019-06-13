#!/bin/bash
#
# v=3.18.1; curl http://dl.hhvm.com/debian/pool/main/h/hhvm/hhvm_$v~jessie_amd64.deb -O; \
#   ar p hhvm_$v~jessie_amd64.deb data.tar.xz|tar xJv ./usr/bin/hhvm; \
#   strip usr/bin/hhvm; mv usr/bin/hhvm /srv/http/3v4l.org/bin/hhvm-$v

set -e
cd $(dirname $0)"/in/"

ISGIT=0
[[ "$1" == "--branch" ]] && { ISGIT=1; shift; }
# FIXME - allow passing both an org (php) and a branch
version=$1

[[ -d ../root/$version/ ]] && rm -R ../root/php-src-$version/ ../root/php-$version/

echo -ne "Downloading...\r"

if [[ $ISGIT -gt 0 ]]; then
	rm -fv $version.tar.bz2
	curl -LO# https://github.com/php/php-src/archive/$version.tar.gz || { echo not a valid branch name: $version >&2; exit 1; }

	echo -ne "Extracting...\r"
	tar xaf $version.tar.gz -C ../root/ || rm -v php-$version.tar.gz

	ROOT=php-src-$version
else
	[[ ! -f php-$version.tar.bz2 ]] && curl -OsS https://www.php.net/distributions/php-$version.tar.bz2
	[[ $(du php-$version.tar.bz2|cut -f1) -lt 999 ]] && rm php-$version.tar.bz2
	[[ ! -f php-$version.tar.bz2 ]] && curl -O# http://museum.php.net/php5/php-$version.tar.bz2

	echo -ne "Extracting...\r"
	tar xaf php-$version.tar.bz2 -C ../root/ || rm -v php-$version.tar.bz2

	ROOT=php-$version
fi

cd ../root/$ROOT/
[[ $ISGIT -gt 0 ]] && ./buildconf
confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-intl --enable-bcmath --enable-calendar --enable-mbstring --with-zlib --with-gettext --disable-cgi --with-gmp --with-mcrypt"
confFlags="--prefix=/usr --exec-prefix=/usr --without-pear --enable-mbstring --with-zlib --disable-cgi"
confFlags="$confFlags --with-curl=/usr"

vers=${version//./}; [[ ${#vers} -eq 3 ]] && vers=${vers:0:2}0${vers:2}; [[ $vers == *a* || $vers == *RC* ]] && vers=${vers:0:3}0
[[ $vers -gt 5209 && $vers -lt 5407 ]] && patch -p0 <../../php-with-libxml2-29plus.patch
[[ $vers -gt 5209 && $vers -lt 5400  ]] && patch -p0 <../../php-with-newer-gmp.patch
[[ $vers -gt 5407 && $vers -lt 5415 ]] && confFlags="$confFlags --without-openssl";
#[[ $vers -gt 7000-1 ]] && confFlags="$confFlags --with-password-argon2"
[[ $vers -gt 7200-1 ]] && confFlags="$confFlags --with-sodium=shared"
[[ $vers -gt 7300-1 ]] && confFlags="$confFlags --without-curl"

EXTENSION_DIR=/usr/lib/php/$version/modules; export EXTENSION_DIR

for ext in intl bcmath; do confFlags="$confFlags --enable-$ext=shared"; done
for ext in curl gmp iconv; do confFlags="$confFlags --with-$ext=shared"; done

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
rm -R root/$ROOT/

echo -e "Done...       \r"
