<?php

namespace uhi67\umvc;

use Exception;

/**
 * HTTP related stuff
 *
 * HTTP response constants and status texts are borrowed from Symfony\Component\HttpFoundation\Response
 * (c) Fabien Potencier <fabien@symfony.com>
 */
class HTTP {
    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_PROCESSING = 102;            // RFC2518
    public const HTTP_EARLY_HINTS = 103;           // RFC8297
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTI_STATUS = 207;          // RFC4918
    public const HTTP_ALREADY_REPORTED = 208;      // RFC5842
    public const HTTP_IM_USED = 226;               // RFC3229
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_RESERVED = 306;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENTLY_REDIRECT = 308;  // RFC7238
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LONG = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_EXPECTATION_FAILED = 417;
    public const HTTP_I_AM_A_TEAPOT = 418;                                               // RFC2324
    public const HTTP_MISDIRECTED_REQUEST = 421;                                         // RFC7540
    public const HTTP_UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    public const HTTP_LOCKED = 423;                                                      // RFC4918
    public const HTTP_FAILED_DEPENDENCY = 424;                                           // RFC4918
    public const HTTP_TOO_EARLY = 425;                                                   // RFC-ietf-httpbis-replay-04
    public const HTTP_UPGRADE_REQUIRED = 426;                                            // RFC2817
    public const HTTP_PRECONDITION_REQUIRED = 428;                                       // RFC6585
    public const HTTP_TOO_MANY_REQUESTS = 429;                                           // RFC6585
    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    public const HTTP_INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    public const HTTP_LOOP_DETECTED = 508;                                               // RFC5842
    public const HTTP_NOT_EXTENDED = 510;                                                // RFC2774
    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;                             // RFC6585

    /**
     * Status codes translation table.
     *
     * The list of codes is complete according to the
     * {@link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml Hypertext Transfer Protocol (HTTP) Status Code Registry}
     * (last updated 2021-10-01).
     *
     * Unless otherwise noted, the status code is defined in RFC2616.
     *
     * @var array
     */
    public static $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',                                           // RFC-ietf-httpbis-semantics
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Content',                                       // RFC-ietf-httpbis-semantics
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Too Early',                                                   // RFC-ietf-httpbis-replay-04
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',                                     // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];

	/**
	 * Set a cookie.
	 *
	 * @param string $name The name of the cookie.
	 * @param string|NULL $value The value of the cookie. Set to NULL to delete the cookie.
	 * @param array|NULL $params Cookie parameters.
	 * @param bool $throw Whether to throw exception if setcookie() fails.
	 *
	 * @return bool -- false if $throw is false and cookie cannot be set
	 *
	 * @throws Exception -- If any parameter has an incorrect type.
	 *
	 * @author Andjelko Horvat
	 * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
	 */
	public static function setCookie($name, $value, $params = null, $throw = true) {
		if (!(is_string($name) && // $name must be a string
			(is_string($value) || is_null($value)) && // $value can be a string or null
			(is_array($params) || is_null($params)) && // $params can be an array or null
			is_bool($throw)) // $throw must be boolean
		) {
			throw new Exception('Invalid input parameters.');
		}

		$default_params = [
			'lifetime' => 0,
			'expire'   => null,
			'path'     => '/',
			'domain'   => null,
			'secure'   => false,
			'httponly' => true,
			'raw'      => false,
			'samesite' => null,
		];

		if ($params !== null) {
			$params = array_merge($default_params, $params);
		} else {
			$params = $default_params;
		}

		// Do not set secure cookie if not on HTTPS
		if ($params['secure'] && !self::isHTTPS()) {
			if ($throw) {
				throw new Exception(
					'Cannot set cookie: Setting secure cookie on plain HTTP is not allowed.'
				);
			}
			App::$app->log('warning', 'Error setting cookie: setting secure cookie on plain HTTP is not allowed.', ['tags'=>'umvc']);
			return false;
		}

		if ($value === null) {
			$expire = time() - 365 * 24 * 60 * 60;
			$value = strval($value);
		} elseif (isset($params['expire'])) {
			$expire = intval($params['expire']);
		} elseif ($params['lifetime'] === 0) {
			$expire = 0;
		} else {
			$expire = time() + intval($params['lifetime']);
		}

		if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
			/* use the new options array for PHP >= 7.3 */
			if ($params['raw']) {
				/** @psalm-suppress InvalidArgument  Remove when Psalm >= 3.4.10 */
				$success = @setrawcookie(
					$name,
					$value,
					[
						'expires' => $expire,
						'path' => $params['path'],
						'domain' => $params['domain'],
						'secure' => $params['secure'],
						'httponly' => $params['httponly'],
						'samesite' => $params['samesite'],
					]
				);
			} else {
				/** @psalm-suppress InvalidArgument  Remove when Psalm >= 3.4.10 */
				$success = @setcookie(
					$name,
					$value,
					[
						'expires' => $expire,
						'path' => $params['path'],
						'domain' => $params['domain'],
						'secure' => $params['secure'],
						'httponly' => $params['httponly'],
						'samesite' => $params['samesite'],
					]
				);
			}
		} else {
			/* in older versions of PHP we need a nasty hack to set RFC6265bis SameSite attribute */
			if ($params['samesite'] !== null and !preg_match('/;\s+samesite/i', $params['path'])) {
				$params['path'] .= '; SameSite='.$params['samesite'];
			}
			if ($params['raw']) {
				$success = @setrawcookie(
					$name,
					$value,
					$expire,
					$params['path'],
					$params['domain'],
					$params['secure'],
					$params['httponly']
				);
			} else {
				$success = @setcookie(
					$name,
					$value,
					$expire,
					$params['path'],
					$params['domain'],
					$params['secure'],
					$params['httponly']
				);
			}
		}

