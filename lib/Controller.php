<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;

/**
 * Represents a function in the application.
 * The App dispatcher will run the proper action of the selected Controller class.
 *
 * All main function's path in the application must be mapped to a Controller class named `<function>Controller`.
 * The `action<Action>` methods are mapped to the function action, e.g. CRUD action names.
 *
 * **Example:**
 * - Suppose the HTTP request is /user/create
 * - The dispatcher finds the UserController class, creates an instance of it, and invokes its go() method
 * - The UserController finds the actionCreate() method, based on the remainder of the path (`/create`)
 * - The actionCreate() method performs the desired function
 *
 * ### Most important properties and methods:
 * - app: the application instance that invoked this controller
 * - path: the path portion passed to the controller (controller name already shifted out)
 * - query: the query part of the original request, as an associative array
 * - beforeAction(): Invoked before each action. if you define it, it must return true to enable the current action.
 * - csvResponse(): generates a CSV-format response from the give dataset
 * - jsonResponse(): generates a JSON-format response from the give dataset
 * - jsonErrorResponse(): generates a JSON-formatted error response (HTTP status is still 200 in this case)
 * - render(): same as ->app->render();
 *
 * @package UMVC Simple Application Framework
 */
class Controller extends BaseController
{
    /** @var Asset[] $assets -- registered assets indexed by name */
    public array $assets = [];

    public function init(): void {
        $this->registerAssets();
    }

    /**
     * Descendant classes must override and register asset packages here.
     * The default implementation is empty.
     * @return void
     */
    public function registerAssets() {
        // This function is intentionally empty. Descendants need not call it.
    }

    /**
     * Outputs a JSON response
     *
     * @param object|array $data -- array or object containing the output data. Null is not permitted, use empty array for empty data
     * @param array $headers -- more custom headers to send
     * @return int
     * @throws Exception -- if the response is not a valid data to convert to JSON.
     */
    public function jsonResponse(object|array $data, array $headers=[]): int {
        foreach ($headers as $header) $this->app->sendHeader($header);
        $this->app->sendHeader('Content-Type: application/json');
        $result = json_encode($data);
        if(!$result) throw new Exception('Invalid data');
        echo $result;
        return 0;
    }

    /**
     * Outputs a CSV response.
     * The data must be a 2-dim array containing plain text.
     * If a header line is needed, it must be in the data.
     *
     * @param array[] $models -- array containing the output data. Null is not permitted, use empty array for empty data
     * @param array $headers -- more custom headers to send
     * @return int
     * @throws Exception -- if the response is not a valid data to convert to JSON.
     */
    public function csvResponse(array $models, array $headers=[]): int {
        foreach ($headers as $header) $this->app->sendHeader($header);
        $this->app->sendHeader('Content-Type: application/csv; charset=UTF-8');
        $this->app->sendHeader("Cache-Control: no-cache, must-revalidate");  // HTTP/1.1

        if(empty($models)) {
            echo "No results.", PHP_EOL;
        }
        else {
            $s = fopen('php://output', 'w');
            fputs($s, chr(239) . chr(187) . chr(191)); // UTF8_BOM, unless Excel doesn't recognize UTF8 characters
            foreach($models as $model) {
               fputcsv($s, array_values($model), ';');
            }
            fclose($s);
        }
        return 0;
    }

    /**
     * Returns an error response
     *
     * @param string|mixed $error -- The error message (can be another structure)
     * @return int
     * @throws Exception
     */
    public function jsonErrorResponse(mixed $error): int {
        return $this->jsonResponse([
            'success' => false,
            'error' => $error,
        ]);
    }

    /**
     * Returns an error response
     *
     * @param string $error -- The error message
     * @param string $format -- HTML: displays a HTML error page; JSON: returns a JSON error response
     * @return int
     * @throws Exception -- in case of HTML (Exception will be caught and displayed as HTML)
     */
    public function errorResponse(string $error, string $format='HTML'): int {
        if($format=='JSON') return $this->jsonErrorResponse($error);
        throw new Exception($error);
    }

