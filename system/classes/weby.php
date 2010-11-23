<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Contains the most low-level helpers methods in Weby:
 *
 * - Environment initialization
 * - Locating files within the cascading filesystem
 * - Auto-loading and transparent extension of classes
 * - Variable and path debugging
 *
 * @package    Weby
 * @category   Base
 * @author     Weby Team
 * @copyright  (c) 2010 Weby Team
 */
class Weby {
	// Release version
	const VERSION  = '0.1.23';

	// Log message types
	const ERROR = 'ERROR';
	const DEBUG = 'DEBUG';
	const INFO  = 'INFO';

	// Common environment type constants for consistency and convenience
	const PRODUCTION  = 'production';
	const STAGING     = 'staging';
	const TESTING     = 'testing';
	const DEVELOPMENT = 'development';

	// Security check that is added to all generated PHP files
	const FILE_SECURITY = '<?php defined(\'SYSPATH\') or die(\'No direct script access.\');';
	
	/**
	 * @var  object  config object
	 */
	public static $config = NULL;
	
	// Is the environment initialized?
	protected static $_init = FALSE;
	
	// Currently active modules
	protected static $_modules = array();

	// Include paths that are used to find files
	protected static $_paths = array(APPPATH, SYSPATH);

	// File path cache
	protected static $_files = array();
	
	// Has the file cache changed?
	protected static $_files_changed = FALSE;

	/**
	 * @var  array  PHP error code => human readable name
	 */
	public static $php_errors = array(
		E_ERROR              => 'Fatal Error',
		E_USER_ERROR         => 'User Error',
		E_PARSE              => 'Parse Error',
		E_WARNING            => 'Warning',
		E_USER_WARNING       => 'User Warning',
		E_STRICT             => 'Strict',
		E_NOTICE             => 'Notice',
		E_RECOVERABLE_ERROR  => 'Recoverable Error',
	);
	
	/**
	 * @var  string  current environment name
	 */
	public static $environment = Weby::DEVELOPMENT;
	
	/**
	 * @var  boolean  Windows environment?
	 */
	public static $is_windows = FALSE;

	/**
	 * @var  boolean  magic quotes enabled?
	 */
	public static $magic_quotes = FALSE;
	
	/**
	 * @var  string  character set of input and output
	 */
	public static $charset = 'utf-8';

	/**
	 * @var  string  base URL to the application
	 */
	public static $base_url = '/';

	/**
	 * @var  string  application index file
	 */
	public static $index_file = 'index.php';
	
	/**
	 * @var  string  cache directory
	 */
	public static $cache_dir;

	/**
	 * @var  boolean  enabling internal caching?
	 */
	public static $caching = TRUE;

	/**
	 * @var  boolean  enable error handling?
	 */
	public static $errors = TRUE;
	
	/**
	 * @var  array  types of errors to display at shutdown
	 */
	public static $shutdown_errors = array(E_PARSE, E_ERROR, E_USER_ERROR, E_COMPILE_ERROR);
	
	public function __construct()
	{
	}
	
