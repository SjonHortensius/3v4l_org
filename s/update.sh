#!/bin/sh
set -e

cd `dirname $0`

cat ../ext/CodeMirror/lib/codemirror.css ./my.css | php -r "require('/srv/http/.common/Basic_Framework/library/Basic/Static.php');echo Basic_Static::cssStrip(file_get_contents('php://stdin'));" > c.css
cat ../ext/CodeMirror/lib/codemirror.js ../ext/CodeMirror/mode/*/*.js ./my.js | php -r "require('/srv/http/.common/jsminplus.php');ini_set('memory_limit', '256M');echo JSMinPlus::minify(file_get_contents('php://stdin'));" > c.js
