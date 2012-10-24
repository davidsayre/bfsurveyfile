Extension: BfSurveyFile

Functionality:
	Add a new survey question type for 'file' upload
	Render a survey form with the file upload question display the file upload input.
	Click the file input to choose a local file for upload
	click upload to send the file to the /surveyupload/ajaxupload module/view for storage and return the file path
	Store the file path in the 'answer' per question

Config: UploadDirectoryName - directory used within the var path