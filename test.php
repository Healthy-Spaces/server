<!DOCTYPE html>
<html>
	<head>
		<title>Server Test Suite</title>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
		<script>
			
			$(document).ready(function() {
				console.log("sending data");
				
				var jsonData = {"userID":"newUser","data":{"endDate":"2017-03-09T16:49:18-0800","results":[{"results":[{"questionType":7,"textAnswer":"lichlyts@oregonstate.edu","_class":"ORKTextQuestionResult","startDate":"2017-03-09T16:48:43-0800","identifier":"ORKRegistrationFormItemEmail","endDate":"2017-03-09T16:48:43-0800"},{"questionType":7,"textAnswer":"sam","_class":"ORKTextQuestionResult","startDate":"2017-03-09T16:48:45-0800","identifier":"ORKRegistrationFormItemPassword","endDate":"2017-03-09T16:48:45-0800"},{"questionType":7,"textAnswer":"sam","_class":"ORKTextQuestionResult","startDate":"2017-03-09T16:48:45-0800","identifier":"ORKRegistrationFormItemConfirmPassword","endDate":"2017-03-09T16:48:45-0800"},{"questionType":7,"textAnswer":"Sam","_class":"ORKTextQuestionResult","startDate":"2017-03-09T16:48:46-0800","identifier":"ORKRegistrationFormItemGivenName","endDate":"2017-03-09T16:48:46-0800"},{"questionType":7,"textAnswer":"Lichlyter","_class":"ORKTextQuestionResult","startDate":"2017-03-09T16:48:48-0800","identifier":"ORKRegistrationFormItemFamilyName","endDate":"2017-03-09T16:48:48-0800"},{"questionType":2,"choiceAnswers":["male"],"_class":"ORKChoiceQuestionResult","startDate":"2017-03-09T16:48:51-0800","identifier":"ORKRegistrationFormItemGender","endDate":"2017-03-09T16:48:51-0800"},{"_class":"ORKDateQuestionResult","timeZone":-28800,"endDate":"2017-03-09T16:48:48-0800","startDate":"2017-03-09T16:48:48-0800","dateAnswer":"1997-03-09T16:48:33-0800","identifier":"ORKRegistrationFormItemDOB","calendar":"gregorian","questionType":10}],"_class":"ORKStepResult","startDate":"2017-03-09T16:48:33-0800","identifier":"registrationStep","endDate":"2017-03-09T16:48:51-0800"},{"results":[],"_class":"ORKStepResult","startDate":"2017-03-09T16:48:51-0800","identifier":"VisualConsentStep","endDate":"2017-03-09T16:49:07-0800"},{"results":[{"questionType":2,"choiceAnswers":[0],"_class":"ORKChoiceQuestionResult","startDate":"2017-03-09T16:49:07-0800","identifier":"sharingStep","endDate":"2017-03-09T16:49:08-0800"}],"_class":"ORKStepResult","startDate":"2017-03-09T16:49:07-0800","identifier":"sharingStep","endDate":"2017-03-09T16:49:08-0800"},{"results":[{"consented":true,"signature":{"_class":"ORKConsentSignature","signatureDate":"3\/9\/17","requiresName":true,"identifier":"participant","familyName":"Lichlyter","givenName":"Samuel","requiresSignatureImage":true},"_class":"ORKConsentSignatureResult","startDate":"2017-03-09T16:49:08-0800","identifier":"participant","endDate":"2017-03-09T16:49:18-0800"}],"_class":"ORKStepResult","startDate":"2017-03-09T16:49:08-0800","identifier":"ConsentReviewStep","endDate":"2017-03-09T16:49:18-0800"}],"_class":"ORKTaskResult","startDate":"2017-03-09T16:48:33-0800","taskRunUUID":"FAFFB3BF-79F7-4B33-A020-4CE98BDC79C4","identifier":"registrationTask"}}
				
				var string = JSON.stringify(jsonData);
				console.log(string);
				
				$.ajax({
					url: "test2.php",
					data: "json=" + string,
					type: "POST",
					dataType: "json"
				});
				
				console.log("sent data");
			});
			
		</script>
	</head>
	<body>
	</body>
</html>
