<?php

class vB5_Admin_Controller_Sitebuilder extends vB5_Admin_Controller
{
	protected $_step = 1;
	protected $_total_steps = 8;
	protected $_pagetitle = '';
	protected $_steptitle = '';
	protected $_dateformat = array(
		'fulldate' => 'F j, Y g:i A'
	);

	protected $_navigation_items = array(
		array(
			'route'  => 'sitebuilder',
			'anchor' => '1. Site Theme',
		),
		array(
			'route'  => 'sitebuilder/header',
			'anchor' => '2. Header',
		),
		array(
			'route'  => 'sitebuilder/pagetemplates',
			'anchor' => '3. Page Templates',
		),
		array(
			'route'  => 'sitebuilder/pages',
			'anchor' => '4. Pages',
		),
		array(
			'route'  => 'sitebuilder/topnavbar',
			'anchor' => '5. Top Navigation Menu',
		),
		array(
			'route'  => 'sitebuilder/bottomnavbar',
			'anchor' => '6. Bottom Navigation Menu',
		),
		array(
			'route'  => 'sitebuilder/advertising',
			'anchor' => '7. Advertising',
		),
		array(
			'route'  => 'sitebuilder/publish',
			'anchor' => '8. Publish',
		),
	);

	// @todo instead of this function, we could probably use the new
	// template inclusion tag to pull in the header and footer templates.
	protected function _render_sitebuilder_page($content)
	{
		$config = vB5_Config::instance();

		$baseurl = $config->baseurl;

		/*$stylesheets = array(
			$config->admincpdir.'/css/base.css',
			$config->admincpdir.'/css/sitebuilder.css',
			$config->admincpdir.'/css/jquery-ui.css',
			$config->admincpdir.'/css/jquery.ui.spinner.css',
		);*/


		$cnt = count($this->_navigation_items);
		for ($i = 0; $i < $cnt; ++$i)
		{
			//$this->_navigation_items[$i]['checked'] = $i + 1 < $this->_step;
			//$this->_navigation_items[$i]['selected'] = $i + 1 == $this->_step;

			$class = $i + 1 < $this->_step ? 'checked' : ($i + 1 == $this->_step ? 'selected' : '');
			$this->_navigation_items[$i]['class'] = $class ? " class=\"$class\"" : '';
		}

		$templater = new vB5_Template('admin_sitebuilder_header');
		$templater->register('baseurl', $baseurl);
		//$templater->register('stylesheets', $stylesheets);
		$templater->register('navigation_items', $this->_navigation_items);
		$templater->register('completion_percentage', intval(floor((($this->_step - 1) * 100) / $this->_total_steps)));
		$templater->register('pagetitle', $this->_pagetitle);
		$templater->register('steptitle', $this->_steptitle);
		$templater->register('page_cssclass', $this->_page_cssclass);
		$header = $templater->render(true);

		$templater = new vB5_Template('admin_sitebuilder_footer');
		$templater->register('baseurl', $baseurl);
		$templater->register('step_count', $this->_step);
		$footer = $templater->render(true);

		return $header . $content . $footer;
	}

	protected function _render_sitebuilder_navlesspage($content, $stylesheets, $scripts)
	{
		$baseurl = vB5_Config::instance()->baseurl;
		$templater = new vB5_Template('admin_header');
		$templater->register('baseurl', $baseurl);
		$templater->register('pagetitle', $this->_pagetitle);
		$templater->register('page_cssclass', $this->_page_cssclass);
		$templater->register('stylesheets', $stylesheets);
		$header = $templater->render(true);

		$templater = new vB5_Template('admin_footer');
		$templater->register('baseurl', $baseurl);
		$templater->register('scripts', $scripts);
		$footer = $templater->render(true);

		return $header . $content . $footer;
	}

	protected function _render_iframe_response($success, $message, $data)
	{
		//do nothing if form was submitted normally and not submitted to hidden iframe
		if ($_POST['iframeSubmit'] == "1"){
			$json = array(
				'success' => $success,
				'message' => $message,
				'data' => $data
			);
			echo '
				<script type="text/javascript">
					if (typeof parent.onRenderIframeResponseCallback == "function"){
						parent.onRenderIframeResponseCallback('. json_encode($json) .');
					}
				</script>
			';
			exit;
		}
	}

	// default action
	public function action_index()
	{
		$this->action_theme();
	}

