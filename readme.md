UMVC framework
==============

Version 1.3 -- 2022-11-29

A simple web-application framework implementing model-view-controller (MVC) architectural pattern.

Key features
------------
- simple html/php based views with rendering hierarchy
- PDO-based database connection with array-based query builder (only MySQL is currently implemented)
- easy-to-use Model classes
- simple URL-path to Controller/method translation with automatic parameter passing
- database migration support
- user login via SAML (Using this feature needs `composer require "simplesamlphp/simplesamlphp:^1.19.2"`)

### Modules

UMVC supports modules, see configuration/components.

### Codeception support

The framework supports functional testing with its framework plugin.
Globally installed codeception must be compatible with required (currently v3.1.2)

### Command line

UMVC supports CLI. You may create your own commands. The built-in commands are:

- migrate

Run commands as `php app $command $action $parameters`

Installation
------------
The framework can be included into your project with composer. Run `composer require uhi67/umvc:dev-master`

First steps
-----------
1. Create `composer.json` of your application, and include uh67/umvc, e.g `composer init --name myname/myapp --require uhi67/umvc:*`
2. run `composer update`

...

Development information
-----------------------
### Installation standalone and internal unit tests

**Warning: This part is under construction.**

This repository contains a built-in test application for internal codeception unit tests.

#### Standalone installation steps:

- `git clone`
- `composer update`
- Create `tests/_data/test-config.php` based on the template
- Create the `umvc-test` database in sync with the configuration above
- run `codecept run unit` for unit tests

## Testing in docker

A built-in dockerized testing environment can be used to test with different php and database versions. 

**Steps:**

1. configure the needed database version in `tests/docker-compose.yml` (make clones of this template file)  
2. configure the php version in `tests/docker/Dockerfile` (extension installation steps may change)
3. configure the used ports and base-url in `tests/.env`
4. build the stack using `docker compose up --build -d` (in the tests dir)
5. your php container is now should be 'umvc-php-1'
6. run unit tests with `docker exec -it umvc-php-1 php vendor/bin/codecept run unit`

Change log
----------
### Version 1.3 -- 2022-11-29

- Migration SQL transaction issues
- mySQL 8.0 compatibility, keeping 5.7 compatibility
- App: view path fixed
- cli config check
- AppHelper::waitFor()
- unit test fix
- A simple dockerized test application with testing guide is included
- Docker: waiting for database container initialization (simple approach) 

### Version 1.2.1 -- 2022-10-02

- twig/twig < 2.15.3 vulnerability fix (composer.lock)
- AuthManager::actionLogin() added to fix default login return issue

### Version 1.2 -- 2022-09-20

- bugfixes, phpdoc fixes 
- linkButton signature has changed
- Connection::connect is back
- Connection information methods (getTables(), etc) 
- MysqlConnection dropXXX methods
- migrate/reset command
- unit tests, test app (draft)
- showException previous message fixed
- Model primary key check
- SamlAuth: update user record only at login
- Version file creation removed 

### Version 1.1 -- 2022-08-30

- Asset registry improvements (Controller::registerAssets(), etc)
- Html::img() added
- Session, Request, FileUpload classes
- localization (App::l(), App::$locale, L10n, L10nFile, etc)
- App: default layout parameter
- render bugfixes
- SamlAuth::get() fixed

#### Upgrade notes for version 1.1

- App::asset() calls must be replaced by App::linkAssetFile() calls (using same arguments)
