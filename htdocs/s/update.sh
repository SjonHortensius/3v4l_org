#!/bin/sh
set -e

cd `dirname $0`

cat ../ext/LRTEditor/src/style.css ./my.css ../ext/glyphicons-halflings.css | php -r "require('/srv/http/.common/Basic_Framework/library/Basic/Static.php');echo Basic_Static::prefixCss3(Basic_Static::cssStrip(file_get_contents('php://stdin')));" > c.css

cat ../ext/LRTEditor/shjs/lang/sh_php.js \
	../ext/LRTEditor/shjs/sh_main.min.js \
	../ext/LRTEditor/src/LRTEditor.js \
	../ext/LRTEditor/src/LRTEditor.HighlightPlugin.js \
	../ext/LRTEditor/src/LRTEditor.MinimalPlugin.js \
	../ext/LRTEditor/src/LRTEditor.UndoPlugin.js \
	../ext/LRTEditor/src/LRTEditor.FormPlugin.js \
	./my.js >c.js

[[ $1 == 'd' ]] && exit 0

mv c.js c2.js
cat c2.js | php -r "require('/srv/http/.common/jsminplus.php');ini_set('memory_limit', '256M');echo JSMinPlus::minify(file_get_contents('php://stdin'));" > c.js

[[ $1 == 'q' ]] && exit 0
mv c.js c2.js

curl -s -d compilation_level=SIMPLE_OPTIMIZATIONS -d output_format=text -d output_info=compiled_code --data-urlencode "js_code@c2.js" http://closure-compiler.appspot.com/compile > c.js

rm c2.js
