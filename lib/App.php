<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use Codeception\Util\Debug;
use ErrorException;
use Exception;
use Throwable;

/**
 * The Application class is the main dispatcher and renderer of the application.
 * A single app instance is created for each HTTP request or CLI invoke.
 * The app component manages the logged-in user and other global components, like cache or logging.
 * The app object the chooses the proper Controller to run.
 * The app object is available in the Controller and the views.
 *
 * ### The most important properties:
 *
 * - $app: static property always refers to the current single instance of App
 * - sapi: the actual value of PHP_SAPI, use it instead of the constant. (cli/cgi/apache/...)
 * - basePath: the file-system root path of the application
 * - baseUrl: URL of the current page without query parameters
 * - config: The content of the configuration array
 * - connection: (read-only) the current default database connection (auto-connected at startup based on config)
 * - controller: the currently executed controller instance
 * - headers: the HTTP headers will be sent after completing the request
 * - url: The actual requested URL (null in CLI)
 * - path: The actual requested path in the URL (recognized controller name removed after dispatching)
 *
 * - auth: The user authentication object
 * - user: The logged-in user instance or null if no user logged in.
 * - cache: The configured cache instance or null if no cache defined
 *
 * ### The most important methods
 *
 * - addFlash(): stores a flash message to display at the next request
 * - cached(): compute a value in a cached way. If the value is valid in the cache, the computer function is not run.
 * - createUrl(): create a new url based on current with new query parameters
 * - log(): logs a message into the configured logfile
 * - loggedIn(): true, if a user is logged in
 * - logout(): makes the user logged out
 * - redirect: renders a redirect state
 * - render: renders a view and applies the layout
 * - renderPartial: renders a partial view without applying layout
 * - sendHeader(): sends put an HTTP header. Use this instead of native function
 * - requireLogin(): redirects to SAML-login or throws an exception if current user is not logged-in
 *
 * @property-read Component[] $components
 * @property-read Connection $db -- the default DB connection
 * @property-read CacheInterface $cache -- the actual cache object
 * @property-read AuthManager $auth -- the actual auth manager
 * @property-read Connection $connection -- the default database connection defined in 'db' component
 * @property-read L10n $l10n
 * @package UMVC Simple Application Framework
 */
class App extends Component {
    /** @var array $config -- configuration settings */
    public $config;
    /** @var string $title of the application (used in CLI echo) */
    public $title;
    /** @var string|Controller|null -- the default controller of the application */
    public $mainControllerClass = null;

	/** @var string -- base URL of the application's landing page */
	public $baseUrl;
    /** @var string -- base path of the application */
    public $basePath;
	/** @var string -- path of the runtime directory, default is $basePath.'/runtime' */
	public $runtimePath;
    /** @var string -- URL of the current page without query parameters */
    public $currentUrl;
    /** @var UserInterface|Model|null $user -- The logged-in user or null */
    public $user;

    /** @var App $app -- The single instance of the App. Read only, please don't overwrite it runtime */
    public static $app;

    /** @var string -- The actual request URL */
    public $url;
    /** @var array */
    public $query;
    /** @var string[] -- elements in URL path */
    public $path;
    /** @var Controller|Command|null -- the currently executed controller */
    public $controller;
    /** @var string */
    public $sapi;
    /** @var int $responseStatus -- the http response status sent after completing the request */
    public $responseStatus;
    /** @var array $headers -- the http headers will be sent after completing the request */
    public $headers;
    /** @var Request $request */
    public $request;
    /** @var Session $session */
    public $session;

    /** @var string $layout -- the default layout */
    public $layout = 'layout';

    /**
     * @var string $source_locale -- the locale of the source messages for localization.
     * locale can be an ISO 639-1 language code ('en') optionally extended with a ISO 3166-1-a2 region ('en-GB')
     */
    public $source_locale = 'en-GB';
    /** @var string $locale -- the current locale for localization, e.g. "hu-HU".*/
    public $locale = 'en-GB';


    /** @var Component[] $_components  -- the configured components */
    private $_components;
    /** @var Asset[] -- Registered Assets */
    private $_assets;
    /** @var Connection $_connection -- the default database connection */
    private $_connection;
	/** @var bool|string -- Actually requested locale of current render for partial views */
	private $userLocale = true;

