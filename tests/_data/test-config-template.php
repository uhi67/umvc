<?php
/*
|--------------------------------------------------------------------------
| The main configuration file of the application for codecept tests
| Copy this template as /tests/_data/test-config.php
|--------------------------------------------------------------------------
Must not depend on any external condition.
Don't put in any statement causing side effects.
*/

use uhi67\umvc\FileCache;
use uhi67\umvc\L10nFile;

require __DIR__.'/testapp/models/User.php';

return [
    // Must be set to "production" on production site
    'application_env' => 'development',
	'basePath' => __DIR__.'/testapp',
	'runtimePath' => dirname(__DIR__).'/_output/runtime',

    // Must be set to 'on' if site is behind a reverse proxy terminating the HTTPS connection and forwarding HTTP.
    'https' => 'off',

    'cli_components' => [
        'db',
        'cache',
    ],

    'components' => [
        'db' => [
            \uhi67\umvc\MysqlConnection::class,
            // ---- DATABASE CONFIGURATION
            'name' => $dbName = getenv('DB_NAME') ?: "umvc-test",
            'dsn' => getenv('DB_DSN') ?: "mysql:host=localhost;dbname=$dbName",
            'user' => getenv('DB_USER') ?: "umvc-test",
            'password' => getenv('DB_PASSWORD') ?: "umvc-test",
        ],
        //---- Authentication module
//        'auth' => [
//            \uhi67\umvc\SamlAuth::class,
//            //---- SAML related configuration values
//            // Must be set to a secure password
//            'admin_password' => '...',
//            // Must be set to a secure random string
//            'secret_salt' => '...',
//            // A specific IdP can be configured to login with. If not specified, the discovery service will be called.
//            'idp' => null,
//            // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
//            'disco' => null,
//            // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
//            'authSource' => 'umvc-test',
//            'idAttribute' => 'eduPersonPrincipalName',
//            'userModel' => \app\models\User::class, // Must be a Model implementing UserInterface
//        ],
        // Define the cache used for accelerating database operations. May be omitted in development environment.
        'cache' => [
            // A component definition array must contain a classname and optional initialization values for public properties.
            FileCache::class,
        ],
	    'l10n' => [
			L10nFile::class,
	    ]
    ],
];
