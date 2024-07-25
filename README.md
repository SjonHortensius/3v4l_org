# PHPShell - submit and visualize output across multiple versions of PHP
# this is deployed on https://3v4l.org

## Overview

This application runs on top of [Basic_Framework](https://github.com/SjonHortensius/Basic_Framework) which is an MVCish framework - so for any specific action you can jump right into the Action class. After a user submits code (through library/PhpShell/Action/New.php#76) it is stored in the database and the daemon will be notified.  

## Setup instructions

For local development see [CONTRIBUTING.md](CONTRIBUTING.md)

If you want to run this, follow these steps:

* install postgresql, golang, php-fpm, memcached and nginx
* compile the daemon, it's path is configured in the `.service` file
* create two postgresql users, one for the website and one for the daemon
* load `images/postgresql/fixtures/01_structure.sql` in a fresh database
* update the password for the website in `config.ini`
* specify the dsn for the daemon in `daemon.service`
* it's probably best to use a separate domain or IP for the webserver, configure nginx to pass all requests to `index.php`
```
server
{
	listen	80;

	server_name	phpshell.example.com;
	root		/srv/http/example.com/phpshell/htdocs;

	location /
	{
		expires off;

		include	    	fastcgi_params;
		fastcgi_param	SCRIPT_FILENAME $document_root/index.php;
		fastcgi_pass	unix:/var/run/php-fpm/php-fpm.sock;
	}
}
```

## Files of interest if you want to jump right in

* Handling of submits: library/PhpShell/Action/New.php#76
* Processing by daemon: daemon.go
* Visualisation of output: library/PhpShell/Action/Script.php#L68 and tpl/script.html
