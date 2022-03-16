<?php
$app_config = include dirname(dirname(__DIR__)).'/config.php';

$https = $app_config['https'] ?? getenv('HTTPS');
/** Reverse proxy protocol patch */
$protocol = ($https=='on' || ($_SERVER['SERVER_PORT']??80) == 443) ? "https" : "http";
if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $https=='on') {
	$protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $protocol;
	if($https == "on") $protocol = 'https';
}

$baseurlpath = $protocol . '://' . $_SERVER["HTTP_HOST"] . '/simplesaml/';
return $baseurlpath;
