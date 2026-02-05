<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnused */

/** Session class -- a simple basic session handler */

namespace uhi67\umvc;

use Exception;
use Psr\Log\LogLevel;

/**
 * # Class Session
 *
 * ###Session configuration example for `config.php`
 *
 * ```php
 * 'session' => [
 *     'class' => Session::class,
 *     'name' => 'sess_sample',
 *     'lifetime' => 1800,
 *     'cookie_path' => '',
 *     'cookie_domain' => 'sample.hu',
 * ],
 * ```
 * @property-read int $id
 */
class Session extends Component
{
    /** @var string */
    public string $name = 'umvc_app';
    /** @var int */
    public int $lifetime = 1800;
    /** @var string */
    public string $cookie_path = '/';
    /** @var string|bool */
    public string|bool|null $cookie_domain = null;

    /**
     * @throws Exception
     */
    public function prepare(): void
    {
        if ($this->cookie_domain === true && $this->parent instanceof App) {
            $this->cookie_domain = parse_url($this->parent->urlPath ?? '', PHP_URL_HOST);
        }
        if (App::isCLI()) {
            return;
        }
        ini_set("session.gc_maxlifetime", $this->lifetime + 900);
        ini_set("session.lifetime", $this->lifetime);
        ini_set("session.gc_probability", "100");
        ini_set('session.use_strict_mode', true);
        session_set_cookie_params(0, $this->cookie_path, $this->cookie_domain);
        session_name($this->name);
        session_cache_limiter('private_no_expire');
        if (headers_sent($file, $line)) {
            throw new Exception("Cannot start session, headers already sent in $file:$line");
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            throw new Exception('Session is already active.');
        }

        if (!static::is_started() && !session_start()) {
            App::log(LogLevel::ERROR, 'Error starting session');
        } else {
            App::logInner(LogLevel::DEBUG, 'Session is started');
        }
    }

    public function expired(): false
    {
        return false;
    }

    /**
     * @throws Exception
     */
    function __destruct()
    {
        #$this->finish();
    }

    /**
     */
    function finish(): void
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
            unset($_SESSION);
        }
    }

    function log($str): void
    {
        App::log(LogLevel::DEBUG, $str);
    }

    public static function is_started(): bool
    {
        if (!App::isCLI()) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                return session_status() === PHP_SESSION_ACTIVE;
            } else {
                return !(session_id() === '');
            }
        }
        return false;
    }

    public function getId(): false|string
    {
        return session_id();
    }

    public static function unserialize($session_data): array
    {
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
    public static function strvars($s): false|array
    {
        $a = [];
        while ($s) {
            if (str_starts_with($s, '!')) {
                $s = substr($s, 1);
                $var = static::gettoch($s, '|');
                $a[$var] = '';
            } else {
                $var = static::gettoch($s, '|');
                $a[$var] = static::getvarvalue($s);
            }
        }
        return $a;
    }

    public static function gettoch(&$s, $l): string
    {
        $p = strpos($s, $l);
        if ($p === false) {
            $s = '';
            return $s;
        }
        $w = substr($s, 0, $p);
        $s = substr($s, $p + strlen($l));
        return $w;
    }

    public static function getch(&$s): string
    {
        if ($s == '') {
            return '';
        }
        $w = substr($s, 0, 1);
        $s = substr($s, 1);
        return $w;
    }

    /**
     * Returns a value from the string (part of session parser)
     *
     * @param $s --
     * @return array|string
     */
    private static function getvarvalue(&$s): array|string
    {
        $t = static::getch($s); // type
        switch ($t) {
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
                for ($i = 0; $i < $n; $i++) {
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
     * @param string|null $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function get(string $name = null, mixed $default = null): mixed
    {
        if (!isset($_SESSION)) {
            return null;
        }
        if ($name === null) {
            return $_SESSION;
        }
        return ArrayHelper::getValue($_SESSION, $name, $default);
    }

    /**
     * Returns integer or default (default null)
     *
     * - integer of value if not empty
     * - null if the value is empty and no default or default is null
     * - default (as integer), if the value is empty
     *
     * @param null $name
     * @param null $default
     *
     * @return int|null
     */
    public function getInt($name = null, $default = null): ?int
    {
        $value = $this->get($name, $default);
        if ($value === '') {
            $value = null;
        }
        return $value === null ? $default : (int)$value;
    }

    public function set($name, $value): void
    {
        $_SESSION[$name] = $value;
    }

    public function empty(): void
    {
        $_SESSION = [];
    }
}
