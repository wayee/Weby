<?php defined('SYSPATH') or die('No direct script access.');

if ( ! defined('WEBY_START_TIME'))
{
	/**
	 * Define the start time of the application, used for profiling.
	 */
	define('WEBY_START_TIME', microtime(TRUE));
}

if ( ! defined('WEBY_START_MEMORY'))
{
	/**
	 * Define the memory usage at the start of the application, used for profiling.
	 */
	define('WEBY_START_MEMORY', memory_get_usage());
}

/**
 * Weby translation/internationalization function. The PHP function
 * [strtr](http://php.net/strtr) is used for replacing parameters.
 *
 *    __('Welcome back, :user', array(':user' => $username));
 *
 * @uses    I18n::get
 * @param   string  text to translate
 * @param   array   values to replace in the translated text
 * @param   string  target language
 * @return  string
 */
function __($string, array $values = NULL, $lang = 'en-us')
{
	return empty($values) ? $string : strtr($string, $values);
}
