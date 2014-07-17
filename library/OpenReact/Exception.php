<?php
/**
	OpenReact

  	LICENSE:
  	This source file is subject to the Simplified BSD license that is
  	bundled	with this package in the file LICENSE.txt.
	It is also available through the world-wide-web at this URL:
	http://account.react.com/license/simplified-bsd
	If you did not receive a copy of the license and are unable to
	obtain it through the world-wide-web, please send an email
	to openreact-license@react.com so we can send you a copy immediately.

	Copyright (c) 2011 React B.V. (http://www.react.com)
*/
/**
	Exception class for usage by OpenReact classes.
	Add message parameters plus exception 'autocreation'.
*/
class OpenReact_Exception extends Exception
{
	/**
		Construct an exception.

		Parameters:
			message - (string) Exception message, also supports printf() formatting with the second parameters
			params - (array) Parameters for insertion in the message
			cause - (Exception|null) Exception which 'caused' this exception
			code - (int) Error/exception code
	*/
	public function __construct($message, array $params = array(), $cause = null, $code = 0)
	{
		if (false !== strpos($message, '%s') && is_array($params) && !empty($params))
			$message = vsprintf($message, $params);

		parent::__construct($message, $code, $cause);
	}

	/**
		Try to auto-create a non-existing OpenReact_*Exception class.

		Parameters:
			className - (string) class to autoload

		Returns:
			(boolean) if the class was loaded (and thus created)
	*/
	public static function autocreate($className)
	{
		if (strpos($className, 'OpenReact_') !== 0 || substr($className, -strlen('Exception')) !== 'Exception')
			return false;

		eval('class ' . $className . ' extends OpenReact_Exception {};');
	}
}