	/**
	 * Initializes the environment:
	 *
	 * - Disables register_globals and magic_quotes_gpc
	 * - Determines the current environment
	 * - Set global settings
	 * - Sanitizes GET, POST, and COOKIE variables
	 * - Converts GET, POST, and COOKIE variables to the global character set
	 *
	 * Any of the global settings can be set here:
	 *
	 * Type      | Setting    | Description                                    | Default Value
	 * ----------|------------|------------------------------------------------|---------------
	 * `boolean` | errors     | use internal error and exception handling?     | `TRUE`
	 * `boolean` | caching    | cache the location of files between requests?  | `FALSE`
	 * `string`  | charset    | character set used for all input and output    | `"utf-8"`
	 * `string`  | base_url   | set the base URL for the application           | `"/"`
	 * `string`  | index_file | set the index.php file name                    | `"index.php"`
	 * `string`  | cache_dir  | set the cache directory path                   | `APPPATH."cache"`
	 *
	 * @throws  Weby_Exception
	 * @param   array   global settings
	 * @return  void
	 * @uses    Weby::globals
	 * @uses    Weby::sanitize
	 */
	public static function init(array $settings = NULL)
	{
		if (Weby::$_init)
		{
			// Do not allow execution twice
			return;
		}

		// Weby is now initialized
		Weby::$_init = TRUE;

		// Start an output buffer
		ob_start();

		if (defined('E_DEPRECATED'))
		{
			// E_DEPRECATED only exists in PHP >= 5.3.0
			Weby::$php_errors[E_DEPRECATED] = 'Deprecated';
		}

		if (isset($settings['errors']))
		{
			// Enable error handling
			Weby::$errors = (bool) $settings['errors'];
		}

		if (Weby::$errors === TRUE)
		{
			// Enable Weby exception handling, adds stack traces and error source.
			set_exception_handler(array('Weby', 'exception_handler'));

			// Enable Weby error handling, converts all PHP errors to exceptions.
			set_error_handler(array('Weby', 'error_handler'));
		}

		// Enable the Weby shutdown handler, which catches E_FATAL errors.
		register_shutdown_function(array('Weby', 'shutdown_handler'));

		if (ini_get('register_globals'))
		{
			// Reverse the effects of register_globals
			Weby::globals();
		}

		// Determine if we are running in a Windows environment
		Weby::$is_windows = (DIRECTORY_SEPARATOR === '\\');
		
		if (isset($settings['cache_dir']))
		{
			// Set the cache directory path
			Weby::$cache_dir = realpath($settings['cache_dir']);
		}
		else
		{
			// Use the default cache directory
			Weby::$cache_dir = APPPATH.'cache';
		}

		if ( ! is_writable(Weby::$cache_dir))
		{
			throw new Weby_Exception('Directory :dir must be writable',
				array(':dir' => Weby::debug_path(Weby::$cache_dir)));
		}

		if (isset($settings['caching']))
		{
			// Enable or disable internal caching
			Weby::$caching = (bool) $settings['caching'];
		}

		if (Weby::$caching === TRUE)
		{
			// Load the file path cache
			Weby::$_files = Weby::cache('Weby::find_file()');
		}

		if (isset($settings['charset']))
		{
			// Set the system character set
			Weby::$charset = strtolower($settings['charset']);
		}

		if (function_exists('mb_internal_encoding'))
		{
			// Set the MB extension encoding to the same character set
			mb_internal_encoding(Weby::$charset);
		}

		if (isset($settings['base_url']))
		{
			// Set the base URL
			Weby::$base_url = rtrim($settings['base_url'], '/').'/';
		}

		if (isset($settings['index_file']))
		{
			// Set the index file
			Weby::$index_file = trim($settings['index_file'], '/');
		}

		// Determine if the extremely evil magic quotes are enabled
		Weby::$magic_quotes = (bool) get_magic_quotes_gpc();

		// Sanitize all request variables
		$_GET    = Weby::sanitize($_GET);
		$_POST   = Weby::sanitize($_POST);
		$_COOKIE = Weby::sanitize($_COOKIE);

		// Load the config
//		Weby::$config = Config::instance();
	}
	
/**
	 * Cleans up the environment:
	 *
	 * - Restore the previous error and exception handlers
	 * - Destroy the Weby::$log and Weby::$config objects
	 *
	 * @return  void
	 */
	public static function deinit()
	{
		if (Weby::$_init)
		{
			// Removed the autoloader
			spl_autoload_unregister(array('Weby', 'auto_load'));

			if (Weby::$errors)
			{
				// Go back to the previous error handler
				restore_error_handler();

				// Go back to the previous exception handler
				restore_exception_handler();
			}

			// Destroy objects created by init
			Weby::$config = NULL;

			// Reset internal storage
			Weby::$_modules = Weby::$_files = array();
			Weby::$_paths   = array(APPPATH, SYSPATH);

			// Reset file cache status
			Weby::$_files_changed = FALSE;

			// Weby is no longer initialized
			Weby::$_init = FALSE;
		}
	}
	
/**
	 * Changes the currently enabled modules. Module paths may be relative
	 * or absolute, but must point to a directory:
	 *
	 *     Weby::modules(array('modules/foo', MODPATH.'bar'));
	 *
	 * @param   array  list of module paths
	 * @return  array  enabled modules
	 */
	public static function modules(array $modules = NULL)
	{
		if ($modules === NULL)
			return Weby::$_modules;

		// Start a new list of include paths, APPPATH first
		$paths = array(APPPATH);

		foreach ($modules as $name => $path)
		{
			if (is_dir($path))
			{
				// Add the module to include paths
				$paths[] = $modules[$name] = realpath($path).DIRECTORY_SEPARATOR;
			}
			else
			{
				// This module is invalid, remove it
				unset($modules[$name]);
			}
		}

		// Finish the include paths by adding SYSPATH
		$paths[] = SYSPATH;

		// Set the new include paths
		Weby::$_paths = $paths;

		// Set the current module list
		Weby::$_modules = $modules;

		foreach (Weby::$_modules as $path)
		{
			$init = $path.'init'.EXT;

			if (is_file($init))
			{
				// Include the module initialization file once
				require_once $init;
			}
		}

		return Weby::$_modules;
	}
	
