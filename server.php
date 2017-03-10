<?php
	
	class Server {
		
		private $mysqli = NULL;
		
		function __construct() {
			$this->mysqli = new mysqli("127.0.0.1", "healthy_server", "healthy.Server2017", "healthy") or die("Could not connect to database");
		}
		
		public function register($data) {
				
			if (!isset($this->mysqli)) {
				echo "Connection not set";
				//exit(2);
			}
			
			// convert data if needed
			if (gettype($data) == "string") {
				$data = json_decode($data, true);
			}
			
			// Extract data
			
			// Initialize needed variables
			$email = "";
			$password = "";
			$givenName = "";
			$familyName = "";
			$gender = "";
			$dob = "";
			$sharePermissions = "";
			$consented = "";
			
			// get data			
			$results;
			if (gettype($data["data"]) == "string") {
				$results = json_decode($data["data"], true);
			} else {
				$results = $data["data"]["results"];
			}
			foreach ($results as $result) {
				foreach ($result["results"] as $r) {
					$identifier = $r["identifier"];
					switch ($identifier) {
						case "ORKRegistrationFormItemEmail":
							$email = $r["textAnswer"];
							break;
						case "ORKRegistrationFormItemPassword":
							$password = $r["textAnswer"];
							break;
						case "ORKRegistrationFormItemGivenName":
							$givenName = $r["textAnswer"];
							break;
						case "ORKRegistrationFormItemFamilyName":
							$familyName = $r["textAnswer"];
							break;
						case "ORKRegistrationFormItemGender":
							$gender = $r["choiceAnswers"][0];
							break;
						case "ORKRegistrationFormItemDOB":
							$dob = $r["dateAnswer"];
							date_default_timezone_set('America/Los_Angeles');
							$dob = strtotime($dob);
							$dob = date("Y-m-d", $dob);
							break;
						case "sharingStep": 
							$sharePermissions = $r["choiceAnswers"][0];
							break;
						case "participant":
							$consented = $r["consented"];
							break;
							
						default: break;
					}
				}
			}
			
			// start transaction to database
			$success = true;
			$query = "INSERT INTO users (email, password, givenName, familyName, gender, dob, sharePermission, consented) VALUES ('$email', '$password', '$givenName', '$familyName', '$gender', '$dob', $sharePermissions, $consented)";
						
			// build errors array
			$errors = array();
			
			// do not autocommit in case of failure
			$this->mysqli->autocommit(false);
			
			// setup transaction
			$result = $this->mysqli->begin_transaction();
			if (!$result) {
				array_push($errors, "Registration: Failed transaction setup");
				$success = false;
			}
				
			// INSERT query
			$result = $this->mysqli->query($query);
			if (!$result) {
				array_push($errors, "Registration: failed INSERT");
				$success = false;
			}
				
			// get userID | SELECT query
			$query = "SELECT uid FROM users WHERE email='$email' LIMIT 1";
			$result = $this->mysqli->query($query);
			$userID;
			if (!$result) {
				array_push($errors, "Registration: failed SELECT");
				$success = false;
			} else {
				$userID = $result->fetch_assoc();
			}
				
			if ($success && isset($userID) && !empty($userID)) {
				$this->mysqli->commit();
				echo json_encode($userID);
			} else {
				$this->mysqli->rollback();
				$jsonErrors = json_encode($errors);
				echo "{\"errors\": $jsonErrors}";
			}
			
			$this->mysqli->close();
		}
		
		public function login($data) {
			
			$success = true;
			$email = $data["email"];
			$password = $data["password"];
			
			$query = "SELECT uid FROM users WHERE (email='$email' AND password='$password') LIMIT 1";
			
			// build errors array
			$errors = array();
			
			// do not autocommit in case of failure
			$this->mysqli->autocommit(false);
			
			$result = $this->mysqli->begin_transaction();
			if (!$result) {
				array_push($errors, "Login: Failed transaction setup");
				$success = false;
			}
				
			// get userID | SELECT query
			$result = $this->mysqli->query($query);
			$userID;
			if (!$result) {
				array_push($errors, "Login: failed SELECT");
				$success = false;
			} else {
				$userID = $result->fetch_assoc();
			}
			
			// check if success and commit
			if ($success && isset($userID) && !empty($userID)) {
				$this->mysqli->commit();
				echo json_encode($userID);
			} else {
				$this->mysqli->rollback();
				$jsonErrors = json_encode($errors);
				echo "{\"errors\": $jsonErrors}";
			}
			
			$this->mysqli->close();
		}
		
	}
		
	
?>