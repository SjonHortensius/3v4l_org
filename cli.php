#!/usr/bin/php
<?php

if ('http' != getenv('USER'))
	die('Error, run this script as http user');

if (empty($_SERVER['argv'][1]))
	die(print_r(array_map(function($f){ return lcfirst(basename($f, '.php'));}, glob(__DIR__.'/library/PhpShell/Action/Cli/*.php'))));

$_SERVER['HTTP_ACCEPT'] = 'text/plain';
$_SERVER['REQUEST_URI'] = $_SERVER['DOCUMENT_URI'] = 'cli_'.$_SERVER['argv'][1];
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/htdocs/index.php';

chdir(__DIR__. '/htdocs');
require('./index.php');