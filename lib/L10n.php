<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use DateTime;
use Exception;
use IntlDateFormatter;

/**
 * L10n
 * Template (and base and default class) for L10n functions
 *
 * You must provide your class for L10n functionality and configure classname in the config file at 'L10n' key.
 * Your class must provide getText and formatDate for App::l and App::fd functions
 * The current language and locale may be set and get via App::setLocale/getLocale/getLang
 *
 * This template does not use database, and does not translate any text.
 * Uses IntlDateFormatter for formatting dates.
 *
 * ### Configuration
 *
 * ```
 * 'l10n' => [
 * 		'class' => L10n::class,
 * 		'defaultLocale' => 'hu',		// Default language with optional locale, may be changed by App::setLocale(lang/locale)
 *      'supportedLocales' => ['hu'=>'Magyar', 'en'=>'English', 'en-US'], // Supported locales with optional name
 * 		'source' => 'hu',	            // Default source language, default is 'en'
 *      'param' => 'la',                // Language swith parameter
 *      'cookieName' => 'language',     // Cookie name for selected language if cookie is enabled
 * 		'cookieParams' => [],			// Optional cookie parameters
 * ],
 * ```
 * Available and default cookie parameters see at {@see L10n::$cookieParams}
 *
 * ### Translation file format
 *
 * ```
 * return array(
 * 		'original text' => 'eredeti szöveg',
 * 		...
 * );
 * ```
 *
 * @author uhi
 * @copyright 2011 - 2022
 *
 * @property string $locale -- Current language with optional locale
 * @property-read string $lang
 */
class L10n extends Component {
	private static $_messages;
	/** @var string $defaultLocale -- Default language with optional locale in ll-CC format (ISO 639-1 && ISO 3166-1) Default value is locale */
	public $defaultLocale;
	/** @var array $supportedLocales -- Supported languages with optional locale e.g. ['hu', 'en'=>'English', 'en-US']. Default is {@see $defaultLocale}, or ['en'] */
	public $supportedLocales;
	/** @var string $source -- Source language, default is 'en' */
	public $source;
	/** @var string $switchParam -- Request parameter to change language -- no auto change if skipped */
	public $switchParam;
	/** @var string $cookieName -- Cookie name to store selected language -- no cookie if empty */
	public $cookieName;
	/** @var array $cookieParams -- Cookie parameters. Default exists */
	public $cookieParams = [
		'lifetime' => 60 * 60 * 24 * 366, 	// in sec, default is one year
		'domain'   => null,					// enabled domain, null is not limited.
		'path'     => '/',
		'secure'   => false,
		'httponly' => false,
		'samesite' => null,
	];

	/**
	 * {@inheritdoc}
	 * @throws Exception
	 */
	public function prepare() {
		if (!$this->defaultLocale && $this->locale) {
			$this->defaultLocale = $this->locale;
		}
		if (!$this->supportedLocales) {
			$this->supportedLocales = $this->defaultLocale ? [$this->defaultLocale] : ['en'];
		}
		// Canonize to [locale => name, ...] format
		foreach($this->supportedLocales as $key => $name) {
			if(is_int($key)) {
				unset($this->supportedLocales[$key]);
				$this->supportedLocales[$name] = $name;
			}
		}

		if($this->switchParam && ($locale = App::$app->request->req($this->switchParam))) $this->setUserLocale($locale);

		if(!$this->source) $this->source = App::$app->source_locale;

		// Set (global!) locale and ensure it is supported
		if(!$this->locale) $this->locale = $this->getUserLocale();
		if($this->supportedLocales && !$this->isSupported($this->locale)) $this->locale = null;
		if(!$this->locale) {
			$sli = array_keys($this->supportedLocales);
			$this->locale = $sli[0] ?? 'en-GB';
		}
	}

