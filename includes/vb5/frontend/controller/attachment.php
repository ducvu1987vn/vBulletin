<?php

class vB5_Frontend_Controller_Attachment extends vB5_Frontend_Controller {
	public function actionFetch() {
		if (!empty($_REQUEST['id']) AND intval($_REQUEST['id'])) {
			$request = array('id' => $_REQUEST['id']);

			if (!empty($_REQUEST['thumb']) AND intval($_REQUEST['thumb'])) {
				$request['thumb'] = $_REQUEST['thumb'];
			}
			$api = Api_InterfaceAbstract::instance();
			$fileInfo = $api->callApi('attach', 'fetchImage', $request);
			if (!empty($fileInfo)) {
				header('Cache-control: max-age=31536000, private');
				header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileInfo['dateline']) . ' GMT');
				header('ETag: "' . $fileInfo['filedataid'] . '"');
				header('Accept-Ranges: bytes');
				header('Content-transfer-encoding: binary');
				header("Content-Length:\"" . $fileInfo['filesize'] );
				header('Content-Type: ' . $fileInfo['htmlType'] );
				header("Content-Disposition: inline filename*=" . $fileInfo['filename']);
				echo $fileInfo['filedata'];
			}
		}
	}

	public function actionRemove() {
		//Note that we shouldn't actually do anything here. If the filedata record isn't
		//used it will soon be deleted.
		if (!empty($_REQUEST['id']) && intval($_REQUEST['id'])) {
			$request = array('id' => $_REQUEST['id']);
			
			$api = Api_InterfaceAbstract::instance();
			$api->callApi('attach', 'removeAttachment', $request);
		}
	}
}
