<?php
	session_start();
	include_once 'server.php';
	require __DIR__ . '/vendor/autoload.php';
		
	if (php_sapi_name() == "cli") {
		parse_str($argv[1], $_POST);
	}
	
	if (isset($_POST["data"])) {
		$data = $_POST["data"];
		
		// fix data
		$data = preg_replace('/[[:space:]]/', '+', $data);
		
		$server = new Server;
		
		// Unencrypt Data
		$cryptor = new \RNCryptor\RNCryptor\Decryptor;
		$plaindata = $cryptor->decrypt($data, $server->config->serverHashPassword);
				
		$json = json_decode($plaindata, true);
		
		if (isset($json["task"]) && !empty($json["task"])) {
			$task = $json["task"];
			switch ($task) {
				case "register": 
					$server->register($json);
					break;
				case "login":
					$server->login($json);
					break;
				case "saveSurvey":
					$server->saveSurvey($json);
					break;
				case "saveLocation":
					$server->saveLocation($json);
					break;
				case "getNameAndEmail":
					$server->getNameAndEmail($json);
					break;
				default: 
					$server->printError("{\"errors\":[\"Malformed Server Request\"]");
					break;
			}
		} 
		
	} else {
		echo "{\"errors\": [\"no data\"]}";
	}

	
?>
