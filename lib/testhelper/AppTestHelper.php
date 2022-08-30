<?php

namespace Helper;

use Codeception\Lib\Framework;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use uhi67\umvc\App;
use uhi67\umvc\Model;
use Codeception\Configuration;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\PHPUnit\Constraint\JsonContains;
use Codeception\Step;
use Codeception\TestInterface;
use Codeception\Util\Debug;
use Exception;
use PHPUnit\Framework\Assert;
use ReflectionException;

/**
 * # Test helper for App
 *
 * ### Using:
 *
 * 1. Create a file named 'AppHelper.php' in your project's tests/_support/Helper folder referring to this:
 *
 * `require_once dirname(__DIR__, 3) . '/vendor/uhi67/umvc/testhelper/AppTestHelper.php';`
 *
 * 2. Include in your `...suite.yml` as:
 *
 * ```
 * modules:
 *   enabled:
 *     - \Helper\AppHelper:
 *           configFile: 'tests/_data/testConfig.php'
 * ```
 *
 * @property AppConnector $client
 * @package UMVC Simple Application Framework
 */
class AppTestHelper extends Framework {
	protected $requiredFields = ['configFile'];
	protected $config = ['loader'=>'core', 'sapi'=>'apache']; // optional parameters and defaults of module

    /** @var App $app -- the application instance started by client */
	public $app;
    /** @var string $configFile -- path of your configFile */
    protected $configFile;
    /** @var array $appConfig -- config of your App application */
    protected $appConfig;

	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * @throws ModuleConfigException
	 * @throws ModuleException
	 */
	public function _initialize() {
		$this->configFile = Configuration::projectDir() . $this->config['configFile'];
		if (!is_file($this->configFile)) {
			throw new ModuleConfigException(
			    __CLASS__,
			    "The application config file does not exist: " . $this->configFile
			);
		}
		$this->appConfig = require($this->configFile);
		if(!is_array($this->appConfig)) throw new ModuleException(__CLASS__, "The App test config is invalid: `$this->configFile` (may be return is missing)");
		$this->appConfig['params']['testsuite'] = $this->config;

        defined('ENV') || define('ENV', $this->appConfig['application_env'] ?? 'production');
        defined('ENV_DEV') || define('ENV_DEV', ENV != 'production');
	}

	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * Run before each test
	 *
	 * @param TestInterface $test
	 *
	 * @throws Exception
	 */
	public function _before(TestInterface $test) {
		$this->client = new AppConnector([]);
		$this->client->appConfig = $this->appConfig;
		$this->client->sapi = $this->config['sapi'];
		$this->app = $this->client->getApplication();
	}

	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * @param TestInterface $test
	 */
	public function _after(TestInterface $test) {
        $_SESSION = [];
        $_FILES = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];

		if($this->app) {
			Debug::debug('Destroying application');
			$this->client->destroyApplication();
		}
		else {
			Debug::debug('Application instance not found');
		}