	/**
	 * Returns the the currently active include paths, including the
	 * application and system paths.
	 *
	 * @return  array
	 */
	public static function include_paths()
	{
		return Weby::$_paths;
	}
	
	/**
	 * Finds the path of a file by directory, filename, and extension.
	 * If no extension is given, the default EXT extension will be used.
	 *
	 * When searching the "config" or "i18n" directories, or when the
	 * $aggregate_files flag is set to true, an array of files
	 * will be returned. These files will return arrays which must be
	 * merged together.
	 *
	 *     // Returns an absolute path to views/template.php
	 *     Weby::find_file('views', 'template');
	 *
	 *     // Returns an absolute path to media/css/style.css
	 *     Weby::find_file('media', 'css/style', 'css');
	 *
	 *     // Returns an array of all the "mimes" configuration file
	 *     Weby::find_file('config', 'mimes');
	 *
	 * @param   string   directory name (views, i18n, classes, extensions, etc.)
	 * @param   string   filename with subdirectory
	 * @param   string   extension to search for
	 * @param   boolean  return an array of files?
	 * @return  array    a list of files when $array is TRUE
	 * @return  string   single file path
	 */
	public static function find_file($dir, $file, $ext = NULL, $array = FALSE)
	{
		// Use the defined extension by default
		$ext = ($ext === NULL) ? EXT : '.'.$ext;

		// Create a partial path of the filename
		$path = $dir.DIRECTORY_SEPARATOR.$file.$ext;
		if (Weby::$caching === TRUE AND isset(Weby::$_files[$path]))
		{
			// This path has been cached
			return Weby::$_files[$path];
		}

		if ($array OR $dir === 'config' OR $dir === 'i18n' OR $dir === 'messages')
		{
			// Include paths must be searched in reverse
			$paths = array_reverse(Weby::$_paths);

			// Array of files that have been found
			$found = array();

			foreach ($paths as $dir)
			{
				if (is_file($dir.$path))
				{
					// This path has a file, add it to the list
					$found[] = $dir.$path;
				}
			}
		}
		else
		{
			// The file has not been found yet
			$found = FALSE;

			foreach (Weby::$_paths as $dir)
			{
				if (is_file($dir.$path))
				{
					// A path has been found
					$found = $dir.$path;

					// Stop searching
					break;
				}
			}
		}
		
		if (Weby::$caching === TRUE)
		{
			// Add the path to the cache
			Weby::$_files[$path] = $found;

			// Files have been changed
			Weby::$_files_changed = TRUE;
		}

		return $found;
	}
	
