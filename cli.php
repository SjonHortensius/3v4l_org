#!/usr/bin/php
<?php

$_SERVER['HTTP_ACCEPT'] = 'text/plain';
$_SERVER['REQUEST_URI'] = $_SERVER['DOCUMENT_URI'] = $_SERVER['argv'][1];
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/htdocs/index.php';

chdir(__DIR__. '/htdocs');
require('./index.php');