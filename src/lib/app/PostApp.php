<?php 
/**
 * Class PostApp
 *
 * @category  Class PostApp
 * @author    M.Noermoehammad
 * @license   MIT
 * @version   1.0
 * @since     Since Release 1.0
 *
 */
class PostApp extends BaseApp
{
  
/**
 * an instance of View
 * 
 * @var string
 * 
 */
  private $view;

/**
 * an instance of PostEvent
 * 
 * @var string
 * 
 */
  private $postEvent;
    
/**
 * Initialize instance of object properties and method
 * 
 * @param object $postEvent
 * 
 */
  public function __construct(PostEvent $postEvent)
  {
    
    $this->postEvent = $postEvent;
   
  }
  
  /**
   * Retrieve all posts
   *  
   * {@inheritDoc}
   * @see BaseApp::listItems()
   */
  public function listItems()
  {
    $errors = array();
    $status = array();
    $checkError = true;
    $checkStatus = false;
    
    if (isset($_GET['error'])) {
        $checkError = false;
        if ($_GET['error'] == 'postNotFound') array_push($errors, "Error: Post Not Found!");
    }
    
    if (isset($_GET['status'])) {
        $checkStatus = true;
        if ($_GET['status'] == 'postAdded') array_push($status, "New post added");
        if ($_GET['status'] == 'postUpdated') array_push($status, "Post updated");
        if ($_GET['status'] == 'postDeleted') array_push($status, "Post deleted");
    }
   
    $this->setView('all-posts');
    $this->setPageTitle('Posts');
    $this->view->set('pageTitle', $this->getPageTitle());
    
    if (!$checkError) {
        $this->view->set('errors', $errors);
    } 
    
    if ($checkStatus) {
        $this->view->set('status', $status);
    }
    
    if ($this->postEvent->postAuthorLevel() == 'administrator') {

      $this->view->set('postsTotal', $this->postEvent->totalPosts());
      $this->view->set('posts', $this->postEvent->grabPosts());

    } else {

      $this->view->set('postsTotal', $this->postEvent->totalPosts([$this->postEvent->postAuthorId()]));
      $this->view->set('posts', $this->postEvent->grabPosts('ID', $this->postEvent->postAuthorId()));
      
    }
    
    return $this->view->render();
    
  }
  
