<?php
/**
 * Application Web starter.
 * Modified for test application
 */
ini_set('display_errors', 1);
$autoload = dirname(__DIR__,4) . '/vendor/autoload.php';
if(!file_exists($autoload)) throw new Exception('Missing vendor library. Please run `composer install` first.');
require_once $autoload;
$configFile = dirname(__DIR__, 2) . '/test-config.php';
\uhi67\umvc\App::createRun($configFile);
