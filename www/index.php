<?php /** @noinspection PhpUnhandledExceptionInspection */
/**
 * Application Web starter.
 * Copy this file to the application's web root as index.php
 */
if(!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) throw new Exception('Missing vendor library. Please run `composer install` first.');
require_once dirname(__DIR__) . '/vendor/autoload.php';
$configFile = dirname(__DIR__) . '/config/config.php';
// App class can be overridden here or in the main config
\uhi67\umvc\App::createRun($configFile);