  /**
   * Insert new post
   * 
   * {@inheritDoc}
   * @see BaseApp::insert()
   * 
   */
  public function insert()
  {
    
    $topics = new TopicDao();
    $medialib = new MediaDao();
    $errors = array();
    $checkError = true;
    
    if (isset($_POST['postFormSubmit'])) {
        
        $file_location = isset($_FILES['media']['tmp_name']) ? $_FILES['media']['tmp_name'] : '';
 	      $file_type = isset($_FILES['media']['type']) ? $_FILES['media']['type'] : '';
 	      $file_name = isset($_FILES['media']['name']) ? $_FILES['media']['name'] : '';
 	      $file_size = isset($_FILES['media']['size']) ? $_FILES['media']['size'] : '';
 	      $file_error = isset($_FILES['media']['error']) ? $_FILES['media']['error'] : '';
       
        $filters = [
            'post_title' => FILTER_SANITIZE_SPECIAL_CHARS,
            'post_content' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'media_id' => FILTER_SANITIZE_NUMBER_INT,
            'catID' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY],
            'post_summary' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'post_keyword' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'post_status' => FILTER_SANITIZE_STRING,
            'comment_status' => FILTER_SANITIZE_STRING
        ];

        $form_fields = ['post_title' => 200, 'post_summary' => 320, 'post_keyword' => 200, 'post_content' => 10000];

      try {
        
         if (!csrf_check_token('csrfToken', $_POST, 60*10)) {
         
             header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
             throw new AppException("Sorry, unpleasant attempt detected!");
             
         }
         
         if ((empty($_POST['post_title'])) || (empty($_POST['post_content']))) {
           
            $checkError = false;
            array_push($errors, "Please enter a required fields");
            
         }
         
         if (!empty($file_location)) {

           if (!isset($file_error) || is_array($file_error)) {

             $checkError = false;
             array_push($errors, "Invalid paramenter");
            
          }
  
          switch ($file_error) {
  
            case UPLOAD_ERR_OK:
                break;
           
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
              
              $checkError = false;
              array_push($errors, "Exceeded filesize limit");
    
              break;
    
            default:
                
              $checkError = false;
              array_push($errors, "Unknown errors");
              
              break;
              
          }
  
          if ($file_size > APP_FILE_SIZE) {
  
            $checkError = false;
            array_push($errors, "Exceeded file size limit. Maximum file size is. ".format_size_unit(APP_FILE_SIZE));
   
          }
   
          if(false === check_file_name($file_location)) {
   
            $checkError = false;
            array_push($errors, "file name is not valid");
   
          }
   
          if(true === check_file_length($file_location)) {
   
            $checkError = false;
            array_push($errors, "file name is too long");
           
          }
   
         if(false === check_mime_type(mime_type_dictionary(), $file_location)) {
   
            $checkError = false;
            array_push($errors, "Invalid file format");
   
         }

       }

       $new_filename = generate_filename($file_name)['new_filename'];

       list($width, $height) = (!empty($file_location)) ? getimagesize($file_location) : null;

       if (generate_filename($file_name)['file_extension'] == "jpeg" 
               || generate_filename($file_name)['file_extension'] == "jpg" 
               || generate_filename($file_name)['file_extension'] == "png" 
               || generate_filename($file_name)['file_extension'] == "gif" 
               || generate_filename($file_name)['file_extension'] == "webp") {

              $media_metavalue = array(
                  'Origin' => rename_file($file_name), 
                  'File type' => $file_type, 
                  'File size' => format_size_unit($file_size), 
                  'Uploaded on' => date("Y-m-d H:i:s"), 
                  'Dimension' => $width.'x'.$height);

        } else {

              $media_metavalue = array(
                  'Origin' => rename_file($file_name), 
                  'File type' => $file_type, 
                  'File size' => format_size_unit($file_size), 
                  'Uploaded on' => date("Y-m-d H:i:s"));

        }

        // upload image
        if (is_uploaded_file($file_location)) {

          upload_media($file_location, $file_type, $file_size, basename($new_filename));

        }

        $media_access = (isset($_POST['post_status']) && ($_POST['post_status'] == 'publish')) ? 'public' : 'private';

        $bind_media = [
          'media_filename' => $new_filename, 
          'media_caption' => prevent_injection(distill_post_request($filters)['post_title']), 
          'media_type' => $file_type, 
          'media_target' => 'blog', 
          'media_user' => $this->postEvent->postAuthorLevel(), 
          'media_access' => $media_access, 
          'media_status' => '1'];

        $append_media = $medialib->createMedia($bind_media);

        if (!$checkError) {
            
          $this->setView('edit-post');
          $this->setPageTitle('Add New Post');
          $this->setFormAction(ActionConst::NEWPOST);
          $this->view->set('pageTitle', $this->getPageTitle());
          $this->view->set('formAction', $this->getFormAction());
          $this->view->set('errors', $errors);
          $this->view->set('formData', $_POST);
          $this->view->set('topics', $topics->setCheckBoxTopic());
       
         if ($this->postEvent->postAuthorLevel() == 'contributor') {

           $this->view->set('medialibs', $medialib->dropDownMediaSelect());

          } else {

           $this->view->set('medialibs', $medialib->mediaBlogImageUploaded());

          }

          $this->view->set('postStatus', $this->postEvent->postStatusDropDown());
          $this->view->set('commentStatus', $this->postEvent->commentStatusDropDown());
          $this->view->set('csrfToken', csrf_generate_token('csrfToken'));
        
       } else {

        if (empty($file_location)) {

           if (isset($_POST['media_id']) && gettype($_POST['media_id'] == "integer")) {

              $this->postEvent->setPostImage((int)distill_post_request($filters)['media_id']);

           }

           if(isset($_POST['catID']) && ($_POST['catID'] == 0)) {

              $this->postEvent->setTopics(0);
              $this->postEvent->setPostAuthor((int)$this->postEvent->postAuthorId());
              $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
              $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
              $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
              $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
              $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);

           } else {

              $this->postEvent->setTopics(distill_post_request($filters)['catID']);
              $this->postEvent->setPostAuthor((int)$this->postEvent->postAuthorId());
              $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
              $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
              $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
              $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
              $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);

           }

        } else {

            $mediameta = [
              'media_id' => $append_media,
              'meta_key' => $new_filename,
              'meta_value' => json_encode($media_metavalue)
            ];

            $medialib->createMediaMeta($mediameta);

            $this->postEvent->setPostImage($append_media);

            if (isset($_POST['catID']) && ($_POST['catID'] == 0)) {

               $this->postEvent->setTopics(0);
               $this->postEvent->setPostAuthor((int)$this->postEvent->postAuthorId());
               $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
               $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
               $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
               $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
               $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
               $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
               $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);

            } else {

              $this->postEvent->setTopics(distill_post_request($filters)['catID']);
              $this->postEvent->setPostAuthor((int)$this->postEvent->postAuthorId());
              $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
              $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
              $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
              $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
              $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
              $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);

            }

          }

             $this->postEvent->addPost();
      
            direct_page('index.php?load=posts&status=postAdded', 200);

       }
      
      } catch (AppException $e) {
          
         LogError::setStatusCode(http_response_code());
         LogError::newMessage($e);
         LogError::customErrorMessage('admin');
         
      }
    
    } else {
        
        $this->setView('edit-post');
        $this->setPageTitle('Add New Post');
        $this->setFormAction(ActionConst::NEWPOST);
        $this->view->set('pageTitle', $this->getPageTitle());
        $this->view->set('formAction', $this->getFormAction());
        $this->view->set('topics', $topics->setCheckBoxTopic());
        
        if ($this->postEvent->postAuthorLevel() == 'contributor') { 

           $this->view->set('medialibs', $medialib->dropDownMediaSelect());

        } else {

            $this->view->set('medialibs', $medialib->mediaBlogImageUploaded());

        }
        
        $this->view->set('postStatus', $this->postEvent->postStatusDropDown());
        $this->view->set('commentStatus', $this->postEvent->commentStatusDropDown());
        $this->view->set('csrfToken', csrf_generate_token('csrfToken'));
        
    }
   
    return $this->view->render();
   
  }
  
  /**
   * Update post
   * 
   * {@inheritDoc}
   * @see BaseApp::update()
   * 
   */
  public function update($id)
  {
  
    $topics = new TopicDao();
    $medialib = new MediaDao();
    $errors = array();
    $checkError = true;
    
    if (!$getPost = $this->postEvent->grabPost($id)) {
        
        direct_page('index.php?load=posts&error=postNotFound', 404);
        
    }
    
    $data_post = array(
        'ID' => $getPost['ID'],
        'media_id' => $getPost['media_id'],
        'post_title' => $getPost['post_title'],
        'post_content' => $getPost['post_content'],
        'post_summary' => $getPost['post_summary'],
        'post_keyword' => $getPost['post_keyword']
    );

    $getMedia = $medialib->findMediaBlog((int)$getPost['media_id']);
    
    if (isset($_POST['postFormSubmit'])) {
        
       $file_location = isset($_FILES['media']['tmp_name']) ? $_FILES['media']['tmp_name'] : '';
       $file_type = isset($_FILES['media']['type']) ? $_FILES['media']['type'] : '';
       $file_name = isset($_FILES['media']['name']) ? $_FILES['media']['name'] : '';
       $file_size = isset($_FILES['media']['size']) ? $_FILES['media']['size'] : '';
       $file_error = isset($_FILES['media']['error']) ? $_FILES['media']['error'] : '';

       $filters = [
          'post_id' => FILTER_SANITIZE_NUMBER_INT,
          'post_title' => FILTER_SANITIZE_SPECIAL_CHARS,
          'post_content' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
          'media_id' => FILTER_SANITIZE_NUMBER_INT,
          'catID' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY],
          'post_summary' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
          'post_keyword' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
          'post_status' => FILTER_SANITIZE_STRING,
          'comment_status' => FILTER_SANITIZE_STRING
       ];

       $form_fields = ['post_title' => 200, 'post_summary' => 320, 'post_keyword' => 200, 'post_content' => 10000];
 
        try {
            
            if (!csrf_check_token('csrfToken', $_POST, 60*10)) {
                
                header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request");
                throw new AppException("Sorry, unpleasant attempt detected!");
                
            }
            
            if ((empty($_POST['post_title'])) || (empty($_POST['post_content']))) {
           
               $checkError = false;
               array_push($errors, "Please enter a required fields");
              
            }
            
            if (!$checkError) {
                
                $this->setView('edit-post');
                $this->setPageTitle('Edit Post');
                $this->setFormAction(ActionConst::EDITPOST);
                $this->view->set('pageTitle', $this->getPageTitle());
                $this->view->set('formAction', $this->getFormAction());
                $this->view->set('errors', $errors);
                $this->view->set('postData', $data_post);
                $this->view->set('topics', $topics->setCheckBoxTopic($getPost['ID']));

                if ($this->postEvent->postAuthorLevel() == 'contributor') {

                  $this->view->set('medialibs', $medialib->dropDownMediaSelect($getPost['media_id']));
       
                } else {
       
                   $this->view->set('medialibs', $medialib->mediaBlogImageUploaded($getPost['media_id']));
       
                }

                $this->view->set('postStatus', $this->postEvent->postStatusDropDown($getPost['post_status']));
                $this->view->set('commentStatus', $this->postEvent->commentStatusDropDown($getPost['comment_status']));
                $this->view->set('csrfToken', csrf_generate_token('csrfToken'));
                
            } else {
              
                if (!empty($file_location)) {

                  if (!isset($file_error) || is_array($file_error)) {
              
                    $checkError = false;
                    array_push($errors, "Invalid paramenter");
               
                  }
      
                 switch ($file_error) {
      
                   case UPLOAD_ERR_OK:
                       break;
                    
                   case UPLOAD_ERR_INI_SIZE:
                   case UPLOAD_ERR_FORM_SIZE:
                     
                     $checkError = false;
                     array_push($errors, "Exceeded filesize limit");
           
                     break;
           
                   default:
                       
                     $checkError = false;
                     array_push($errors, "Unknown errors");
                     
                     break;
                     
                  }
           
                 if ($file_size > APP_FILE_SIZE) {
           
                   $checkError = false;
                   array_push($errors, "Exceeded file size limit. Maximum file size is. ".format_size_unit(APP_FILE_SIZE));
           
                 }
           
                 if (true === form_size_validation($form_fields)) {
           
                    $checkError = false;
                    array_push($errors, "Exceeded characters limit than allowed.");
                      
                 }
              
                 if (true === check_file_length($file_location)) {
           
                     $checkError = false;
                     array_push($errors, "file name is too long");
           
                 }
           
                 if (false === check_file_name($file_location)) {
           
                     $checkError = false;
                     array_push($errors, "file name is not valid");
           
                 }
           
                 if (false === check_mime_type(mime_type_dictionary(), $file_location)) {
           
                     $checkError = false;
                     array_push($errors, "Invalid file format");
                     
                 }

                  $new_filename = generate_filename($file_name)['new_filename'];

                  list($width, $height) = (!empty($file_location)) ? getimagesize($file_location) : null;

                  if (generate_filename($file_name)['file_extension'] == "jpeg" 
                      || generate_filename($file_name)['file_extension'] == "jpg" 
                      || generate_filename($file_name)['file_extension'] == "png" 
                      || generate_filename($file_name)['file_extension'] == "gif" 
                      || generate_filename($file_name)['file_extension'] == "webp") {

                      $media_metavalue = array(
                          'Origin' => rename_file($file_name), 
                          'File type' => $file_type, 
                          'File size' => format_size_unit($file_size), 
                          'Uploaded on' => date("Y-m-d H:i:s"), 
                          'Dimension' => $width.'x'.$height);

                  } else {

                      $media_metavalue = array(
                          'Origin' => rename_file($file_name), 
                          'File type' => $file_type, 
                          'File size' => format_size_unit($file_size), 
                          'Uploaded on' => date("Y-m-d H:i:s"));

                  }

                  if (is_uploaded_file($file_location)) {

                    upload_media($file_location, $file_type, $file_size, basename($new_filename));
      
                  }

                  $media_access = (isset($_POST['post_status']) && ($_POST['post_status'] == 'publish')) ? 'public' : 'private';

                  $bind_media = [
                    'media_filename' => $new_filename, 
                    'media_caption' => prevent_injection(distill_post_request($filters)['post_title']), 
                    'media_type' => $file_type, 
                    'media_target' => 'blog', 
                    'media_user' => $this->postEvent->postAuthorId(), 
                    'media_access' => $media_access, 
                    'media_status' => '1'];
        
                  $append_media = $medialib->createMedia($bind_media);

                  $mediameta = [
                    'media_id' => $append_media,
                    'meta_key' => $new_filename,
                    'meta_value' => json_encode($media_metavalue)
                  ];
  
                  $medialib->createMediaMeta($mediameta);

                  $this->postEvent->setPostId((int)distill_post_request($filters)['post_id']);
                  $this->postEvent->setPostImage($append_media);
                  $this->postEvent->setTopics(distill_post_request($filters)['catID']);
                  $this->postEvent->setPostAuthor($this->postEvent->postAuthorId());
                  $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
                  $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
                  $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
                  $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
                  $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
                  $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
                  $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);

                } else {

                  if (isset($_POST['media_id']) && gettype($_POST['media_id'] == "integer")) {

                     $this->postEvent->setPostImage((int)distill_post_request($filters)['media_id']);
      
                  }

                  $this->postEvent->setPostId((int)distill_post_request($filters)['post_id']);
                  $this->postEvent->setTopics(distill_post_request($filters)['catID']);
                  $this->postEvent->setPostAuthor((int)$this->postEvent->postAuthorId());
                  $this->postEvent->setPostTitle(distill_post_request($filters)['post_title']);
                  $this->postEvent->setPostSlug(distill_post_request($filters)['post_title']);
                  $this->postEvent->setPostContent(distill_post_request($filters)['post_content']);
                  $this->postEvent->setPublish(distill_post_request($filters)['post_status']);
                  $this->postEvent->setComment(distill_post_request($filters)['comment_status']);
                  $this->postEvent->setMetaDesc(distill_post_request($filters)['post_summary']);
                  $this->postEvent->setMetaKeys(distill_post_request($filters)['post_keyword']);
                    
                }
 
                $this->postEvent->modifyPost();
                direct_page('index.php?load=posts&status=postUpdated', 200);
                
            }
            
        } catch (AppException $e) {
   
            LogError::setStatusCode(http_response_code());
            LogError::newMessage($e);
            LogError::customErrorMessage('admin');
            
        }
        
    } else {
   
        $this->setView('edit-post');
        $this->setPageTitle('Edit Post');
        $this->setFormAction(ActionConst::EDITPOST);
        $this->view->set('pageTitle', $this->getPageTitle());
        $this->view->set('formAction', $this->getFormAction());
        $this->view->set('postData', $data_post);
        $this->view->set('topics', $topics->setCheckBoxTopic($getPost['ID']));

        if ($this->postEvent->postAuthorLevel() == 'contributor') {

          $this->view->set('medialibs', $medialib->dropDownMediaSelect($getPost['media_id']));

        } else {

           $this->view->set('medialibs', $medialib->mediaBlogImageUploaded($getPost['media_id']));

        }

        $this->view->set('postStatus', $this->postEvent->postStatusDropDown($getPost['post_status']));
        $this->view->set('commentStatus', $this->postEvent->commentStatusDropDown($getPost['comment_status']));
        $this->view->set('csrfToken', csrf_generate_token('csrfToken'));
        
    }
      
    return $this->view->render();
    
  }
  
  /**
   * Delete post
   * 
   * {@inheritDoc}
   * @see BaseApp::remove()
   */
  public function remove($id)
  {
    $this->postEvent->setPostId($id);
    $this->postEvent->removePost();  
    direct_page('index.php?load=posts&status=postDeleted', 200);
  }
    
/**
 * Set View
 * 
 * @param string $viewName
 * 
 */
  protected function setView($viewName)
  {
    $this->view = new View('admin', 'ui', 'posts', $viewName);
  }
  
}