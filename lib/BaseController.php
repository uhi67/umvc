<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;
use ReflectionMethod;
use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use uhi67\umvc\Component;
use uhi67\umvc\HTTP;

/**
 * BaseController is the common part of the **Controller** and the **Command** classes.
 * Controllers performs the application functions via named actions.
 *
 * - {@see Controller} serves the HTTP requests,
 * - {@see Command} serves the CLI commands.
 *
 * Both has a parent App, an action to run, and optional query parameters.
 */
class BaseController extends Component {
    /** @var App $app -- the parent application object */
    public App $app;
    /** @var string|null -- name of the currently executed action (without 'action' prefix) */
    public ?string $action;
    /** @var array -- query parameters to use */
    public array $query;
    /** @var string[] $path -- unused path elements after controller (or action) name */
    public array $path;

    /**
     * beforeAction is executed before each action, and cancels the action if returns false.
     * The default behavior is true (enable the action).
     * Called only if the action method exists.
     */
    public function beforeAction(): bool {
        return true;
    }

    /**
     * The default action will run if no other match is found on the path elements.
     * The default behavior throws an Exception.
     * Override in your controller, if a default action is needed.
     * @throws Exception
     */
    public function actionDefault() {
        throw new Exception('No default action is defined in '.call_user_func([get_called_class(), 'shortName']));
    }

    /**
     * Execute the request by this controller
     *
     * @throws Exception if no matching action
     * @return int -- HTTP response status
     */
    public function go(): int {
        // Search for action method to call
        $methodName = null;
        $this->action = null;
        $func = 'action'.AppHelper::camelize($this->path[0] ?? 'default');
        if(isset($this->path[0]) && is_callable([$this, $func]))
        {
            $this->action = array_shift($this->path);
            $methodName = $func;
        }
        else if(is_callable([$this, $func = 'actionDefault'])) {
            $this->action = 'default';
            $methodName = $func;
        }

        // Call the action method with the required parameters from the request
        if($methodName) {
            if(!$this->beforeAction()) return 0;
            $args = [];
            $ref = new ReflectionMethod($this, $methodName);
            foreach($ref->getParameters() as $param) {
                $urlizedParameterName = AppHelper::underscore(AppHelper::camelize($param->name));
                $defaultValue = $param->isOptional()? $param->getDefaultValue() : null;
                // If the next item of the path is an integer, it can be used as value of $id parameter (get parameter overrides it)
                if($param->name=='id' && isset($this->path[0]) && ctype_digit($this->path[0])) $defaultValue = (int)array_shift($this->path);
                $args[$param->name] = $this->query[$urlizedParameterName] ?? $defaultValue;
                if(!$param->isOptional() && !isset($this->query[$urlizedParameterName]) && $defaultValue===null) {
                    throw new Exception("The action `$this->action` requires parameter `$urlizedParameterName`", HTTP::HTTP_BAD_REQUEST);
                }
            }
            $status = call_user_func_array([$this, $methodName], $args);
            return $status ?: ($this->app->responseStatus ?: 200);
        }

        // We are here if no action method was found for the request
        $pathInfo = implode('/', $this->path);
        throw new Exception('Unknown action at '.$pathInfo, HTTP::HTTP_NOT_FOUND);
    }
}
