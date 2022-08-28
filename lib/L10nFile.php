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
			$this->dir = rtrim($this->dir, '/');
		}
		else {
			$this->dir = App::$app->basePath.'/messages';
		}
		if(!is_dir($this->dir)) throw new Exception('Message directory does not exists: '.$this->dir);
	}

	/**
	 * Translates a text from source language to given language.
	 *
	 * If category directory is not found, returns source**.
	 * If translation is not found, returns source*.
	 *
	 * @param string $category -- message category, the framework itself uses 'umvc'. Application default is 'app'
	 * @param string $source - source language text or text identifier
	 * @param array $params - replaces {$var} parameters
	 * @param integer $lang - target language code (ll or ll-LL) to translate into. Default is the language set in the framework
	 *
	 * @return string
	 */
	public function getText($category, $source, $params=NULL, $lang=null) {
		if($lang===null) $lang = $this->lang;

		$directory = $this->directory($category);
		if(!is_dir($directory)) return $source.'***'; // The category does not exist

		$text = static::getTextFile($category, $directory, $source, $lang);

		// substitute parameters
		if($params) {
			if(!is_array($params)) $params = [$params];
			$text = AppHelper::substitute($text, $params);
		}
		return $text;
	}

	/**
	 * Returns the directory for the category.
	 * If the directory is not found in the app,
	 * - 'umvc' and 'umvc/*' will refer to the framework directory,
	 * - 'vendor/lib/*' categories will refer to the corresponding library (~/messages/*)
	 *
	 * @param string $category
	 * @return string
	 */
	public function directory($category) {
		$category = str_replace('..', '.', $category); // prevent backstep in the directory tree
		$directory = $this->dir.'/'.$category;
		if(!is_dir($directory)) {
			$cats = explode('/', $category);
			if($cats[0]=='umvc') {
				$directory = dirname(__DIR__).'/messages';
				array_shift($cats);
				if(count($cats)>0) $directory .= '/'.implode('/', $cats);
			}
			elseif(count($cats)>=2) {
				$directory = dirname(__DIR__).'/vendor/'.array_shift($cats).'/'.array_shift($cats).'/messages';
				if(count($cats)>0) $directory .= '/'.implode('/', $cats);
			}
		}
		return $directory;
	}
}
