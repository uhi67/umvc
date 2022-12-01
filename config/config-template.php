<?php
/**
 * The main configuration file of the application
 *
 * Create the configuration file for your application in the /config directory based on this template.
 * The configuration should be version-controlled with your code, and use secrets from environment variables or other external source.
 */
return [
	\uhi67\umvc\App::class, // Can be an application-specific descendant
	// Can be set to "development" or "local" on development site
    'application_env' => 'production',
	'mainControllerClass' => '\app\controllers\MainController', // A user class derived from \uhi67\umvc\Controller
	'layout'=>'layouts/main',

    // Must be set to 'on' if site is behind a reverse proxy terminating the HTTPS connection and forwarding HTTP.
    'https' => 'off',

    'components' => [
        // ---- DATABASE CONFIGURATION
        'db' => [
            \uhi67\umvc\MysqlConnection::class,
            'dsn' => "mysql:host=localhost;dbname=",
	        'name' => "",
            'user' => "",
            'password' => "",
        ],

        //---- Authentication module
        'auth' => [
            \uhi67\umvc\SamlAuth::class,
            //---- SAML related configuration values
            // Must be set to a secure password
            'admin_password' => '',
            // Must be set to a secure random string
            'secret_salt' => '',
            // A specific IdP can be configured to log in with. If not specified, the discovery service will be called.
            'idp' => null,
            // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
            'disco' => null,
            // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
            'auth_source' => '',
        ],

        // The cache used (for accelerating database operations). May be omitted in development environment.
        'cache' => [
            // A component definition array must contain a classname and optional initialization values for public properties.
            \uhi67\umvc\FileCache::class,
        ]
    ],
];
