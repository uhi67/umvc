<?php

namespace uhi67\umvc;

use Exception;

/**
 * Class Request
 *
 * @package uhi67\umvc
 * @property-read boolean $isGet
 */
class Request extends Component {
	/** @var string $request -- original full request uri */
	public $url;
	/** @var string -- base URL of the application's landing page (Default is auto-detected) */
	public $baseUrl;
	/** @var array $query -- get query variables */
	public $query;

	/**
	 * @throws Exception
	 */
	public function init() {
		if(!$this->url) $this->url = ArrayHelper::getValue($_SERVER, 'REQUEST_URI');

		// Determines original baseurl (canonic)
		if(!$this->baseUrl && $this->parent->baseUrl) $this->baseUrl = $this->parent->baseUrl;
		if(!$this->baseUrl && isset($_SERVER['HTTP_HOST'])) {
			$prot = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
			$prot = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? "https" : $prot;
			$this->baseUrl = $prot . "://" . $_SERVER['HTTP_HOST'] . ArrayHelper::getValue($_SERVER, 'SCRIPT_NAME', '');
			if(substr($this->baseUrl,-10)=='/index.php') $this->baseUrl = substr($this->baseUrl, 0, -10);
		}
		$this->baseUrl = trim($this->baseUrl, '/\\');
		if(!$this->query) $this->query = $_GET;
	}

	public function urlPart($part, $url=null) {
		if(!$url) $url = $this->url;
		return parse_url($url, $part);
	}

	/**
	 * Returns a value from the GET only, or all variables.
	 *
	 * @param string $name
	 * @param mixed $default
	 *
	 * @return array|mixed
	 */
	public function get($name=null, $default = null) {
		if($name===null) return $this->query;
		return ArrayHelper::getValue($this->query, $name, $default);
	}

	/**
	 * Returns a value from the POST only, or all variables.
	 *
	 * @param string $name
	 * @param mixed $default
	 *
	 * @return array|mixed
	 */
	public function post($name = null, $default = null) {
		if($name===null) return $_POST;
		return ArrayHelper::getValue($_POST, $name, $default);
	}

	/**
	 * Returns an uploaded file from the request
	 *
	 * Returns a value from previously stored query if present.
	 * This value can be set by Util::setReq()
	 *
	 * Returns array of FileUpload if request variable is an array variable[]
	 *
	 * @param string $name
	 *
	 * @return FileUpload|array|null
	 */
	public function file($name) {
		if(!isset($_FILES) || !isset($_FILES[$name])) return null;
		if(is_array($_FILES[$name])) {
			$result = [];
			if(is_array($_FILES[$name]['name'])) {
				foreach($_FILES[$name]['name'] as $fieldname => $filename) {
					$result[$fieldname] = FileUpload::createFromField($name, $fieldname);
				}
			}
			else {
				$result = FileUpload::createFromField($name);
			}
			return $result;
		} else $fileupload = FileUpload::createFromField($name);
		return $fileupload;
	}

	/**
	 * @inheritDoc
	 * @param string $name
	 * @return array|FileUpload|null
	 * @deprecated use file()
	 */
	public function getFile($name) { return $this->file($name); }

	/**
	 * Gets a variable from get or post (get first)
	 *
	 * @inheritDoc
	 */
	public function req($name=null, $default = null) {
		if($name===null) return null;
		$value = ArrayHelper::getValue($this->query, $name);
		if($value==='' || $value===null) return $this->post($name, $default);
		return $value;
	}

	/**
	 * Returns an integer value from request (GET or POST):
	 *
	 * - integer of value if not empty
	 * - null if value is empty and no default or default is null
	 * - default (as integer), if value is empty
	 *
	 * @inheritDoc
	 */
	public function reqInt($name, $default = null) {
		$value = $this->req($name, $default);
		if($value === '') $value = $default;
		if($value !== null) $value = (int)$value;
		return $value;
	}


	/**
	 * @inheritDoc
	 */
	public function reqArray($name, $default = []) {
		$result = $this->req($name, $default);
		if(!is_array($result)) return $default;
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function reqMultiOr($name, $default = 0) {
		$a = array_values($this->getArray($name));
		if(count($a) == 0) return null;
		$r = 0;
		/** @noinspection PhpStatementHasEmptyBodyInspection */
		for($i = 0; $i < count($a); $r |= $a[$i++]) ;
		return $r;
	}

	/**
	 * TODO: convert to only GET (next major version)
	 *
	 * Returns an integer value from request:
	 *
	 * - integer of value if not empty
	 * - null if value is empty and no default or default is null
	 * - default (as integer), if value is empty
	 *
	 * @inheritDoc
	 */
	public function getInt($name, $default = null) {
		$value = $this->req($name, $default);
		if($value === '') $value = $default;
		if($value !== null) $value = (int)$value;
		return $value;
	}

	/**
	 * TODO: convert to only GET (next major version)
	 *
	 * @inheritDoc
	 */
	public function getArray($name, $default = []) {
		$result = $this->req($name, $default);
		if(!is_array($result)) return $default;
		return $result;
	}

	/**
	 * TODO: convert to only GET (next major version)
	 *
	 * @inheritDoc
	 */
	public function getMultiOr($name, $default = 0) {
		$a = array_values($this->getArray($name));
		if(count($a) == 0) return null;
		$r = 0;
		/** @noinspection PhpStatementHasEmptyBodyInspection */
		for($i = 0; $i < count($a); $r |= $a[$i++]) ;
		return $r;
	}

	/**
	 * Returns an integer value from the POST
	 *
	 * - integer of value if not empty
	 * - null if value is empty and no default or default is null
	 * - default (as integer), if value is empty
	 *
	 * @inheritDoc
	 */
	public function postInt($name, $default = null) {
		$value = $this->post($name, $default);
		if($value === '') $value = $default;
		if($value !== null) $value = (int)$value;
		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function postArray($name, $default = []) {
		$result = $this->post($name, $default);
		if(!is_array($result)) return $default;
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function postMultiOr($name, $default = 0) {
		$a = array_values($this->postArray($name));
		if(count($a) == 0) return null;
		$r = 0;
		/** @noinspection PhpStatementHasEmptyBodyInspection */
		for($i = 0; $i < count($a); $r |= $a[$i++]) ;
		return $r;
	}

	/**
	 * Modifies a given or current URL with new parameter values
	 *
	 * @param string|null  $url -- url to modify
	 * @param array $params -- new parameter values
	 *
	 * @return string
	 */
	public static function modUrl($url=null, $params=[]) {
		if($url===null) $url = ArrayHelper::getValue($_SERVER, 'REQUEST_URI', '');
		$fragment = '';
		if(isset($params['#'])) {
			$fragment = $params['#'];
			unset($params['#']);
		}

		$u = parse_url($url);
		$base = (isset($u['scheme']) ? $u['scheme'].'://' : '') .
			(isset($u['user']) ? $u['user'] : '').
			(isset($u['pass']) ? ':' . $u['pass']  : '').
			(isset($u['host']) ? $u['host'] : '').
			(isset($u['port']) ? ':' . $u['port'] : '').
			(isset($u['path']) ? $u['path'] : '');
		$query = [];
		if(isset($u['query']))	parse_str($u['query'], $query);
		$params = array_filter(array_merge($query, $params), function($item) { return $item!== null; });
		return
			$base .
			(count($params) ? ('?' . http_build_query($params)) : '') .
			($fragment ? ('#' . $fragment) : '');
	}
    
    public function getIsGet() {
        return $_SERVER['REQUEST_METHOD']=='GET';
    }
}