	/**
	 * Recursively finds all of the files in the specified directory.
	 *
	 *     $views = Weby::list_files('views');
	 *
	 * @param   string  directory name
	 * @param   array   list of paths to search
	 * @return  array
	 */
	public static function list_files($directory = NULL, array $paths = NULL)
	{
		if ($directory !== NULL)
		{
			// Add the directory separator
			$directory .= DIRECTORY_SEPARATOR;
		}

		if ($paths === NULL)
		{
			// Use the default paths
			$paths = Weby::$_paths;
		}

		// Create an array for the files
		$found = array();

		foreach ($paths as $path)
		{
			if (is_dir($path.$directory))
			{
				// Create a new directory iterator
				$dir = new DirectoryIterator($path.$directory);

				foreach ($dir as $file)
				{
					// Get the file name
					$filename = $file->getFilename();

					if ($filename[0] === '.' OR $filename[strlen($filename)-1] === '~')
					{
						// Skip all hidden files and UNIX backup files
						continue;
					}

					// Relative filename is the array key
					$key = $directory.$filename;

					if ($file->isDir())
					{
						if ($sub_dir = Weby::list_files($key, $paths))
						{
							if (isset($found[$key]))
							{
								// Append the sub-directory list
								$found[$key] += $sub_dir;
							}
							else
							{
								// Create a new sub-directory list
								$found[$key] = $sub_dir;
							}
						}
					}
					else
					{
						if ( ! isset($found[$key]))
						{
							// Add new files to the list
							$found[$key] = realpath($file->getPathName());
						}
					}
				}
			}
		}

		// Sort the results alphabetically
		ksort($found);

		return $found;
	}
	
	/**
	 * Loads a file within a totally empty scope and returns the output:
	 *
	 *     $foo = Weby::load('foo.php');
	 *
	 * @param   string
	 * @return  mixed
	 */
	public static function load($file)
	{
		return include $file;
	}
	
	/**
	 * Creates a new configuration object for the requested group.
	 *
	 * @param   string   group name
	 * @return  Config
	 */
	public static function config($group)
	{
		// Lazy load config
		if (Weby::$config === NULL) {
			Weby::$config = Config::instance();
			Weby::$config->attach(new Config_File);
		}
		
		static $config;

		if (strpos($group, '.') !== FALSE)
		{
			// Split the config group and path
			list ($group, $path) = explode('.', $group, 2);
		}

		if ( ! isset($config[$group]))
		{
			// Load the config group into the cache
			$config[$group] = Weby::$config->load($group);
		}

		if (isset($path))
		{
			return Arr::path($config[$group], $path);
		}
		else
		{
			return $config[$group];
		}
	}
	
	/**
	 * Provides simple file-based caching for strings and arrays:
	 *
	 *     // Set the "foo" cache
	 *     Weby::cache('foo', 'hello, world');
	 *
	 *     // Get the "foo" cache
	 *     $foo = Weby::cache('foo');
	 *
	 * All caches are stored as PHP code, generated with [var_export][ref-var].
	 * Caching objects may not work as expected. Storing references or an
	 * object or array that has recursion will cause an E_FATAL.
	 *
	 * [ref-var]: http://php.net/var_export
	 *
	 * @throws  Weby_Exception
	 * @param   string   name of the cache
	 * @param   mixed    data to cache
	 * @param   integer  number of seconds the cache is valid for
	 * @return  mixed    for getting
	 * @return  boolean  for setting
	 */
	public static function cache($name, $data = NULL, $lifetime = 60)
	{
		// Cache file is a hash of the name
		$file = sha1($name).'.txt';

		// Cache directories are split by keys to prevent filesystem overload
		$dir = Weby::$cache_dir.DIRECTORY_SEPARATOR.$file[0].$file[1].DIRECTORY_SEPARATOR;

		try
		{
			if ($data === NULL)
			{
				if (is_file($dir.$file))
				{
					if ((time() - filemtime($dir.$file)) < $lifetime)
					{
						// Return the cache
						return unserialize(file_get_contents($dir.$file));
					}
					else
					{
						try
						{
							// Cache has expired
							unlink($dir.$file);
						}
						catch (Exception $e)
						{
							// Cache has already been deleted
							return NULL;
						}
					}
				}

				// Cache not found
				return NULL;
			}

			if ( ! is_dir($dir))
			{
				// Create the cache directory
				mkdir($dir, 0777, TRUE);

				// Set permissions (must be manually set to fix umask issues)
				chmod($dir, 0777);
			}
//echo '<pre>';print_r($arg_list = func_get_args());exit(); //$a = 0/0;
			// Write the cache
			return (bool) file_put_contents($dir.$file, serialize($data));
		}
		catch (Exception $e)
		{
			throw $e;
		}
	}
	
	public static function auto_load($class)
	{
		// Transform the class name into a path
		$file = str_replace('_', '/', strtolower($class));

		if ($path = Weby::find_file('classes', $file))
		{
			// Load the class file
			require $path;

			// Class has been found
			return TRUE;
		}

		// Class is not in the filesystem
		return FALSE;
	}
	
