UMVC framework
==============

Version 1.1 -- 2022-08-30

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

Change log
----------

### Version 1.1 -- 2022-08-30

- Asset registry inprovements (Controller::registerAssets(), etc)
- Html::img() added
- Session, Request, FileUpload classes
- localization (App::l(), App::$locale, L10n, L10nFile, etc)
- App: default layout parameter
- render bugfixes
- SamlAuth::get() fixed

#### Upgrade notes for version 1.1

- App::asset() calls must be replaced by App::linkAssetFile() calls (using same arguments)
