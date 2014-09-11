Extension: BfSurveyFile

Installation:
	1) Copy code to the 'extension/bfsurveyfile' directory
	2) enable the extension in the Admin or settings/override/site.ini.append.php
	3) regenerate the autoloads to register the new classes.
	4) clear the template caches
	5) edit the .htaccess (or apache .conf) with this rewrite rule
		
		#bfsurveyfile
		RewriteRule ^var/([^/]+/)?storage/surveyfiles/.* - [L]
	
	You are now ready to add this 'file' datatype to surveys

Functionality:
	New survey question type for 'file' upload
	Render a survey form with the file upload question display the file upload input.
	User choose a local file to upload
	on Submit; check the file provided if set to manditory
	Store the file path in the 'answer' field (for review by admins)

	Will upload the file into the php TEMP directory, check file size and type, then move into a final path of '<ez>/var/<siteaccess>/bfsurveyfile/survey_<survey_contentobject_id>'


Config: 
	AllowedExtensions[] - 3 and 4 letter file extensions (.jpg, .png, .tiff)
	MaxFileSize - byte count to limit file upload

Troubleshooting:
	If the bfsurvey extension loads before the ezsurvey extension the 'file' may not appear becuase the array is reset.
	fix:
		edit ezsurvey/settings/ezsurvey.ini.append.php and comment out the ExtensionDirectories[] init	
		[QuestionTypeSettings]
		# Comment out to allow append from other extensions
		#ExtensionDirectories[]