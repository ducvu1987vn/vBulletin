<?php

class vB5_Frontend_Controller
{
	/** vboptions **/
	protected $vboptions = array();

	function __construct()
	{
		$vboptions = vB5_Template_Options::instance()->getOptions();
		$this->vboptions = $vboptions['options'];
	}

	/**
	 * Sends the response as a JSON encoded string
	 *
	 * @param	mixed	The data (usually an array) to send
	 */
	public function sendAsJson($data)
	{
		if (headers_sent($file, $line))
		{
			throw new Exception("Cannot send response, headers already sent. File: $file Line: $line");
		}

		// We need to convert $data charset if we're not using UTF-8
		if (vB5_String::getTempCharset() != 'UTF-8')
		{
			$data = vB5_String::toCharset($data, vB5_String::getTempCharset(), 'UTF-8');
		}

		//If this is IE9 we need to send type "text/html".
		//Yes, we know that's not the standard.
		if (isset($_SERVER['HTTP_USER_AGENT']) &&
			(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
		{
			header('Content-type: text/plain; charset=UTF-8');
		}
		else
		{
			header('Content-type: application/json; charset=UTF-8');
		}

		// IE will cache ajax requests, and we need to prevent this - VBV-148
		header('Cache-Control: max-age=0,no-cache,no-store,post-check=0,pre-check=0');
		header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Pragma: no-cache");
		header("ContentEncoding: UTF8");

		if (isset($data['template']) AND !empty($data['template']))
		{
			$data['template'] = $this->outputPage($data['template'], false);
		}
		echo json_encode($data);
	}

	/**
	 * Show a simple and clear message page which contains no widget
	 *
	 * @param string $title Page title. HTML will be escaped.
	 * @param string $msg Message to display. HTML is allowed and the caller must make sure it's valid.
	 */
	public function showMsgPage($title, $msg)
	{
		//We want the simplest possible page.
		$templater = new vB5_Template('message_page');

		$router = vB5_ApplicationAbstract::instance()->getRouter();
		$arguments = $router->getArguments();
		$page = array();
		$page['routeInfo'] = array(
			'routeId' => $router->getRouteId(),
			'arguments' => $arguments,
			'queryParameters' => $router->getQueryParameters(),
		);
		$page['title'] = $title;
		$page['ignore_np_notices'] = isset($_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) ?
			explode(',', $_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) : array();
		$templater->registerGlobal('page', $page);
		$templater->register('message', $msg);
		$this->outputPage($templater->render());
	}

	/**
	 * Replaces special characters in a given string with dashes to make the string SEO friendly
	 * Note: This is really restrictive. If it can be helped, leave it to core's vB_String::getUrlIdent. 
	 *
	 * @param	string	The string to be converted
	 */
	protected function toSeoFriendly($str)
	{
		if (!empty($str))
		{
			return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($str)), '-');
		}
		return $str;
	}

	/**
	 * Handle errors that are returned by API for use in JSON AJAX responses.
	 *
	 * @param	mixed	The result array to populate errors into. It will contain error phrase ids.
	 * @param	mixed	The returned object by the API call.
	 *
	 * @return	boolean	true errors are found, false, otherwise.
	 */
	protected function handleErrorsForAjax(&$result, $return)
	{
		if ($return AND !empty($return['errors']))
		{
			if (isset($return['errors'][0][1]))
			{
				// it is a phraseid with variables
				$errorList = array($return['errors'][0]);
			}
			else
			{
				$errorList = array($return['errors'][0][0]);
			}

			if (!empty($result['error']))
			{
				//merge and remove duplicate error ids
				$errorList = array_merge($errorList, $result['error']);
				$errorList = array_unique($errorList);
			}

			$result['error'] = $errorList;
			return true;
		}
		return false;
	}

	/**
	 * Checks if this is a POST request
	 */
	protected function verifyPostRequest()
	{
		if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST')
		{
			// show exception and stack trace in debug mode
			throw new Exception('This action only available via POST');
		}
	}

	/**
	 * Any final processing, and then output the page
	 */
	protected function outputPage($html, $exit = true)
	{
		$styleid = vB5_Template_Stylevar::instance()->getPreferredStyleId();

		if (!$styleid)
		{
			$styleid = $this->vboptions['styleid'];
		}

		$fullPage = Api_InterfaceAbstract::instance()->callApi('template', 'processReplacementVars', array($html, $styleid));

		if ($exit)
		{
			echo $fullPage;
			exit;
		}

		return $fullPage;
	}
}
