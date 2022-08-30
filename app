#!/usr/bin/env php
<?php
/**
 * Application CLI starter
 * Copy this file to the application's root
 */

if(!file_exists(__DIR__ . '/vendor/autoload.php')) throw new Exception('Missing vendor library. Please run `composer install` first.');
require_once __DIR__ . '/vendor/autoload.php';
$configFile = __DIR__ . '/config/config.php';
\uhi67\umvc\App::cli($configFile);
