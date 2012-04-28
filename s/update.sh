#!/bin/sh
set -e

cd `dirnam $0`

cat ../CodeMirror/lib/codemirror.css ./my.css | php -r "require('/srv/http/.common/Basic_Framework/library/Basic/Action.php');require('/srv/http/local/dev/noopz/library/NooPz/Action.php');require('/srv/http/local/dev/noopz/library/NooPz/Action/Static.php');echo NooPz_Action_Static::stripCss(file_get_contents('php://stdin'));" > c.css
