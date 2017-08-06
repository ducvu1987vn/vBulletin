<?php

class vB5_Frontend_Controller_Style extends vB5_Frontend_Controller
{
	public function actionSaveGeneratedStyle()
	{
		//$scheme, $parentid, $title, $displayorder = 1, $userselect = false
		//, $_POST['type'], $_POST['id']

		$response = Api_InterfaceAbstract::instance()->callApi('style', 'generateStyle', array(
				'scheme' => $_POST['scheme'],
				'type' => $_POST['type'],
				'parentid' => $_POST['parentid'],
				'title' => $_POST['name'],
				'displayorder' => empty($_POST['displayorder'])?1:$_POST['displayorder'],
				'userselect' => !empty($_POST['userselect'])));
		$this->sendAsJson($response);
	}

}