	/**
	 * We are being executed from the CLI
	 * @return bool
	 */
	public static function isCLI() {
		return php_sapi_name() == "cli";
	}

	/**
     * Initializes the components defined in the config.
     *
     * 'components' as name=>config pairs define the common components for web API.
     * These are also the default components for CLI API as well.
     * 'cli_components' if exist, define the components for CLI API.
     * 'cli_components' may contain references to 'components' elements, using single strings (numeric-indexed).
     * (In contrast, 'components' may not refer to 'cli_components' elements)
     *
     * @throws Exception
     */
    public function init() {
        if(!static::$app) static::$app = $this;
        if(!$this->sapi) $this->sapi = PHP_SAPI;
        error_reporting(E_ALL);

        // Other configurable settings
	    $conf = ['title', 'mainControllerClass', 'layout', 'basePath', 'runtimePath', 'baseUrl'];
        foreach($conf as $key) {
            if(array_key_exists($key, $this->config)) $this->$key = $this->config[$key];
        }
	    if(!$this->basePath) $this->basePath = dirname(__DIR__, 4);
		if(!$this->runtimePath) $this->runtimePath = $this->basePath.'/runtime';

	    if($this->sapi != 'cli') {
            $this->currentUrl = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : null;
		    if(!is_dir($logDir = $this->runtimePath.'/logs')) {
			    if(!@mkdir($logDir, 0774, true)) {
				    throw new Exception("Failed to create dir `$logDir`");
			    }
		    }
            if(!$this->request) $this->request = new Request(['parent'=>$this]);
            if(!$this->session) $this->session = new Session(['parent'=>$this]);
        }

        $components = $this->config['components'] ?? [];
        $referredComponents = [];
        if($this->sapi == 'cli') {
            $components = $this->config['cli_components'] ?? $this->config['components'];
            $referredComponents = $this->config['components'] ?? [];
        }

	    // Default components
	    if(!isset($components['l10n'])) $components['l10n'] = [
		    'class' => L10n::class,
	    ];

	    $this->_components = [];
        if($components) {
            foreach ($components as $name => $config) {
                if(is_integer($name)) {
                    if(!is_string($config)) throw new Exception('Component definition must have a name key');
                    if(!array_key_exists($config, $referredComponents)) throw new Exception("Invalid component reference '$config'");
                    $name = $config;
                    $config = $referredComponents[$config];
                }
                if(!is_array($config)) throw new Exception("Component configuration array was expected at '$name'");
                /** @var Component $obj */
                $obj = Component::create($config);
                $obj->parent = $this;
                $this->_components[$name] = $obj;
            }

            // When all component has been initialized, each of them is prepared
            foreach ($this->_components as $component) {
                if (is_callable([$component, 'prepare'])) {
                    $component->prepare();
                }
            }
        }
    }

