<?php
/**
 * Application Web starter.
 * Copy this file to the application's web root as index.php
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
$configFile = dirname(__DIR__) . '/config/config.php';
// App class can be overridden here or in the main config
\uhi67\umvc\App::createRun($configFile);