		if (!$success) {
			if ($throw) {
				throw new Exception('Cannot set cookie: headers already sent.');
			}
			App::log('error', 'Error setting cookie: headers already sent.', 'umvc');
		}
		return $success;
	}

	/**
	 * Returns value of the named cookie or the default value if cookie does not exist
	 *
	 * @param string $name
	 * @param string $default
	 *
	 * @return string|null
	 */
	public static function getCookie($name, $default=null) {
		if(!array_key_exists($name, $_COOKIE)) return $default;
		return $_COOKIE[$name];
	}

	/**
	 * This function checks if the http call used HTTPS protocol.
	 *
	 * @return boolean -- true if the HTTPS is used, false otherwise.
	 */
	public static function isHTTPS() {
		return strncmp(self::getSelfURL(), 'https://', 8) == 0;
	}

	/**
	 * The actual url of current request can be determined in two ways:
	 *
	 * 1. baseurl specified in the application config
	 * 2. Compute from php environment
	 *
	 * Behind a reverse proxy terminating https, use the following pattern in the config.
	 * Make sure to set `HTTPS` environment variable if the external protocol is https.
	 * ```
	 * $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") ? 'http' : 'https';
	 * $config = [
	 *      'baseurl' => $scheme.'://'.$_SERVER['SERVER_NAME'].'/virtualpath/',
	 * ```
	 *
	 * @return string
	 */
	public static function getSelfURL() {
		$baseurl = App::$app->urlPath;
		if (!empty($baseurl)) {
			$protocol = parse_url($baseurl, PHP_URL_SCHEME);
			$hostname = parse_url($baseurl, PHP_URL_HOST);
			$port = parse_url($baseurl, PHP_URL_PORT);
			$port = !empty($port) ? ':'.$port : '';
		} else {
			// no baseurl specified for app, just use the current URL
			$protocol = 'http';
			$protocol .= (self::getServerHTTPS()) ? 's' : '';
			$hostname = self::getServerHost();
			$port = self::getServerPort();
		}
		return $protocol.'://'.$hostname.$port.$_SERVER['REQUEST_URI'];
	}

	/**
	 * Retrieve HTTPS status from $_SERVER environment variables.
	 *
	 * @return boolean True if the request was performed through HTTPS, false otherwise.
	 *
	 * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
	 */
	public static function getServerHTTPS()
	{
		if (!array_key_exists('HTTPS', $_SERVER)) {
			// not an https-request
			return false;
		}

		if ($_SERVER['HTTPS'] === 'off') {
			// IIS with HTTPS off
			return false;
		}

		// otherwise, HTTPS will be non-empty
		return !empty($_SERVER['HTTPS']);
	}

	/**
	 * Retrieve the port number from $_SERVER environment variables.
	 *
	 * @return string The port number prepended by a colon, if it is different from the default port for the protocol
	 *     (80 for HTTP, 443 for HTTPS), or an empty string otherwise.
	 *
	 * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
	 */
	public static function getServerPort()
	{
		$default_port = self::getServerHTTPS() ? '443' : '80';
		$port = $_SERVER['SERVER_PORT'] ?? $default_port;

		// Take care of edge-case where SERVER_PORT is an integer
		$port = strval($port);

		if ($port !== $default_port) {
			return ':'.$port;
		}
		return '';
	}

	/**
	 * Retrieve Host value from $_SERVER environment variables.
	 *
	 * @return string The current host name, including the port if needed. It will use localhost when unable to
	 *     determine the current host.
	 *
	 * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
	 */
	private static function getServerHost() {
		if (array_key_exists('HTTP_HOST', $_SERVER)) {
			$current = $_SERVER['HTTP_HOST'];
		} elseif (array_key_exists('SERVER_NAME', $_SERVER)) {
			$current = $_SERVER['SERVER_NAME'];
		} else {
			// almost certainly not what you want, but...
			$current = 'localhost';
		}

		if (strstr($current, ":")) {
			$decomposed = explode(":", $current) ?: [];
			$port = array_pop($decomposed);
			if (!is_numeric($port)) {
				array_push($decomposed, $port);
			}
			$current = implode(":", $decomposed);
		}
		return $current;
	}

	/**
	 * This function parses the Accept-Language HTTP header and returns an associative array with each language and the
	 * score for that language. If a language includes a region, then the result will include both the language with
	 * the region and the language without the region.
	 *
	 * The returned array will be in the same order as the input.
	 *
	 * @return array An associative array with each language and the score for that language.
	 *
	 * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
	 */
	public static function getAcceptLanguage() {
		if (!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
			// no Accept-Language header, return an empty set
			return [];
		}

		$languages = explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));

		$ret = [];

		foreach ($languages as $l) {
			$opts = explode(';', $l);

			$l = trim(array_shift($opts)); // the language is the first element

			$q = 1.0;

			// iterate over all options, and check for the quality option
			foreach ($opts as $o) {
				$o = explode('=', $o);
				if (count($o) < 2) {
					// skip option with no value
					continue;
				}

				$name = trim($o[0]);
				$value = trim($o[1]);

				if ($name === 'q') {
					$q = (float) $value;
				}
			}

			// remove the old key to ensure that the element is added to the end
			unset($ret[$l]);

			// set the quality in the result
			$ret[$l] = $q;

			if (strpos($l, '-')) {
				// the language includes a region part

				// extract the language without the region
				$l = explode('-', $l);
				$l = $l[0];

				// add this language to the result (unless it is defined already)
				if (!array_key_exists($l, $ret)) {
					$ret[$l] = $q;
				}
			}
		}
		return $ret;
	}
}