	/**
	 * Determines preferred user language/locale
	 *
	 * 1. request parameter
	 * 2. session
	 * 3. language cookie
	 * 4. HTTP Accept_Language
	 *
	 * @return string -- locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 * @throws
	 * @see App::getLocale()
	 */
	public function getUserLocale() {
		// 1. request parameter
		if($this->switchParam && ($la = App::$app->request->req($this->switchParam)) && ($la = $this->isSupported($la))) {
			return $la;
		}

		// 2. session
		if(App::$app->session && ($la = App::$app->session->get('language')) && ($la = $this->isSupported($la))) {
			return $la;
		}

		// 3. language cookie
		if(($la = $this->getLanguageCookie()) && ($la = $this->isSupported($la))) {
			return $la;
		}

		// 4. HTTP Accept_Language
		if($la = static::getHTTPLanguage()) {
			return $la;
		}

		// 5. Application locale
		if($la = App::$app->locale) {
			return $la;
		}

		return $this->defaultLocale;
	}

	/**
	 * Sets current locale by user will
	 *
	 * - Sets locale in session
	 * - Sets locale cookie
	 *
	 * If locale is not supported, locale not changes
	 *
	 * @param string $locale -- locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 * @return string|null -- the locale set (the supported value), null if not set.
	 * @throws Exception
	 * @see App::$locale
	 */
	public function setUserLocale($locale) {
		if(!($locale = $this->isSupported($locale))) return null;
		$this->locale = $locale;
		$_SESSION['umxc_locale'] = $locale;
		$this->setLanguageCookie($locale);
		return $locale;
	}

	/**
	 * Returns first matching supported locale or false if not supported
	 *
	 * @param $locale -- language (ISO 639-1) or locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 * @param $strict -- if false, partly matching is enabled, e.g. 'en-GB' will supported by 'en-US'
	 * @return string|false -- locale in ll-CC format (ISO 639-1 && ISO 3166-1) or false if not found
	 * @throws Exception
	 */
	public function isSupported($locale, $strict=false) {
		if(!is_string($locale)) throw new Exception('Locale must be a string');
		if(!$locale) return false;
		if(array_key_exists($locale, $this->supportedLocales)) return $locale;
		if(strlen($locale)>2) {
			if($strict) return false;
			$locale = substr($locale,0,2);
		}
		if(array_key_exists($locale, $this->supportedLocales)) return $locale;
		foreach ($this->supportedLocales as $key=>$name) {
			if(substr($key,0,2)==$locale) return $key;
		}
		return false;
	}

	/**
	 * Returns name of given or current locale
	 *
	 * @param string|null $locale
	 * @return string
	 * @throws Exception
	 */
	public function localeName($locale=null) {
		if($locale===null) $locale = $this->locale;
		return $this->supportedLocales[$this->isSupported($locale)] ?? null;
	}

	/**
	 * Localizes a text.
	 * This default implementation translates only framework texts ('umvc' category), all other texts are returned without change.
	 *
	 * @param string $category -- message category, the framework itself uses 'umvc'. Application default is 'app'
	 * @param string $source - source language text or text identifier
	 * @param array $params - replaces {$var} parameters
	 * @param integer $lang - language code (ll or ll-LL) to translate into. Default is the language set in the framework
	 *
	 * @return string
	 */
	public function getText($category, $source, $params=NULL, $lang=null) {
		if($category=='umvc') {
			if(!$lang) $lang = App::$app->locale;
			$text = static::getTextFile($category, dirname(__DIR__).'/messages', $source, $lang);
		} else {
			// Default is shortcut solution
			$text = $source;
		}
		// substitute parameters
        if($params && !is_array($params)) $params = [$params];
		if($params) $text = AppHelper::substitute($text, $params);
		return $text;
	}

	/**
	 * formats a date for given locale
	 *
	 * @param DateTime $datetime
	 * @param int $datetype -- date format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param string $locale -- locale in ll-cc format (ISO 639-1 && ISO 3166-1)
	 * @return string
	 */
	public function formatDate($datetime, $datetype=IntlDateFormatter::SHORT, $locale=null) {
		return $this->formatDateTime($datetime, $datetype, IntlDateFormatter::NONE, $locale);
	}

	/**
	 * formats a date for given locale
	 *
	 * @param DateTime $datetime
	 * @param int $datetype -- date format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param int $timetype -- time format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param string $locale -- locale in ll-cc format (ISO 639-1 && ISO 3166-1)
	 * @return string
	 */
	public function formatDateTime($datetime, $datetype=IntlDateFormatter::SHORT, $timetype=IntlDateFormatter::NONE, $locale=null) {
		return $this->formatDateTime($datetime, $datetype, $timetype, $locale);
	}

