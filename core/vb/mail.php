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
* Mail queueing class. This class should be accessed as a singleton via fetchInstance()!
* This class does not actually send emails, but rather queues them to be sent later in a batch.
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-29 02:50:59 +0800
* @copyright 	vBulletin Solutions Inc.
*
*/
class vB_Mail
{
	/**
	* Destination address
	*
	* @var	string
	*/
	protected $toemail = '';

	/**
	* Subject
	*
	* @var	string
	*/
	protected $subject = '';

	/**
	* Message
	*
	* @var	string
	*/
	protected $message = '';

	/**
	* All headers to be sent with the message
	*
	* @var	string
	*/
	protected $headers = '';

	/**
	* Sender email
	*
	* @var	string
	*/
	protected $fromemail = '';

	/**
	* Line delimiter
	*
	* @var	string
	*/
	protected $delimiter = "\r\n";

	/**
	* Switch to enable/disable debugging. When enabled, warnings are not suppressed
	*
	* @var	boolean
	*/
	protected $debug = false;

	/**
	* Message to log if logging is enabled
	*
	* @var	string
	*/
	protected $log = '';
	/**
	* Starts the process of sending an email - either immediately or by adding it to the mail queue.
	*
	* @param string $toemail Destination email address
	* @param string $subject Email message subject
	* @param string $message Email message body
	* @param boolean $sendnow If true, do not use the mail queue and send immediately
	* @param string $from Optional name/email to use in 'From' header
	* @param string	$uheaders Additional headers
	* @param string	$username Username of person sending the email
	* @return bool
	*/
	public static function vbmail($toemail, $subject, $message, $sendnow = false, $from = '', $uheaders = '', $username = '')
	{
		if (empty($toemail))
		{
			return false;
		}

		if (!($mail = self::fetchLibrary(!$sendnow AND vB::getDatastore()->getOption('usemailqueue'))))
		{
			return false;
		}

		if (!$mail->start($toemail, $subject, $message, $from, $uheaders, $username))
		{
			return false;
		}

		$floodReturn['valid'] = true;
		if(!empty($from))
		{
			$floodReturn = self::emailFloodCheck();
		}

		if ($floodReturn['valid'])
		{
			return $mail->send();
		}
		else
		{
			return $floodReturn['error'];
		}
	}

	/**
	* Begin adding email to the mail queue
	*/
	public static function vbmailStart()
	{
		$mail = vB_Mail_Queue::fetchInstance();
		$mail->setBulk(true);
	}

	/**
	* Stop adding mail to the mail queue and insert the mailqueue data for sending later
	*/
	public static function vbmailEnd()
	{
		$mail = vB_Mail_Queue::fetchInstance();
		$mail->setBulk(false);
	}

	/**
	* Reads the email message queue and delivers a number of pending emails to the message sender
	*/
	public static function execMailQueue()
	{
		$vboptions = vB::getDatastore()->getValue('options');
		$mailqueue = vB::getDatastore()->getValue('mailqueue');

		if ($mailqueue !== null AND $mailqueue > 0 AND $vboptions['usemailqueue'])
		{
			// mailqueue template holds number of emails awaiting sending

			$mail = vB_Mail_Queue::fetchInstance();
			$mail->execQueue();
		}
	}

	/**
	* Constructor
	*
	* @param	vB_Registry	vBulletin registry object
	*/
	public function vB_Mail()
	{
		$sendmail_path = @ini_get('sendmail_path');
		if (!$sendmail_path OR vB::getDatastore()->getOption('use_smtp') OR defined('FORCE_MAIL_CRLF'))
		{
			// no sendmail, so we're using SMTP or a server that lines CRLF to send mail // the use_smtp part is for the MailQueue extension
			$this->delimiter = "\r\n";
		}
		else
		{
			$this->delimiter = "\n";
		}
	}

	/**
	 * Factory method for mail.
	 *
	 * @param 	vB_Registry	vBulletin registry object
	 * @param	bool		Whether mail sending can be deferred
	 *
	 * @return	vB_Mail
	 */
	public static function fetchLibrary($deferred = false)
	{
		if ($deferred)
		{
			return vB_Mail_Queue::fetchInstance();
		}

		if (vB::getDatastore()->getOption('use_smtp'))
		{
			return new vB_Mail_Smtp();
		}

		return new vB_Mail();
	}

