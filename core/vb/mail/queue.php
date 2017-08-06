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
class vB_Mail_Queue extends vB_Mail
{
	/**
	* The data to insert into the mail queue
	*
	* @var	array
	*/
	protected $mailsql = array();

	/**
	* The number of mails being inserted into the queue
	*
	* @var	string
	*/
	protected $mailcounter = 0;

	/**
	* Whether to do bulk inserts into the database.
	* Never set this option directly!
	*
	* @var	boolean
	*/
	protected $bulk = false;

	protected function __construct()
	{
		// Register shutdown function here
		vB_Shutdown::instance()->add(array('vB_Mail', 'execMailQueue'));
	}

	/**
	* Inserts the message into the queue instead of sending it.
	*
	* @return	string	True on success, false on failure
	*/
	protected function execSend()
	{
		if (!$this->toemail)
		{
			return false;
		}

		$data = array(
			'dateline' => vB::getRequest()->getTimeNow(),
			'toemail' => $this->toemail,
			'fromemail' => $this->fromemail,
			'subject' => $this->subject,
			'message' => $this->message,
			'header' => $this->headers,
		);

		if ($this->bulk)
		{
			$this->mailsql[] = $data;

			$this->mailcounter++;

			// current insert exceeds half megabyte, insert it and start over
			if ($this->arraySize($this->mailsql) > 524288)
			{
				$this->setBulk(false);
				$this->setBulk(true);
			}
		}
		else
		{
			/*insert query*/
			vB::getDbAssertor()->insert('mailqueue', $data);

			vB::getDbAssertor()->assertQuery('mailqueue_updatecount', array('counter' => 1));

			// if we're using a alternate datastore, we need to give it an integer value
			// this may not be atomic
			$mailqueue_db = vB::getDbAssertor()->getRow('datastore', array('title' => 'mailqueue'));
			vB::getDatastore()->build('mailqueue', intval($mailqueue_db['data']));
		}

		return true;
	}

	protected function arraySize($a)
	{
	    $size = 0;
	    foreach($a as $v)
	    {
	        $size += is_array($v) ? $this->arraySize($v) : strlen($v);
	    }
	    return $size;
	}

	/**
	* Sets the bulk option. If disabling the option, this also flushes
	* the cache into the database.
	*
	* @param boolean $bulk
	*/
	function setBulk($bulk)
	{
		if ($bulk)
		{
			$this->bulk = true;
			$this->mailcounter = 0;
			$this->mailsql = '';
		}
		else if ($this->mailcounter AND $this->mailsql)
		{
			// turning off bulk sending, so save all the mails

			/*insert query*/
			$insertdata = array();
			foreach ($this->mailsql as $sql)
			{
				$insertdata[] = array_values($sql);
			}

			vB::getDbAssertor()->insertMultiple('mailqueue', array_keys($this->mailsql[0]), $insertdata);

			$currentcount = vB::getDatastore()->getValue('mailqueue');

			// if we're using a alternate datastore, we need to give it an integer value
			// this may not be atomic
			vB::getDatastore()->build('mailqueue', intval($currentcount) + $this->mailcounter);
		}

		$this->bulk = true;
		$this->mailsql = array();
		$this->mailcounter = 0;
	}

	/**
	* Singleton emulator. Fetches the instance if it doesn't exist.
	* Be sure to accept a reference if using this function!
	*
	* @return	vB_QueueMail	Reference to the instance
	*/
	public static function fetchInstance()
	{
		static $instance = null;

		if ($instance === null)
		{
			$instance = new vB_Mail_Queue();
		}

		return $instance;
	}

	/**
	* The only part of this class which actually sends an email.
	* Sends mail from the queue.
	*/
	public function execQueue()
	{
		$vboptions = vB::getDatastore()->getValue('options');
		if ($vboptions['usemailqueue'] == 2)
		{
			// Lock mailqueue table so that only one process can
			// send a batch of emails and then delete them
			vB::getDbAssertor()->assertQuery('mailqueue_locktable');
		}

		$emails = vB::getDbAssertor()->getRows('mailqueue_fetch', array(
			'limit' => intval($vboptions['emailsendnum'])
		));

		$mailqueueids = array();
		$newmail = 0;
		$emailarray = array();
		foreach ($emails as $email)
		{
			// count up number of mails about to send
			$mailqueueids[] = $email['mailqueueid'];
			$newmail++;
			$emailarray[] = $email;
		}
		if (!empty($mailqueueids))
		{
			// remove mails from queue - to stop duplicates being sent
			vB::getDbAssertor()->delete('mailqueue', array('mailqueueid' => $mailqueueids));

			if ($vboptions['usemailqueue'] == 2)
			{
				vB::getDbAssertor()->assertQuery('unlock_tables');
			}

			$prototype = vB_Mail::fetchLibrary();

			foreach ($emailarray AS $email)
			{
				// send those mails
				$mail = clone($prototype);
				$mail->quickSet($email['toemail'], $email['subject'], $email['message'], $email['header'], $email['fromemail']);
				$mail->send();
			}

			$newmail = 'data - ' . intval($newmail);
		}
		else
		{
			if ($vboptions['usemailqueue'] == 2)
			{
				vB::getDbAssertor()->assertQuery('unlock_tables');
			}

			$newmail = 0;
		}

		// update number of mails remaining
		vB::getDbAssertor()->assertQuery('mailqueue_updatecount2', array('newmail' => $newmail));

		// if we're using a alternate datastore, we need to give it an integer value
		// this may not be atomic
		$mailqueue_db = vB::getDbAssertor()->getRow('datastore', array('title' => 'mailqueue'));
		vB::getDatastore()->build('mailqueue', intval($mailqueue_db['data']));
	}
}
