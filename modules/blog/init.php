<?php defined('SYSPATH') or die('No direct script access.');

// Translated user guide
Route::set('blog/test', 'blog/test(/<page>)', array(
		'page' => '.+',
	))
	->defaults(array(
		'controller' => 'blog',
		'action'     => 'test',
	));

