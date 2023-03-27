UMVC framework
==============

Version 1.3.1 -- 2023-03-27

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

First steps to build your application using UMVC
------------------------------------------------

**Warning: This part is under construction.**

1. Create `composer.json` of your application, and include `uh67/umvc`, e.g `composer init --name myname/myapp --require uhi67/umvc:*`.
2. Run `composer update`.
3. Copy `vendor/uh67/umvc/app` to your application's root. This is the launcher of the CLI commands. 
4. Copy `vendor/uh67/umvc/www/index.php` and `.htaccess` to your application's www directory. This is the router of the web interface. 
5. Create your application's config in the `config/config.php` file, see template in `vendor/uh67/umvc/config/config-template.php`.
6. Create a `runtime` directory writable by the webserver to place temporary files.
7. Create the `www/assets` directory writable by the webserver to place cached asset files of various components.

#### Build your application using MVC pattern

1. Create your controllers in the `controllers` dir, using `\app\controllers` namespace, and derive them from `uhi67\umvc\Controller`.
2. Create the views in the `views` dir, in simple PHTML format, and organize them according to `views/controller/action.php` structure.
3. Create your models in the `models` directory. Database models are `\uhi67\umvc\Model`, database-less models are `\uhi67\umvc\BaseModel`.
                                         
#### Other basic principles

1. Migrations are for versioning and recording database changes in source code. Place migration steps into `migrations` directory.
2. Layout is a special view to embed all other views within. Place layouts in `views/layouts` directory. Views can call other partial views.
3. You can define CLI commands in `commands` directory, deriving from `\uhi67\umvc\Command` class. There are some built-in command in the framework. `php app` command lists all available commands, both built-in and custom ones.
4. A simple file-based localization support is built-in. Place your translations into `messges/la.php` files where "la" is the language you want to translate to.

#### Built-in components

1. `MySqlConnection` -- to connect to database. Includes SQL query builder. Currently the only implementation of `Connection`.
2. `FileCache` -- the only implementation of `CacheInterface`.
3. `SamlAuth` -- the only implementation for `AuthManager`.
4. `L10n` -- simple localization, default auto-included, translates UMVC messages only.
5. `L10nFile` -- the file-based localization to translate messages of your application.

#### Other basic classes you can use

1. `Form` -- a widget with built-in view to display and process HTML forms using your Models.
2. `Grid` (widget, but the built-in view is still missing) -- to display paginated, filtered lists of Models.
3. `Query` -- Represents a parametrized and flexible SQL query in php structure. SQL command can be built from it.
4. `Request` -- Represents the HTTP request, can be used to fetch GET and POST parameters.
5. `Session` -- Represents the current PHP session, can be used to get and set variables.
    
#### Other topics (coming soon)

- Component configurations and properties
- URLs and routing
- Logging
- Basic structure to update a Model using Form
- Grid pagination and filtering
- Model validation
- Helpers (Classes with static methods only) 

...


Framework development information
---------------------------------
### Installation standalone and internal unit tests

This repository contains a built-in test application for internal codeception unit tests.
The only purpose of the test app in the `tests` directory is to be able to run unit tests, and not a sample application to start with.

#### Standalone installation steps:

- `git clone`
- `composer update`
- Create `tests/_data/test-config.php` based on the template
- Create the `umvc-test` database according to the database settings in `tests/_data/test-config.php`
- Run `php vendor/bin/codecept run unit` for unit tests

More unit tests are coming...

## Testing in docker

A built-in dockerized testing environment can be used to test with different php and database versions. 

**Steps:**

1. configure the needed database version in `tests/docker-compose.yml` (make clones of this template file)  
2. configure the php version in `tests/docker/Dockerfile` (extension installation steps may change)
3. configure the used ports and base-url in `tests/.env`
4. build the stack using `docker compose up --build -d` (in the `tests` dir)
5. your php container should now be 'umvc-php-1'
6. run unit tests with `docker exec -it umvc-php-1 php vendor/bin/codecept run unit`

Change log
----------
### Version 1.3.1 -- 2023-03-27

- SQL Builder: use tablename as default alias
- migrate/wait command added
- postponed connection of Connection
- localized render

### Version 1.3 -- 2022-12-03

- Migration SQL transaction issues
- MySQL 8.0 compatibility, keeping 5.7 compatibility
- App: view path fixed
- CLI config check
- AppHelper::waitFor()
- Unit test fix
- A simple dockerized test application with testing guide is included
- Docker: waiting for database container initialization (simple approach)
- First steps documentation added

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
