# PHPShell - submit and visualize output across multiple versions of PHP
# this is deployed on https://3v4l.org

## Overview

This application runs on top of [Basic_Framework](https://github.com/SjonHortensius/Basic_Framework) which is an MVCish framework - so for any specific action you can jump right into the Action class. After a user submits code (through library/PhpShell/Action/New.php#76) it is stored in the database and the daemon will be notified.  

## Files of interest if you want to jump right in

* Handling of submits: library/PhpShell/Action/New.php#76
* Processing by daemon: daemon.go
* Visualisation of output: library/PhpShell/Action/Script.php#L68 and tpl/script.html
