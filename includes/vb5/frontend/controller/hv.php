<?php

class vB5_Frontend_Controller_Hv extends vB5_Frontend_Controller
{
	public function actionImage()
	{
		$api = Api_InterfaceAbstract::instance();

		$image = $api->callApi('hv', 'fetchHvImage', array('hash' => $_REQUEST['hash']));

		switch ($image['type'])
		{
			case 'gif':
				header('Content-transfer-encoding: binary');
				header('Content-disposition: inline; filename=image.gif');
				header('Content-type: image/gif');
				break;

			case 'png':
				header('Content-transfer-encoding: binary');
				header('Content-disposition: inline; filename=image.png');
				header('Content-type: image/png');
				break;

			case 'jpg':
				header('Content-transfer-encoding: binary');
				header('Content-disposition: inline; filename=image.jpg');
				header('Content-type: image/jpeg');
				break;
		}

		echo $image['data'];
	}

}