    /**
     * Gets current http host info
     *
     * @return string -- protocol://host
     */
    public function hostInfo() {
        $https = $this->config['https'] ?? getenv('HTTPS');
        /** Reverse proxy protocol patch */
        $protocol = ($https=='on' || ($_SERVER['SERVER_PORT']??80) == 443) ? "https" : "http";
        if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $https=='on') {
            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $protocol;
            if($https == "on") $protocol = 'https';
        }
        return $protocol . '://' . $_SERVER["HTTP_HOST"];
    }

    /**
     * Displays an error message and never returns
     *
     * @noinspection PhpReturnDocTypeMismatchInspection*
     * @throws Exception
     */
    public function error(int $status, string $message) {
        $protocol = ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0');
        $title = HTTP::$statusTexts[$status] ?? '';
        $this->sendHeader($protocol . ' ' . $status . ' ' . $title);
        echo $this->render('error', ['status'=>$status, 'title'=>$title, 'message'=>$message]);
        return $status;
    }

    /**
     * Create App from given config file and run.
     * Called from index.php
     *
     * @param $configFile
     * @return int
     */
    public static function createRun($configFile) {
        try {
            $config = include $configFile;
            defined('ENV') || define('ENV', $config['application_env'] ?? 'production');
            defined('ENV_DEV') || define('ENV_DEV', ENV != 'production');
            if(ENV_DEV) ini_set('display_errors', 'On');

            $cli = PHP_SAPI=='cli';
            if(!$cli && session_status()==PHP_SESSION_NONE) {
                ini_set('session.use_strict_mode', true);
                session_start();
            }

            set_error_handler(function($severity, $errstr, $errfile, $errline) {
                $err = new ErrorException($errstr, 0, $severity, $errfile, $errline);
                AppHelper::showException($err);
                exit(500);
            });
            register_shutdown_function(function() {
                $error = error_get_last();
                if($error !== NULL) {
                    echo '<pre>', $error["message"], '</pre>', PHP_EOL;
                }
            });

            /** @var App $app */
            // Default application class (uhi67\umvc\App) may be overriden in config
            $class = $config['class'] ?? ($config[0] ?? App::class);
            $app = App::create(['class'=>$class, 'config'=>$config]);
            return $app->run();
        }
        catch(Throwable $e) {
            AppHelper::showException($e);
            return -1;
        }
    }

    /**
     * Runs the application as web function.
     * Finds a controller by request path and calls it with request parameters.
     *
     * Path elements are mapped to FQ class-name + optional action-name.
     * Called from createRun and codeception connector
     *
     * @return int -- HTTP status code
     */
    public function run() {
        try {
            if(!$this->url) $this->url = ArrayHelper::getValue($_SERVER, 'REQUEST_URI');
            if(!$this->currentUrl) $this->currentUrl = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : null;
            if(!$this->query) $this->query = $_GET;
            if(!$this->path) {
				$this->path = parse_url($this->url, PHP_URL_PATH);
            }
	        $basePath = $this->baseUrl ? explode('/', trim(parse_url($this->baseUrl, PHP_URL_PATH), '/')) : [];
            $this->path = $this->path ? explode('/', trim($this->path, '/')) : [];
	        while($basePath && $basePath[0]==$this->path[0]) {
		        array_shift($basePath); array_shift($this->path);
	        }

            if(ENV_DEV) Debug::debug('[url] '.$this->url);

            if($this->path==[''] && $this->mainControllerClass) {
                // The default action of main page can be called in the short way
                return $this->runController($this->mainControllerClass, [], $this->query);
            }
            else {
                // Find the actual controller class for this path, and let it go
                for ($i = 1; $i <= count($this->path); $i++) {
                    $classPath = array_slice($this->path, 0, $i);
                    $classPath[$i - 1] = AppHelper::camelize($classPath[$i - 1]);
                    $controllerClass = 'app\controllers\\' . implode('\\', $classPath) . 'Controller';
                    if (class_exists($controllerClass)) {
                        return $this->runController($controllerClass, array_slice($this->path, $i), $this->query);
                    }
                }
            }

            // Last resort: main controller action, if exists
            $action = $this->path[0]??'default';
            if($this->mainControllerClass && is_callable([$this->mainControllerClass, 'action'.$action])) {
                return $this->runController($this->mainControllerClass, $this->path, $this->query);
            }

            $pathInfo = implode('/', $this->path);
            throw new Exception("Page not found ($pathInfo)", HTTP::HTTP_NOT_FOUND);
        }
        catch(Throwable $e) {
            $this->responseStatus = $e->getCode() ?: HTTP::HTTP_INTERNAL_SERVER_ERROR;
            AppHelper::showException($e, $this->responseStatus);
        }
        return $this->responseStatus;
    }

    /**
     * @param string $controllerClass -- ClassName ot the controller to be called
     * @param string[] $path -- the remainder of the request path after the controller name
     * @param array $query -- the actual GET query
     * @return int -- HTTP response status
     * @throws Exception -- if invalid action was requested
     */
    public function runController($controllerClass, $path, $query) {
        $this->controller = new $controllerClass(['app' => $this, 'path' => $path, 'query' => $query]);
        return $this->controller->go();
    }

    /**
     * Deletes the session data of the current user
     */
    public function logout() {
        if($this->hasComponent('auth') && $this->auth->isAuthenticated()) $this->auth->logout();
        $this->user = null;
        if(session_status()==PHP_SESSION_ACTIVE) session_destroy();
        return null;
    }

    /**
     * Returns the default (first) DB connection
     *
     * @return Connection
     * @throws Exception
     */
    public function getConnection($required=false) {
        if(!$this->_connection) {
            $this->_connection = $this->hasComponent('db', Connection::class);
        }
        if(!$this->_connection && $required) throw new Exception('Database connection is not defined');
        return $this->_connection;
    }

	/**
	 * ## Returns rendered contents of the view
	 *
	 * ### Definitions of localized views:**
	 *
	 * - source locale: the locale used in the source code and the base language of the translations.
	 * - default view: the original view path without localization, e.g 'main/index' written in the language and rules of the source locale
	 * - localized view: the view path with locale code, e.g. 'main/en/index' or 'main/en-GB/index' whichever fits better.
	 * - source-locale view: the default view or the localized view of the source-locale
	 * - locale can be an ISO 639-1 language code ('en') optionally extended with a ISO 3166-1-a2 region ('en-GB')
	 *
	 * ### Rules for locale and language codes**
	 *
	 * - If current locale is 'en-GB', the path with 'en-GB' is preferred, otherwise 'en' is used. No other 'en-*' is used
	 * - If current locale is 'en', the path with 'en' is used, no 'en-*' is recognized.
	 *
	 * ### Locale selection
	 *
	 * - true: use current locale if the localized view exists, otherwise use the default view or source-locale view.
	 * - false: do not use localized view, even if exists. If the unlocalized (default) view does not exist, an exception occurs.
	 * - explicit locale: use the specified locale, as defined at 'true' case.
	 *
	 * Note: returns an error message rendered as a string on internal rendering errors or Exception
	 *
	 * @param string $viewName -- basename of a php view-file in the `views` directory, without extension and without localization code
	 * @param array $params -- parameters to assign to variables used in the view
	 * @param string $layout -- the layout applied to the result after the view rendered. If false, no layout will be applied.
	 * @param array $layoutParams -- optional parameters for the layout view
	 * @param string|bool|null $locale -- use localized layout selection (ISO 639-1 language / ISO 3166-1-a2 locale), see above
	 *
	 * @return null|string -- null if view file (or layout file if applied) does not exist
	 * @throws Exception -- if view path does not exist
	 */
	public function render($viewName, $params=[], $layout=null, $layoutParams=[], $locale=true) {
		if($locale === null || $locale===true) $locale = $this->locale;
		if($locale) {
			$this->userLocale = $locale;
			// Priority order: 1. Localized view (with long or short locale) / 2. untranslated / 3. default-locale view (long/short)
			$viewFile = $this->localizedViewFile($viewName, $locale);
			if(!$viewFile) {
				$viewFile = $this->viewFile($viewName);
			}
			if(!$viewFile) {
				$viewFile = $this->localizedViewFile($viewName, $this->source_locale);
			}
		} else {
			$viewFile = $this->viewFile($viewName);
		}
		if(!$viewFile) return null;
		return $this->renderFile($viewFile, $params, $layout, $layoutParams);
	}

	/**
	 * Returns best localized view filename using long or short locale. Checks if the view file exists.
	 * Returns null if none of them exists.
	 *
	 * @param string $viewName
	 * @param string|null $locale -- optional
	 * @return string|null
	 * @throws Exception -- if view path does not exist
	 */
	public function localizedViewFile($viewName, $locale) {
		// 1. Look up view file using full locale
		$lv = $locale ? $this->localizedViewName($viewName, $locale) : $viewName;
		$viewFile = $this->viewFile($lv);
		if(!$viewFile && $locale) {
			// 2. Look up view file using short language code
			$lv = $this->localizedViewName($viewName, substr($locale, 0, 2));
			$viewFile = $this->viewFile($lv);
		}
		return $viewFile;
	}

	/**
	 * Returns view name completed with location path.
	 *
	 * Examples:
	 *
	 * - 'view1', 'la' => 'la/view1'
	 * - 'controller/action', 'la' => 'controller/la/action'
	 *
	 * The result of invalid $viewName or $locale is undefined!
	 *
	 * @param string $viewName
	 * @param string $locale
	 * @return string
	 */
	private function localizedViewName($viewName, $locale) {
		$p = strrpos($viewName, '/');
		if($p===false) $p = -1;
		return substr($viewName,0, $p+1) . $locale . '/'.substr($viewName, $p+1);
	}
    /**
     * Returns rendered contents of the view using a $viewFile
     *
     * If layout is null (or omitted), the default layout is applied.
     *
     * @param string $viewFile -- a php view-file with absolute path or relative to the `views` directory
     * @param array $params -- parameters to assign to variables used in the view
     * @param string|bool $layout -- the layout applied to this render after the view rendered. If false, no layout will be applied.
     * @param array $layoutParams -- optional parameters for the layout view
     *
     * @return null|string
     * @throws Exception -- if file does not exist
     */
    public function renderFile($viewFile, $params=[], $layout=null, $layoutParams=[]) {
	    try {
		    if($layout === null) $layout = $this->layout;
			if($viewFile && !AppHelper::pathIsAbsolute($viewFile)) $viewFile = $this->basePath.'/views/' . $viewFile.'.php';
		    if(!file_exists($viewFile)) throw new Exception("View file '$viewFile' does not exist", HTTP::HTTP_NOT_FOUND);
			$content = $this->renderPhpFile($viewFile, $params??[]);
			if($layout) {
                $content = $this->render($layout, array_merge(['content'=>$content], $layoutParams??[]), false);
			}
		}
		catch(Throwable $e) {
			$content = "<div>Render error in view '$viewFile': ".$e->getMessage().'</div>';
		}
        return $content;
    }

	/**
	 * Returns the file name of the view file.
	 * Returns null if view file does not exist.
	 * View can be in the application or in the framework.
	 *
	 * @param string $viewName
	 * @return string|null
	 * @throws Exception -- if view path does not exist
	 */
	public function viewFile($viewName) {
		$viewPath = $this->basePath.'/views';
		if(!is_dir($viewPath)) throw new Exception("View path '$viewPath' does not exist");
		$viewFile = $viewPath . '/' . $viewName.'.php';
		// If view not found in the app, look up in the framework
		if(!file_exists($viewFile)) {
			$viewPath = dirname(__DIR__) . '/views';
			$viewFile = $viewPath . '/' . $viewName . '.php';
		}
		if(!file_exists($viewFile)) return null;
		return $viewFile;
	}

    /**
     * Renders a partial view without layout
     *
     * @throws Exception
     */
    public function renderPartial($viewName, $params=[]) {
        $result = $this->render($viewName, $params, false, null, $this->userLocale);
		if(ENV_DEV && $result===null) return "[ **Render error: view '$viewName' not found** ]";
		return $result;
    }


    /**
     * Internal renderer with output buffering and variable scope isolation
     *
     * @param string $_file_
     * @param array $_params_
     *
     * @return false|string
     */
    private function renderPhpFile($_file_, $_params_ = []) {
        $_level_ = ob_get_level();
        ob_start();
        ob_implicit_flush(false);
        extract($_params_, EXTR_SKIP);
        try {
            require $_file_;
            return ob_get_clean();
        }
		catch(Throwable $e) {
			echo "<h2>Server error</h2>";
			echo "<div>Error rendering file '$_file_'</div>\n";
			if(ENV_DEV) AppHelper::showException($e);
			return ob_get_clean();
		}
        finally {
            while(ob_get_level() > $_level_) ob_end_clean();
        }
    }

    /**
     * Gets and renders all flash messages from the session
     *
     * Replaces <?php include BASE_PATH . '/includes/flash_messages.php'; ?>
     *
     * @throws Exception
     */
    public function renderFlashMessages() {
        $flash_messages = static::getFlashMessages();
        static::clearFlashMessages();
        $content = '';
        Assertions::assertArray($flash_messages);
        foreach($flash_messages as $flash_message) {
            $severity = 'info';
            if(is_array($flash_message)) {
                $severity = $flash_message[0];
                $flash_message = $flash_message[1];
            }
            $classes = [
                'info' => 'alert-info',
                'success' => 'alert-success',
                'warning' => 'alert-warning',
                'failure' => 'alert-danger',
                'error' => 'alert-danger',
            ];
            $class = $classes[$severity] ?? 'alert-secondary';
            $titles = [
                'info' => '',
                'success' => 'Success!',
                'warning' => 'Warning!',
                'failure' => 'Oops!',
                'error' => 'Oops!',
            ];
            $title = $titles[$severity] ?? '';
            $content .= $this->renderPartial('_flash', [
                'class' => $class,
                'text1' => $title,
                'text2' => $flash_message
            ]);
        }
        return $content;
    }

    /**
     * Adds a flash message to the session.
     * The message will be displayed at the next page render (it may be the current or the next page request).
     *
     * @param $message
     * @param string $severity -- alert class: error, failure, warning, success, info
     */
    public static function addFlash($message, $severity='info') {
        $flash_messages = static::getFlashMessages();
        $flash_messages[] = [$severity, $message];
        static::setFlashMessages($flash_messages);
    }

	public static function getFlashMessages($clear=false) {
		$flashMessages = $_SESSION['flash_messages'] ?? [];
		if($clear) static::clearFlashMessages();
		return $flashMessages;
	}
    private static function setFlashMessages(array $messages) { $_SESSION['flash_messages'] = $messages; }
    public static function clearFlashMessages() { static::setFlashMessages([]); }

    public function loggedIn(): bool {
        return $this->user !== null;
    }

    /**
     * Requires login for this page
     *
     * @param bool $force -- if true, redirects to the login if needed, will return only if user logged in. If false, throws an exception if user is not logged in.
     *
     * @return bool -- true if redirect issued, false otherwise
     * @throws Exception -- throws an exception if user not logged in.
     */
    public function requireLogin($force=true) {
        $uid = $_SESSION['uid'] ?? null;
        if(!$this->loggedIn()) {
            if($force && !array_key_exists('login', $_REQUEST) && $uid != AuthManager::INVALID_USER) {
                return $this->redirect(['login'=>true, 'ReturnTo' => $this->url]);
            }
            else {
                throw new Exception('Must be logged in', HTTP::HTTP_FORBIDDEN);
            }
        }
        return false;
    }

    /**
     * @param $url
     * @return bool -- true means the page is already rendered
     * @throws Exception
     */
    public function redirect($url): bool {
        if(is_array($url)) $url = $this->createUrl($url);
        $this->sendHeader('Location: '.$url);
        $this->responseStatus = 302;
        return true;
    }

    /**
     * Creates a URL from a base URL and query parameters.
     * The base url also may contain a query part.
     *
     * @param array $url -- array of base url and query parameters (all optional)
     *  - at index 0 is the base url, if missing or empty the current page is used
     *  - integer-indexed parameters are additional path elements added to the url path separated by /
     *  - # key refers to the current fragment
     *  - all other key/value pairs are query parameters. Existing parameters with same keys are overwritten.
     * @param bool $absolute -- return absolute URL
     * @return string
     * @throws Exception
     */
    public function createUrl(array $url, $absolute=false): string {
		if(isset($url[0]) && $url[0]=='') $baseUrl = $this->baseUrl;
        else $baseUrl = $url[0] ?? $this->url;
        unset($url[0]);
        parse_str(parse_url($baseUrl, PHP_URL_QUERY), $query);
        $baseUrl = parse_url($baseUrl, PHP_URL_PATH);

        if($absolute) {
            $isRelative = strncmp($baseUrl, '//', 2) && strpos($baseUrl, '://') === false;
            if ($isRelative) {
                $baseUrl = $this->hostInfo() . '/' . ltrim($baseUrl, '/');
            }
        }

        foreach($url as $key=>$value) {
            if(substr($baseUrl, -1)=='/') $baseUrl = substr($baseUrl,0,-1);
            if(is_int($key)) {
                if(is_array($value)) throw new Exception('Invalid url part: '.$key.'='.print_r($value, true));
                $baseUrl .= '/' .$value;
            }
            else $query[$key] = $value;
        }
        $queryPart = $query ? '?'.http_build_query($query) : '';
        return $baseUrl . $queryPart;
    }

    /**
     * Returns a value using configured cache
     *
     * @param string $key -- The cache key. If null, cache will be skipped, value computed directly
     * @param callable $compute -- function():mixed -- computes the actual value
     * @param bool|int $refresh -- if true, new value is computed and stored into the cache. If int, used as a TTL value
     * @return mixed
     */
    public function cached($key, $compute, $refresh=false) {
        $ttl = is_int($refresh) ? $refresh : null;
        $refresh = is_int($refresh) ? false : $refresh;
        return $this->hasComponent('cache') && $key!==null ? $this->cache->cache($key, $compute, $ttl, $refresh) : $compute();
    }

    /**
     * A very basic logger
     *
     * The PSR-3 standard levels are used (@see \Psr\Log\LogLevel)
     *
     * @param string $level -- emergency/alert/critical/error/warning/notice/info/debug
     * @param string $message -- string only
     */
    public static function log($level, $message, $params=[]) {
	    $logfile = self::$app->runtimePath . '/logs/app.log';
	    $sid = session_id();
	    $uid = App::$app->getUserId();
	    if(!is_string($message)) $message = json_encode($message);
	    if($params) {
		    foreach($params as $k=>$v) $message = str_replace("\{$k\}", $v, $message);
	    }
	    $data_to_log = date(DATE_ATOM) . ' '. $level . ' ('.$uid.') ['.$sid.'] ' . $message . PHP_EOL;
	    file_put_contents($logfile, $data_to_log, FILE_APPEND + LOCK_EX);
    }

    /**
     * Sends out a header. Use this function instead of native header()
     *
     * @param $header
     * @return void
     */
    public function sendHeader($header) {
        header($header);
        $this->headers[] = $header;
        if(preg_match('~^http/[\d.]+\s+(\d+)~i', $header, $mm)) $this->responseStatus = $mm[1];
        if(!$this->responseStatus && preg_match('~^location:\s~i', $header)) $this->responseStatus = 302;
    }

    public function runCli() {
        try {
            $this->query = CliHelper::parseArgs();
            if(!($this->query[0]??null)) {
                $this->path = ['default'];
            }
            else {
                $this->path = explode('/', array_shift($this->query));
            }

            $pathinfo = '';
            $controllerClass = '';
            // Find the actual controller class for this path, and let it go
            for ($i = 1; $i <= count($this->path); $i++) {
                $classPath = array_slice($this->path, 0, $i);
                $classPath[$i - 1] = AppHelper::camelize($classPath[$i - 1]);
                $controllerClass = 'app\commands\\' . ($pathinfo = implode('\\', $classPath)) . 'Controller';
                if (class_exists($controllerClass)) {
                    return $this->runController($controllerClass, array_slice($this->path, $i), $this->query);
                }
                $controllerClass = 'uhi67\umvc\commands\\' . implode('\\', $classPath) . 'Controller';
                if (class_exists($controllerClass)) {
                    return $this->runController($controllerClass, array_slice($this->path, $i), $this->query);
                }
            }
            throw new Exception("Command not found ($pathinfo, $controllerClass)", HTTP::HTTP_NOT_FOUND);
        }
        catch(Throwable $e) {
            $this->responseStatus = $e->getCode() ?: HTTP::HTTP_INTERNAL_SERVER_ERROR;
            AppHelper::showException($e, $this->responseStatus);
        }
        return $this->responseStatus;
    }

    /**
     * Asset manager for distributed packages
     *
     * The first call on an asset package copies the package content from the vendor dir into the asset cache.
     * The content of the package is kept together.
     * The `patterns` parameter must be specified only at the first call. At subsequent calls it will be ignored.
     *
     * Finally, returns the valid web-accessible url path for the requested file.
     *
     * Composer install script clears the asset cache.
     *
     * @param string $package -- package root directory relative to the vendor dir or beginning with '/' indicates relative to the project root.
     * @param string $resource -- the resource to return
     * @param array|null $patterns -- optional pattern array (RegEx patterns to select files from the package path), see {@see Asset::matchPattern() }
     * @return string -- the valid url accessible by the client
     * @throws Exception
     */
    public function linkAssetFile(string $package, string $resource, ?array $patterns=null) {
        if(!$this->controller) throw new Exception('No controller is executed');
	    try {
	        if(!isset($this->controller->assets[$package])) {
					// Create a new asset package (copies files on init)
					$asset = new Asset([
						'path' => $package,
						'patterns' => $patterns,
					]);
					// Register the asset package
	            $this->controller->registerAsset($asset);
	        }
	        else $asset = $this->controller->assets[$package];
			return $asset->url($resource);
		}
		catch(Throwable $e) {
			App::log('error', "Error in asset '$package' at resource '$resource': {msg}", ['msg'=>$e->getMessage()]);
			return '/js/error.js?res='.urlencode("$package::$resource");
		}
    }

    /**
     * Returns a configured component or other property
     *
     * @param string $name the component or  property name
     *
     * @return mixed the component object or a property value
     * @throws Exception
     */
    public function __get($name) {
        if(array_key_exists($name, $this->_components)) return $this->_components[$name];
        return parent::__get($name);
    }

    public function hasComponent($name, $type=Component::class) {
        if(array_key_exists($name, $this->_components) && $this->_components[$name] instanceof $type) return $this->_components[$name];
        return null;
    }

    public function getComponents() {
        return $this->_components;
    }

    public static function cli($configFile) {
        try {
			if(!file_exists($configFile)) throw new Exception("Config file at '$configFile' is missing.");
            $config = include $configFile;
            defined('ENV') || define('ENV', $config['application_env'] ?? 'production');
            defined('ENV_DEV') || define('ENV_DEV', ENV != 'production');
            if(ENV_DEV) ini_set('display_errors', 'On');

            set_error_handler(function($severity, $errstr, $errfile, $errline) {
                $err = new ErrorException($errstr, 0, $severity, $errfile, $errline);
                AppHelper::showException($err);
                exit(500);
            });

            /** @var App $app */
            $app = App::create(['config'=>$config]);

            return $app->runCli();
        }
        catch(Throwable $e) {
            AppHelper::showException($e);
            return -1;
        }
    }

    public static function nameSpace() {
        return substr(static::class, 0, strrpos(static::class, '\\'));
    }

    /**
     * Returns the uid of the logged-in user or empty string if no user is logged in.
     *
     * @return mixed|string
     */
    public static function getUserId() {
        return App::$app->user ? App::$app->user->getUserId() : '';
    }

	/**
	 * This method is only introduced to avoid duplicating explanations in the source code.
	 * When used as shown below, a PHP runtime error will be raised for variables expected by a view that are not passed.
	 * Please note that such an error is automatically raised when the variable is explicitly referenced in the PHP view.
	 * However, in some cases, like when the variable is referenced in the PHP view but only in a JS <script> tag,
	 * the error message is not clear and the server output is messed up without any warning.
	 *     <script>console.log('<?= $undefinedVar ?>');</script>
	 * Therefore, this method helps prevent unnecessary headaches for the developer.
	 *
	 * Usage in a template/view file:
	 *
     *     assert($this->requireVars($var1, ..., $varN), ''); // $var<i> are variables expected by the view
     *                                                        // the use of assert() is to not impact production environment
	 *
	 * @param array $variables -- Variable list of PHP variables
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function requireVars(...$variables) {
		return true; // for assert() to succeed: see usage in documentation above
	}

	/**
	 * Localizes a messaget text using configured localization (l10n) class.
	 *
	 * Category syntax
	 * - umvc -- framework messages, located in the /vendor/uhi67/umvc/messages dir
	 * - avendor/alib -- library texts, located in the /vendor/avendor/alib/messages dir
	 * - avendor/alib/acat -- library category, located in the /vendor/avendor/alib/messages/acat dir
	 * - any/other -- application categories, depending on current l10n class (e.g. located in the /messages/any/other dir of the application)
	 * - app -- the default if none specified; depending on current l10n class (e.g. located in the /messages/app dir of the application)
	 *
	 * @param string $category
	 * @param string $message
	 * @param array $params
	 * @param string|null $locale
	 * @return string
	 * @throws Exception
	 */
	public static function l($category, $message, $params=[], $locale=null) {
		if(!static::$app) throw new Exception('Application is not initialized');
		if(!static::$app->hasComponent('l10n')) {
			if($params) $message = Apphelper::substitute($message, $params);
			return $message;
		}
		if(!$locale) $locale = static::$app->locale;
		return static::$app->l10n->getText($category, $message, $params, $locale);
	}
}
