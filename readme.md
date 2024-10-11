UMVC framework
==============

Version 2.0 -- 2024-10-11

A simple web-application framework implementing model-view-controller (MVC) architectural pattern.

Key features
------------
- simple html/php based views with rendering hierarchy
- PDO-based database connection with array-based query builder (only MySQL is currently implemented)
- easy-to-use Model classes
- simple URL-path to Controller/method translation with automatic parameter passing
- database migration support
- user login via SAML (Using this feature needs `composer require "simplesamlphp/simplesamlphp:^2.2"`)

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
The framework can be included into your project with composer. Run `composer require uhi67/umvc:dev-master`.
New, empty project can be created using `composer create-project --prefer-dist uhi67/umvc-app name`. This will deliver you a new empty application using UMVC framework into the named directory. Choose any name you prefer.

First steps to build your application using UMVC
------------------------------------------------

**Warning: This part is under construction.**
Learn more about the mentioned classes in the docblock of the class definition.

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

#### Component configurations and properties

All components,most of UMVC classes, including the main App class itself, is a `\uhi67\umvc\Component`.
`Component` implements **property** features: magic getter and setter uses getProperty and setProperty methods.
`Component` is **configurable**: constructor accepts a configuration array containing values for public properties.

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

#### Entry scripts, URLs and routing

The single entry script for the web application is the `www/index.php`.
Respectively, the single entry script for the CLI application is the `app` file.
Both of them must be copied into your application directory from the `vendor/uhi67/umvc/` directory.

The `www/.htaccess` rules redirect all not-found requests to the `www/index.php`.
However, static assets are served directly from the www directory. Learn more about serving library assets later.

The `index.php` initializes the autoloader, load the main configuration, creates the main object 
(class defined in the configuration, usually `uhi67/umvc/App` or its descendant).
The main configuration follows the rules of the configurable `Component`.

##### Routing

All URL formed as https://myapp/acontroller/anaction is processed the following way:

`uhi67/umvc/App` parses the request, computes the actual controller class (derived from `uhi67/umvc/Controller`) to use, 
creates the controller and runs the requested action method. As in the example above, **acontroller** refers to your controller 
class in your `controllers` directory as _AcontrollerController_, and **anaction** refers to the action method (as _actionAnaction_).

If the action name is missing from the URL, the `actionDefault` will be run. If the controller name is missing as well, 
the configured _mainController_ will be used. It is also possible to create a URL with an action name of the 
default controller without specifying the controller name - the only restriction you cannot have a controller with the same name as this action.

Al CLI command formed as `php app acontroller/anaction` is processed the following way:

`uhi67/umvc/App` parses the request, computes the actual controller class (derived from `uhi67/umvc/Command`) to use,
creates the controller and runs the requested action method. As in the example above, **acontroller** refers to your controller
class in your `commands` directory as _AcontrollerController_, and **anaction** refers to the action method (as _actionAnaction_).
Built-in commands can be run the same way. A command with the same name in our application overrides the built-in command.

##### using URLs in your controller action

The parts of the current URL request can be accessed as:
- path: the path portion passed to the controller (controller name already shifted out)
- query: the query part of the original request, as an associative array

To create a new URL using controller and action names, and optioanl qurey parameters, use one of the following:
- $this->app->createUrl(['controller/action', 'key'=>'queryvalue', ..., '#'=>'fragment']) -- all parts are optional
- return $this->app->redirect([...]) -- to terminate the action with a redirection

#### Serving library assets

In your view files, you can refer your static assets located under `www` directory in static way, e.g `<link href="/assets/css/app.css" rel="stylesheet">`.
On the contrary, if you want to refer to an asset file located somewhere in the composer-created vendor library, you can use them this way:
- `<script src="<?= $this->linkAssetFile('npm-asset/bootstrap/dist', 'js/bootstrap.bundle.min.js') ?>"></script>`

The `linkAssetFile` function copies all the files from the directory in the first argument into the asset 
cache directory under the `www` , and creates a valid URL to the file int the second argument.
Note:  The first argument identifias an asset package. Only the first call for any package copies the files. 
All subsequent calls to the same package generates only the link for the file.

The asset cache is emptied by the `composer install` command.
The asset cache is always `www/asset/cache` and is not configurable.

#### Other topics (coming soon)

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

### version 2.0 -- "2024-10-11"

- requires php 8.2
- fix baseUrl usage 

### version 1.4 -- 2024-07-25

- Query::filterInReferredModels() added
- Empty caches: skip .gitignore files
- UMVC guide and refer umvc app
- php 8 compatibility fixes
- Other bugfixes

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
