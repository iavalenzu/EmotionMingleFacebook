<?php

App::uses('AppController', 'Controller');

require_once APP . 'Vendor' . DS . 'Facebook' . DS . 'autoload.php';

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class HomeController extends AppController {

    public $uses = array('User', 'Friend', 'Like', 'Comment', 'SelectedFriend');    
    
    public $fb;

    public function beforeFilter() {
        parent::beforeFilter();

        session_start();

        $this->fb = new Facebook\Facebook([
            'app_id' => '791535754293026',
            'app_secret' => 'c20f2a9cc413df08251eccc086c1310c',
            'default_graph_version' => 'v2.2',
        ]);

        //print_r($this->fb);
    }

    function login() 
    {
        
       $accessToken = $_SESSION['facebook_access_token'];

        if(isset($accessToken) && !empty($accessToken))
        {
            $this->redirect("home");
        }
 
        $helper = $this->fb->getRedirectLoginHelper();

        $permissions = ['email', 'user_likes']; // optional

        $loginUrl = $helper->getLoginUrl('http://localhost/EmotionMingleFacebook/home/loginCallback', $permissions);

        $this->set("loginUrl", $loginUrl);
    }

    function home() 
    {
        $accessToken = $_SESSION['facebook_access_token'];

        if(!isset($accessToken)){
            $this->redirect("logout");
        }
        
        // Sets the default fallback access token so we don't have to pass it to each request
        $this->fb->setDefaultAccessToken($accessToken);

        try 
        {
            $response = $this->fb->get('/me?fields=id,name');

            $userNode = $response->getGraphUser();
            
            $userId = $userNode->getId();
            $userName = $userNode->getName();

            $user = $this->User->findByFacebookUserId($userId);

            if(!isset($user) || empty($user))
            {
                $user = array(
                    'name' => $userName,
                    'facebook_user_id' => $userId
                );

                if(!$this->User->save($user))
                {
                }
                else
                {
                    $this->processPhotoLikes($this->User->id);
                    $this->processPhotoComments($this->User->id);

                    $this->processPostLikes($this->User->id);
                    $this->processPostComments($this->User->id);
                }
            
            }
            else
            {
                $this->processPhotoLikes($user['User']['id']);
                $this->processPhotoComments($user['User']['id']);
                
                
                $this->processPostLikes($user['User']['id']);
                $this->processPostComments($user['User']['id']);
            }
            
            
            //$this->redirect('showSelectedUsers');
            
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
        }

    }
    
    
    private function processPostLikes($user_id)
    {
        /**
         * Se obtienen los likes asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=posts{likes{id,username,pic,profile_type,name},message,id}');
        
        $node = $response->getGraphNode();

        $posts = $node->getField('posts');
        
        if(empty($posts)){
            return;
        }

        /**
         * Para cada foto se obtienen los likes asociados
         */
        
        foreach($posts as $post)
        {
            $sourceId = $post['id'];
            
            $likes = $post->getField('likes');
            
            if(empty($likes))
            {
                continue;
            }
            
            /**
             * Para cada like se obtiene la info del amigo que hizo el like
             */
            
            foreach($likes as $like)
            {
                $friendId = $like['id'];
                $friendName = $like['name'];
                $friendPic = $like['pic'];
                
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                /*
                $existingFriend = $this->Friend->find('first', array(
                    'conditions' => array(
                        'Friend.user_id' => $user_id,
                        'Friend.facebook_user_id' => $friendId)
                ));
                */
                
                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se actualiza le foto de perfil del amigo
                     */
                    
                    $existingFriend['Friend']['pic'] = $friendPic;
                    
                    $this->Friend->save($existingFriend);                    
                    
                    /**
                     * Se busca si el like ya se encuentra en la BD
                     */
                    
                    $existingLike = $this->Like->findByFriendIdAndUserIdAndSourceAndType($existingFriend['Friend']['id'], $user_id, $sourceId, 'POST');
                    
                    /*
                    $existingLike = $this->Like->find('first', array(
                        'conditions' => array(
                            'Like.friend_id' => $existingFriend['Friend']['id'],
                            'Like.user_id' => $user_id,
                            'Like.source' => $sourceId,
                            'Like.type' => 'PHOTO'
                        )
                    ));
                    */
                    
                    /**
                     * Si no se encuentra el like en la BD, se crea el registro
                     */
                    
                    if(!isset($existingLike) || empty($existingLike))
                    {
                        $newLike = array(
                            'friend_id' => $existingFriend['Friend']['id'],
                            'user_id' => $user_id,
                            'source' => $sourceId,
                            'type' => 'POST'
                        );
                        
                        $this->Like->create();
                        
                        if(!$this->Like->save($newLike))
                        {
                            $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                        }

                    }
                    
                }
                else
                {
                    /**
                     * Si el amigo no se encuentra en la BD, se crea el nuevo registro
                     * y el like asociado
                     */
                    
                    $newFriend = array(
                        'user_id' => $user_id,
                        'facebook_user_id' => $friendId,
                        'name' => $friendName,
                        'pic' => $friendPic
                    );
                    
                    $this->Friend->create();
                    
                    if(!$this->Friend->save($newFriend))
                    {
                        $this->Session->setFlash(__('Error al guardar la nueva conexion.'));
                    }
                    else
                    {
                        
                        $existingLike = $this->Like->findByFriendIdAndUserIdAndSourceAndType($this->Friend->id, $user_id, $sourceId, 'POST');
                        
                        /*
                        $existingLike = $this->Like->find('first', array(
                            'conditions' => array(
                                'Like.friend_id' => $this->Friend->id,
                                'Like.user_id' => $user_id,
                                'Like.source' => $sourceId,
                                'Like.type' => 'PHOTO'
                            )
                        ));
                        */
                        
                        if(!isset($existingLike) || empty($existingLike))
                        {
                            $newLike = array(
                                'friend_id' => $this->Friend->id,
                                'user_id' => $user_id,
                                'source' => $sourceId,
                                'type' => 'POST'
                            );

                            $this->Like->create();
                            
                            if(!$this->Like->save($newLike))
                            {
                                $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                            }

                        }
                        
                    }
                    
                }
                  
            }
            
            
        }
        
        

    }

    private function processPostComments($user_id)
    {
        /**
         * Se obtienen los comentarios asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=posts{comments{from,message,id},id}');
        
        $node = $response->getGraphNode();

        $posts = $node->getField('posts');
        
        if(empty($posts)){
            return;
        }
        
        /**
         * Para cada foto se obtienen los likes asociados
         */
        
        foreach($posts as $post)
        {
            $sourceId = $post['id'];
            
            $comments = $post->getField('comments');
            
            if(empty($comments))
            {
                continue;
            }
            
            /**
             * Para cada like se obtiene la info del amigo que hizo el like
             */
            
            foreach($comments as $comment)
            {
                
                $from = $comment->getField('from');
                                
                $friendId = $from['id'];
                $friendName = $from['name'];
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                /*
                $existingFriend = $this->Friend->find('first', array(
                    'conditions' => array(
                        'Friend.user_id' => $user_id,
                        'Friend.facebook_user_id' => $friendId)
                ));
                */
                
                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se busca si el like ya se encuentra en la BD
                     */
                    
                    $existingComment = $this->Comment->findByFriendIdAndUserIdAndSourceAndType($existingFriend['Friend']['id'], $user_id, $sourceId, 'POST');
                    
                    /*
                    $existingLike = $this->Like->find('first', array(
                        'conditions' => array(
                            'Like.friend_id' => $existingFriend['Friend']['id'],
                            'Like.user_id' => $user_id,
                            'Like.source' => $sourceId,
                            'Like.type' => 'PHOTO'
                        )
                    ));
                    */
                    
                    /**
                     * Si no se encuentra el like en la BD, se crea el registro
                     */
                    
                    if(!isset($existingComment) || empty($existingComment))
                    {
                        $newComment = array(
                            'friend_id' => $existingFriend['Friend']['id'],
                            'user_id' => $user_id,
                            'source' => $sourceId,
                            'type' => 'POST'
                        );
                        
                        $this->Comment->create();
                        
                        if(!$this->Comment->save($newComment))
                        {
                            $this->Session->setFlash(__('Error al guardar el nuevo comentario.'));
                        }

                    }
                    
                }
                else
                {
                    
                    /**
                     * Si el amigo no se encuentra en la BD, se crea el nuevo registro
                     * y el like asociado
                     */
                    
                    $newFriend = array(
                        'user_id' => $user_id,
                        'facebook_user_id' => $friendId,
                        'name' => $friendName,
                        'pic' => ''
                    );
                    
                    $this->Friend->create();
                    
                    if(!$this->Friend->save($newFriend))
                    {
                        $this->Session->setFlash(__('Error al guardar la nueva conexion.'));
                    }
                    else
                    {
                        
                        $existingComment = $this->Comment->findByFriendIdAndUserIdAndSourceAndType($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                        
                        /*
                        $existingLike = $this->Like->find('first', array(
                            'conditions' => array(
                                'Like.friend_id' => $this->Friend->id,
                                'Like.user_id' => $user_id,
                                'Like.source' => $sourceId,
                                'Like.type' => 'PHOTO'
                            )
                        ));
                        */
                        
                        if(!isset($existingComment) || empty($existingComment))
                        {
                            $newComment = array(
                                'friend_id' => $this->Friend->id,
                                'user_id' => $user_id,
                                'source' => $sourceId,
                                'type' => 'POST'
                            );

                            $this->Comment->create();
                            
                            if(!$this->Comment->save($newComment))
                            {
                                $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                            }

                        }
                        
                    }
                    
                }
                  
            }
            
            
        }
        

    }
    
    
    private function processPhotoLikes($user_id)
    {
        /**
         * Se obtienen los likes asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=photos{likes{username,name,profile_type,id,pic},id}');
        
        $node = $response->getGraphNode();

        $photos = $node->getField('photos');
        
        if(empty($photos)){
            return;
        }

        /**
         * Para cada foto se obtienen los likes asociados
         */
        
        foreach($photos as $photo)
        {
            $sourceId = $photo['id'];
            
            $likes = $photo->getField('likes');
            
            if(empty($likes))
            {
                continue;
            }
            
            /**
             * Para cada like se obtiene la info del amigo que hizo el like
             */
            
            foreach($likes as $like)
            {
                $friendId = $like['id'];
                $friendName = $like['name'];
                $friendPic = $like['pic'];
                
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                /*
                $existingFriend = $this->Friend->find('first', array(
                    'conditions' => array(
                        'Friend.user_id' => $user_id,
                        'Friend.facebook_user_id' => $friendId)
                ));
                */
                
                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se actualiza le foto de perfil del amigo
                     */
                    
                    $existingFriend['Friend']['pic'] = $friendPic;
                    
                    $this->Friend->save($existingFriend);                    
                    
                    /**
                     * Se busca si el like ya se encuentra en la BD
                     */
                    
                    $existingLike = $this->Like->findByFriendIdAndUserIdAndSourceAndType($existingFriend['Friend']['id'], $user_id, $sourceId, 'PHOTO');
                    
                    /*
                    $existingLike = $this->Like->find('first', array(
                        'conditions' => array(
                            'Like.friend_id' => $existingFriend['Friend']['id'],
                            'Like.user_id' => $user_id,
                            'Like.source' => $sourceId,
                            'Like.type' => 'PHOTO'
                        )
                    ));
                    */
                    
                    /**
                     * Si no se encuentra el like en la BD, se crea el registro
                     */
                    
                    if(!isset($existingLike) || empty($existingLike))
                    {
                        $newLike = array(
                            'friend_id' => $existingFriend['Friend']['id'],
                            'user_id' => $user_id,
                            'source' => $sourceId,
                            'type' => 'PHOTO'
                        );
                        
                        $this->Like->create();
                        
                        if(!$this->Like->save($newLike))
                        {
                            $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                        }

                    }
                    
                }
                else
                {
                    /**
                     * Si el amigo no se encuentra en la BD, se crea el nuevo registro
                     * y el like asociado
                     */
                    
                    $newFriend = array(
                        'user_id' => $user_id,
                        'facebook_user_id' => $friendId,
                        'name' => $friendName,
                        'pic' => $friendPic
                    );
                    
                    $this->Friend->create();
                    
                    if(!$this->Friend->save($newFriend))
                    {
                        $this->Session->setFlash(__('Error al guardar la nueva conexion.'));
                    }
                    else
                    {
                        
                        $existingLike = $this->Like->findByFriendIdAndUserIdAndSourceAndType($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                        
                        /*
                        $existingLike = $this->Like->find('first', array(
                            'conditions' => array(
                                'Like.friend_id' => $this->Friend->id,
                                'Like.user_id' => $user_id,
                                'Like.source' => $sourceId,
                                'Like.type' => 'PHOTO'
                            )
                        ));
                        */
                        
                        if(!isset($existingLike) || empty($existingLike))
                        {
                            $newLike = array(
                                'friend_id' => $this->Friend->id,
                                'user_id' => $user_id,
                                'source' => $sourceId,
                                'type' => 'PHOTO'
                            );

                            $this->Like->create();
                            
                            if(!$this->Like->save($newLike))
                            {
                                $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                            }

                        }
                        
                    }
                    
                }
                  
            }
            
            
        }
        
        
    }
    
    private function processPhotoComments($user_id)
    {
        /**
         * Se obtienen los comentarios asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=photos{comments{from,message,id}}');
        
        $node = $response->getGraphNode();

        $photos = $node->getField('photos');
        
        if(empty($photos)){
            return;
        }

        /**
         * Para cada foto se obtienen los likes asociados
         */
        
        foreach($photos as $photo)
        {
            $sourceId = $photo['id'];
            
            $comments = $photo->getField('comments');
            
            if(empty($comments))
            {
                continue;
            }
            
            /**
             * Para cada like se obtiene la info del amigo que hizo el like
             */
            
            foreach($comments as $comment)
            {
                
                $from = $comment->getField('from');
                                
                $friendId = $from['id'];
                $friendName = $from['name'];
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                /*
                $existingFriend = $this->Friend->find('first', array(
                    'conditions' => array(
                        'Friend.user_id' => $user_id,
                        'Friend.facebook_user_id' => $friendId)
                ));
                */
                
                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se busca si el like ya se encuentra en la BD
                     */
                    
                    $existingComment = $this->Comment->findByFriendIdAndUserIdAndSourceAndType($existingFriend['Friend']['id'], $user_id, $sourceId, 'PHOTO');
                    
                    /*
                    $existingLike = $this->Like->find('first', array(
                        'conditions' => array(
                            'Like.friend_id' => $existingFriend['Friend']['id'],
                            'Like.user_id' => $user_id,
                            'Like.source' => $sourceId,
                            'Like.type' => 'PHOTO'
                        )
                    ));
                    */
                    
                    /**
                     * Si no se encuentra el like en la BD, se crea el registro
                     */
                    
                    if(!isset($existingComment) || empty($existingComment))
                    {
                        $newComment = array(
                            'friend_id' => $existingFriend['Friend']['id'],
                            'user_id' => $user_id,
                            'source' => $sourceId,
                            'type' => 'PHOTO'
                        );
                        
                        $this->Comment->create();
                        
                        if(!$this->Comment->save($newComment))
                        {
                            $this->Session->setFlash(__('Error al guardar el nuevo comentario.'));
                        }

                    }
                    
                }
                else
                {
                    
                    /**
                     * Si el amigo no se encuentra en la BD, se crea el nuevo registro
                     * y el like asociado
                     */
                    
                    $newFriend = array(
                        'user_id' => $user_id,
                        'facebook_user_id' => $friendId,
                        'name' => $friendName,
                        'pic' => ''
                    );
                    
                    $this->Friend->create();
                    
                    if(!$this->Friend->save($newFriend))
                    {
                        $this->Session->setFlash(__('Error al guardar la nueva conexion.'));
                    }
                    else
                    {
                        
                        $existingComment = $this->Comment->findByFriendIdAndUserIdAndSourceAndType($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                        
                        /*
                        $existingLike = $this->Like->find('first', array(
                            'conditions' => array(
                                'Like.friend_id' => $this->Friend->id,
                                'Like.user_id' => $user_id,
                                'Like.source' => $sourceId,
                                'Like.type' => 'PHOTO'
                            )
                        ));
                        */
                        
                        if(!isset($existingComment) || empty($existingComment))
                        {
                            $newComment = array(
                                'friend_id' => $this->Friend->id,
                                'user_id' => $user_id,
                                'source' => $sourceId,
                                'type' => 'PHOTO'
                            );

                            $this->Comment->create();
                            
                            if(!$this->Comment->save($newComment))
                            {
                                $this->Session->setFlash(__('Error al guardar el nuevo like.'));
                            }

                        }
                        
                    }
                    
                }
                  
            }
            
            
        }
        
        
    }
    
    
    function loginCallback() 
    {
        $this->autoRender = false;

        $helper = $this->fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        if (isset($accessToken)) {
            // Logged in!
            $_SESSION['facebook_access_token'] = (string) $accessToken;

            // Now you can redirect to another page and use the
            // access token from $_SESSION['facebook_access_token']
            
            $this->redirect('home');
        }
    }

    function index() {
        $this->redirect("login");
    }
    
    function logout()
    {
        $_SESSION['facebook_access_token'] = "";
                
        $this->redirect('login');
    }
    
    function modifySelectedUsers()
    {
        $accessToken = $_SESSION['facebook_access_token'];

        if(!isset($accessToken)){
            $this->redirect("logout");
        }
        
        // Sets the default fallback access token so we don't have to pass it to each request
        $this->fb->setDefaultAccessToken($accessToken);

        $response = $this->fb->get('/me?fields=id,name');

        $userNode = $response->getGraphUser();

        $userId = $userNode->getId();

        $user = $this->User->findByFacebookUserId($userId);

        if($user)
        {
            /**
             * Se genera la lista de amigos ordenados segun el numero de likes que han hecho
             */
            
            $likes = $this->Like->find('all', array(
                'fields' => array('COUNT(*) AS Total', 'Like.friend_id', 'Friend.*'),
                'conditions' => array(
                    'Like.user_id' => $user['User']['id']
                ),
                'group' => 'Like.friend_id',
                'order' => 'Total DESC',
                'limit' => 30
            ));
            
            $this->set('likes', $likes);
            
            
            if(!empty($this->data))
            {
                $friendList = $this->data['friend_list'];

                if(count($friendList) > 8)
                {
                    $this->Session->setFlash(__('Debes seleccionar a los mas 8 usuarios.'));
                }
                else
                {
                    $selectedFriends = array();
                    
                    foreach($friendList as $friendId)
                    {
                        $selectedFriends[] = array(
                            'user_id' => $user['User']['id'],
                            'friend_id' => $friendId
                        );
                        
                    }
                    
                    $this->SelectedFriend->clearSelectedFriendFromUser($user['User']['id']);
                    
                    if(!$this->SelectedFriend->saveAll($selectedFriends))
                    {
                        $this->Session->setFlash(__('Ocurrio un error.'));
                    }
                    else
                    {
                        $this->Session->setFlash(__('Los contactos seleccionados se han guardado con exito.'));
                        
                        $this->redirect('showSelectedUsers');
                    }
                    
                }

            }
            
            
        }
        
    }

    function showSelectedUsers()
    {
        $accessToken = $_SESSION['facebook_access_token'];

        if(!isset($accessToken)){
            $this->redirect("logout");
        }
        
        // Sets the default fallback access token so we don't have to pass it to each request
        $this->fb->setDefaultAccessToken($accessToken);

        $response = $this->fb->get('/me?fields=id,name');

        $userNode = $response->getGraphUser();

        $userId = $userNode->getId();

        $user = $this->User->findByFacebookUserId($userId);

        if($user)
        {
            $selectedUsers =  $this->SelectedFriend->find('all', array(
                'conditions' => array(
                    'SelectedFriend.user_id' => $user['User']['id']
                )
            ));
            
            $this->set('selectedUsers', $selectedUsers);
            
        }        
    }
    
    function getEmotionMingleTreeData($user_id = null)
    {
        
    }
    
    
}
