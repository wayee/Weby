<?php defined('SYSPATH') or die('No direct access');
/**
 * Weby exception class.
 *
 * @package    Weby
 * @category   Exceptions
 * @author     Weby Team
 * @copyright  (c) 2010 Weby Team
 */
class Weby_Exception extends Exception {

	/**
	 * Creates a new translated exception.
	 *
	 *     throw new Weby_Exception('Something went terrible wrong, :user',
	 *         array(':user' => $user));
	 *
	 * @param   string   error message
	 * @param   array    translation variables
	 * @param   integer  the exception code
	 * @return  void
	 */
	public function __construct($message, array $variables = NULL, $code = 0)
	{
		// Set the message
//		$message = __($message, $variables);

		// Pass the message to the parent
		parent::__construct($message, $code);
	}

	/**
	 * Magic object-to-string method.
	 *
	 *     echo $exception;
	 *
	 * @uses    Weby::exception_text
	 * @return  string
	 */
	public function __toString()
	{
		return Weby::exception_text($this);
	}

} // End Weby_Exception