		parent::_after($test);
	}

	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * @param array $settings
	 *
	 * @throws Exception
	 */
	public function _beforeSuite($settings = []) {
		Debug::debug('_beforeSuite');
	}


	/** @noinspection PhpMethodNamingConventionInspection */

	public function _afterSuite() {
		Debug::debug('_afterSuite');
	}


	/** @noinspection PhpMethodNamingConventionInspection */

	public function _beforeStep(Step $step) {

	}


	/** @noinspection PhpMethodNamingConventionInspection */

    /**
     * Called only in functional tests after $I->... steps
     */
	public function _afterStep(Step $step) {
        Debug::debug('_afterStep');
        Debug::debug('# Session is '.json_encode($_SESSION));
    }


	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * @param TestInterface $test
	 * @param $fail
	 *
	 */
	public function _failed(TestInterface $test, $fail) {
		Debug::debug('_failed');
	}


	/** @noinspection PhpMethodNamingConventionInspection */

	public function _cleanup() {
		Debug::debug('_cleanup');
	}

	/**
	 * To support to use the behavior of urlManager component
	 * for the methods like this: amOnPage(), sendAjaxRequest(), etc.
	 *
	 * @param $method
	 * @param $uri
	 * @param array $parameters
	 * @param array $files
	 * @param array $server
	 * @param string|null $content
	 * @param bool $changeHistory
	 *
	 * @return Crawler
	 * @throws Exception
	 */
	protected function clientRequest($method, $uri, array $parameters = [], array $files = [], array $server = [], $content = null, $changeHistory = true): Crawler
	{
		if (is_array($uri)) {
			$uri = $this->app->createUrl($uri);
		}
		return parent::clientRequest($method, $uri, $parameters, $files, $server, $content, $changeHistory);
	}

	/**
	 * @param int|array|null $userid -- null to log out
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function amLoggedInAs($userid=null) {
        if(!$this->app->hasComponent('auth')) throw new Exception('No user authentication component is configured');
		if($userid===null) $this->app->logout();
		else {
            $this->app->auth->login($userid);
        }
	}

    /**
     * @throws Exception
     */
    public function amLoggedOut() {
        $this->app->logout();
    }

	/**
	 * Inserts record into the database.
	 *
	 * ``` php
	 * <?php
	 * $user_id = $I->haveRecord('User', ['uid'=>'dilbert@test.test', 'displayname' => 'Dilbert']);
	 * ?>
	 * ```
	 *
	 * @param string $model - model classname
	 * @param array $attributes
	 *
	 * @return boolean
	 * @throws Exception
	 */
	public function haveRecord($model, $attributes = []) {
		if(!is_a($model, Model::class, true)) throw new Exception('Invalid model name '.$model);
		/** @var Model $record */
		$record = new $model($attributes);
		return $record->save();
	}

	/**
	 * Checks that record exists in database.
	 *
	 * ``` php
	 * $I->seeRecord('User', ['name' => 'Dilbert']);
	 * ```
	 *
	 * @param       $model
	 * @param array $attributes
	 *
	 * @throws Exception
	 */
	public function seeRecord($model, $attributes = []) {
		$this->assertNotNull($this->app->connection);
	    $record = $this->findRecord($model, $attributes);
	    if (!$record) {
	        $this->fail("Couldn't find $model with " . json_encode($attributes));
	    }
	    $this->debugSection($model, json_encode($record));
	}

	/**
	 * Checks that record does not exist in database.
	 *
	 * ``` php
	 * $I->dontSeeRecord('app\models\User', ['name' => 'Dilbert']);
	 * ```
	 *
	 * @param       $model
	 * @param array $attributes
	 *
	 * @part orm
	 * @throws Exception
	 */
	public function dontSeeRecord($model, $attributes = [])
	{
	    $record = $this->findRecord($model, $attributes);
	    $this->debugSection($model, json_encode($record));
	    if ($record) {
	        $this->fail("Unexpectedly managed to find $model with " . json_encode($attributes));
	    }
	}

	/**
	 * Retrieves record from database
	 *
	 * ``` php
	 * $category = $I->grabRecord('app\models\User', ['name' => 'Dilbert']);
	 * ```
	 *
	 * @param       $model
	 * @param array $attributes
	 *
	 * @return mixed
	 * @part orm
	 * @throws Exception
	 */
	public function grabRecord($model, $attributes = []) {
	    return $this->findRecord($model, $attributes);
	}

	/**
	 * @throws Exception
	 */
	protected function findRecord($model, $attributes = [])	{
	    if(!is_a($model, Model::class, true)) throw new Exception("Class `$model` is not a Model");
	    return call_user_func([$model, 'getOne'], $attributes, $this->app->connection);
	}

	/**
	 * @param string|array $page
	 */
	public function amOnPage($page) {
		parent::amOnPage($page);
	}

    /**
     * Request a page using POST (without submitting an actual form)
     *
     * @param string|array $page
     * @param array $params -- POST parameters
     */
    public function sendPostRequest($page, $params=[]) {
        $this->_loadPage('POST', $page, $params);
    }

    public function seeInSession($key, $value=null) {
		$this->assertArrayHasKey($key, $this->client->session);
		if($value!==null) $this->assertEquals($value, $this->client->session[$key]);
	}

    public function seeHttpHeader($key, $value=null) {
        $key = strtolower($key);
        foreach($this->client->headers as $header) {
            Debug::debug('Header: '.$header);
            [$k, $v] = explode(' ', $header);
            $k = strtolower($k);
            if($k==$key || $k==$key.':') {
                if(!$value) return;
                if($v!=$value) $this->fail("Response header '$key' contains value '$v' instead of expected '$value'");
                return;
            }
        }
        $this->fail("Response does not contain header '$key'");
    }

    /**
     * Checks whether last response was valid JSON.
     * This is done with json_last_error function.
     */
    public function seeResponseIsJson(): void
    {
        /** @var Response $response */
        $response = $this->client->getResponse();
        $responseContent = $response->getContent();
        Assert::assertNotEquals('', $responseContent, 'response is empty');
        $this->decodeAndValidateJson($responseContent);
    }

    /**
     * Converts string to json and asserts that no error occurred while decoding.
     *
     * @param string $jsonString the json encoded string
     * @param string $errorFormat optional string for custom sprintf format
     */
    protected function decodeAndValidateJson(string $jsonString, string $errorFormat="Invalid json: %s. System message: %s.")
    {
        $json = json_decode($jsonString);
        $errorCode = json_last_error();
        $errorMessage = json_last_error_msg();
        Assert::assertSame(
            JSON_ERROR_NONE,
            $errorCode,
            sprintf(
                $errorFormat,
                $jsonString,
                $errorMessage
            )
        );
        return $json;
    }

    /**
     * Checks whether the last JSON response contains provided array.
     * The response is converted to array with json_decode($response, true)
     * Thus, JSON is represented by associative array.
     * This method matches that response array contains provided array.
     *
     * Examples:
     *
     * ``` php
     * <?php
     * // response: {name: john, email: john@gmail.com}
     * $I->seeResponseContainsJson(array('name' => 'john'));
     *
     * // response {user: john, profile: { email: john@gmail.com }}
     * $I->seeResponseContainsJson(array('email' => 'john@gmail.com'));
     *
     * ```
     *
     * This method recursively checks if one array can be found inside another.
     *
     * @param array $json
     */
    public function seeResponseContainsJson($json = []): void
    {
        /** @var Response $response */
        $response = $this->client->getResponse();
        $responseContent = $response->getContent();
        Assert::assertThat(
            $responseContent,
            new JsonContains($json)
        );
    }

}
