<?php
namespace uhi67\umvc;
use Exception;

/**
 * L10n (Localization is 12 letter long)
 * File-based solution for L10n functions
 *
 * Uses php files in translation directory for translations
 * Uses default L10n for formatting dates.
 *
 * ### Configuration
 *
 * ```
 * 'l10n' => array(
 * 		'class' => L10nFile::class,
 * 		'dir' => $defpath.'/translations', // Place of app translation files. This is the default
 * 		'defaultLocale' => 'hu',		// Default language with optional locale, may be changed by App::setLang(lang/locale)
 *      'supportedLocales' => ['hu'=>'Magyar', 'en'=>'English', 'en-US'], // Supported locales with optional name
 * 		'source' => 'hu',		// Default source language, default is 'en'
 *      'param' => 'la',                // Language swith parameter
 *      'cookieName' => 'language',     // Cookie name for selected language if cookie is enabled
 * 		'cookieParams' => [],			// Optional cookie parameters
 * ),
 * ```
 *
 * ### Translation file format
 *
 * *File name is 'category/target_lang.php'*
 *
 * ```
 * return array(
 * 		'original text' => 'eredeti szöveg',
 * 		...
 * );
 * ```
 *
 * @author uhi
 * @package umvc
 * @copyright 2017-2022
 */

class L10nFile extends L10n {
	/** @var string $dir -- directory of translation files in form 'la.php', default is def/traslations */
	public $dir;
	/** @var string|array $source -- Default source language, default is 'en'; or source languages for categories */
	public $source;

	/**
	 * {@inheritdoc}
	 * @throws Exception
	 */
	public function prepare() {
		parent::prepare();
		if($this->dir) {
			if(substr($this->dir,-1)!='/') $this->dir .= '/';
		}
		else {
			$this->dir = UXApp::$app->defPath.'/translations/';
		}
		if(!Util::makeDir($this->dir,2)) throw new UXAppException('Configuration error: directory does not exists for translation class.', $this->dir);
	}

	/**
	 * Translates a piece of text from source language to given language.
	 *
	 * 'umvc' category is translated by parent class, all others from files in the configured directory.
	 * If translation is not found, returns source*
	 *
	 * @param string $category -- message category, the framework uses 'umvc'. Application default is 'app'
	 * @param string $source - source language text or text identifier
	 * @param array $params - replaces {$var} parameters
	 * @param integer $lang - target language code (ll or ll-LL) to translate into. Default is the language set in the framework
	 *
	 * @return string
	 */
	public function getText($category, $source, $params=NULL, $lang=null) {
		if($lang===null) $lang = $this->lang;
		if($category=='umvc') return parent::getText($category, $source, $params, $lang);

		$source_lang = is_array($this->source) ? ArrayUtils::getValue($this->source, $category) : $this->source;

		$text = $source_lang == $lang && !is_int($source)? $source : static::getTextFile($this->dir.$category.'/', $source, $lang);

		// substitute parameters
        if($params) {
        	if(!is_array($params)) $params = [$params];
        	$text = Util::substitute($text, $params);
       	}
		return $text;
	}
}
