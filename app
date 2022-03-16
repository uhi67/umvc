#!/usr/bin/env php
<?php
/**
 * Application CLI starter
 * Copy this file to the application's root
 */

require_once __DIR__ . '/vendor/autoload.php';
$configFile = __DIR__ . '/config/config.php';
\uhi67\umvc\App::cli($configFile);
