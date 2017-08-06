<?php
class vB5_Route_Search extends vB5_Route
{
	public function getUrl()
	{
		// the regex contains the url
		$url = '/' . $this->regex;
		
		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}
		
		return $url;
	}

	public function getCanonicalRoute()
	{
		if (!isset($this->canonicalRoute))
		{
			if (!empty($this->arguments['pageid']))
			{
				$page = vB::getDbAssertor()->getRow('page', array('pageid'=>$this->arguments['pageid']));
			}
			if (!empty($page['routeid']))
			{
				$this->canonicalRoute = self::getRoute($page['routeid']);
			}
			else
			{
				return $this;
			}
		}

		return $this->canonicalRoute;
	}

	protected static function validInput(array &$data)
	{
		if (
				!isset($data['contentid']) OR !is_numeric($data['contentid']) OR
				!isset($data['prefix']) OR
				!isset($data['action'])
			)
		{
			return FALSE;
		}

		$data['regex'] = $data['prefix'];
		$data['class'] = __CLASS__;
		$data['controller']	= 'search';
		$data['arguments']	= '';//serialize(array('pageid' => $data['contentid']));

		return parent::validInput($data);
	}

}
?>