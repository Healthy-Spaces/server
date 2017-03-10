<?php
	
	include 'server.php';
	include_once 'dbconnect.php';
	
	$jsonString = $_POST["json"];
	
	$server = new Server;
	
	$server->register($jsonString);

?>