	/**
	 * Recursively sanitizes an input variable:
	 *
	 * - Strips slashes if magic quotes are enabled
	 * - Normalizes all newlines to LF
	 *
	 * @param   mixed  any variable
	 * @return  mixed  sanitized variable
	 */
	public static function sanitize($value)
	{
		if (is_array($value) OR is_object($value))
		{
			foreach ($value as $key => $val)
			{
				// Recursively clean each value
				$value[$key] = Weby::sanitize($val);
			}
		}
		elseif (is_string($value))
		{
			if (Weby::$magic_quotes === TRUE)
			{
				// Remove slashes added by magic quotes
				$value = stripslashes($value);
			}

			if (strpos($value, "\r") !== FALSE)
			{
				// Standardize newlines
				$value = str_replace(array("\r\n", "\r"), "\n", $value);
			}
		}

		return $value;
	}
	
	/**
	 * Reverts the effects of the `register_globals` PHP setting by unsetting
	 * all global varibles except for the default super globals (GPCS, etc).
	 *
	 *     if (ini_get('register_globals'))
	 *     {
	 *         Weby::globals();
	 *     }
	 *
	 * @return  void
	 */
	public static function globals()
	{
		if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
		{
			// Prevent malicious GLOBALS overload attack
			echo "Global variable overload attack detected! Request aborted.\n";

			// Exit with an error status
			exit(1);
		}

		// Get the variable names of all globals
		$global_variables = array_keys($GLOBALS);

		// Remove the standard global variables from the list
		$global_variables = array_diff($global_variables, array(
			'_COOKIE',
			'_ENV',
			'_GET',
			'_FILES',
			'_POST',
			'_REQUEST',
			'_SERVER',
			'_SESSION',
			'GLOBALS',
		));

		foreach ($global_variables as $name)
		{
			// Unset the global variable, effectively disabling register_globals
			unset($GLOBALS[$name]);
		}
	}
	
	/**
	 * PHP error handler, converts all errors into ErrorExceptions. This handler
	 * respects error_reporting settings.
	 *
	 * @throws  ErrorException
	 * @return  TRUE
	 */
	public static function error_handler($code, $error, $file = NULL, $line = NULL)
	{
		if (error_reporting() & $code)
		{
			// This error is not suppressed by current error reporting settings
			// Convert the error into an ErrorException
			throw new ErrorException($error, $code, 0, $file, $line);
		}

		// Do not execute the PHP error handler
		return TRUE;
	}
	
	public static function exception_handler(Exception $e)
	{
		try
		{
			// Get the exception information
			$type    = get_class($e);
			$code    = $e->getCode();
			$message = $e->getMessage();
			$file    = $e->getFile();
			$line    = $e->getLine();

			// Create a text version of the exception
			$error = Weby::exception_text($e);

			// Get the exception backtrace
			$trace = $e->getTrace();

			if ($e instanceof ErrorException)
			{
				if (isset(Weby::$php_errors[$code]))
				{
					// Use the human-readable error name
					$code = Weby::$php_errors[$code];
				}

				if (version_compare(PHP_VERSION, '5.3', '<'))
				{
					// Workaround for a bug in ErrorException::getTrace() that exists in
					// all PHP 5.2 versions. @see http://bugs.php.net/bug.php?id=45895
					for ($i = count($trace) - 1; $i > 0; --$i)
					{
						if (isset($trace[$i - 1]['args']))
						{
							// Re-position the args
							$trace[$i]['args'] = $trace[$i - 1]['args'];

							// Remove the args
							unset($trace[$i - 1]['args']);
						}
					}
				}
			}

			if ( ! headers_sent())
			{
				// Make sure the proper content type is sent with a 500 status
				header('Content-Type: text/html; charset='.Weby::$charset, TRUE, 500);
			}

			// Start an output buffer
			ob_start();

			// Include the exception HTML
			include Weby::find_file('views', 'weby/error');

			// Display the contents of the output buffer
			echo ob_get_clean();

			return TRUE;
		}
		catch (Exception $e)
		{
			// Clean the output buffer if one exists
			ob_get_level() and ob_clean();

			// Display the exception text
			echo Weby::exception_text($e), "\n";

			// Exit with an error status
			exit(1);
		}
	}
	
