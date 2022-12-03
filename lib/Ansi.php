<?php /** @noinspection PhpUnused */

namespace uhi67\umvc;
/**
 * Ansi is a class for print colors and cursor control characters in command-line interfaces
 *
 * ## Example
 * ```php
 * echo Ansi::color("Index of actions", 'light cyan'), PHP_EOL;
 * ```
 *
 * @see AppCommand
 * @package UMVC Simple Application Framework
 */
class Ansi {
   	public static $commands = [
   		'blink' => '\033[5m',
   		'bold' => '\033[1m',
		'dim' => '\033[2m',
		'rev' => '\033[7m',
		'sitm' => '\033[3m',
		'ritm' => '\033[23m',
		'smso' => '\033[7m',
		'rmso' => '\033[27m',
		'smul' => '\033[4m',
		'rmul' => '\033[24m', 		//	underlined text off
		'setab #1' => '\033[4#1m', 	//	set background color #1 (0-7)
		'setaf #1' => '\033[3#1m', 	//	set text color #1 (0-7)
		'sgr0' => '\033(B\033[m', 		//	reset text attributes
		// Cursor movement
		'sc' => '\0337', 				//	save cursor position
		'rc' => '\0338', 				//	restore saved cursor position
		'clear' => '\033[H\033[2J', 	//	clear screen and move cursor to top left
		'cuu #1' => '\033[#1A', 		//	move cursor up #1 rows
		'cud #1' => '\033[#1B', 		//	move cursor down #1 rows
		'cuf #1' => '\033[#1C', 		//	move cursor right #1 columns
		'cub #1' => '\033[#1D',		//	move cursor left #1 columns
		'home' => '\033[H', 			//	move cursor to top left
		'hpa #1' => '\033[#1G', 		//	move cursor to column #1
		'vpa #1' => '\033[#1d', 		//	move cursor to row #1, first column
		'cup #1 #2' => '\033[#1;#2H', //	move cursor to row #1, column #2
		// Removing characters
		'dch #1' => '\033#1P', 		//	remove #1 characters (like backspacing)
		'dl #1' => '\033#1M',		 	//	remove #1 lines
		'ech #1' => '\033#1X', 		//	clear #1 characters (without moving cursor)
		'ed' => '\033[J', 			//	clear to bottom of screen
		'el' => '\033[K', 			//	clear to end of line
		'el1' => '\033[1K', 			//	clear to beginning of line
    ];
   	public static $colors = [
   		'black' => '0;30',
		'red'	=> '0;31',
		'green'	=> '0;32',
		'brown'	=> '0;33',
		'blue'	=> '0;34',
		'purple'=> '0;35',
		'cyan'	=> '0;36',
		'light gray'	=> '0;37',
		'dark gray'		=> '1;30',
		'light red'		=> '1;31',
		'light green'	=> '1;32',
		'yellow'		=> '1;33',
		'light blue'	=> '1;34',
		'light purple'	=> '1;35',
		'light cyan'	=> '1;36',
		'white'			=> '1;37',
    ];

	private static $backgrounds = [
		'black' => '40',
		'red' => '41',
		'green' => '42',
		'yellow' => '43',
		'blue' => '44',
		'magenta' => '45',
		'cyan' => '46',
		'light gray' => '47',
		'white'	=> '47', // alias
	];

	/**
	 * Returns ANSI colored text
	 *
	 * if close is true (default) the colors will be rested at the end of the string
	 *
	 * @param string $string -- the message to color
	 * @param string $fg -- foreground color name
	 * @param string|null $bg -- background color name (default is unchanged)
	 * @param bool $close -- restore color after the message
	 * @return string -- string with pre- and appended ansi color commands
	 */
	public static function color($string, $fg, $bg='null', $close=true) {
		if(!$fg) $fg = $bg=='white' ? 'black' : 'white';
		if(!is_string($fg)) return $string.'*'; //throw new InternalException('fg must be string');
		$color = self::$colors[trim(strtolower($fg))] ?? '0';
		$bg = $bg ? (self::$backgrounds[trim(strtolower($bg))] ?? '0'): '';
		$bgx = $bg ? "\033[{$bg}m" : '';
		return "\033[{$color}m$bgx" . $string . ($close ? "\033[0m" : '');
	}

	/**
	 * Converts HTML color to the closest ANSI color
	 *
	 * ## Example
	 * `echo Ansi::color($message, Ansi::htmltoansi($color))`
	 *
	 * @param string $color -- the HTML color code in form '#12C' or '#1122CC'
	 * @return string -- the ANSI color name
	 */
	public static function htmltoansi($color) {
		if(preg_match('/^#?([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $color, $m)) {
			$rgb = [hexdec($m[1].$m[1]), hexdec($m[2].$m[2]), hexdec($m[3].$m[3])];
		}
		else if(preg_match('/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $color, $m)) {
			$rgb = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
		}
		else return false;

		$hsv = self::rgb_to_hsv($rgb);
		if($hsv['v'] < 0.1) return 'black';
		if($hsv['v'] > 0.5) $r = 'light '; else $r='';
		if($hsv['s'] < 0.2) return $r.'gray';
		if($hsv['h'] < 0.2) return $r.'red';
		if($hsv['h'] < 0.08) $r = $r.'red';
		else if($hsv['h'] < 0.25) $r = $r.'brown';
		else if($hsv['h'] < 0.41) $r = $r.'green';
		else if($hsv['h'] < 0.58) $r = $r.'cyan';
		else if($hsv['h'] < 0.75) $r = $r.'blue';
		else if($hsv['h'] < 0.83) $r = $r.'purple';
		else $r = $r.'red';
		if($r=="light brown") $r='yellow';
		return $r;
	}

	/** @noinspection PhpMethodNamingConventionInspection */

	/**
	 * Converts RGB color to HSV model
	 *
	 * @param array|integer $r -- array of RGB Values: 0-255 or R value
	 * @param integer $g
	 * @param integer $b
	 * @return array 	 -- HSV Results: 0-1
	 */
	public static function rgb_to_hsv ($r, $g=0, $b=0) {
		if(is_array($r)) {
			$b = $r[2];
			$g = $r[1];
			$r = $r[0];
		}
		$hsl = [];

		$var_r = ($r / 255);
		$var_g = ($g / 255);
		$var_b = ($b / 255);

		$var_min = min($var_r, $var_g, $var_b);
		$var_max = max($var_r, $var_g, $var_b);
		$del_max = $var_max - $var_min;

		$v = $var_max;

		$h=0;
		if ($del_max == 0)
		{
		  $s = 0;
		}
		else
		{
		  $s = $del_max / $var_max;

		  $del_r = ( ( ( $var_max - $var_r ) / 6 ) + ( $del_max / 2 ) ) / $del_max;
		  $del_g = ( ( ( $var_max - $var_g ) / 6 ) + ( $del_max / 2 ) ) / $del_max;
		  $del_b = ( ( ( $var_max - $var_b ) / 6 ) + ( $del_max / 2 ) ) / $del_max;

		  if      ($var_r == $var_max) $h = $del_b - $del_g;
		  else if ($var_g == $var_max) $h = ( 1 / 3 ) + $del_r - $del_b;
		  else if ($var_b == $var_max) $h = ( 2 / 3 ) + $del_g - $del_r;

		  if ($h<0) $h++;
		  if ($h>1) $h--;
		}

		$hsl['h'] = $h;
		$hsl['s'] = $s;
		$hsl['v'] = $v;

		return $hsl;
	}
}
