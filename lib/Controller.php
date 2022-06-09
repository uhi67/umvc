<?php

namespace uhi67\umvc;

use Exception;
use ReflectionMethod;

/**
 * Represents a function in the application.
 * The App dispatcher will run the proper action of the selected Controller class.
 *
 * All main function's path in the application must be mapped to a Controller class named `<function>Controller`.
 * The `action<Action>` methods are mapped to the function action, e.g. CRUD action names.
 *
 * **Example:**
 * - Suppose the HTTP request is /user/create
 * - The dispatcher finds the UserController class, creates an instance of it, and invokes its go() method
 * - The UserController finds the actionCreate() method, based on the remainder of the path ('/create')
 * - The actionCreate() method performs the desired function
 *
 * ### Most important properties and methods:
 * - app: the application instance that invoked this controller
 * - path: the path portion passed to the controller (controller name already shifted out)
 * - query: the query part of the original request, as an associative array
 * - beforeAction(): Invoked before each action. if you define it, it must return true to enable the current action.
 * - csvResponse(): generates a CSV-format response from the give dataset
 * - jsonResponse(): generates a JSON-format response from the give dataset
 * - jsonErrorResponse(): generates a JSON-formatted error response (HTTP status is still 200 in this case)
 * - render(): same as ->app->render();
 *
 * @package UMVC Simple Application Framework
 */
class Controller extends Component
{
    /** @var App $app -- the parent application object */
    public $app;
    /** @var string[] $path -- unused path elements after controller (or action) name */
    public $path;
    /** @var array -- query parameters to use */
    public $query;
    /** @var string|null -- name of the currently executed action (without 'action' prefix) */
    public $action;
    
    /** @var Asset[] $assets -- registered assets indexed by name */
    public $assets = [];

    /**
     * Determines and performs the requested action using $this controller
     *
     * @throws Exception if no matching action
     * @return int -- HTTP response status
     */
    public function go() {
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

    /**
     * beforeAction is executed before each action, and cancels the action if returns false.
     * The default behavior is true (enable the action).
     * Called only if the action method exists.
     */
    public function beforeAction() {
        return true;
    }

    /**
     * The default action will run if no other match is found on the path elements.
     * The default behavior throws an Exception.
     * Override in your controller, if a default action is needed.
     * @throws Exception
     */
    public function actionDefault() {
        throw new Exception('No default action is defined');
    }

    /**
     * Outputs a JSON response
     *
     * @param array|object $response -- array or object containing the output data. Null is not permitted, use empty array for empty data
     * @param array $headers -- more custom headers to send
     * @return int
     * @throws Exception -- if the response is not a valid data to convert to JSON.
     */
    public function jsonResponse($response, $headers=[]) {
        foreach ($headers as $header) $this->app->sendHeader($header);
        $this->app->sendHeader('Content-Type: application/json');
        $result = json_encode($response);
        if(!$result) throw new Exception('Invalid data');
        echo $result;
        //Debug::debug('[JSON result] '.$result);
        return 0;
    }

    /**
     * Outputs a CSV response.
     * The data must be a 2-dim array containing plain text.
     * If a header line is needed, it must be in the data.
     *
     * @param array[] $models -- array containing the output data. Null is not permitted, use empty array for empty data
     * @param array $headers -- more custom headers to send
     * @return int
     * @throws Exception -- if the response is not a valid data to convert to JSON.
     */
    public function csvResponse($models, $headers=[]) {
        foreach ($headers as $header) $this->app->sendHeader($header);
        $this->app->sendHeader('Content-Type: application/csv; charset=UTF-8');
        $this->app->sendHeader("Cache-Control: no-cache, must-revalidate");  // HTTP/1.1

        if(empty($models)) {
            echo "No results.", PHP_EOL;
        }
        else {
            $s = fopen('php://output', 'w');
            fputs($s, chr(239) . chr(187) . chr(191)); // UTF8_BOM, unless Excel doesn't recognize UTF8 characters
            foreach($models as $model) {
               fputcsv($s, array_values($model), ';');
            }
            fclose($s);
        }
        return 0;
    }

    /**
     * Returns an error response
     *
     * @param string|mixed $error -- The error message (can be another structure)
     * @return int
     * @throws Exception
     */
    public function jsonErrorResponse($error) {
        return $this->jsonResponse([
            'success' => false,
            'error' => $error,
        ]);
    }

    /**
     * Returns an error response
     *
     * @param string $error -- The error message
     * @param string $format -- HTML: displays a HTML error page; JSON: returns a JSON error response
     * @return int
     * @throws Exception -- in case of HTML (Exception will be caught and displayed as HTML)
     */
    public function errorResponse($error, $format='HTML') {
        if($format=='JSON') return $this->jsonErrorResponse($error);
        throw new Exception($error);
    }

    /**
     * A shortcut for app->render
     *
     * @param string $viewName -- basename of a php view-file in the `views` directory, without extension
     * @param array $params -- parameters to assign to variables used in the view
     * @param string $layout -- the layout applied to this render after the view rendered. Ignored if false-like
     * @param array $layoutParams -- optional parameters for the layout view
     *
     * @return false|string
     * @throws Exception
     */
    public function render($viewName, $params=[], $layout='layout', $layoutParams=[]) {
        return $this->app->render($viewName, $params, $layout, $layoutParams);
    }

    public function registerAsset(Asset $asset) {
        // TODO: implement 'after'
        $this->assets[] = $asset;
    }

    public function linkAssets($extensions=null) {
        $html = '';
        foreach($this->assets as $asset) {
            foreach($asset->files as $file) {
                // TODO iterate file pattern in the cache (use extension filter)
                // TODO create link based on extension
                $css_example = '<link rel="stylesheet" href="<?= $this->asset("twitter/bootstrap/dist", "css/bootstrap.min.css"); ?>">';
                $js_example = '<script src="<?= $this->asset("bower-asset/jquery/dist", "jquery.min.js") ?>"></script>';
            }
        }

        return $html;
    }

}
