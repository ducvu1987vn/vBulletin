<?php

/**
 *	Class to handle fetching the template filenames when stored on the filesystem.
 *	Note that this only works in collapsed mode (non collasped mode is currently not implemented)
 *	and requires that the template file is the same path for both front end and backend code. 
 */
class vB5_Template_Cache_Filesystem extends vB5_Template_Cache
{
	
	public function isTemplateText()
	{
		return false;
	}

	/**
	 * Receives either a template name or an array of template names to be fetched from the API
	 * @param mixed $templateName
	 */
	protected function fetchTemplate($templateName)
	{
		if (!is_array($templateName))
		{
			$templateName = array($templateName);
		}

		$response = Api_InterfaceAbstract::instance()->callApi('template', 'getTemplateIds', array('template_names' => $templateName));

		$template_path = vB5_Template_Options::instance()->get('options.template_cache_path');
		if (isset($response['ids']))
		{
			foreach ($response['ids'] AS $name => $templateid)
			{

				$file_name = "template$templateid.php";

				//this matches the filename logic from template library saveTemplateToFileSystem and needs to
				//so that we come up with the same file in both cases.
				$real_path = realpath($template_path);
				if ($real_path === false)
				{
					$real_path = realpath(vB5_Config::instance()->core_path . '/' . $template_path);
				}

				if ($real_path === false)
				{
					$file = false;
				}
				else
				{
					$file = $real_path . "/$file_name";
				}


				$this->cache[$name] = $file;
				
			}
		}
	}
}
