<?php

class vB5_Frontend_Controller_Admin extends vB5_Frontend_Controller
{

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * This method was previously used by pagetemplate_edit
	 * @deprecated
	 */
	public function pagetemplateSave()
	{
		$api = $this->getApi();

		// we need an input cleaner
		$input = array(
			'templatetitle' => trim(strval($_POST['templatetitle'])),
			'screenlayoutid' => intval($_POST['screenlayoutid']),
			'pagetemplateid' => intval($_POST['pagetemplateid']),
		);

		if (empty($input['templatetitle']))
		{
			echo 'The title cannot be empty. Please go back and correct this problem.';
			exit;
		}
		if ($input['screenlayoutid'] < 1)
		{
			echo 'You must specify a screen layout. Please go back and correct this problem.';
			exit;
		}


		// page template
		$valuePairs = array(
			'title' => $input['templatetitle'],
			'screenlayoutid' => $input['screenlayoutid'],
		);

		$pagetemplateid = $input['pagetemplateid'];
		if ($pagetemplateid < 1)
		{
			// If no widgets were configured on the page template, we won't have a page template ID.
			$pagetemplateid = $api->callApi('database', 'insert', array('pagetemplate', $valuePairs));
		}
		else
		{
			$api->callApi('database', 'update', array('pagetemplate', $valuePairs, "pagetemplateid = $pagetemplateid"));
		}

		// widgets

		// we need a dedicated input cleaner
		$columns = array();
		$input['displaysections'] = (array) $_POST['displaysections'];
		foreach ($input['displaysections'] AS $sectionNumber => $widgetInfo)
		{
			$columns[intval($sectionNumber)] = explode(',', trim(strval($widgetInfo)));
		}

		$widgets = array();

		foreach ($columns as $displaycolumn => $columnwidgets)
		{
			$displayorder = 0;
			foreach ($columnwidgets as $columnwidget)
			{
				if (strpos($columnwidget, '=') !== false)
				{
					list($columnwidgetid, $columnwidgetinstanceid) = explode('=', $columnwidget, 2);
					$columnwidgetid = (int) $columnwidgetid;
					$columnwidgetinstanceid = (int) $columnwidgetinstanceid;
				}
				else
				{
					$columnwidgetid = (int) $columnwidget;
					$columnwidgetinstanceid = 0;
				}

				if (!$columnwidgetid)
				{
					continue;
				}

				$widgets[] = array(
					'widgetinstanceid' => $columnwidgetinstanceid,
					'pagetemplateid'   => $pagetemplateid,
					'widgetid'         => $columnwidgetid,
					'displaysection'   => $displaycolumn,
					'displayorder'     => $displayorder,
				);

				++$displayorder;
			}
		}

		foreach ($widgets as $widget)
		{
			$widgetinstanceid = $widget['widgetinstanceid'];
			unset($widget['widgetinstanceid']);

			if ($widgetinstanceid > 0)
			{
				$api->callApi('database', 'update', array('widgetinstance', $widget, "widgetinstanceid = $widgetinstanceid"));
			}
			else
			{
				$api->callApi('database', 'insert', array('widgetinstance', $widget));
			}
		}



		// return to the page they were on (if applicable)
		$returnUrl = vB5_Config::instance()->baseurl;
		if (isset($_REQUEST['return']) AND $_REQUEST['return'] == 'page')
		{
			$returnPageId = (int) $_REQUEST['pageid'];
			$page = $api->callApi('page', 'fetchPageById', array($pageid));
			if ($page)
			{
				$returnUrl = $page['url'];
			}
		}

		header('Location: ' . $returnUrl);
		exit;
	}

	protected function getApi()
	{
		return Api_InterfaceAbstract::instance();
	}
}
