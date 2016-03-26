#!/bin/bash
set -e ; cd `dirname $0`/htdocs/

rm sitemap*.*

ls /srv/http/3v4l.org/in -l --time-style='+%Y-%m-%d' | while read x x x x x d n
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