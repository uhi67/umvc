<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

/**
 * Represents a CLI function in the application.
 * The App dispatcher will run the proper action of the selected Controller class.
 *
 * All main function's path in the application must be mapped to a Controller class named `<function>Controller`
 * The `action<Action>` methods are mapped to the function action, e.g. CRUD action names.
 *
 * @package UMVC Simple Application Framework
 */
class Command extends BaseController
{
}
