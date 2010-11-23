<?php
/**
 * The directory in which your application specific resources are located.
 * The application directory must contain the bootstrap.php file.
 */
$application = 'application';

/**
 * The directory in which your modules are located.
 */
$modules = 'modules';

/**
 * The directory in which the Kohana resources are located. The system
 * directory must contain the classes/weby.php file.
 */
$system = 'system';

define('EXT', '.php');

// ini_set('display_errors', TRUE);
error_reporting(E_ALL | E_STRICT);
// When you application is live and in production
// error_reporting(E_ALL & ~E_NOTICE);

define('DOCROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR);

if (!is_dir($application) AND is_dir(DOCROOT.$application))
	$application = DOCROOT.$application;

if ( ! is_dir($modules) AND is_dir(DOCROOT.$modules))
	$modules = DOCROOT.$modules;

if (!is_dir($system) AND is_dir(DOCROOT.$system))
	$system = DOCROOT.$system;

// Define the absolute paths for configured directories
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('MODPATH', realpath($modules).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);

// Load the base, low-level functions
require SYSPATH.'base'.EXT;

// Clean up the configuration vars
unset($application, $modules, $system);

if (file_exists('install'.EXT)) {
	// Load the installation check
//	return include 'install'.EXT;
}

// Load the core Weby class
if (is_file(APPPATH.'classes/weby'.EXT)) {
	require APPPATH.'classes/weby'.EXT;
} else {
	require SYSPATH.'classes/weby'.EXT;
}

// Bootstrap the application
require APPPATH.'bootstrap'.EXT;