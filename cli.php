<?php

$_SERVER['HTTP_ACCEPT'] = 'text/plain';
$_SERVER['REQUEST_URI'] = $_SERVER['argv'][1];

chdir('./htdocs');
require('./index.php');