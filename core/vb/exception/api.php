<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Api Exception
 * Exception thrown by API methods
 */
class vB_Exception_Api extends vB_Exception
{
	protected $errors = array();

	public function __construct($phrase_id = '', $args = array(), $message = '', $line = false, $code = false, $file = false, $line = false)
	{
		$fullmessage = '<b>API Error</b><br>';

		if ($message)
		{
			$fullmessage .= '<b>Message:</b>: ' . htmlspecialchars($message) . '<br>';
		}

		if (!empty($line))
		{
			$fullmessage .= '<b>Line:</b> ' . htmlspecialchars($line) . '<br>';
		}

		if ($phrase_id)
		{
			if (is_array($phrase_id))
			{
				$phrase_id = array_pop($phrase_id);
			}
			if (!is_array($args))
			{
				$args = array($args);
			}
			$this->add_error($phrase_id, $args);
			$fullmessage .= '<b>Error:</b> ' . htmlspecialchars($phrase_id) . '<br>';
			if ($args)
			{
				$fullmessage .= '<b>Args:</b><br><pre style="font-family:Lucida Console,Monaco5,monospace;font-size:small;overflow:auto;border:1px solid #CCC;">';
				$fullmessage .= htmlspecialchars(var_export($args, true));
				$fullmessage .= '</pre>';
			}
		}

		parent::__construct($fullmessage, $code, $file, $line);
	}

	public function add_error($phrase_id, array $args = array())
	{
		$error = $args;
		array_unshift($error, $phrase_id);
		$this->errors[] = $error;
	}

	public function has_errors()
	{
		return !empty($this->errors);
	}

	public function get_errors()
	{
		return $this->errors;
	}

	public function has_error($phrase_id)
	{
		if (!$this->has_errors())
		{
			return false;
		}
		foreach ($this->errors as $error)
		{
			if (in_array($phrase_id, $error))
			{
				return true;
			}
		}
		return false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 34912 $
|| ####################################################################
\*======================================================================*/
