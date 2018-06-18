<?php
	
	require 'config.php';
	
	class Server {
		
		private $mysqli = NULL;
		public $config = NULL;
		
		function __construct() {
			$this->mysqli = new mysqli("127.0.0.1", "healthy_server", "healthy.Server2017", "healthy") or die("Could not connect to database");
			$this->config = new Config();
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
							$password = password_hash($password . $this->config->salt, PASSWORD_BCRYPT);
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
			$query = "SELECT uid FROM users WHERE (email='$email' AND password='$password') LIMIT 1";
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
		}
		
		public function login($data) {
			
			$email = "";
			$password = "";
		
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
						case "ORKLoginFormItemEmail":
							$email = $r["textAnswer"];
							break;
						case "ORKLoginFormItemPassword":
							$password = $r["textAnswer"];
							break;
					}
				}
			}
			
			$success = true;
			$query = "SELECT uid, password FROM users WHERE (email='$email') LIMIT 1";
			
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
			$result = $result->fetch_assoc();
			$userID;
			$passwordHash = NULL;
			if (!$result) {
				array_push($errors, "Login: failed SELECT");
				$success = false;
			} else {
				$userID = $result['uid'];
				$passwordHash = $result['password'];
			}
			
			// verify password hash
			$passCheck = password_verify($password . $this->config->salt, $passwordHash);
			if (!$passCheck) { 
				array_push($errors, "Login: password failed verification");
				$success = false;
			}
			
			// check if success and commit
			if ($success && isset($userID) && !empty($userID)) {
				$this->mysqli->commit();
				echo "{\"uid\": \"$userID\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				echo "{\"errors\": $errors}";
			}
		}
		
		public function saveSurvey($data) {
			
			if (gettype($data) == "string") {
				$data = json_decode($data, true);
			}
			
			// get encompassing data
			$uid = $data["userID"];
			$results = $data["data"]["results"];
			$taskRunUUID = $data["data"]["taskRunUUID"];
			
			$errors = array();
			$success = true;
						
			foreach ($results as $result) {
				foreach ($result["results"] as $r) {
					if (isset($r["questionType"])) {
						$type = $r["questionType"];
						$result = $this->saveQuestionWithType($r, $type, $uid, $taskRunUUID);
						
						$jsonResult = json_decode($result, true);
						if (isset($jsonResult["errors"])) {
							$success = false;
							array_push($errors, $result);
						}
					}
				}
			}
			
			if (!$success) {
				echo json_encode($errors);
			} else {
				echo "{\"success\":\"true\"}";
			}
		}
		
		private function saveQuestionWithType($result, $type, $uid, $taskRunUUID) {

			switch ($type) {
				case 1:
					// scale answer
// 					echo "Scale Answer; \n";
					return $this->saveScaleAnswer($result, $uid, $taskRunUUID);
					break;
				case 2:
					// single choice
// 					echo "Single Choice; \n";
					return $this->saveSingleChoice($result, $uid, $taskRunUUID);
					break;
				case 3:
					// multiple choice
// 					echo "Multiple Choice; \n";
					return $this->saveMultipleChoice($result, $uid, $taskRunUUID);
					break;
				case 5: 
					// instructionStep
// 					echo "Instruction Step; \n";
					break;
				case 6:
					// numericAnswer
// 					echo "Numeric Answer; \n";
					return $this->saveNumericAnswer($result, $uid, $taskRunUUID);
					break;
				case 7:
					// booleanAnswer
// 					echo "Boolean Answer; \n";
					return $this->saveBooleanAnswer($result, $uid, $taskRunUUID);
					break;
				case 8:
					// TODO: textAnswer
// 					echo "Text Answer; \n";
					return $this->saveTextAnswer($result, $uid, $taskRunUUID);
					break;
				case 11:
					// dateAnswer
// 					echo "Date Answer; \n";
					return $this->saveDateAnswer($result, $uid, $taskRunUUID);
					break;
				case 14:
					// locationAnswer
// 					echo "Location Answer; \n";
					return $this->saveLocationAnswer($result, $uid, $taskRunUUID);
					break;
				default:
					echo "{'errors': 'undefined: $type' \n";
					break;
			}
		}
		
		private function saveScaleAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["scaleAnswer"])) {
				$skipped = "true";
			}
						
			$identifier = $result["identifier"];
			
			switch ($identifier) {
				case "undefined":
				case null:
					array_push($errors, "Save Scale Answer: Found 'undefined' or 'null' value");
					break;
				default: 
				
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Scale Answer: Failed transaction setup");
						$success = false;
					}
						
					// save scale answer data | Scale Answer INSERT
					if ($skipped == "true") {
						$scaleAnswer = "NULL";
					} else {
						$scaleAnswer = $result["scaleAnswer"];
					}
					$scaleAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($scaleAnswer, '$identifier')";
					$returned = $this->mysqli->query($scaleAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Scale Answer: failed Scale Answer INSERT with query: $scaleAnswerQuery");
						$success = false;
					} 
					
					// get sid from Scale Answer | Scale Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Scale Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$sid = $result;
					$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Scale Answer: failed Completed INSERT");
						$success = false;
					}
					
					break;
				}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveSingleChoice($result, $uid, $taskRunUUID) {
			
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["choiceAnswers"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			$singleChoice = $result["choiceAnswers"];
			
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Single Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					// save scale answer data | Single Answer INSERT
					if ($skipped == "true") {
						$singleChoice = "NULL";
					} else {
						$singleChoice = $result["choiceAnswers"];
						$singleChoice = $singleChoice[0];
					}
					$singleChoiceAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($singleChoice, '$identifier')";
					$returned = $this->mysqli->query($singleChoiceAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Single Answer: failed Single Answer INSERT with query: $scaleAnswerQuery");
						$success = false;
					} 
					
					// get sid from Single Answer | Single Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Single Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$sid = $result;
					$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Single Answer: failed Completed INSERT");
						$success = false;
					}
					
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
			
		}
		
		private function saveMultipleChoice($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["choiceAnswers"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			$multipleChoice = $result["choiceAnswers"];
			
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Multiple Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					// save scale answer data | Multiple Answer INSERT
					if ($skipped == "true") {
						$multipleChoice = "NULL";
					} else {
						$multipleChoice = $result["choiceAnswers"];
					}
					
					// FIXME: NEEDS REFACTORING 
					if (gettype($multipleChoice) == "array") {
						foreach ($multipleChoice as $choice) {
							$choiceAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($choice, '$identifier')";
							$returned = $this->mysqli->query($choiceAnswerQuery);
							if (!$returned) {
								array_push($errors, "Save Multiple Answer: failed Multiple Answer INSERT with query: $scaleAnswerQuery");
								$success = false;
							}
							
							// get sid from Multiple Answer | Multiple Answer SELECT
							$result = $this->mysqli->insert_id;
							if (!$result) {
								array_push($errors, "Save Multiple Answer: Failed to get last INSERT ID");
								$success = false;
							}
							
							// save completed data | completed INSERT
							$sid = $result;
							$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
							$returned = $this->mysqli->query($completedQuery);
							if (!$returned) {
								array_push($errors, "Save Multiple Answer: failed Completed INSERT");
								$success = false;
							}
						}
					} else {
						$choiceAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($multipleChoice, '$identifier')";
						$returned = $this->mysqli->query($choiceAnswerQuery);
						if (!$returned) {
							array_push($errors, "Save Multiple Answer: failed Multiple Answer INSERT with query: $scaleAnswerQuery");
							$success = false;
						}
						
						// get sid from Multiple Answer | Multiple Answer SELECT
						$result = $this->mysqli->insert_id;
						if (!$result) {
							array_push($errors, "Save Multiple Answer: Failed to get last INSERT ID");
							$success = false;
						}
						
						// save completed data | completed INSERT
						$sid = $result;
						$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
						$returned = $this->mysqli->query($completedQuery);
						if (!$returned) {
							array_push($errors, "Save Multiple Answer: failed Completed INSERT");
							$success = false;
						}
					}
					
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveNumericAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["numericAnswer"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			$numericAnswer = $result["numericAnswer"];
			
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Numeric Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Numeric Answer: Failed transaction setup");
						$success = false;
					}
						
					// save scale answer data | Numeric Answer INSERT
					if ($skipped == "true") {
						$numericAnswer = "NULL";
					} else {
						$numericAnswer = $result["numericAnswer"];
					}
					$numericAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($numericAnswer, '$identifier')";
					$returned = $this->mysqli->query($numericAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Numeric Answer: failed Numeric Answer INSERT with query: $numericAnswerQuery");
						$success = false;
					} 
					
					// get sid from Numeric Answer | Numeric Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Numeric Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$sid = $result;
					$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Numeric Answer: failed Completed INSERT");
						$success = false;
					}
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveTextAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = false;
			if (!isset($result["textAnswer"])) {
				$skipped = true;
			}
			
			$identifier = $result["identifier"];
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Text Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// get date for each answer: 
					$endDate = $result["endDate"];
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
					
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Text Answer: Failed transaction setup");
						$success = false;
					}
					
					// save text answer data | Text Answer INSERT
					if ($skipped == true) {
						$textAnswer = "NULL";
					} else {
						$textAnswer = $result["textAnswer"];
					}
					$textAnswerQuery = "INSERT INTO textAnswer (answer, answerIdentifier) VALUES ('$textAnswer', '$identifier')";
					$returned = $this->mysqli->query($textAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Text Answer: failed Text Answer INSERT with query: $textAnswerQuery");
						$success = false;
					}
					
					// get tid from Text Answer | Text Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Text Answer: Failed to get last INSERT id");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$tid = $result;
					$completedQuery = "INSERT INTO completed (uid, tid, endDate, surveyID) VALUES ($uid, $tid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Text Answer: failed Completed INSERT with query: $completedQuery");
						$success = false;
					}
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveDateAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["dateAnswer"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Date Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Date Answer: Failed transaction setup");
						$success = false;
					}
						
					// save date answer data | Date Answer INSERT
					if ($skipped == "true") {
						$dateAnswer = null;
						$dateAnswerQuery = "INSERT INTO dateAnswer (answerIdentifier) VALUES ('$identifier')";
					} else {
						$dateAnswer = $result["dateAnswer"];
						date_default_timezone_set('America/Los_Angeles');
						$dateAnswer = strtotime($dateAnswer);
						$dateAnswer = date("Y-m-d", $dateAnswer);
						$dateAnswerQuery = "INSERT INTO dateAnswer (answer, answerIdentifier) VALUES ('$dateAnswer', '$identifier')";
					}
					
					$returned = $this->mysqli->query($dateAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Date Answer: failed Date Answer INSERT with query: $dateAnswerQuery");
						$success = false;
					} 
					
					// get sid from Date Answer | Date Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Date Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$did = $result;
					$completedQuery = "INSERT INTO completed (uid, did, endDate, surveyID) VALUES ($uid, $did, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Date Answer: failed Completed INSERT with query: $completedQuery");
						$success = false;
					}
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveBooleanAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["booleanAnswer"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			$booleanAnswer = $result["booleanAnswer"];
											
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Boolean Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Boolean Answer: Failed transaction setup");
						$success = false;
					}
						
					// save boolean answer data | Boolean Answer INSERT
					if ($skipped == "true") {
						$booleanAnswer = "NULL";
					} else {
						$booleanAnswer = $result["booleanAnswer"];
					}
					
					switch ($booleanAnswer) {
						case true:  $booleanAnswer = 1; break;
						case false: $booleanAnswer = 0; break;
						default: 						break;
					}
					
					$booleanAnswerQuery = "INSERT INTO answer (answer, answerIdentifier) VALUES ($booleanAnswer, '$identifier')";
					$returned = $this->mysqli->query($booleanAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Boolean Answer: failed Scale Answer INSERT with query: $booleanAnswerQuery");
						$success = false;
					} 
					
					// get sid from Boolean Answer | Boolean Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Boolean Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$sid = $result;
					$completedQuery = "INSERT INTO completed (uid, sid, endDate, surveyID) VALUES ($uid, $sid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Boolean Answer: failed Completed INSERT");
						$success = false;
					}
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		private function saveLocationAnswer($result, $uid, $taskRunUUID) {
			if (gettype($result) == "string") {
				$result = json_decode($result, true);
			}
			
			$errors = array();
			$success = true;
			
			$skipped = "false";
			if (!isset($result["locationAnswer"])) {
				$skipped = "true";
			}
			
			$identifier = $result["identifier"];
			
			// Setup variables
			$lat = "NULL";
			$lon = "NULL";
			$accuracy = "NULL";
			
			switch ($identifier) {
				case "unidentified":
				case null:
					array_push($errors, "Save Location Answer: Found 'undefined' or 'null' value");
					break;
				default:
					// Get end date for each answer:
					$endDate = $result["endDate"];
					date_default_timezone_set('America/Los_Angeles');
					$endDate = strtotime($endDate);
					$endDate = date("Y-m-d H:i:s", $endDate);
											
					// do not autocommit in case of failure
					$this->mysqli->autocommit(false);
					
					$returned = $this->mysqli->begin_transaction();
					if (!$returned) {
						array_push($errors, "Save Location Answer: Failed transaction setup");
						$success = false;
					}
						
					// save scale answer data | Location Answer INSERT
					if ($skipped == "true") {
						$locationAnswer = "NULL";
					} else {
						$locationAnswer = $result["locationAnswer"];
						$lat = $locationAnswer["region"]["coordinate"]["latitude"];
						$lon = $locationAnswer["region"]["coordinate"]["longitude"];
						$accuracy = $locationAnswer["region"]["radius"];
					}
					$locationAnswerQuery = "INSERT INTO location (lat, lon, time, accuracy) VALUES ($lat, $lon, NULL, $accuracy)";
					$returned = $this->mysqli->query($locationAnswerQuery);
					if (!$returned) {
						array_push($errors, "Save Location Answer: failed Location Answer INSERT with query: $locationAnswerQuery");
						$success = false;
					} 
					
					// get sid from Location Answer | Location Answer SELECT
					$result = $this->mysqli->insert_id;
					if (!$result) {
						array_push($errors, "Save Location Answer: Failed to get last INSERT ID");
						$success = false;
					}
					
					// save completed data | completed INSERT
					$lid = $result;
					$completedQuery = "INSERT INTO completed (uid, lid, endDate, surveyID) VALUES ($uid, $lid, '$endDate', '$taskRunUUID')";
					$returned = $this->mysqli->query($completedQuery);
					if (!$returned) {
						array_push($errors, "Save Location Answer: failed Completed INSERT");
						$success = false;
					}
					break;
			}
			
			// check if success and commit
			if ($success && isset($uid) && !empty($uid)) {
				$this->mysqli->commit();
				return "{\"success\": \"true\"}";
			} else {
				$this->mysqli->rollback();
				$errors = json_encode($errors);
				return "{\"errors\": $errors}";
			}
		}
		
		function getNameAndEmail($data) {
			if (gettype($data) == "string") {
				$data = json_decode($data);
			}
			
			$errors = array();
			
			if (isset($data["uid"]) && !empty($data["uid"])) {
				
				$uid = $data["uid"];
				
				$success = true;
				$query = "SELECT email, givenName, familyName FROM users WHERE uid=$uid";
				
				$result = $this->mysqli->query($query);
				if (!$result) {
					$success = false;
					array_push($errors, "Get Name and Email: Query Failed");
				} else {
					$result_array = $result->fetch_assoc();
				}
				
				if ($success) {
					echo json_encode($result_array);
				} else {
					$jsonErrors = json_encode($errors);
					echo "{\"errors\": $jsonErrors}";
				}
			}
		}
		
		function saveLocation($data) {
			if (gettype($data) == "string") {
				$data = json_decode($data, true);
			}
			
			$lat = $data["lat"];
			$lon = $data["lon"];
			$time = $data["time"];
			$accuracy = $data["accuracy"];
			
			$errors = array();
						
			if (isset($data["userID"]) && !empty($data["userID"])) {
				
				$uid = $data["userID"];
				
				$success = true;
				
				// format time	
				date_default_timezone_set('America/Los_Angeles');
				$time = strtotime($time);				
				$time = date("Y-m-d H:i:s");
								
				// do not autocommit
				$this->mysqli->autocommit(false);
				
				$query = "INSERT INTO location (lat, lon, time, accuracy) VALUES ($lat, $lon, '$time', $accuracy)";
				
				$result = $this->mysqli->begin_transaction();
				if (!$result) {
					array_push($errors, "Location: Failed to start transaction");
					$success = false;
				}
				
				$result = $this->mysqli->query($query);
				if (!$result) {
					array_push($errors, "Location: Failed location INSERT with query: $query");
					$success = false;
				}
				
				$result = $this->mysqli->insert_id;
				if (!$result) {
					array_push($errors, "Location: Failed to get last INSERT ID");
					$success = false;
				}
				
				$lid = $result;
				$query = "INSERT INTO user_location (uid, lid, isAnswer) VALUES ($uid, $lid, 0)";
				$result = $this->mysqli->query($query);
				if (!$result) {
					array_push($errors, "Location: Failed user_location INSERT query: $query");
					$success = false;
				}
				
				if ($success) {
					$this->mysqli->commit();
					echo "{\"success\": true}";
				} else {
					$this->mysqli->rollback();
					$jsonErrors = json_encode($errors);
					echo "{\"errors\": $jsonErrors}";
				}
			}
		}
		
		function __deconstruct() {
			$this->mysqli->close();
		}
		
	}
		
	
?>