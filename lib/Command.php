<?php

namespace uhi67\umvc;

use Exception;
use ReflectionMethod;

/**
 * Represents a CLI function in the application.
 * The App dispatcher will run the proper action of the selected Controller class.
 *
 * All main function's path in the application must be mapped to a Controller class named `<function>Controller`
 * The `action<Action>` methods are mapped to the function action, e.g. CRUD action names.
 *
 * @package UMVC Simple Application Framework
 */
class Command extends Component
{
    /** @var App $app -- the parent application object */
    public $app;
    /** @var string[] $path -- unused path elements after controller (or action) name */
    public $path;
    /** @var array -- query parameters to use */
    public $query;
    /** @var string|null -- name of the currently executed action (without 'action' prefix) */
    public $action;

    /**
     * Execute the request by this controller
     *
     * @return int -- HTTP response status
     * @throws Exception if no matching action
     */
    public function go()
    {
        // Search for action method to call
        $methodName = null;
        $this->action = null;
        if (isset($this->path[0]) && is_callable([$this, $func = 'action' . AppHelper::camelize($this->path[0])])) {
            $this->action = array_shift($this->path);
            $methodName = $func;
        } else {
            if (is_callable([$this, $func = 'actionDefault'])) {
                $this->action = 'default';
                $methodName = $func;
            }
        }

        // Call the action method with the required parameters from the request
        if ($methodName) {
            if (!$this->beforeAction()) {
                return 0;
            }
            $args = [];
            $ref = new ReflectionMethod($this, $methodName);
            foreach ($ref->getParameters() as $param) {
                $urlizedParameterName = AppHelper::underscore(AppHelper::camelize($param->name));
                $defaultValue = $param->isOptional() ? $param->getDefaultValue() : null;
                // If the next item of the path is an integer, it can be used as value of $id parameter (get parameter overrides it)
                if ($param->name == 'id' && isset($this->path[0]) && ctype_digit($this->path[0])) {
                    $defaultValue = (int)array_shift($this->path);
                }
                $args[$param->name] = $this->query[$urlizedParameterName] ?? $defaultValue;
                if (!$param->isOptional() && !isset($this->query[$urlizedParameterName]) && $defaultValue === null) {
                    throw new Exception(
                        "The action `$this->action` requires parameter `$urlizedParameterName`",
                        HTTP::HTTP_BAD_REQUEST
                    );
                }
            }
            $status = call_user_func_array([$this, $methodName], $args);
            return $status ?: ($this->app->responseStatus ?: 200);
        }

        // We are here if no action method was found for the request
        $pathInfo = implode('/', $this->path);
        throw new Exception('Unknown action at ' . $pathInfo, HTTP::HTTP_NOT_FOUND);
    }

    /**
     * beforeAction is executed before each action, and cancels the action if returns false.
     * The default behavior is true (enable the action).
     * Called only if the action method exists.
     */
    public function beforeAction()
    {
        return true;
    }

    /**
     * The default action will run if no other match is found on the path elements.
     * The default behavior throws an Exception.
     * Override in your controller, if a default action is needed.
     * @throws Exception
     */
    public function actionDefault()
    {
        throw new Exception('No default action is defined');
    }
}
