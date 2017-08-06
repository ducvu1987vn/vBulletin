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
* SMTP Mail Sending Object
*
* This class sends email from vBulletin using an SMTP wrapper
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-29 02:50:59 +0800
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Mail_Smtp extends vB_Mail
{
	/**
	* SMTP host
	*
	* @var	string
	*/
	protected $smtpHost;

	/**
	* SMTP port
	*
	* @var	integer
	*/
	protected $smtpPort;

	/**
	* SMTP username
	*
	* @var	string
	*/
	protected $smtpUser;

	/**
	* SMTP password
	*
	* @var	string
	*/
	protected $smtpPass;

	/**
	* Raw SMTP socket
	*
	* @var	resource
	*/
	protected $smtpSocket = null;

	/**
	* Return code from SMTP server
	*
	* @var	integer
	*/
	protected $smtpReturn = 0;

	/**
	* What security method to use
	*
	* @var	string
	*/
	protected $secure = '';

	/**
	* Constructor
	*/
	function __construct()
	{
		$vboptions = vB::getDatastore()->getValue('options');
		$this->secure = $vboptions['smtp_tls'];

		// Prior to 3.8 this was a radio button so SSL is 1
		if ($vboptions['smtp_tls'] == 1)
		{
			$this->secure = 'ssl';
		}

		//since ('ssl' == 0) is true in php, we need to check for legacy 0 values as well
		//note that in the off change that somebody gets '0' into the system, this will
		//work just fine without conversion.
		else if ($vboptions['smtp_tls'] === 0)
		{
			$this->secure = 'none';
		}

		$this->smtpHost = $vboptions['smtp_host'];
		$this->smtpPort = (!empty($vboptions['smtp_port']) ? intval($vboptions['smtp_port']) : 25);
		$this->smtpUser =& $vboptions['smtp_user'];
		$this->smtpPass =& $vboptions['smtp_pass'];

		$this->delimiter = "\r\n";
	}

	/**
	* Sends instruction to SMTP server
	*
	* @param string $msg Message to be sent to server
	* @param mixed $expectedResult Message code expected to be returned or false if non expected
	*
	* @return boolean Returns false on error
	*/
	function sendMessage($msg, $expectedResult = false)
	{
		if ($msg !== false AND !empty($msg))
		{
			fputs($this->smtpSocket, $msg . "\r\n");
		}
		if ($expectedResult !== false)
		{
			$result = '';
			while ($line = @fgets($this->smtpSocket, 1024))
			{
				$result .= $line;
				if (preg_match('#^(\d{3}) #', $line, $matches))
				{
					break;
				}
			}
			$this->smtpReturn = intval($matches[1]);
			return ($this->smtpReturn == $expectedResult);
		}
		return true;
	}

	/**
	* Triggers PHP warning on error
	*
	* @param string $msg Error message to be shown
	*
	* @return boolean Always returns false (error)
	*/
	function errorMessage($msg)
	{
		if ($this->debug)
		{
			trigger_error($msg, E_USER_WARNING);
		}
		$this->logEmail($msg);
		return false;
	}

	function sendHello()
	{
		if (!$this->smtpSocket)
		{
			return false;
		}
		if (!$this->sendMessage('EHLO ' . $this->smtpHost, 250))
		{
			if (!$this->sendMessage('HELO ' . $this->smtpHost, 250))
			{
				return false;
			}
		}
		return true;
	}

	/**
	* Attempts to send email based on parameters passed into start()/quick_set()
	*
	* @return boolean Returns false on error
	*/
	protected function execSend()
	{
		if (!$this->toemail)
		{
			return false;
		}

		$this->smtpSocket = fsockopen(($this->secure == 'ssl' ? 'ssl://' : 'tcp://') . $this->smtpHost, $this->smtpPort, $errno, $errstr, 30);

		if ($this->smtpSocket)
		{
			if (!$this->sendMessage(false, 220))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response when connecting to SMTP server');
			}

			// do initial handshake
			if (!$this->sendHello())
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during handshake');
			}

			if ($this->secure == 'tls' AND function_exists('stream_socket_enable_crypto'))
			{
				if ($this->sendMessage('STARTTLS', 220))
				{
					if (!stream_socket_enable_crypto($this->smtpSocket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
					{
						return $this->errorMessage('Unable to negotitate TLS handshake.');
					}
				}

				// After TLS say Hi again
				$this->sendHello();
			}

			if ($this->smtpUser AND $this->smtpPass)
			{
				if ($this->sendMessage('AUTH LOGIN', 334))
				{
					if (!$this->sendMessage(base64_encode($this->smtpUser), 334) OR !$this->sendMessage(base64_encode($this->smtpPass), 235))
					{
						return $this->errorMessage($this->smtpReturn . ' Authorization to the SMTP server failed');
					}
				}
			}

			if (!$this->sendMessage('MAIL FROM:<' . $this->fromemail . '>', 250))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during FROM address transmission');
			}

			// we could have multiple addresses since a few people might expect this to be the same as PHP
			$addresses = explode(',', $this->toemail);
			foreach ($addresses AS $address)
			{
				if (!$this->sendMessage('RCPT TO:<' . trim($address) . '>', 250))
				{
					return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during TO address transmission');
				}
			}
			if ($this->sendMessage('DATA', 354))
			{
				$this->sendMessage('Date: ' . gmdate('r'), false);
				$this->sendMessage('To: ' . $this->toemail, false);
				$this->sendMessage(trim($this->headers), false); // trim to prevent double \r\n
				$this->sendMessage('Subject: ' . $this->subject, false);
				$this->sendMessage("\r\n", false); // this makes a double \r\n
				// catch any single dots on their own
				$this->message = preg_replace('#^\.' . $this->delimiter . '#m', '..' . $this->delimiter, $this->message);
				$this->sendMessage($this->message, false);
			}
			else
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during data transmission');
			}

			if (!$this->sendMessage('.', 250))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server when ending transmission');
			}

			// Don't check that QUIT returns a valid result as some servers just kill the connection e.g. smtp.gmail.com
			$this->sendMessage('QUIT', 221);

			fclose($this->smtpSocket);
			$this->logEmail();
			return true;
		}
		else
		{
			return $this->errorMessage('Unable to connect to SMTP server');
		}
	}
}
