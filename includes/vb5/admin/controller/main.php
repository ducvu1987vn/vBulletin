<?php

class vB5_Admin_Controller_Main extends vB5_Admin_Controller
{
	function action_index()
	{
		$templater = new vB5_Template('admin_index');
		echo $templater->render();
	}
}