	/**
	 * Translates a text using a translation file in the given $dir.
	 * Does not substitute parameters.
	 * If the language definition file does not exist, returns original with appended '**'.
	 * If the specific text does not exist in the file, returns original with an appended '*'.
	 *
	 * @param string $cat
	 * @param string $dir -- the directory of the language files (e.g. a category directory) without trailing '/'
	 * @param string $source -- text in original language
	 * @param string $lang -- language to translate to
	 * @param string|null $originalLanguage -- the source language (if translation is missing to this target, no warning is issued)
	 * @return string
	 */
	public function getTextFile($cat, $dir, $source, $lang, $originalLanguage=null) {
		if(!self::$_messages) self::$_messages = [];
		if(!isset(self::$_messages[$cat])) self::$_messages[$cat] = [];
		if(!isset(self::$_messages[$cat][$lang])) {
			$la = $lang;
			if(strlen($lang)==5 && !file_exists($dir.'/'.$lang.'.php')) $la = substr($lang,0,2);
			$messagefile = $dir.'/'.$la.'.php';
			if(!file_exists($messagefile)) {
				if($originalLanguage && substr($lang,0,2) == substr($originalLanguage,0,2)) return $source;
				App::log('warning', "Translation file is missing: `$messagefile`");
				return $source.'**';
			}
			self::$_messages[$cat][$lang] = require $messagefile;
		}

		if(!isset(self::$_messages[$cat][$lang][$source])) return $source.'*';
		return self::$_messages[$cat][$lang][$source];
	}


	/** @noinspection PhpUnused */

	/**
	 * Returns language code only
	 * @return string
	 */
	public function getLang() {
		if(!$this->locale) $this->locale = $this->getUserLocale();
		return $this->locale ? substr($this->locale, 0, 2) : $this->locale;
	}

	/**
	 * Retrieve the user-selected language from a cookie.
	 *
	 * @return string|null The selected language or null if unset, false if not supported.
	 * @throws Exception
	 */
	public function getLanguageCookie() {
		$cookieName = $this->cookieName;
		if(!$cookieName) return null;
		if(!isset($_COOKIE[$cookieName])) return null;
		$locale = strtolower((string) $_COOKIE[$cookieName]);
		return $this->isSupported($locale);
	}


	/**
	 * Sets the given locale in a cookie.
	 * Does nothing if the locale is not supported, or the headers have already been sent.
	 *
	 * @param string $locale -- The locale set by the user.
	 *
	 * @return bool|null -- false if cookie is not set, null if cookie is not enabled or headers are sent.
	 * @throws
	 */
	public function setLanguageCookie($locale) {
		$cookieName = $this->cookieName;
		if(!$cookieName || headers_sent()) return null;
		if(!($locale = $this->isSupported($locale))) return false;
		return Http::setCookie($cookieName, $locale, $this->cookieParams, false);
	}

	/**
	 * This method returns the preferred language for the user based on the Accept-Language HTTP header.
	 *
	 * @return string|null The preferred language based on the Accept-Language HTTP header,
	 * or null if none of the languages in the header is available.
	 * @throws Exception
	 */
	private function getHTTPLanguage() {
		$localeScore = Http::getAcceptLanguage(); // [locale => score,...]

		$bestLocale = null;
		$bestScore = -1.0;

		foreach ($localeScore as $locale => $score) {
			$locale = $this->isSupported($locale);
			if(!$locale) continue;

			/* Some user agents use very limited precision of the quality value, but order the elements in descending
			 * order. Therefore, we rely on the order of the output from getAcceptLanguage() matching the order of the
			 * languages in the header when two languages have the same quality.
			 */
			if ($score > $bestScore) {
				$bestLocale = $locale;
				$bestScore = $score;
			}
		}
		return $bestLocale;
	}

	/**
	 * Returns country from locale. If locale is a language only, the language code is returned (lowercase)
	 *
	 * @param string $locale -- la or la-CC
	 *
	 * @return string
	 */
	public static function country($locale) {
		if(strlen($locale)<5) return substr($locale,0,2);
		return substr($locale,3,2);
	}

	public function getLocale() {
		return App::$app->locale;
	}

	public function setLocale($locale) {
		App::$app->locale = $locale;
	}
}
