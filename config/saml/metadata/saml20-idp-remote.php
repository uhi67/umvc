<?php

$app_config = include dirname(dirname(__DIR__)).'/config.php';
$env = $app_config['APPLICATION_ENV'] ?? 'development';

$baseurlpath = include dirname(__DIR__) . '/config/baseurl.php';

if($env!='production') {
    /*
     * Internal test auth. source
     */
    $metadata[$baseurlpath . 'saml2/idp/metadata.php'] = array(
        'name' => array(
            'hu' => 'Helyi teszt bejelentkezés',
            'en' => 'Local test authentication source'
        ),
        'description' => 'Here you can login with a local test account.',

        'SingleSignOnService' => $baseurlpath . 'saml2/idp/SSOService.php',
        'SingleLogoutService' => $baseurlpath . 'saml2/idp/SingleLogoutService.php',
        'certificate' => 'umvc.crt',
    );
}
