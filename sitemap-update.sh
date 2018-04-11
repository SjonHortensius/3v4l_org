#!/bin/bash
set -e ; cd `dirname $0`/htdocs/

rm sitemap*.* ||:

sudo -u postgres psql phpshell -tc "SELECT short, TO_CHAR(created, 'YYYY-MM-DD') FROM input WHERE created>'1970-01-01' AND state='done'" | while read n x d
do
	[[ -z $n ]] && continue

	echo "<url><loc>https://3v4l.org/$n</loc><lastmod>$d</lastmod><changefreq>monthly</changefreq></url>"
done | split --verbose -dl 50000 - sitemap

echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' > sitemap.xml

for f in sitemap??
do
	echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' > $f.xml
	cat $f >> $f.xml
	echo '</urlset>' >> $f.xml
	echo "<sitemap><loc>https://3v4l.org/$f.xml</loc><lastmod>`date +%Y-%m-%d`</lastmod></sitemap>" >> sitemap.xml
	rm $f
done

echo '</sitemapindex>' >> sitemap.xml

exit 0