	// this should be a status page with saved instances of the sitebuilder wizard.
	public function action_status()
	{
		$baseurl = vB5_Config::instance()->baseurl;
		$config = vB5_Config::instance();
		$this->_pagetitle = 'Site Builder Status';
		$this->_page_cssclass = 'sb-status';

		$stylesheets = array(
			$baseurl.'/'.$config->admincpdir.'/css/jquery-ui.css'
		);

		$scripts = array(
			$baseurl.'/'.$config->admincpdir.'/js/jquery-1.4.2.min.js',
			$baseurl.'/'.$config->admincpdir.'/js/jquery-ui.min.js',
			$baseurl.'/'.$config->admincpdir.'/js/sitebuilder_status.js'
		);

		$templater = new vB5_Template('admin_sitebuilder_status');
		$templater->register('baseurl', $baseurl);
		$templater->register('navigation_items', $this->_navigation_items);
		$templater->register('dateformat', $this->_dateformat);

		echo $this->_render_sitebuilder_navlesspage($templater->render(), $stylesheets, $scripts);
	}

	/**
	 * Step 1 Theme
	 */

	public function action_theme()
	{
		$this->_step = 1;
		$this->_pagetitle = 'Step 1: Site Theme - Site Builder';
		$this->_steptitle = 'Choose your Site Theme';
		$this->_page_cssclass = 'sb-site-theme';

		$themes = array(
			array(
				'id' => '1',
				'name' => 'vB5 Theme',
				'thumbnail' => 'site-theme-1-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '2',
				'name' => 'Channel Theme',
				'thumbnail' => 'site-theme-1-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '3',
				'name' => 'Blog Theme',
				'thumbnail' => 'site-theme-1-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '4',
				'name' => 'CMS Theme',
				'thumbnail' => 'site-theme-1-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '5',
				'name' => 'Custom Theme',
				'thumbnail' => 'site-theme-custom-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			)/*,
			array(
				'id' => '6',
				'name' => 'Site Theme 6',
				'thumbnail' => 'site-theme-2-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '7',
				'name' => 'Site Theme 7',
				'thumbnail' => 'site-theme-3-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),
			array(
				'id' => '8',
				'name' => 'Site Theme 8',
				'thumbnail' => 'site-theme-4-thumbnail.png',
				'selected' => false,
				'disabled' => false,
			),*/
		);

		$templater = new vB5_Template('admin_sitebuilder_step1_theme');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);
		$templater->register('themes', $themes);
		$templater->register('theme_displaycount', count($themes));


		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savetheme()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(true, "Success!", null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/header'));
		exit;
	}

	/**
	 * Step 2 Header
	 */

	public function action_header()
	{
		$this->_step = 2;
		$this->_pagetitle = 'Step 2: Header - Site Builder';
		$this->_steptitle = 'Create your Header';
		$this->_page_cssclass = 'sb-header';
		$current_logo = vB5_Template_Runtime::fetchStylevar("titleimage") . '?' . time(); //append time to prevent caching

		$templater = new vB5_Template('admin_sitebuilder_step2_header');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);
		$templater->register('current_logo', $current_logo);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_saveheader()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		$errormsg = "";
		$success = false;

		switch($_POST["logoOptions"]){
			case "0": //upload logo
				$logopath = vB5_Template_Runtime::fetchStylevar("titleimage");
				//if titleimage stylevar is set and uploadlogo is checked but no uploaded file is set, then just ignore it and keep the existing logo untouched
				if (!empty($logopath) && empty($_FILES["uploadFile"]["name"])) {
					$success = true;
					$this->_render_iframe_response(true, "Success!", null);
					break;
				}

				$file = $_FILES["uploadFile"];
				$imagetype = $file["type"];
				if ($imagetype == "image/gif" || $imagetype == "image/jpeg" || $imagetype == "image/jpg" || $imagetype == "image/png") {
					$corepath = vB5_Config::instance()->core_path;
					$baseurl_core = vB5_Config::instance()->baseurl_core;
					$uploadtarget = $corepath . '/' . $logopath; //@todo: append wizard instance id in the target filename

					//upload logo to the titleimage stylevar location
					if (move_uploaded_file($file['tmp_name'], $uploadtarget))
					{
						$success = true;
						$this->_render_iframe_response(true, "Success!", array('uploadedLogo'=>$baseurl_core.'/'.$logopath));
					}
					else {
						$errormsg = "Error uploading file.";
						$this->_render_iframe_response(false, $errormsg, null);
					}
				}
				else {
					$errormsg = "The uploaded file should be a valid image (.gif, .jpg, .jpeg, or .png).";
					$this->_render_iframe_response(false, $errormsg, null);
				}

				break;
			case "1": //custom html
				if (!isset($_POST["customhtml"]) || trim($_POST["customhtml"])=='') {
					$errormsg = 'Custom HTML should not be empty.';
					$this->_render_iframe_response(false, $errormsg, null);
				}
				else {
					//@todo: filter custom html input here to prevent XSS attacks

					//@todo: save/update custom html setting in the database

					$success = true;
					$this->_render_iframe_response(true, "Success!", null);
				}
				break;
			default: //no logo
				//@todo: save/update no logo setting in the database

				$success = true;
				$this->_render_iframe_response(true, "Success!", null);
				break;
		}
		//if form is submitted normally (using Next button), execution goes through  here
		if ($success) {
			// go to next step
			header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/pagetemplates'));
		}
		else {
			echo '<h3>Error</h3><p>' .$errormsg.'</p>';
		}
		exit;
	}

	/**
	 * Step 3
	 */

	public function action_pagetemplates()
	{
		$this->_step = 3;
		$this->_pagetitle = 'Step 3: Page Templates - Site Builder';
		$this->_steptitle = 'Create your Page Templates';
		$this->_page_cssclass = 'sb-page-templates';

		$pagetemplates = array(
			array('name' => 'Homepage'),
			array('name' => 'Channel'),
			array('name' => 'Sub-Channel'),
			array('name' => 'Photo'),
			array('name' => 'Video'),
			array('name' => 'Link'),
			array('name' => 'Poll'),
		);

		$templater = new vB5_Template('admin_sitebuilder_step3_pagetemplates');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);
		$templater->register('templates', $pagetemplates);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savepagetemplates()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(true, "Success!", null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/pages'));
		exit;
	}


	public function action_editpagetemplate()
	{
		$this->_step = 3;
		$this->_pagetitle = 'Step 3: Page Templates - Site Builder';
		$this->_steptitle = 'Create New Page Template';
		$this->_page_cssclass = ' sb-page-templates-new';

		$templater = new vB5_Template('admin_sitebuilder_step3_editpagetemplate');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savepagetemplate()
	{
		// input needs validation obviously
		$title = trim(strval($_POST['title']));
		if (empty($title))
		{
			echo 'The title cannot be empty. Please go back and correct this problem.';
			exit;
		}
		if (!intval($_POST['screenlayoutid']))
		{
			echo 'You must specify a screen layout. Please go back and correct this problem.';
			exit;
		}

		$api = Api_InterfaceAbstract::instance();

		// page template
		$valuePairs = array(
			'title' => trim(strval($_POST['title'])),
			'screenlayoutid' => intval($_POST['screenlayoutid']),
		);
		$pagetemplateid = intval($_POST['pagetemplateid']);
		if ($pagetemplateid < 1)
		{
			// If no widgets were configured on the page template, we won't have a page template ID.
			$pagetemplateid = $api->callApi('database', 'insert', array('pagetemplate', $valuePairs));
		}
		else
		{
			$api->callApi('database', 'update', array('pagetemplate', $valuePairs, "pagetemplateid = $pagetemplateid"));
		}

		// widgets in page template
		$widgets = array();

		$columns = array();
		$columns[0] = explode(',', trim(strval($_POST['widgetsleft'])));
		$columns[1] = explode(',', trim(strval($_POST['widgetsright'])));

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

		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/pagetemplates'));
		exit;
	}

	/**
	 * Step 4
	 */
	public function action_pages()
	{
		$this->_step = 4;
		$this->_pagetitle = 'Step 4: Pages - Site Builder';
		$this->_steptitle = 'Create New Pages';
		$this->_page_cssclass = 'sb-pages';

		$templater = new vB5_Template('admin_sitebuilder_step4_pages');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savepages()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(false, $errormsg, null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/topnavbar'));
		exit;
	}

	public function action_editpage()
	{
		$this->_step = 4;
		$this->_pagetitle = 'Step 4: Pages - Site Builder';
		$this->_steptitle = 'Create New Page';
		$this->_page_cssclass = 'sb-pages-new';

		$templater = new vB5_Template('admin_sitebuilder_step4_editpage');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savepage()
	{
		// input needs validation obviously
		// and instead of printing an error message, we should send the user back
		// to the form and show them what's wrong so they can fix it.
		$title = trim(strval($_POST['title']));
		if (empty($title))
		{
			echo 'The title cannot be empty. Please go back and correct this problem.';
			exit;
		}
		// if empty, use the automatic URL index.php/page/PAGEID
		//$url = trim(strval($_POST['url']));
		//if (empty($url))
		//{
		//	echo 'The URL cannot be empty. Please go back and correct this problem.';
		//	exit;
		//}
		if (!intval($_POST['pagetemplateid']))
		{
			echo 'You must specify a page template. Please go back and correct this problem.';
			exit;
		}

		$api = Api_InterfaceAbstract::instance();

		// page
		$valuePairs = array(
			'pagetemplateid' => intval($_POST['pagetemplateid']),
			'title' => trim(strval($_POST['title'])),
			'metakeywords' => trim(strval($_POST['metakeywords'])),
			'metadescription' => trim(strval($_POST['metadescription'])),
			'urlprefix' => trim(strval($_POST['url'])),
			'moderatorid' => intval($_POST['moderatorid']),
		);
		$pageid = $api->callApi('database', 'insert', array('page', $valuePairs));

		// route
		$url = trim(strval($_POST['url']));
		if (!empty($url))
		{
			$valuePairs = array(
				'regex' => trim(strval($_POST['url'])),
				'controller' => 'page',
				'action' => 'index',
				'contentid' => $pageid,
			);
			$routeid = $api->callApi('database', 'insert', array('routenew', $valuePairs));

			// update page with routeid (for deleting it when deleting a page)
			$valuePairs = array(
				'routeid' => $routeid,
			);
			$api->callApi('database', 'update', array('page', $valuePairs, "pageid = $pageid"));
		}
		else
		{
			// The default URL of /index.php/page/PAGEID will be used
		}

		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/pages'));
		exit;
	}

	/**
	 * Step 5
	 */

	public function action_topnavbar()
	{
		$this->_step = 5;
		$this->_pagetitle = 'Step 5: Top Navigation Bar - Site Builder';
		$this->_steptitle = 'Create your Top Navigation Bar';
		$this->_page_cssclass = 'sb-top-navbar';

		$templater = new vB5_Template('admin_sitebuilder_step5_topnavbar');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savetopnavbar()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(false, $errormsg, null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/bottomnavbar'));
		exit;
	}

	/**
	 * Step 6
	 */

	public function action_bottomnavbar()
	{
		$this->_step = 6;
		$this->_pagetitle = 'Step 6: Bottom Navigation Bar - Site Builder';
		$this->_steptitle = 'Create your Bottom Navigation Bar';
		$this->_page_cssclass = 'sb-bottom-navbar';

		$templater = new vB5_Template('admin_sitebuilder_step6_bottomnavbar');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_savebottomnavbar()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(false, $errormsg, null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/advertising'));
		exit;
	}

	/**
	 * Step 7
	 */

	public function action_advertising()
	{
		$this->_step = 7;
		$this->_pagetitle = 'Step 7: Ad Manager - Site Builder';
		$this->_steptitle = 'Advertising';
		$this->_page_cssclass = 'sb-ads';

		$templater = new vB5_Template('admin_sitebuilder_step7_advertising');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());
	}

	public function action_saveadvertising()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		//for Save button which submits the form to the hidden iframe
		$this->_render_iframe_response(false, $errormsg, null);

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/publish'));
		exit;
	}

	public function action_newad()
	{
		echo '<h3>Not implemented yet.</h3>';
	}

	/**
	 * Step 8
	 */

	public function action_publish()
	{
		$this->_step = 8;
		$this->_pagetitle = 'Step 8: Publish your Site - Site Builder';
		$this->_steptitle = 'Publish your Site';
		$this->_page_cssclass = 'sb-publish';

		//@todo: retrieve data from db
		//step 1
		$selectedTheme = array(
			'id' => '1',
			'name' => 'vB5 Theme',
			'thumbnail' => 'site-theme-1-thumbnail.png'
		);

		//step 2
		$selectedHeader = array(
			'type' => '0', //0=uploaded image logo, 1=custom  html, 2=no logo
			'value' => vB5_Template_Runtime::fetchStylevar("titleimage") . '?' . time() //append time to prevent caching
		);

		$templater = new vB5_Template('admin_sitebuilder_step8_publish');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);
		$templater->register('selectedTheme', $selectedTheme);
		$templater->register('selectedHeader', $selectedHeader);

		echo $this->_render_sitebuilder_page($templater->render());
	}


	public function action_savepublish()
	{
		// dummy func. Doesn't save anything.

		// save sitebuilder wizard state here (for resuming an unfinished wizard instance)

		// go to next step
		header('Location: ' . vB5_Template_Runtime::buildUrl('sitebuilder/finished'));
		exit;
	}


	public function action_finished()
	{
		$this->_step = 9;
		$this->_pagetitle = 'Finished - Site Builder';
		$this->_steptitle = 'All done!';
		$this->_page_cssclass = '';

		$templater = new vB5_Template('admin_sitebuilder_step9_finished');
		$templater->register('baseurl', vB5_Config::instance()->baseurl);

		echo $this->_render_sitebuilder_page($templater->render());

		//header('Location: ' . vB5_Template_Runtime::buildUrl('pages'));
	}

	/**
	 * Deletes Site Builder Wizard instance on Status page
	 */
	public function action_deletewizard()
	{
		if (isset($_POST['instanceid']) && intval($_POST['instanceid']) > 0) {
			$where = "instanceid=" . $_POST['instanceid'];

			$api = Api_InterfaceAbstract::instance();
			$api->callApi('database', 'delete', array('instance', $where));
		}

		header('Location: ' . vB_Template_Runtime::buildUrl('sitebuilder/status'));
	}
}