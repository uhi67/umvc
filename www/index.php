<?php
/**
 * Application Web starter.
 * Copy this file to the application's web root as index.php
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
$configFile = dirname(__DIR__) . '/config/config.php';
return \uhi67\umvc\App::createRun($configFile);