	/**
	* Starts the process of sending an email - preps it so it's fully ready to send.
	* Call send() to actually send it.
	*
	* @param string	$toemail Destination email address
	* @param string	$subject Email message subject
	* @param string	$message Email message body
	* @param string	$from Optional name/email to use in 'From' header
	* @param string	$uheaders Additional headers
	* @param string	$username Username of person sending the email
	*
	* @return boolean True on success, false on failure
	*/
	public function start($toemail, $subject, $message, $from = '', $uheaders = '', $username = '')
	{
		$toemail = $this->fetchFirstLine($toemail);

		if (empty($toemail))
		{
			return false;
		}

		$delimiter =& $this->delimiter;
		$vboptions = vB::getDatastore()->getValue('options');

		$toemail = vB_String::unHtmlSpecialChars($toemail);
		$subject = $this->fetchFirstLine($subject);
		$message = preg_replace("#(\r\n|\r|\n)#s", $delimiter, trim($message));

		if ((strtolower(vB_Template_Runtime::fetchStyleVar('charset')) == 'iso-8859-1' OR vB_Template_Runtime::fetchStyleVar('charset') == '') AND preg_match('/&[a-z0-9#]+;/i', $message))
		{
			$message = utf8_encode($message);
			$subject = utf8_encode($subject);
			$username = utf8_encode($username);

			$encoding = 'UTF-8';
			$unicode_decode = true;
		}
		else if ($vboptions['utf8encode'])
		{
			$message = to_utf8($message, vB_Template_Runtime::fetchStyleVar('charset'));
			$subject = to_utf8($subject, vB_Template_Runtime::fetchStyleVar('charset'));
			$username = to_utf8($username, vB_Template_Runtime::fetchStyleVar('charset'));

			$encoding = 'UTF-8';
			$unicode_decode = true;
		}
		else
		{
			// we know nothing about the message's encoding in relation to UTF-8,
			// so we can't modify the message at all; just set the encoding
			$encoding = vB_Template_Runtime::fetchStyleVar('charset');
			$unicode_decode = false;
		}

		// theses lines may need to call convert_int_to_utf8 directly
		$message = vB_String::unHtmlSpecialChars($message, $unicode_decode);
		$subject = $this->encodeEmailHeader(vB_String::unHtmlSpecialChars($subject, $unicode_decode), $encoding, false, false);

		$from = $this->fetchFirstLine($from);
		if (empty($from))
		{
			$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('x_mailer'));
			if (isset($vbphrase['x_mailer']))
			{
				$mailfromname = sprintf($this->fetchFirstLine($vbphrase['x_mailer']), $vboptions['bbtitle']);
			}
			else
			{
				$mailfromname = $vboptions['bbtitle'];
			}

			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);
			}
			$mailfromname = $this->encodeEmailHeader(vB_String::unHtmlSpecialChars($mailfromname, $unicode_decode), $encoding);

			$headers = "From: $mailfromname <" . $vboptions['webmasteremail'] . '>' . $delimiter;
			$headers .= 'Auto-Submitted: auto-generated' . $delimiter;

			// Exchange (Oh Microsoft) doesn't respect auto-generated: http://www.vbulletin.com/forum/project.php?issueid=27687
			if ($vboptions['usebulkheader'])
			{
				$headers .= 'Precedence: bulk' . $delimiter;
			}
		}
		else
		{
			if ($username)
			{
				$mailfromname = "$username @ " . $vboptions['bbtitle'];
			}
			else
			{
				$mailfromname = $from;
			}

			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);
			}
			$mailfromname = $this->encodeEmailHeader(vB_String::unHtmlSpecialChars($mailfromname, $unicode_decode), $encoding);

			$headers = "From: $mailfromname <$from>" . $delimiter;
			$headers .= "Sender: " . $vboptions['webmasteremail'] . $delimiter;
		}

		$fromemail = empty($vboptions['bounceemail']) ? $vboptions['webmasteremail'] : $vboptions['bounceemail'];
		$headers .= 'Return-Path: ' . $fromemail . $delimiter;

		$http_host = vB::getRequest()->getVbHttpHost();
		if (!$http_host)
		{
			$http_host = substr(md5($message), 12, 18) . '.vb_unknown.unknown';
		}

		$msgid = '<' . gmdate('YmdHis') . '.' . substr(md5($message . microtime()), 0, 12) . '@' . $http_host . '>';
		$headers .= 'Message-ID: ' . $msgid . $delimiter;

		$headers .= preg_replace("#(\r\n|\r|\n)#s", $delimiter, $uheaders);
		unset($uheaders);

		$headers .= 'MIME-Version: 1.0' . $delimiter;
		$headers .= 'Content-Type: text/plain' . iif($encoding, "; charset=\"$encoding\"") . $delimiter;
		$headers .= 'Content-Transfer-Encoding: 8bit' . $delimiter;
		$headers .= 'X-Priority: 3' . $delimiter;
		$headers .= 'X-Mailer: vBulletin Mail via PHP' . $delimiter;

		$this->toemail = $toemail;
		$this->subject = $subject;
		$this->message = $message;
		$this->headers = $headers;
		$this->fromemail = $fromemail;

		return true;
	}

	/**
	* Set all the necessary variables for sending a message.
	*
	* @param string	$toemail Destination address
	* @param string	$subject Subject
	* @param string	$message Message
	* @param string	$headers All headers to be sent with the message
	* @param string	$fromemail Sender email
	*/
	public function quickSet($toemail, $subject, $message, $headers, $fromemail)
	{
		$this->toemail = $toemail;
		$this->subject = $subject;
		$this->message = $message;
		$this->headers = $headers;
		$this->fromemail = $fromemail;
	}

	/**
	 * Send the mail.
	 * Note: If you define DISABLE_MAIL in config.php as:
	 *	 delimited email addresses	- Only mail for the recipients will be sent
	 *	<filename>.log				- Mail will be logged to the given file if writable
	 *  any other value				- Mail will be disabled
	 *
	 * @param bool $force_send If true, DISABLE_MAIL will be ignored.
	 *
	 * @return boolean True on success, false on failure
	 */
	public function send($force_send = false)
	{
		// No recipient, abort
		if (!$this->toemail)
		{
			return false;
		}

		// Check debug settings
		if (!$force_send AND defined('DISABLE_MAIL'))
		{
			if (is_string(DISABLE_MAIL))
			{
				// check for a recipient whitelist
				if (strpos(DISABLE_MAIL, '@') !== false)
				{
					// check if the address is allowed
					if (strpos($this->toemail, DISABLE_MAIL) === false)
					{
						return false;
					}
				}
				else if (strpos(DISABLE_MAIL, '.log') !== false)
				{
					// mail is only logged
					$this->logEmail('DEBUG', DISABLE_MAIL);

					return true;
				}
				else
				{
					// recipient not in the whitelist and not logging
					return false;
				}
			}
			else
			{
				// DISABLE_MAIL defined but isn't a string so just disable
				return false;
			}
		}

		// Send the mail
		if( $this->execSend())
		{
			vB_Library::instance('user')->updateEmailFloodTime();
		}
		else
		{
			return false;
		}
		return true;

	}

	/**
	* Actually send the message.
	*
	* @return boolean True on success, false on failure
	*/
	protected function execSend()
	{
		if (!$this->toemail)
		{
			return false;
		}

		@ini_set('sendmail_from', $this->fromemail);

		if ((!defined('SAFEMODE') OR !SAFEMODE) AND vB::getDatastore()->getOption('needfromemail'))
		{
			$result =  @mail($this->toemail, $this->subject, $this->message, trim($this->headers), '-f ' . $this->fromemail);
		}
		else
		{
			$result = @mail($this->toemail, $this->subject, $this->message, trim($this->headers));
		}

		$this->logEmail($result);
		return $result;
	}

	/**
	* Returns the first line of a string -- good to prevent errors when sending emails (above)
	*
	* @param string $text String to be trimmed
	*
	* @return string
	*/
	protected function fetchFirstLine($text)
	{
		$text = preg_replace("/(\r\n|\r|\n)/s", "\r\n", trim($text));
		$pos = strpos($text, "\r\n");
		if ($pos !== false)
		{
			return substr($text, 0, $pos);
		}
		return $text;
	}

	/**
	* Encodes a mail header to be RFC 2047 compliant. This allows for support
	* of non-ASCII character sets via the quoted-printable encoding.
	*
	* @param string $text The text to encode
	* @param string $charset The character set of the text
	* @param bool $force_encode Whether to force encoding into quoted-printable even if not necessary
	* @param bool $quoted_string Whether to quote the string; applies only if encoding is not done
	*
	* @return	string	The encoded header
	*/
	protected function encodeEmailHeader($text, $charset = 'utf-8', $force_encode = false, $quoted_string = true)
	{
		$text = trim($text);

		if (!$charset)
		{
			// don't know how to encode, so we can't
			return $text;
		}

		if ($force_encode == true)
		{
			$qp_encode = true;
		}
		else
		{
			$qp_encode = false;

			for ($i = 0; $i < strlen($text); $i++)
			{
				if (ord($text{$i}) > 127)
				{
					// we have a non ascii character
					$qp_encode = true;
					break;
				}
			}
		}

		if ($qp_encode == true)
		{
			// see rfc 2047; not including _ as allowed here, as I'm encoding spaces with it
			$outtext = preg_replace('#([^a-zA-Z0-9!*+\-/ ])#e', "'=' . strtoupper(dechex(ord(str_replace('\\\"', '\"', '\\1'))))", $text);
			$outtext = str_replace(' ', '_', $outtext);
			$outtext = "=?$charset?q?$outtext?=";
			return $outtext;
		}
		else
		{
			if ($quoted_string)
			{
				$text = str_replace(array('"', '(', ')'), array('\"', '\(', '\)'), $text);
				return "\"$text\"";
			}
			else
			{
				return preg_replace('#(\r\n|\n|\r)+#', ' ', $text);
			}
		}
	}

	/**
	* Sets the debug member
	*
	* @param $debug boolean
	*/
	public function setDebug($debug)
	{
		$this->debug = $debug;
	}

	/**
	 * Logs email to file
	 *
	 * @param bool $status
	 * @param bool $errfile
	 *
	 * @return
	 */
	protected function logEmail($status = true, $errfile = false)
	{
		if ((defined('DEMO_MODE') AND DEMO_MODE == true))
		{
			return;
		}

		$vboptions = vB::getDatastore()->getValue('options');

		// log file is passed or taken from options
		$errfile = $errfile ? $errfile : $vboptions['errorlogemail'];

		// no log file specified
		if (!$errfile)
		{
			return;
		}

		// trim .log from logfile
		$errfile = (substr($errfile, -4) == '.log') ? substr($errfile, 0, -4) : $errfile;

		if ($vboptions['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $vboptions['errorlogmaxsize'])
		{
			@copy("$errfile.log", $errfile . vB::getRequest()->getTimeNow() . '.log');
			@unlink("$errfile.log");
		}

		$timenow = date('r', vB::getRequest()->getTimeNow());

		$fp = @fopen("$errfile.log", 'a+b');

		if ($fp)
		{
			if ($status === true)
			{
				$output = "SUCCESS\r\n";
			}
			else
			{
				$output = "FAILED";
				if ($status !== false)
				{
					$output .= ": $status";
				}
				$output .= "\r\n";
			}
			if ($this->delimiter == "\n")
			{
				$append = "$timenow\r\nTo: " . $this->toemail . "\r\nSubject: " . $this->subject . "\r\n" . $this->headers . "\r\n\r\n" . $this->message . "\r\n=====================================================\r\n\r\n";
				@fwrite($fp, $output . $append);
			}
			else
			{
				$append = preg_replace("#(\r\n|\r|\n)#s", "\r\n", "$timenow\r\nTo: " . $this->toemail . "\r\nSubject: " . $this->subject . "\r\n" . $this->headers . "\r\n\r\n" . $this->message . "\r\n=====================================================\r\n\r\n");

				@fwrite($fp, $output . $append);
			}
			fclose($fp);
		}
	}

	public static function emailFloodCheck()
	{
		$usercontext = vB::getCurrentSession()->fetch_userinfo();
		$timenow =  vB::getRequest()->getTimeNow();
		$timepassed = $timenow - $usercontext['emailstamp'];
		$vboptions = vB::getDatastore()->getValue('options');

		if($vboptions['emailfloodtime'] > 0 AND $timepassed < $vboptions['emailfloodtime'] AND !$usercontext['is_admin'])
		{
			return array('valid' => false, 'error' => array("emailfloodcheck", array($vboptions['emailfloodtime'],($vboptions['emailfloodtime'] - $timepassed))));
		}

		return array('valid' => true, 'error' => array());
	}
}
