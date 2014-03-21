 <?php

// This is a rework of the 3rd party PHP from http://valums.com/ajax-upload

 class eZSurveyFileUploader {
   private $allowedExtensions = array();
   private $sizeLimit = 10485760;
   private $file;

   var $httpVar = 'file'; 

   function __construct( $httpVar = 'file', array $allowedExtensions = array(), $sizeLimit = 10485760){        
       $allowedExtensions = array_map("strtolower", $allowedExtensions);
           
       $this->allowedExtensions = $allowedExtensions;        
       $this->sizeLimit = $sizeLimit;
       $this->httpVar = $httpVar;
       
       $this->checkServerSettings();       
 
       if (isset($_GET[$httpVar])) {
           $this->file = new eZSurveyUploadGetFile($this->httpVar);
       } elseif (isset($_FILES[$this->httpVar])) {
           $this->file = new eZSurveyUploadPostFile($this->httpVar);
       } else {
           $this->file = false;
       }
   }
   
   private function checkServerSettings(){        
       $phpPostMaxSize = $this->toBytes(ini_get('post_max_size'));
       $phpUploadMaxSize = $this->toBytes(ini_get('upload_max_filesize'));        
       $surveyMaxFileSize = $this->toBytes($this->sizeLimit);
       if ($phpPostMaxSize < $surveyMaxFileSize || $phpUploadMaxSize < $surveyMaxFileSize){
           echo 'PHP post_max_size = ' .$phpPostMaxSize;
           echo 'PHP upload_max_filesize = ' .$phpUploadMaxSize ;
           echo 'Survey MaxFileSize = ' .$surveyMaxFileSize;
           die("{'error':'increase post_max_size and upload_max_filesize to $surveyMaxFileSize'}");    
       }        
   }
   
   private function toBytes($str){
       $val = trim($str);
       $last = strtolower($str[strlen($str)-1]);
       switch($last) {
           case 'g': $val *= 1024;
           case 'm': $val *= 1024;
           case 'k': $val *= 1024;        
       }
       return $val;
   }
   
   /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
   function handleUpload($uploadDirectory, $replaceOldFile = FALSE){
       if (!is_writable($uploadDirectory)){
           return array('error' => "Server error. Upload directory isn't writable.");
       }
       
       if (!$this->file){
           return array('error' => 'No files were uploaded.');
       }
       
       $size = $this->file->getSize();
       
       if ($size == 0) {
           return array('error' => 'File is empty');
       }
       
       if ($size > $this->sizeLimit) {
           return array('error' => 'File is too large');
       }
       
       $pathinfo = pathinfo($this->file->getName());
       $filename = $pathinfo['filename'];
       $filelabel = $pathinfo['basename']; //keep un, uniqued name
       $fileize = $this->file->getSize();
       //$filename = md5(uniqid());
       $ext = $pathinfo['extension'];
 
       if($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)){
           $these = implode(', ', $this->allowedExtensions);
           return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
       }
       
       if(!$replaceOldFile){
           /// don't overwrite previous files that were uploaded
           while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
               $filename .= rand(10, 99);
               $pathinfo['filename'] = $filename; //rebuild pathinfo
               $pathinfo['basename'] = $filename . '.' . $ext; //rebuild pathinfo
           }
       }
       
       if ($this->file->save($uploadDirectory . $filename . '.' . $ext)){
           return array( 'success'=>true, 'label' => $filelabel, 'info' => $pathinfo, 'size' => $fileize );
       } else {
           return array('error'=> 'Could not save uploaded file.' . $uploadDirectory . $filename . '.' . $ext .
               'The upload was cancelled, or server error encountered');
       }
       
   }  
}

class eZSurveyUploadPostFile {
  var $httpVar = 'file'; //DJS add httpVar param usage

  function __construct( $httpVar = 'file' ) {
    $this->httpVar = $httpVar;
  }

  function save($path) {
    $moved = move_uploaded_file($_FILES[$this->httpVar]['tmp_name'], $path);
    if(!$moved){
      return false;
    }
      return true;
  }

  function getName() {
      //UniqueUploaderID 
    return $_FILES[$this->httpVar]['name'];
  }

  function getSize() {
    return $_FILES[$this->httpVar]['size'];
  }

}

class eZSurveyUploadGetFile {

  var $httpVar = 'file'; //DJS add httpVar param usage

  /**
  * Save the file to the specified path
  * @return boolean TRUE on success
  */

  function __construct( $httpVar = 'file' ) {
    $this->httpVar = $httpVar;
  }

  function save($path) {    
    $input = fopen("php://input", "r");
    $temp = tmpfile();
    $realSize = stream_copy_to_stream($input, $temp);
    fclose($input);

    //if ($realSize != $this->getSize()){            
    //return false;
    //}

    $target = fopen($path, "w");        
    fseek($temp, 0, SEEK_SET);
    stream_copy_to_stream($temp, $target);
    fclose($target);

    return true;
  }

  function getName() {
    return $_COOKIE['UniqueUploaderID'] . $_GET[$this->$httpVar];
  }

  function getSize() {
    if (isset($_SERVER["CONTENT_LENGTH"])){
      return (int)$_SERVER["CONTENT_LENGTH"];            
    } else {
      throw new Exception('Getting content length is not supported.');
    }      
  }   
}