#!/bin/sh
set -e

cd `dirname $0`

{
	cat ./my.css

	head -n18 ../ext/glyphicons-halflings.css

	# include only the icons we use
	grep -rEo icon-[a-z-]+ ../../tpl/ ./my.js | cut -d: -f2|sort -u|while read i; do grep -A2 --no-group-separator $i ../ext/glyphicons-halflings.css; done
}| php -r "require('/srv/http/.common/Basic_Framework/library/Basic/Static.php');echo Basic_Static::cssStrip(file_get_contents('php://stdin'), 2500);" > c.css

cat ./my.js | php -r "require('/srv/http/.common/jsminplus.php');ini_set('memory_limit', '256M');echo JSMinPlus::minify(file_get_contents('php://stdin'));" > c.js

[[ $1 == 'q' ]] && exit 0

curl -s -d compilation_level=SIMPLE_OPTIMIZATIONS -d output_format=text -d output_info=compiled_code --data-urlencode "js_code@c.js" http://closure-compiler.appspot.com/compile > c2.js
cat c2.js ../ext/diff.js >c.js
rm c2.js
