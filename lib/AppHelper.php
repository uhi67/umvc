<?php /** @noinspection PhpIllegalPsrClassPathInspection */

/** @noinspection PhpUnused */

namespace uhi67\umvc;

use Closure;
use DateTime;
use Error;
use Exception;
use IntlDateFormatter;
use PHPUnit\Event\Code\Throwable;

/**
 * Class for various static helper functions for the framework and the application
 *
 * @package UMVC Simple Application Framework
 */
class AppHelper {

    /**
     * Function to generate random string.
     */
    public static function randomString($n): string {

        $generated_string = "";

        $domain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";

        $len = strlen($domain);

        // Loop to create random string
        for ($i = 0; $i < $n; $i++) {
            // Generate a random index to pick characters
            $index = rand(0, $len - 1);

            // Concatenating the character
            // in resultant string
            $generated_string = $generated_string . $domain[$index];
        }

        return $generated_string;
    }

    /**
     *
     */
    public static function getSecureRandomToken(): string {
        return bin2hex(openssl_random_pseudo_bytes(16));
    }

    /**
     *
     */
    public static function clean_input($data): string {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data);
    }

    /**
     * to prevent xss
     */
    public static function xss_clean($string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES);
    }

    /**
     * Truncates a string at the first occurrence of a breakpoint string after a
     * minimum number of bytes (see strlen()). If the operation succeeds, the
     * truncated string padded with padding characters is returned, otherwise
     * the input string is returned as is with the particularity that null gets
     * truncated to the empty string.
     *
     * @param string|null $string The string to truncate. Returns '' on null.
     * @param int $threshold The minimum number of bytes in string after which
     *                       truncating can occur.
     * @param string $break The breakpoint string for truncating.
     * @param string $pad The padding string.
     * @return string
     * @see strlen()
     *
     */
    public static function truncate(?string $string, int $threshold, string $break = '.', string $pad = '...'): string {
        if ($string === null) return '';

        $stringLen = strlen($string);
        if ($stringLen > $threshold) {
            if (false !== ($breakpoint = strpos($string, $break, $threshold))) {
                if ($breakpoint < $stringLen - 1) {
                    $string = substr($string, 0, $breakpoint) . $pad;
                }
            }
        }
        return $string;
    }

    /**
     * Returns a date formatted.
     *
     * @param string|null $str The date to format. -- Returns '' on null or failure
     * @param string $fmt The format expected for the date.
     * @return string
     */
    public static function format_date(?string $str, string $fmt): string {
        $date = ($str === null) ? false : date_create($str); // we set false because that's what date_create() returns on failure
        return ($date === false) ? '' : date_format($date, $fmt);
    }

    /**
     * Displays an Exception on CLI or HTML output.
     *
     * @param Exception|Throwable|Error $e
     * @param int|null $responseStatus -- HTTP response status, default is 500=HTTP_INTERNAL_SERVER_ERROR
     */
    static function showException(Exception|Throwable|Error $e, int $responseStatus = null): void {
        defined('ENV_DEV') || define('ENV_DEV', 'production');
        $responseStatus = $responseStatus ?: HTTP::HTTP_INTERNAL_SERVER_ERROR;
        $title = HTTP::$statusTexts[$responseStatus] ?? 'Internal application error';

        if (App::isCLI()) {

            $msg = "[$responseStatus] $title: " . $e->getMessage();
            $details = sprintf(" in file '%s' at line '%d'", $e->getFile(), $e->getLine());
            echo Ansi::color($msg, 'light red'), $details, PHP_EOL;
            if (ENV_DEV) {
                $trace = explode(PHP_EOL, $e->getTraceAsString());
                $baseurl = dirname(__DIR__);
                foreach ($trace as $line) {
                    $basepos = strpos($line, $baseurl);
                    $color = ($basepos > 1 && $basepos < 5) ? 'brown' : 'red';
                    echo Ansi::color($line, $color), PHP_EOL;
                }

                while ($e = $e->getPrevious()) {
                    $message = sprintf("%s in file '%s' at line '%d'", html_entity_decode($e->getMessage()), $e->getFile(), $e->getLine());
                    echo Ansi::color(PHP_EOL . PHP_EOL . $message, 'light purple') . PHP_EOL;
                    $trace = explode(PHP_EOL, $e->getTraceAsString());
                    foreach ($trace as $line) {
                        $basepos = strpos($line, $baseurl);
                        $color = ($basepos > 1 && $basepos < 5) ? 'brown' : 'red';
                        echo Ansi::color($line, $color), PHP_EOL;
                    }
                }
            }
            return;
        }
        $errorMessage = (ENV_DEV || $e instanceof UserException) ? $e->getMessage() : 'Something went wrong';
        if (!headers_sent()) http_response_code($responseStatus ?? 500);
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        echo '<html lang="en">';
        echo "<head><title>$title - UMVC</title>";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        echo '</head><body>';
        echo "<h1>$responseStatus $title</h1>";
        echo '<p>Oops, the page cannot be displayed :-(</p>';
        $details = sprintf(" in file '%s' at line '%d'", $e->getFile(), $e->getLine());
        echo '<div><b>' . htmlspecialchars($errorMessage) . '</b>' . (ENV_DEV ? $details : '') . '</div>';

        if (ENV_DEV) {
            $basePath = dirname(__DIR__, 4);
            echo '<pre>';
            echo preg_replace(
                [
                    '~' . str_replace(['\\', '~'], ['\\\\', '\~'], $basePath) . '~',
                    '/^(#\\d+ [^(]+)(\\\\vendor\\\\uhi67\\\\)(umvc)(\\\\[^(]+)(.*)$/m',
                    '/^(#\\d+ [^(]+)(\\\\views\\\\[^(]+)(.*)$/m',
                    '/^(#\\d+ [^(]+)(\\\\controllers\\\\[^(]+)(.*)$/m',
                    '/^(#\\d+ [^(]+)(\\\\models\\\\[^(]+)(.*)$/m',
                    '/^(#\\d+ [^(]+)(\\\\lib\\\\[^(]+)(.*)$/m',
                ],
                [
                    '...',
                    '<span style="color:gray">$1$3</span><span style="color:#333">$4</span><span style="color:gray">$5</span>',
                    '<span style="color:gray">$1</span><span style="color:maroon">$2</span>$3',
                    '<span style="color:gray">$1</span><span style="color:blue">$2</span>$3',
                    '<span style="color:gray">$1</span><span style="color:darkgreen">$2</span>$3',
                    '<span style="color:gray">$1</span><span style="color:saddlebrown">$2</span>$3',
                ],
                htmlspecialchars($e->getTraceAsString())
            );
            while ($e = $e->getPrevious()) {
                $message = sprintf("<b>%s</b> in file '%s' at line '%d'", htmlspecialchars($e->getMessage()), $e->getFile(), $e->getLine());
                echo PHP_EOL, PHP_EOL, $message, PHP_EOL;
                echo htmlspecialchars($e->getTraceAsString());
            }
            echo '</pre>';
        }
        if (ENV_DEV) {
            echo AppHelper::debug();
        }
        echo '</body>';
    }

    public static function debug(): string {
        $content = '';
        if (ENV_DEV) {
            $content = '<div class="debug container dismissable">';
            if (isset($_SESSION)) {
                $content .= '<h3>SESSION</h3><table class="table">';
                foreach ($_SESSION as $key => $value) {
                    $content .= "<tr><th>$key</th><td>" . print_r($value, true) . "</td></tr>";
                }
                $content .= '</table>';
            }
            if (isset($_POST) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $content .= '<h3>POST</h3><table class="table">';
                foreach ($_POST as $key => $value) {
                    $content .= "<tr><th>$key</th><td><pre>" . print_r($value, true) . "</pre></td></tr>";
                }
                $content .= '</table>';
            }
            $content .= '</div>';
        }
        return $content;
    }

    /**
     * Camelizes a string.
     *
     * All world will begin with uppercase character.
     * World delimiters are: ' ', '_', '-', '.', '\'
     *
     * @return string|null The camelized string
     */
    public static function camelize($id): ?string {
        if (is_null($id)) return null;
        return strtr(ucwords(strtr($id, ['_' => ' ', '.' => '_ ', '\\' => '_ ', '-' => ' '])), [' ' => '']);
    }

    /**
     * Converts a string to human-readable form, e.g. for an auto-generated field label
     *
     * Redundant '_id' or 'Id' postfix will be eliminated.
     *
     * @return string|null The camelized string
     */
    public static function humanize($id): ?string {
        if (is_null($id)) return null;
        return static::mb_ucwords(preg_replace('~[_.-]~', ' ', preg_replace('/_id$/', '', static::underscore(static::camelize($id)))));
    }

    /**
     * Converts a (camelized) string to underscore format.
     * Existing underscore ($separator) will be converted to '.'.
     * Replaces all non-name character to _.
     *
     * The result string should be appropriate for a filename or a Model attribute name (using _)
     *
     * Example: 'MyClass' --> 'my_class'
     * But: 'MyClass_id' --> 'my_class.id'
     *
     * If you want to keep existing separators, call camelize first.
     *
     * @param string|null $id -- an identifier in CamelCase
     * @param string $separator -- the separator character to be used between words, default is '_'
     * @return string|null The underscored string, e.g. camel_case
     */
    public static function underscore(?string $id, string $separator = '_'): ?string {
        if (is_null($id)) return null;
        $id = preg_replace('/[^A-Za-z\d.' . $separator . ']+/', $separator, $id);
        $id = preg_replace(['/([A-Z]+)([A-Z][a-z\d])/', '/([a-z\d])([A-Z])/'], ['\\1' . $separator . '\\2', '\\1' . $separator . '\\2'], $id);
        return strtolower($id);
    }

    /**
     * unicode-safe capitalize first letter of all words
     *
     * @param string $string
     * @return string
     */
    public static function mb_ucwords(string $string): string {
        if (empty($string)) return $string;

        $parts = preg_split('/\s+/u', $string, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as &$value) {
            $value = static::mb_ucfirst($value);
        }
        return implode(' ', $parts);
    }

    /**
     * unicode-safe capitalize the fist letter
     *
     * @param string $string the string to be proceeded
     * @return string
     */
    public static function mb_ucfirst(string $string): string {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1, null);
    }

    /**
     * Returns substring before delimiter
     *
     * @param string $s -- string
     * @param string $d -- delimiter
     * @param bool $full -- returns full string if pattern not found
     * @return string -- substring to delimiter or empty string if not found
     */
    static function substring_before(string $s, string $d, bool $full = false): string {
        $p = strpos($s, $d);
        if ($full && $p === false) return $s;
        return substr($s, 0, $p);
    }

    /**
     * Returns substring after delimiter
     *
     * @param string $s -- string
     * @param string $d -- delimiter
     * @param bool $full -- returns full string if pattern not found
     *
     * @return string -- substring to delimiter or empty string if not found
     * @throws Exception -- if delimiter is empty
     */
    static function substring_after(string $s, string $d, bool $full = false): string {
        if (empty($s)) throw new Exception('Empty needle');
        $p = strpos($s, $d);
        if ($p === false) return $full ? $s : '';
        return substr($s, $p + strlen($d));
    }

    /**
     * Generates a valid XML name-id based on given string
     *
     * Replaces invalid characters to valid ones. Replaces accented letters to ASCII letters.
     *
     * - Element names must start with a letter or underscore
     * - Element names can contain letters, digits, underscores, and the specified enabled characters
     * - Element names cannot contain spaces
     *
     * @param string $str
     * @param string $def -- replace invalid characters to, default is '_'. A single character only
     * @param string $ena -- more enabled characters, e.g. '-' (specify - last, escape ] chars.)
     * @param int $maxlen -- maximum length or 0 if no limit. Default is 64.
     *
     * @return string -- the correct output, or empty if input was empty or null
     */
    public static function toNameID(string $str, string $def = '_', string $ena = '.-', int $maxlen = 64): string {
        if ($str == '') return $str;
        if ($maxlen > 0 && strlen($str) > $maxlen) $str = substr($str, 0, $maxlen);
        if (($p = strpos($ena, '-')) < strlen($ena) - 1) $ena = substr($ena, 0, $p) . substr($ena, $p + 1) . '-';

        // If the string is already in nameID format, return itself
        if (preg_match("~^[A-Za-z_][\w_$ena]*$~", $str)) return $str;
        if ($def === null) $def = '_';
        if ($ena === null) $ena = '';

        // Remove diacritics
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);

        // Filter enabled characters
        if (strlen($ena) > 0) $ena = mb_ereg_replace('(.)', '\\\\1', $ena);

        // Filter the string
        $str = mb_ereg_replace('[^A-Za-z0-9' . $def . $ena . ']', $def, $str);

        // Prepend a _ if first character is not alfa
        if (!preg_match("~^[A-Za-z_]~", $str)) $str = '_' . $str;

        return $str;
    }

    /**
     * Converts JSON string into array.
     * Useful when dealing with JSON data stored in database as string.
     *
     * @param string $data
     * @return array -- returns empty array if $data was not an array.
     * @author arlogy
     */
    public static function arrayFromJsonString(string $data): array {
        $data = json_decode($data, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Converts array value into JSON string.
     * Useful to revert AppHelper::arrayFromJsonString().
     *
     * @param mixed $data
     * @return string
     * @author arlogy
     */
    public static function jsonStringFrom(mixed $data): string {
        $data = json_encode($data);
        return is_string($data) ? $data : '';
    }

    /**
     * Substitutes {$key} patterns of the text to values of associative data
     * Used primarily for native language texts, but used for SQL generation where substitution is not based on SQL data syntax.
     * If no substitution possible, the pattern remains unchanged without error
     * Special cases:
     *    - {DMY$var} - convert hungarian date to english (deprecated)
     *  - {$var/subvar} - array resolution within array values (using multiple levels possible)
     *  - Using special characters if necessary: `{{}` -> `{`, `}` -> `}`
     *    - values of DateTime will be substituted as SHORT date of the application's language.
     *
     * @param string $text
     * @param array $data
     *
     * @return string
     */
    public static function substitute(string $text, array $data): string {
        return preg_replace_callback(/* @lang */ '#{(DMY|MDY)?(\\$[a-zA-Z_]+[\\\\/a-zA-Z0-9_-]*)}#', function ($mm) use ($data) {
            if ($mm[2] == '{') return '{';
            if (str_starts_with($mm[2], '$')) {
                // a keyname
                $subvars = explode('/', substr($mm[2], 1));
                $d = $data;
                foreach ($subvars as $subvar) {
                    if (is_array($d) && array_key_exists($subvar, $d)) $d = $d[$subvar] === null ? '#null#' : $d[$subvar];
                    else return $mm[0];
                }
            } else {
                // Other expression (not implemented)
                return $mm[0];
            }
            if ($d instanceof DateTime) $d = static::formatDateTime($d, IntlDateFormatter::SHORT, IntlDateFormatter::NONE);
            if ($mm[1] == 'MDY') {
                $d = static::formatDateTime($d, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, 'en');
            }
            if ($mm[1] == 'DMY') {
                $d = static::formatDateTime($d, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, 'en-GB');
            }
            return $d;
        }, $text);
    }

    /**
     * formats a DateTime value using given locale
     *
     * @param DateTime $datetime
     * @param int $datetype -- date format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
     * @param int $timetype -- time format as IntlDateFormatter::NONE, type values are 'NONE', 'SHORT', 'MEDIUM', 'LONG', 'FULL'
     * @param string|null $locale -- locale in ll-cc format (ISO 639-1 && ISO 3166-1), null to use default
     * @return string
     */
    public static function formatDateTime(DateTime $datetime, int $datetype, int $timetype, string $locale = null): string {
        if (!$locale) $locale = App::$app->locale;
        if (!$locale) $locale = "en-GB";
        $pattern = null;
        if (str_starts_with($locale, 'hu')) {
            if ($datetype == IntlDateFormatter::SHORT && $timetype == IntlDateFormatter::SHORT)
                $pattern = 'yyyy.MM.dd. H:mm';
            if ($datetype == IntlDateFormatter::SHORT && $timetype == IntlDateFormatter::NONE)
                $pattern = 'yyyy.MM.dd.';
        }
        $dateFormatter = new IntlDateFormatter($locale, $datetype, $timetype, null, null, $pattern);
        return $dateFormatter->format($datetime);
    }

    /**
     * Waits for a test to satisfy (i.e. to return a truthy value)
     *
     * See usage example in {@see MigrateController::actionWait()}
     *
     * @param Closure $test -- test to run. Must return truthy value on success
     * @param int $timeout -- seconds to giving up waiting, the minimum allowed value is 1
     * @param int $interval -- seconds between retry attempts, the minimum allowed value is 1
     * @return bool -- true if test succeeded within timeout, false otherwise
     */
    public static function waitFor(Closure $test, int $timeout = 60, int $interval = 1): bool {
        $startTime = time();
        $interval = max(1, $interval);
        $timeout = max(1, $timeout);
        $timeoutPassed = $startTime + $timeout;
        do {
            $lastTry = time();
            if ($test()) return true;
            sleep(max(0, min($timeoutPassed - time(), $lastTry + $interval - time())));
        } while (time() < $timeoutPassed);
        return false;
    }

    /**
     * Returns true if path is absolute, false if not (relative).
     * Empty string considered as relative.
     * Can be used for file system and URL paths as well.
     * Both Windows and Linux file system paths are detected.
     * The path itself is not validated, malformed paths can be either absolute or relative.
     * Note: Paths beginning with drive letter on Windows but not \\ still considered as absolute.
     *
     * @param string $path
     * @return bool
     */
    public static function pathIsAbsolute(string $path): bool {
        return preg_match('~^(/|\\\\|[\w]+:)~', $path);
    }

    /**
     * Determines the base URL of the application considering the reverse proxy effect
     * @return string -- the valid base URL
     */
    public static function baseUrl(): string {
        $https = getenv('HTTPS') ?? 'off';
        $protocol = ($https == 'on' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https" : "http";
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $https == 'on') {
            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $protocol;
            if ($https == "on") {
                $protocol = 'https';
                $_SERVER['SERVER_PORT'] = 443;
                $_SERVER['HTTPS'] = 'on'; // SimpleSAMLphp will apply wrong RelayState URL after login/logout if it's missing
            }
        }
        return $protocol . '://' . $_SERVER["HTTP_HOST"];
    }
}
