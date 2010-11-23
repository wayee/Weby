<?php defined('SYSPATH') or die('No direct script access.');

//-- Environment setup --------------------------------------------------------

/**
 * Set the default time zone.
 *
 * @see  http://php.net/timezones
 */
date_default_timezone_set('Asia/Shanghai');

/**
 * Set the default locale.
 *
 * @see  http://php.net/setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Enable the Weby auto-loader.
 *
 * @see  http://php.net/spl_autoload_register
 */
spl_autoload_register(array('Weby', 'auto_load'));

/**
 * Enable the Weby auto-loader for unserialization.
 *
 * @see  http://php.net/spl_autoload_call
 * @see  http://php.net/manual/var.configuration.php#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');

//-- Configuration and initialization -----------------------------------------

/**
 * Set the environment string by the domain (defaults to Weby::DEVELOPMENT).
 */
Weby::$environment = ($_SERVER['SERVER_NAME'] !== 'localhost') ? Weby::PRODUCTION : Weby::DEVELOPMENT;

/**
 * Initialize Weby, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   NULL
 * - string   index_file  name of your index file, usually "index.php"       index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - boolean  errors      enable or disable error handling                   TRUE
 * - boolean  profile     enable or disable internal profiling               TRUE
 * - boolean  caching     enable or disable internal caching                 FALSE
 */
Weby::init(array(
	'base_url'   => '/weby',
    'index_file' => FALSE,
//    'caching'    => Weby::$environment === Weby::PRODUCTION,
//	'errors'	 => FALSE
));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
//Weby::$config->attach(new Config_File);

/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
Weby::modules(array(
	// 'auth'       => MODPATH.'auth',       // Basic authentication
	// 'cache'      => MODPATH.'cache',      // Caching with multiple backends
	// 'codebench'  => MODPATH.'codebench',  // Benchmarking tool
	// 'database'   => MODPATH.'database',   // Database access
	// 'image'      => MODPATH.'image',      // Image manipulation
	// 'orm'        => MODPATH.'orm',        // Object Relationship Mapping
	// 'oauth'      => MODPATH.'oauth',      // OAuth authentication
	// 'pagination' => MODPATH.'pagination', // Paging of results
	// 'unittest'   => MODPATH.'unittest',   // Unit testing
	// 'userguide'  => MODPATH.'userguide',  // User guide and API documentation
	 'blog'		=> MODPATH.'blog',		 // Blog samlple
	));

/**
 * Set the routes. Each route must have a minimum of a name, a URI and a set of
 * defaults for the URI.
 */

Route::set('welcome/test', 'test(/<page>)', array(
		'page' => '.+',
	))
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'test',
	));

Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'index',
	));


if ( ! defined('SUPPRESS_REQUEST'))
{
	/*echo Request::instance()
		->execute()
		->send_headers()
		->response;*/
	
	/**
	 * Execute the main request. A source of the URI can be passed, eg: $_SERVER['PATH_INFO'].
	 * If no source is specified, the URI will be automatically detected.
	 */
	$request = Request::instance();
	 
	try
	{
	    // Attempt to execute the response
	    $request->execute();
	}
	catch (Exception $e)
	{
	    if (Weby::$environment === Weby::DEVELOPMENT)
	    {
	        // Just re-throw the exception
	        throw $e;
	    }
	 
	    // Create a 404 response
//	    $request->status = 404;
//	    $request->response = View::factory('template')
//	      ->set('title', '404')
//	      ->set('content', View::factory('errors/404'));
	}
	 
	if ($request->send_headers()->response)
	{
	    // Get the total memory and execution time
	    $total = array(
	      '{memory_usage}' => number_format((memory_get_peak_usage() - WEBY_START_MEMORY) / 1024, 2).'KB',
	      '{execution_time}' => number_format(microtime(TRUE) - WEBY_START_TIME, 5).' seconds');
	 
	    // Insert the totals into the response
	    $request->response = str_replace(array_keys($total), $total, $request->response);
	}
	 
	/**
	 * Display the request response.
	 */
	echo $request->response;
}