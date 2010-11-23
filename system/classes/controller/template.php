<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Abstract controller class for automatic templating.
 *
 * @package    Weby
 * @category   Controller
 * @author     Weby Team
 * @copyright  (c) 2008-2009 Weby Team
 * @license    http://kohanaphp.com/license
 */
abstract class Controller_Template extends Controller {

	/**
	 * @var  string  page template
	 */
	public $template = 'template';

	/**
	 * @var  boolean  auto render template
	 **/
	public $auto_render = TRUE;

	/**
	 * Loads the template [View] object.
	 */
	public function before()
	{
		if ($this->auto_render === TRUE)
		{
			// Load the template
			$this->template = View::factory($this->template);
		}

		return parent::before();
	}

	/**
	 * Assigns the template [View] as the request response.
	 */
	public function after()
	{
		if ($this->auto_render === TRUE)
		{
			$this->request->response = $this->template;
		}

		return parent::after();
	}

} // End Controller_Template
