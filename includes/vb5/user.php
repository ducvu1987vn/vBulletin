<?php

/**
 * Singleton object for accessing information about the currently logged in user
 */
class vB5_User implements ArrayAccess
{
	/**
	 * Singleton instance
	 * @var	vB5_User
	 */
	protected static $instance = null;

	/**
	 * User inforamtion
	 * @var	array
	 */
	protected $data = array();

	/**
	 * Singleton instance getter
	 *
	 * @return	vB5_User
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	protected function __construct()
	{
		$this->data = Api_InterfaceAbstract::instance()->callApi('user', 'fetchCurrentUserinfo', array());
	}

	/**
	 * Returns information from the user array
	 *
	 * @param	string	Key in the user array
	 *
	 * @return	mixed	Value
	 */
	protected function _get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	/**
	 * Static getter
	 *
	 * @param	string	Key in the user array
	 *
	 * @return	mixed	Value
	 */
	public static function get($key)
	{
		return self::instance()->_get($key);
	}

	/**
	 * Magic getter
	 *
	 * @param	string	Key in the user array
	 *
	 * @return	mixed	Value
	 */
	public function __get($key)
	{
		return $this->_get($key);
	}

	public static function getLanguageId()
	{
		if ($languageid = vB5_Cookie::get('languageid', vB5_Cookie::TYPE_UINT))
		{
			return $languageid;
		}
		else
		{
			return self::instance()->_get('languageid');
		}
	}

	/**
	 * Functions to implement array access for this object
	 */

	public function offsetSet($key, $value)
	{
		throw new Exception('Cannot set user values via vB5_User');
	}

	public function offsetUnset($key)
	{
		throw new Exception('Cannot change user values via vB5_User');
	}

	public function offsetExists($key)
	{
		return isset($this->data[$key]);
	}

	public function offsetGet($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

}
