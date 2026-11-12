<?php

if (defined('DIR_EXTENSION') && version_compare(VERSION, '4.0', '>=')) {
	require_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert.php');
} else {
	require_once(DIR_SYSTEM . 'library/client_translate_expert.php');
}

class ControllerExtensionModuleClientTranslateExpertCron extends Controller
{
	public function index()
	{
		$debugKey = version_compare(VERSION, '3.0', '>=') ? 'module_client_translate_expert_debug_status' : 'client_translate_expert_debug_status';
		$library = new LibraryClientTranslateExpert($this, $this->config->get($debugKey));

		$result = $library->runCron($this->request->get);

		if ($result['http_status'] == 403)
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$json = json_encode($result['body'], defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0);
		if ($json === false)
			$json = '{"done":false,"rows":0,"chars":0,"table":"","error":"translate_failed","message":"","details":""}';
		$this->response->setOutput($json);
	}
}
