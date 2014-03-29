<?php
class eZSurveyFile extends eZSurveyQuestion
{

  /*
    Question Type: File

    Data Storage: 
      answer - file path from var with file name      

    Limit by extension using ini setting : AllowedExtensions[]
    Limit to file size using ini setting : MaxFileSize
  */

  /*
    NOTE: This questiontype is unlike any other becuase the file upload must process only once.
    eZSurvey->fetchQuestionList() loops over the questions calling question->processViewActions()
    eZSurveyQuestion->storeResult() loops over the questions calling question->answer()
    These two loops cannot communicate with eachother.
    Attempts to use $this->var prove they are not the same instances.
    Only a shared $_POST scope allows the file to be uploaded and path recorded, then found again in answer()
  */

  //this will be prepended to the directory name WITH the survey OBJECT_ID to create uniqueness
  const UPLOAD_DIR_PREFIX = "survey_";
  const UPLOAD_DIR_BASE = "surveyfiles";

  var $uploadPath = '';
  var $allowedExtensions = array(); //TODO, get from question definition
  var $sizeLimit = 10485760;
  
  /*
   * constructor
   */
  function eZSurveyFile( $row = false )
  {
    $bfsf_ini = eZINI::instance('bfsurveyfile.ini');
    $varPath = eZSys::storageDirectory();
    $survey_object_id = 0;

    //get survey object (lookup object ID, as the survey_id changes with each edit)
    $survey = new eZSurveyType();
    $surveyObject = $survey->fetchSurveyByID( $row['survey_id'] );
    if($surveyObject) { $survey_object_id = $surveyObject->attribute('contentobject_id'); }

    //set directory paths
    $surveyUploadDir = self::UPLOAD_DIR_BASE . '/'. self::UPLOAD_DIR_PREFIX . $survey_object_id . '/'; // syntax example: surveyfiles/survey_123/
    $this->uploadPath = $varPath . '/'. $surveyUploadDir;

    //create directory if NOT exists
    if (!is_dir($this->uploadPath)){
      eZDir::mkdir($this->uploadPath, false, true);
    }

    //TODO: error if directory cannot be created

    //set allowed file extensions
    $allowedExtensions = $bfsf_ini->variable('SurveyFile', 'AllowedExtensions');
    if ( isset($allowedExtensions) ) { $this->allowedExtensions = $allowedExtensions; }

    //set max file size
    if ($bfsf_ini->variable('SurveyFile', 'MaxFileSize') > 0) {
      $this->sizeLimit = $bfsf_ini->variable('SurveyFile', 'MaxFileSize');
    }

    $row[ 'type' ] = 'File';
    if ( !isset( $row['mandatory'] ) ) $row['mandatory'] = 0;
    $this->eZSurveyQuestion( $row );
  }

    /*
     * called when a question is created / edited in the admin
     * In this case we only have to save the question text and the mandatory checkbox value
     */
  function processEditActions( &$validation, $params )
  {

    $http = eZHTTPTool::instance();
    $prefix = eZSurveyType::PREFIX_ATTRIBUTE;
    $attributeID = $params[ 'contentobjectattribute_id' ];

    //title of the question
    $postQuestionText = $prefix . '_ezsurvey_question_' . $this->ID . '_text_' . $attributeID;
    if( $http->hasPostVariable( $postQuestionText ) and $http->postVariable( $postQuestionText ) != $this->Text )
    {
       $this->setAttribute( 'text', $http->postVariable( $postQuestionText ) );
    }

    $postQuestionMandatoryHidden = $prefix . '_ezsurvey_question_' . $this->ID . '_mandatory_hidden_' . $attributeID;
    if( $http->hasPostVariable( $postQuestionMandatoryHidden ) )
    {
       $postQuestionMandatory = $prefix . '_ezsurvey_question_' . $this->ID . '_mandatory_' . $attributeID;
       if( $http->hasPostVariable( $postQuestionMandatory ) )
           $newMandatory = 1;
       else
           $newMandatory = 0;

       if( $newMandatory != $this->Mandatory )
           $this->setAttribute( 'mandatory', $newMandatory );
    }
  }
 
   /*
     * Checks input
     */
  function processViewActions( &$validation, $params )
  {

    $http = eZHTTPTool::instance();
    $variableArray = array();

    $prefix = eZSurveyType::PREFIX_ATTRIBUTE;
    $surveyAnswer = '';

    $postSurveyAnswer = $prefix . '_ezsurvey_answer_' . $this->ID . '_' . $this->contentObjectAttributeID();
    $postSurveyFile = $prefix . '_ezsurvey_file_' . $this->ID . '_' . $this->contentObjectAttributeID();

    //Option 1) check for already saved value
    if ( $http->hasPostVariable( $postSurveyAnswer ) )
    {
        $surveyAnswer = $http->postVariable( $postSurveyAnswer );
        if( !empty($surveyAnswer) ) {
            $this->setAnswer( $surveyAnswer );
            $variableArray[ 'answer' ] = $surveyAnswer; //return answer for parsing
            return $variableArray;
        }
    }

    //Option 2) 
    if ( array_key_exists($postSurveyFile,$_FILES) && !empty($_FILES[$postSurveyFile]) ) {
        
        $fileUploadAttempt = true;

        $uploader = new eZSurveyFileUploader($postSurveyFile, $this->allowedExtensions, $this->sizeLimit);
        $uploaderResult = $uploader->handleUpload( $this->uploadPath );
        if(array_key_exists('info', $uploaderResult) ) { 
            $fileName = $uploaderResult['info']['basename'];
            $fileLabel = $uploaderResult['label'];  //normal or unique name
            $filePath = $this->uploadPath . $fileName;

            $surveyAnswer = $filePath;
            $this->setAnswer($surveyAnswer); //answer for Survey->questionList compile pre storeResult()
            $variableArray[ 'answer' ] = $surveyAnswer; //return answer for parsing

            //CRITICAL STEP: Push the answer value BACK into post so that the next answer() call can get it
            $_POST[$postSurveyAnswer] = $surveyAnswer; 
        }

      }

    if ( $this->attribute( 'mandatory' ) == 1 ) {
      if( !$fileUploadAttempt && !$surveyAnswer ) {
        $validation['error'] = true;
        $validation['errors'][] = array( 'message' => ezpI18n::tr( 'survey', 'Please re-enter the file value', null,
        array( '%number' => $this->questionNumber() ) ),
          'question_number' => $this->questionNumber(),
          'code' => 'general_answer_number_as_well',
          'question' => $this );
      }
    }

      return $variableArray;
  }

    //This is called during the processViewActions chain and storeResult();    
    function answer()
    {

      //option 1) check for already defined
      if ( strlen($this->Answer) ) {
        return $this->Answer;
      }

      $http = eZHTTPTool::instance();
      $prefix = eZSurveyType::PREFIX_ATTRIBUTE;

      //option 2) check for answer in $_POST (trick from processViewAction or normal post)
      $postSurveyAnswer = $prefix . '_ezsurvey_answer_' . $this->ID . '_' . $this->contentObjectAttributeID();
      if ( $http->hasPostVariable( $postSurveyAnswer ) && strlen($http->postVariable( $postSurveyAnswer ) ) )
      {
          $surveyAnswer = $http->postVariable( $postSurveyAnswer );
          return $surveyAnswer;
      }    
      
      return $this->Default;
    }

}
eZSurveyQuestion::registerQuestionType( ezpI18n::tr( 'survey', 'File' ), 'File' );
?>