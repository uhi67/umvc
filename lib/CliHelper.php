<?php
namespace uhi67\umvc;

/**
 * CLI-related static helper methods
 *
 * @package UMVC Simple Application Framework
 */
class CliHelper {
	/**
	 * Gets input from STDIN and returns a string right-trimmed for EOLs.
	 *
	 * @param bool $raw If set to true, returns the raw string without trimming
	 * @return string the string read from stdin
	 */
	public static function stdin($raw = false) {
		return $raw ? fgets(STDIN) : rtrim(fgets(STDIN), PHP_EOL);
	}

	/**
	 * Prints a string to STDOUT.
	 *
	 * @param string $string the string to print
	 * @return int|bool Number of bytes printed or false on error
	 */
	public static function stdout($string) {
		return fwrite(STDOUT, $string);
	}

	/**
	 * Prints a string to STDERR.
	 *
	 * @param string $string the string to print
	 * @return int|bool Number of bytes printed or false on error
	 */
	public static function stderr($string) {
		return fwrite(STDERR, $string);
	}

	/**
	 * Asks the user for input. Ends when the user types a carriage return (PHP_EOL). Optionally, It also provides a
	 * prompt.
	 *
	 * @param string $prompt the prompt to display before waiting for input (optional)
	 * @return string the user's input
	 */
	public static function input($prompt = null) {
		if (isset($prompt)) {
			static::stdout($prompt);
		}

		return static::stdin();
	}

	/**
	 * Prints text to STDOUT appended with a carriage return (PHP_EOL).
	 *
	 * @param string $string the text to print
	 * @return int|bool number of bytes printed or false on error.
	 */
	public static function output($string = null) {
		return static::stdout($string . PHP_EOL);
	}

	/**
	 * Prints text to STDERR appended with a carriage return (PHP_EOL).
	 *
	 * @param string $string the text to print
	 * @return int|bool number of bytes printed or false on error.
	 */
	public static function error($string = null) {
		return static::stderr($string . PHP_EOL);
	}

	/**
	 * Prompts the user for input and validates it.
	 *
	 * @param string $text prompt string
	 * @param array $options the options to validate the input:
	 *
	 * - `required`: whether it is required or not
	 * - `default`: default value if no input is inserted by the user
	 * - `pattern`: regular expression pattern to validate user input
	 * - `validator`: a callable function to validate input. The function must accept two parameters:
	 * - `input`: the user input to validate
	 * - `error`: the error value passed by reference if validation failed.
	 *
	 * @return string the user input
	 */
	public static function prompt($text, $options = []) {
		$options = ArrayHelper::merge(
			[
				'required' => false,
				'default' => null,
				'pattern' => null,
				'validator' => null,
				'error' => 'Invalid input.',
			],
			$options
		);
		$error = null;

		top:
		$input = $options['default']
			? static::input("$text [" . $options['default'] . '] ')
			: static::input("$text ");

		if ($input === '') {
			if (isset($options['default'])) {
				$input = $options['default'];
			} elseif ($options['required']) {
				static::output($options['error']);
				goto top;
			}
		} elseif ($options['pattern'] && !preg_match($options['pattern'], $input)) {
			static::output($options['error']);
			goto top;
		} elseif ($options['validator'] &&
			!call_user_func_array($options['validator'], [$input, &$error])
		) {
			static::output(isset($error) ? $error : $options['error']);
			goto top;
		}

		return $input;
	}

	/**
	 * Asks user to confirm by typing y or n.
	 *
	 * A typical usage looks like the following:
	 *
	 * ```php
	 * if (Console::confirm("Are you sure?")) {
	 *     echo "user typed yes\n";
	 * } else {
	 *     echo "user typed no\n";
	 * }
	 * ```
	 *
	 * @param string $message to print out before waiting for user input
	 * @param bool $default this value is returned if no selection is made.
	 * @return bool whether user confirmed
	 */
	public static function confirm($message, $default = false) {
		while (true) {
			static::stdout($message . ' (yes|no) [' . ($default ? 'yes' : 'no') . ']:');
			$input = trim(static::stdin());

			if (empty($input)) {
				return $default;
			}

			if (!strcasecmp($input, 'y') || !strcasecmp($input, 'yes')) {
				return true;
			}

			if (!strcasecmp($input, 'n') || !strcasecmp($input, 'no')) {
				return false;
			}
		}
	}

	/**
	 * Gives the user an option to choose from. Giving '?' as an input will show
	 * a list of options to choose from and their explanations.
	 *
	 * @param string $prompt the prompt message
	 * @param array $options Key-value array of options to choose from. Key is what is inputed and used, value is
	 * what's displayed to end user by help command.
	 *
	 * @return string An option character the user chose
	 */
	public static function select($prompt, $options = []) {
		top:
		static::stdout("$prompt [" . implode(',', array_keys($options)) . ',?]: ');
		$input = static::stdin();
		if ($input === '?') {
			foreach ($options as $key => $value) {
				static::output(" $key - $value");
			}
			static::output(' ? - Show help');
			goto top;
		} elseif (!array_key_exists($input, $options)) {
			goto top;
		}

		return $input;
	}

	/**
	 * Returns command line arguments with optional associated values
	 *
	 * Values may be specified as name=value, returned as name=>value pair.
	 * Arguments without value returned with numeric index.
	 *
	 * @return array
	 */
	public static function parseArgs() {
		$argc = $_SERVER['argc'];
		$argv = $_SERVER['argv'];
		$args = [];
		// Parsing args
		for($i = 1; $i < $argc; $i++) {
			if(preg_match('/^(--)?(\w+)=(.*)$/', $argv[$i], $m)) {
				$args[$m[2]] = $m[3];
			} else $args[] = $argv[$i];
		}
		return $args;
	}
}
