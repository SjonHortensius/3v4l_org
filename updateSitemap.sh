#!/bin/bash
set -e

cd /var/lxc/php_shell/in

echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'

for f in *
do
	echo -e '<url><loc>http://3v4l.org/'$f'</loc><lastmod>'`stat $f|grep Modify:|cut -d' ' -f2`'</lastmod><changefreq>never</changefreq></url>\n'
done

echo '</urlset>';