	/**
	 * Catches errors that are not caught by the error handler, such as E_PARSE.
	 *
	 * @uses    Weby::exception_handler
	 * @return  void
	 */
	public static function shutdown_handler()
	{
		if ( ! Weby::$_init)
		{
			// Do not execute when not active
			return;
		}

		try
		{
			if (Weby::$caching === TRUE AND Weby::$_files_changed === TRUE)
			{
				//echo __FUNCTION__;// Write the file path cache
				Weby::cache('Weby::find_file()', Weby::$_files);
			}
		}
		catch (Exception $e)
		{
			// Pass the exception to the handler
			Weby::exception_handler($e);
		}

		if (Weby::$errors AND $error = error_get_last() AND in_array($error['type'], Weby::$shutdown_errors))
		{
			// Clean the output buffer
			ob_get_level() and ob_clean();

			// Fake an exception for nice debugging
			Weby::exception_handler(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));

			// Shutdown now to avoid a "death loop"
			exit(1);
		}
	}
	
	/**
	 * Get a single line of text representing the exception:
	 *
	 * Error [ Code ]: Message ~ File [ Line ]
	 *
	 * @param   object  Exception
	 * @return  string
	 */
	public static function exception_text(Exception $e)
	{
		return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
			get_class($e), $e->getCode(), strip_tags($e->getMessage()), Weby::debug_path($e->getFile()), $e->getLine());
	}
	
	
