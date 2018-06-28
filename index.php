<?php

// 初始化
error_reporting(E_ALL || ~E_NOTICE);
date_default_timezone_set('PRC');
define('APP_PATH', dirname(__FILE__));
require_once APP_PATH . '/lib/function.php';
require_once APP_PATH . '/vendor/autoload.php';
$GLOBALS['argv'] = $argv;

// session支持
session_id(md5(json_encode($argv)));
session_start();

// 参数
$c = $argv[1] ?: $_GET['c'];
require_once APP_PATH . '/controller/base.php';
$cmdFile = APP_PATH . "/controller/{$c}.php";
if (!file_exists($cmdFile)) {
    die("No cmd file!\n");
}


// 执行
require_once $cmdFile;
$cmdName = "{$c}Cmd";
try {
    $cmd = new $cmdName();
    $cmd->run();
} catch (Exception $e) {
    die($e->getMessage());
}