<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;

use DateTime;
use IntlDateFormatter;

/**
 * L10nInterface
 * Interface for L10n functions
 *
 * You must provide your class for L10n functionality and configure classname in the config file at 'L10n' key.
 * Your class must provide getText and formatDate for UXApp::la and UXApp::fd functions
 * The current language and locale may be set and get via UXApp::setLocale/getLocale/getLang
 *
 * ### Configuration
 *
 * ```
 * 'l10n' => [
 * 		'class' => L10n::class,         // Use any class implementing L10NInterface
 * 		'uappDir' => $uapppath.'/def/translations', // Place of translation files. This is the default
 * 		'defaultLocale' => 'hu',		// Default language with optional locale, may be changed by UXApp::setLang(lang/locale)
 *      'supportedLocales' => ['hu'=>'Magyar', 'en'=>'English', 'en-US'], // Supported locales with optional name
 * 		'source' => 'hu',	            // Default source language, default is 'en'
 *      'param' => 'la',                // Language swith parameter
 *      'cookieName' => 'language',     // Cookie name for selected language if cookie is enabled
 * 		'cookieParams' => [],			// Optional cookie parameters
 * ],
 * ```
 *
 * @author uhi
 * @copyright 2020-2022
 *
 * @property-read string $locale
 * @property-read string $timeZone
 */
Interface L10NInterface extends ComponentInterface {
	/**
	 * Determines preferred user language/locale
	 *
	 * 1. request parameter
	 * 2. session
	 * 3. language cookie
	 * 4. HTTP Accept_Language
	 *
	 * @see UXApp::getLocale()
	 * @return string -- locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 */
	public function getUserLocale();

	/**
	 * Sets current locale by user will
	 *
	 * - Sets locale in session
	 * - Sets locale cookie
	 *
	 * If locale is not supported, locale not changes
	 *
	 * @see UXApp::setLocale()
	 * @param string $locale -- locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 * @return string|null -- the locale set (the supported value), null if not set.
	 */
	public function setUserLocale($locale);

	/**
	 * Returns first matching supported locale or false if not supported
	 *
	 * @param $locale -- language (ISO 639-1) or locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 * @param $strict -- if false, partly matching is enabled, e.g. 'en-GB' will supported by 'en-US'
	 * @return string -- locale in ll-CC format (ISO 639-1 && ISO 3166-1)
	 */
	public function isSupported($locale, $strict=false);

	/**
	 * Returns name of given or current locale
	 *
	 * @param string|null $locale
	 * @return string
	 */
	public function localeName($locale=null);

	/**
	 * localized system text
	 *
	 * @param string $category -- message category, the framework itself uses 'umvc'. Application default is 'app'
	 * @param string $source - source language text or text identifier
	 * @param array $params - replaces $var parameters
	 * @param integer $lang - language code or user default language (ll or ll-LL) to translate into
	 *
	 * @return string
	 */
	public function getText($category, $source, $params=NULL, $lang=null);

	/**
	 * formats a date for given locale
	 *
	 * @param DateTime $datetime
	 * @param int $datetype -- date format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param string $locale -- locale in ll-cc format (ISO 639-1 && ISO 3166-1)
	 * @return string
	 */
	public function formatDate($datetime, $datetype=IntlDateFormatter::SHORT, $locale=null);

	/**
	 * formats a date for given locale
	 *
	 * @param DateTime $datetime
	 * @param int $datetype -- date format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param int $timetype -- time format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
	 * @param string $locale -- locale in ll-cc format (ISO 639-1 && ISO 3166-1)
	 * @return string
	 */
	public function formatDateTime($datetime, $datetype=IntlDateFormatter::SHORT, $timetype=IntlDateFormatter::NONE, $locale=null);

	/**
	 * Translates an 'umvc' category text using built-in translation file in $dir
	 * Does not substitute parameters.
	 * If language definition does not exist, returns original without error.
	 * If specific text does not exist, returns original with an appended '*'.
	 *
	 * @param string $dir
	 * @param string $source -- text in original language
	 * @param string $lang -- language to translate to
	 *
	 * @return string
	 */
	public function getTextFile($dir, $source, $lang);

	/** @noinspection PhpUnused */

	/**
	 * Returns language code only
	 * @return string
	 */
	public function getLang();

	/**
	 * Retrieve the user-selected language from a cookie.
	 *
	 * @return string|null The selected language or null if unset, false if not supported.
	 */
	public function getLanguageCookie();

	/**
	 * Sets the given locale in a cookie.
	 * Does nothing if the locale is not supported, or the headers have already been sent.
	 *
	 * @param string $locale -- The locale set by the user.
	 * @return bool|null -- false if cookie is not set, null if cookie is not enabled or headers are sent.
	 */
	public function setLanguageCookie($locale);

	/**
	 * Returns country from locale. If locale is a language only, the language code is returned (lowercase)
	 *
	 * @param string $locale -- la or la-CC
	 *
	 * @return string
	 */
	public static function country($locale);
}