/**
	 * Returns an HTML string of debugging information about any number of
	 * variables, each wrapped in a "pre" tag:
	 *
	 *     // Displays the type and value of each variable
	 *     echo Weby::debug($foo, $bar, $baz);
	 *
	 * @param   mixed   variable to debug
	 * @param   ...
	 * @return  string
	 */
	public static function debug()
	{
		if (func_num_args() === 0)
			return;

		// Get all passed variables
		$variables = func_get_args();

		$output = array();
		foreach ($variables as $var)
		{
			$output[] = Weby::_dump($var, 1024);
		}

		return '<pre class="debug">'.implode("\n", $output).'</pre>';
	}

	/**
	 * Returns an HTML string of information about a single variable.
	 *
	 * Borrows heavily on concepts from the Debug class of [Nette](http://nettephp.com/).
	 *
	 * @param   mixed    variable to dump
	 * @param   integer  maximum length of strings
	 * @return  string
	 */
	public static function dump($value, $length = 128)
	{
		return Weby::_dump($value, $length);
	}

	/**
	 * Helper for Weby::dump(), handles recursion in arrays and objects.
	 *
	 * @param   mixed    variable to dump
	 * @param   integer  maximum length of strings
	 * @param   integer  recursion level (internal)
	 * @return  string
	 */
	protected static function _dump( & $var, $length = 128, $level = 0)
	{
		if ($var === NULL)
		{
			return '<small>NULL</small>';
		}
		elseif (is_bool($var))
		{
			return '<small>bool</small> '.($var ? 'TRUE' : 'FALSE');
		}
		elseif (is_float($var))
		{
			return '<small>float</small> '.$var;
		}
		elseif (is_resource($var))
		{
			if (($type = get_resource_type($var)) === 'stream' AND $meta = stream_get_meta_data($var))
			{
				$meta = stream_get_meta_data($var);

				if (isset($meta['uri']))
				{
					$file = $meta['uri'];

					if (function_exists('stream_is_local'))
					{
						// Only exists on PHP >= 5.2.4
						if (stream_is_local($file))
						{
							$file = Weby::debug_path($file);
						}
					}

					return '<small>resource</small><span>('.$type.')</span> '.htmlspecialchars($file, ENT_NOQUOTES, Weby::$charset);
				}
			}
			else
			{
				return '<small>resource</small><span>('.$type.')</span>';
			}
		}
		elseif (is_string($var))
		{
			/*if (UTF8::strlen($var) > $length)
			{
				// Encode the truncated string
				$str = htmlspecialchars(UTF8::substr($var, 0, $length), ENT_NOQUOTES, Weby::$charset).'&nbsp;&hellip;';
			}
			else*/
			{
				// Encode the string
				$str = htmlspecialchars($var, ENT_NOQUOTES, Weby::$charset);
			}

			return '<small>string</small><span>('.strlen($var).')</span> "'.$str.'"';
		}
		elseif (is_array($var))
		{
			$output = array();

			// Indentation for this variable
			$space = str_repeat($s = '    ', $level);

			static $marker;

			if ($marker === NULL)
			{
				// Make a unique marker
				$marker = uniqid("\x00");
			}

			if (empty($var))
			{
				// Do nothing
			}
			elseif (isset($var[$marker]))
			{
				$output[] = "(\n$space$s*RECURSION*\n$space)";
			}
			elseif ($level < 5)
			{
				$output[] = "<span>(";

				$var[$marker] = TRUE;
				foreach ($var as $key => & $val)
				{
					if ($key === $marker) continue;
					if ( ! is_int($key))
					{
						$key = '"'.htmlspecialchars($key, ENT_NOQUOTES, self::$charset).'"';
					}

					$output[] = "$space$s$key => ".Weby::_dump($val, $length, $level + 1);
				}
				unset($var[$marker]);

				$output[] = "$space)</span>";
			}
			else
			{
				// Depth too great
				$output[] = "(\n$space$s...\n$space)";
			}

			return '<small>array</small><span>('.count($var).')</span> '.implode("\n", $output);
		}
		elseif (is_object($var))
		{
			// Copy the object as an array
			$array = (array) $var;

			$output = array();

			// Indentation for this variable
			$space = str_repeat($s = '    ', $level);

			$hash = spl_object_hash($var);

			// Objects that are being dumped
			static $objects = array();

			if (empty($var))
			{
				// Do nothing
			}
			elseif (isset($objects[$hash]))
			{
				$output[] = "{\n$space$s*RECURSION*\n$space}";
			}
			elseif ($level < 10)
			{
				$output[] = "<code>{";

				$objects[$hash] = TRUE;
				foreach ($array as $key => & $val)
				{
					if ($key[0] === "\x00")
					{
						// Determine if the access is protected or protected
						$access = '<small>'.($key[1] === '*' ? 'protected' : 'private').'</small>';

						// Remove the access level from the variable name
						$key = substr($key, strrpos($key, "\x00") + 1);
					}
					else
					{
						$access = '<small>public</small>';
					}

					$output[] = "$space$s$access $key => ".Weby::_dump($val, $length, $level + 1);
				}
				unset($objects[$hash]);

				$output[] = "$space}</code>";
			}
			else
			{
				// Depth too great
				$output[] = "{\n$space$s...\n$space}";
			}

			return '<small>object</small> <span>'.get_class($var).'('.count($array).')</span> '.implode("\n", $output);
		}
		else
		{
			return '<small>'.gettype($var).'</small> '.htmlspecialchars(print_r($var, TRUE), ENT_NOQUOTES, Weby::$charset);
		}
	}

	/**
	 * Removes application, system, modpath, or docroot from a filename,
	 * replacing them with the plain text equivalents. Useful for debugging
	 * when you want to display a shorter path.
	 *
	 *     // Displays SYSPATH/classes/kohana.php
	 *     echo Weby::debug_path(Weby::find_file('classes', 'kohana'));
	 *
	 * @param   string  path to debug
	 * @return  string
	 */
	public static function debug_path($file)
	{
		if (strpos($file, APPPATH) === 0)
		{
			$file = 'APPPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(APPPATH));
		}
		elseif (strpos($file, SYSPATH) === 0)
		{
			$file = 'SYSPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(SYSPATH));
		}
		elseif (strpos($file, MODPATH) === 0)
		{
			$file = 'MODPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(MODPATH));
		}
		elseif (strpos($file, DOCROOT) === 0)
		{
			$file = 'DOCROOT'.DIRECTORY_SEPARATOR.substr($file, strlen(DOCROOT));
		}

		return $file;
	}

	/**
	 * Returns an HTML string, highlighting a specific line of a file, with some
	 * number of lines padded above and below.
	 *
	 *     // Highlights the current line of the current file
	 *     echo Weby::debug_source(__FILE__, __LINE__);
	 *
	 * @param   string   file to open
	 * @param   integer  line number to highlight
	 * @param   integer  number of padding lines
	 * @return  string   source of file
	 * @return  FALSE    file is unreadable
	 */
	public static function debug_source($file, $line_number, $padding = 5)
	{
		if ( ! $file OR ! is_readable($file))
		{
			// Continuing will cause errors
			return FALSE;
		}

		// Open the file and set the line position
		$file = fopen($file, 'r');
		$line = 0;

		// Set the reading range
		$range = array('start' => $line_number - $padding, 'end' => $line_number + $padding);

		// Set the zero-padding amount for line numbers
		$format = '% '.strlen($range['end']).'d';

		$source = '';
		while (($row = fgets($file)) !== FALSE)
		{
			// Increment the line number
			if (++$line > $range['end'])
				break;

			if ($line >= $range['start'])
			{
				// Make the row safe for output
				$row = htmlspecialchars($row, ENT_NOQUOTES, Weby::$charset);

				// Trim whitespace and sanitize the row
				$row = '<span class="number">'.sprintf($format, $line).'</span> '.$row;

				if ($line === $line_number)
				{
					// Apply highlighting to this row
					$row = '<span class="line highlight">'.$row.'</span>';
				}
				else
				{
					$row = '<span class="line">'.$row.'</span>';
				}

				// Add to the captured source
				$source .= $row;
			}
		}

		// Close the file
		fclose($file);

		return '<pre class="source"><code>'.$source.'</code></pre>';
	}

	/**
	 * Returns an array of HTML strings that represent each step in the backtrace.
	 *
	 *     // Displays the entire current backtrace
	 *     echo implode('<br/>', Weby::trace());
	 *
	 * @param   string  path to debug
	 * @return  string
	 */
	public static function trace(array $trace = NULL)
	{
		if ($trace === NULL)
		{
			// Start a new trace
			$trace = debug_backtrace();
		}

		// Non-standard function calls
		$statements = array('include', 'include_once', 'require', 'require_once');

		$output = array();
		foreach ($trace as $step)
		{
			if ( ! isset($step['function']))
			{
				// Invalid trace step
				continue;
			}

			if (isset($step['file']) AND isset($step['line']))
			{
				// Include the source of this step
				$source = Weby::debug_source($step['file'], $step['line']);
			}

			if (isset($step['file']))
			{
				$file = $step['file'];

				if (isset($step['line']))
				{
					$line = $step['line'];
				}
			}

			// function()
			$function = $step['function'];

			if (in_array($step['function'], $statements))
			{
				if (empty($step['args']))
				{
					// No arguments
					$args = array();
				}
				else
				{
					// Sanitize the file path
					$args = array($step['args'][0]);
				}
			}
			elseif (isset($step['args']))
			{
				if ( ! function_exists($step['function']) OR strpos($step['function'], '{closure}') !== FALSE)
				{
					// Introspection on closures or language constructs in a stack trace is impossible
					$params = NULL;
				}
				else
				{
					if (isset($step['class']))
					{
						if (method_exists($step['class'], $step['function']))
						{
							$reflection = new ReflectionMethod($step['class'], $step['function']);
						}
						else
						{
							$reflection = new ReflectionMethod($step['class'], '__call');
						}
					}
					else
					{
						$reflection = new ReflectionFunction($step['function']);
					}

					// Get the function parameters
					$params = $reflection->getParameters();
				}

				$args = array();

				foreach ($step['args'] as $i => $arg)
				{
					if (isset($params[$i]))
					{
						// Assign the argument by the parameter name
						$args[$params[$i]->name] = $arg;
					}
					else
					{
						// Assign the argument by number
						$args[$i] = $arg;
					}
				}
			}

			if (isset($step['class']))
			{
				// Class->method() or Class::method()
				$function = $step['class'].$step['type'].$step['function'];
			}

			$output[] = array(
				'function' => $function,
				'args'     => isset($args)   ? $args : NULL,
				'file'     => isset($file)   ? $file : NULL,
				'line'     => isset($line)   ? $line : NULL,
				'source'   => isset($source) ? $source : NULL,
			);

			unset($function, $args, $file, $line, $source);
		}

		return $output;
	}
}