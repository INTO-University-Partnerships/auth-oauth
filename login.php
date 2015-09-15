<?php
define('SLUG', $_GET['slug']);
$_SERVER['REQUEST_URI'] = str_replace(SLUG, '', $_SERVER['REQUEST_URI']);

require_once __DIR__ . '/app.php';
$app->run();