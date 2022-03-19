<?php

/**
 * The Helper namespace contains classes needed to connect the application framework to the codecept functional test.
 * Functional tests cannot be used without a framework module.
 */
namespace Helper;

use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use Codeception\Lib\Connector\Shared\PhpSuperGlobalsConverter;
use Codeception\Util\Debug;
use Exception;
use PDO;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

/**
 * Class AppConnector
 *
 * @package UMVC Simple Application Framework
 */
class AppConnector extends AbstractBrowser {
    use PhpSuperGlobalsConverter;

    /** @var array the config array of App application */
    public $appConfig;

    /** @var string $sapi -- Framework module sets to 'cli' for unit suite based on yml */
    public $sapi;

    public $defaultServerVars = [];

    /** @var array */
    public $headers;
    public $statusCode;

    /** @var App */
    public $app;

    /** @var PDO  */
    public static $db;

    /** @var array $session -- the content of the $_SESSION */
    public $session;

	/**
	 * @return App
	 * @throws Exception
	 */
    public function getApplication() {
        if (!isset($this->app)) {
            $this->startApp($this->sapi);
        }
        return $this->app;
    }

    /**
     * Called only in functional tests, before and after a request
     */
    public function resetApplication() {
        Debug::debug('_resetting App');
        $this->app->headers = [];
        $this->app->responseStatus = 200;
        $this->app->path = null;
        $this->app->url = null;
        $this->app->query = null;
        $this->app->baseUrl = null;
    }

	/**
	 * @param string $sapi
	 *
	 * @throws Exception
	 */
	public function startApp($sapi = 'apache') {
        putenv('SIMPLESAMLPHP_CONFIG_DIR='.dirname(__DIR__, 5).'/config/saml/config');
        putenv('SERVER_PORT=80');
        $_SERVER['HTTP_HOST'] = 'mvc.test';

		if(static::$db && !(is_object(static::$db) && static::$db instanceof PDO)) throw new Exception('invalid connection resource');
		if($this->app) {
			$this->app->init();
			return;
		}
		$this->app = new App(['config' => $this->appConfig, 'sapi'=>$sapi]);
    }

	public function destroyApplication() {
		$this->app = null;
		App::$app = null;
	}

    public function resetPersistentVars() {
        static::$db = null;
        // TODO: reset uploaded file (later)
    }

	/**
	 *
	 * @param Request $request
	 *
	 * @return Response
	 * @throws Exception
	 */
    public function doRequest($request) {
        $this->resetApplication();
		$_COOKIE = $request->getCookies();
        $_SERVER = $request->getServer();
        $this->restoreServerVars();
        $_FILES = $this->remapFiles($request->getFiles());
        $_REQUEST = $this->remapRequestParameters($request->getParameters());
        $_POST = $_GET = [];
        $_SESSION = $this->session ?? [];

        if (strtoupper($request->getMethod()) === 'GET') {
            $_GET = $_REQUEST;
        } else {
            $_POST = $_REQUEST;
        }

        $uri = $request->getUri();
        $pathString = parse_url($uri, PHP_URL_PATH);
        $queryString = parse_url($uri, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $queryString === null ? $pathString : $pathString . '?' . $queryString;
        $_SERVER['REQUEST_METHOD'] = strtoupper($request->getMethod());
        parse_str($queryString, $params);
        foreach ($params as $k => $v) {
            $_GET[$k] = $v;
        }

		$this->headers    = [];
		$this->statusCode = null;

		ob_start();
		$this->app = $this->getApplication();
        Debug::debug('# Session is '.json_encode($_SESSION));

        try {
            // Grab statusCode. Note: Status code is caught only if headers are sent via sendHeader() method
            $this->statusCode = $this->app->run();
        }
        catch(Exception $e) {
            Debug::debug('# AppConnector caught Exception: '.$e->getMessage().' in file '.$e->getFile(). ' at line '.$e->getLine());
            Debug::debug("# AppConnector backtrace: \n".$e->getTraceAsString());
        }
        $this->session = $_SESSION;
		$content = ob_get_clean();

        // Grab issued headers as well. Note: Only headers sent via sendHeader() method are grabbed
        $this->headers = $this->app->headers;

		// save headers and $content into _output dir
        $fileName = AppHelper::underscore($this->app->url);
        if(strlen($fileName)>96) $fileName = substr($fileName,0,64).'.'.md5($fileName);
		file_put_contents($this->app->basePath.'/tests/_output/headers_'.$fileName.'.json', json_encode($this->headers));
		file_put_contents($this->app->basePath.'/tests/_output/content_'.$fileName.'.html', $content);

        // Reset app after run
        $this->resetApplication();

        return new Response($content, $this->statusCode, $this->headers);
    }

    public function restoreServerVars()
    {
        $this->server = $this->defaultServerVars;
        foreach ($this->server as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }
}