    /**
     * ## A shortcut for app->render with optional localized view selection.
     *
     * **Definitions of localized views:**
     * - default view: the original view path without localization, e.g 'main/index'
     * - localized view: the view path with locale code, e.g. 'main/en/index' or 'main/en-GB/index' whichever fits better.
     * - locale can be an ISO 639-1 language code ('en') optionally extended with a ISO 3166-1-a2 region ('en-GB')
     *
     * **Rules for locale and language codes**
     * - If current locale is 'en-GB', the path with 'en-GB' is preferred, otherwise 'en' is used.
     * - If current locale is 'en', the path with 'en' is used, no any 'en-*' is recognized.
     * - If current locale is 'en-US', the path with 'en-US' is preferred, but no other 'en-*' is used.
     *
     * **Locale selection:**
     * - true/null: use current locale if the localized view exists, otherwise use the default view
     * - false: do not use localized view, even if exists. If the default view does not exist, an exception occurs.
     * - explicit locale: use the specified locale, as defined at 'true' case.
     *
     * @param string $viewName -- basename of a php view-file in the `views` directory, without extension and without localization code
     * @param array $params -- parameters to assign to variables used in the view
     * @param string|null $layout -- the layout applied to this render after the view rendered. If false, no layout will be applied.
     * @param array $layoutParams -- optional parameters for the layout view
     * @param bool|string|null $locale -- use localized layout selection (ISO 639-1 language / ISO 3166-1-a2 locale), see above
     *
     * @return false|string
     * @throws Exception
     */
    public function render(string $viewName, array $params=[], string $layout=null, array $layoutParams=[], bool|string $locale=null): false|string {
	    if($locale === null || $locale===true) $locale = $this->app->locale;
		if($locale) {
			// Priority order: 1. Localized view (with long or short locale) / 2. untranslated / 3. default-locale view (long/short)
			$lv = $this->localizedView($viewName, $locale);
			if(!$lv && !($this->app->viewFile($viewName) && file_exists($this->app->viewFile($viewName)))) {
				$lv = $this->localizedView($viewName, App::$app->source_locale);
			}
			if($lv) $viewName = $lv;
		}
        return $this->app->render($viewName, $params, $layout, $layoutParams);
    }

	/**
	 * Returns localized view name using long or short locale. Checks if the view file exists.
	 * Returns null if none of them exists.
	 *
	 * @param string $viewName
	 * @param string $locale
	 * @return string|null
	 * @throws Exception
	 */
	private function localizedView(string $viewName, string $locale): ?string {
		// Look up view file using full locale
		$lv = $this->localizedViewName($viewName, $locale);
		if ($this->app->viewFile($lv) && file_exists($this->app->viewFile($lv))) return $lv;
		// Look up view file using short language code
		$lv = $this->localizedViewName($viewName, substr($locale,0,2));
		if($this->app->viewFile($lv) && file_exists($this->app->viewFile($lv))) return $lv;
		return null;
	}

	private function localizedViewName($viewName, $locale): string {
		$p = strrpos($viewName, '/');
		if($p===false) $p = -1;
		return substr($viewName,0, $p+1) . $locale . '/'.substr($viewName, $p+1);
	}

    /**
     * @param Asset $asset
     * @return void
     */
    public function registerAsset(Asset $asset): void {
        $this->assets[$asset->name] = $asset;
    }

    /**
     * Link registered assets (optionally filtered by extensions)
     *
     * @param string|string[]|null $extensions -- extension name(s), e.g. 'css', default is null == all extensions
     * @return string -- the generated html code
     * @throws Exception
     */
    public function linkAssets(array|string $extensions=null): string {
        $html = '';
        foreach($this->assets as $asset) {
            foreach($asset->files as $file) {
                // Iterate file pattern in the cache (use extension filter)
                Asset::matchPattern($asset->dir, '', $file, function($file) use($asset, $extensions, &$html) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if(!$extensions || in_array($ext, (array)$extensions)) {
                        $html .= match ($ext) {
                            'css' => Html::link([
                                'rel' => 'stylesheet',
                                'href' => $asset->url($file)
                            ]),
                            'js' => Html::tag('script', '', [
                                'src' => $asset->url($file)
                            ]),
                            default => throw new Exception("Unknown asset extension `$ext`"),
                        };
                    }
                });
            }


        }

        return $html;
    }

}
