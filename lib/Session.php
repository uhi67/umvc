<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;
use Exception;
use Psr\Log\LogLevel;

/**
 * # Class BaseSession
 *
 * Session handler must be descendant of this.
 *
 * ###Session configuration example for `config.php`
 *
 * ```php
 * 	'session' => [
 * 	'name' => 'sess_sample',
 * 	'lifetime' => 1800,
 * 	'cookie_path' => '',
 * 	'cookie_domain' => 'sample.hu',
 * 	'logfile' => $datapath.'/session.log',
 * 	'db' => $db, // A DBX connection object
 *	'tableName' => 'session',
 *  '' =>
 * ],
 * ```
 * @property-read int $id
 */
class Session extends Component {
	/** @var string */
	public $name = 'umvc_app';
	/** @var int */
	public $lifetime = 1800;
	/** @var string */
	public $cookie_path = '';
	/** @var string|bool */
	public $cookie_domain;
	/** @var string */
	public $logfile;

	/**
	 * @throws Exception
	 */
	public function prepare() {
		if($this->cookie_domain === true && $this->parent instanceof App) $this->cookie_domain = parse_url($this->parent->currentUrl, PHP_URL_HOST);
		if(App::isCLI()) return;
		ini_set("session.gc_maxlifetime", $this->lifetime + 900);
		ini_set("session.lifetime", $this->lifetime);
		ini_set("session.gc_probability", "100");
		session_set_cookie_params(0, $this->cookie_path, $this->cookie_domain);
		session_name($this->name);
		session_cache_limiter('private_no_expire');
		if(headers_sent($file, $line)) throw new Exception("Cannot start session, headers already sent in $file:$line");
		if(session_status() == PHP_SESSION_ACTIVE) throw new Exception('Session is already active.');

		if(!static::is_started() && !session_start()) static::log('Error starting session');
		else static::log('Session is started');
	}

	public function expired() {
		return false;
	}

	/**
	 * @throws Exception
	 */
	function __destruct() {
		#$this->finish();
	}

	/**
	 */
	function finish() {
		if(session_status() == PHP_SESSION_ACTIVE) {
			session_write_close();
			unset($_SESSION);
		}
	}

	function log($str) {
		App::log(LogLevel::DEBUG, $str);
	}

	public static function is_started() {
		if(App::isCLI()) {
			if(version_compare(phpversion(), '5.4.0', '>=')) {
				return session_status() === PHP_SESSION_ACTIVE;
			} else {
				return !(session_id() === '');
			}
		}
		return FALSE;
	}

	public function getId() {
		return session_id();
	}

	public static function unserialize($session_data) {
		/* save current session */
		$current_session = session_encode();
		$_SESSION = [];
		session_decode($session_data);
		$result = $_SESSION;
		/* restore original session */
		session_decode($current_session);
		return $result;
	}

	/*
		SESSION variables PARSER
		variable:
			!<name>
			<name>|<variablevalue>
		variablevalue:
			N;
			i:<integer>;
			s:<integer>:"<stringvalue>";
			a:<integer>:{<variablevalue>[;<variablevalue>...]}
	*/
	public static function strvars($s) {
		$a = [];
		while($s) {
			if(substr($s, 0, 1) == '!') {
				$s = substr($s, 1);
				$var = static::gettoch($s, '|');
				if($var === false) return false;
				$a[$var] = '';
			} else {
				$var = static::gettoch($s, '|');
				if($var === false) return false;
				$a[$var] = static::getvarvalue($s);
			}
		}
		return $a;
	}

	public static function gettoch(&$s, $l) {
		#trace("sp_gettoch('$l', '$s')");flush();
		$p = strpos($s, $l);
		if($p === false) {
			$s = '';
			return $s;
		}
		$w = substr($s, 0, $p);
		$s = substr($s, $p + strlen($l));
		return $w;
	}

	public static function getch(&$s) {
		#trace("sp_getch(&$s)");flush();
		if($s == '') return '';
		$w = substr($s, 0, 1);
		$s = substr($s, 1);
		return $w;
	}

	/**
	 * Returns a value from the string (part of session parser)
	 *
	 * @param $s --
	 * @return array|false|string
	 */
	private static function getvarvalue(&$s) {
		$t = static::getch($s); // type
		switch($t) {
			case 'N':
			{
				static::gettoch($s, ';'); // closing ';'
				$v = "NULL";
				break;
			}
			case 's':
			{
				static::getch($s);           // type-closing ':'
				static::gettoch($s, ':"'); // size
				$v = static::gettoch($s, '";');  // value
				break;
			}
			case 'i':
			{
				static::getch($s);           // type-closing ':'
				$v = static::gettoch($s, ';');  // value
				break;
			}
			case 'a':
			{
				static::getch($s);           // type-closing ':'
				$n = static::gettoch($s, ':{'); // size
				// elements
				$v = [];
				for($i = 0; $i < $n; $i++) {
					$v[] = static::getvarvalue($s);
				}
				static::gettoch($s, '}');  // remainder
				break;
			}
			default:
				$v = $t;
				break;
		}
		return $v;
	}

	/**
	 * Returns value of a session variable or default if not defined
	 *
	 * @param string $name
	 * @param mixed $default
	 *
	 * @return mixed|null
	 */
	public function get($name = null, $default = null) {
		if(!isset($_SESSION)) return null;
		if($name === null) return $_SESSION;
		return ArrayHelper::getValue($_SESSION, $name, $default);
	}

	/**
	 * Returns integer, or default (default null)
	 *
	 * - integer of value if not empty
	 * - null if value is empty and no default or default is null
	 * - default (as integer), if value is empty
	 *
	 * @param null $name
	 * @param null $default
	 *
	 * @return int|null
	 */
	public function getInt($name = null, $default = null) {
		$value = $this->get($name, $default);
		if($value==='') $value = null;
		return $value===null ? $default : (int)$value;
	}

	public function set($name, $value) {
		$_SESSION[$name] = $value;
	}
}
