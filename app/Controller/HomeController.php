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

    public $uses = array('User', 'Friend', 'Like', 'Comment', 'SelectedFriend', 'Tag');    
    
    public $fb;

    public function beforeFilter() 
    {
        parent::beforeFilter();

        session_start();

        $this->fb = new Facebook\Facebook([
            'app_id' => '791535754293026',
            'app_secret' => 'c20f2a9cc413df08251eccc086c1310c',
            'default_graph_version' => 'v2.2',
        ]);

        $this->set("logout_url", $this->getLogoutUrl2());
        
    }

    function login() 
    {
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token'])){
            $this->redirect("home");
        }
        
        $helper = $this->fb->getRedirectLoginHelper();

        $permissions = ['email', 'user_likes']; // optional

        $loginUrl = $helper->getLoginUrl('http://localhost/EmotionMingleFacebook/home/loginCallback', $permissions);

        $this->set("loginUrl", $loginUrl);
    }

    function home()
    {
        
    }
    
    function refreshData() 
    {
        $this->setRedirecUrl(Router::url(array('controller'=>'home', 'action'=>'refresh'), true));
        $this->redirect('loading');
    }
    
    /**
     * 
     * Se obtienen las fotos que no han sido subidas por mi en las cuales aparezco
     * Esta info se refleja en una registro de la tabla Tag
     * 
     * @param type $user_id
     * @return type
     */
    
    private function processPhotoTags($user_id)
    {

        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen las fotos asociadas al usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=photos{id,from}');
        
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
            $from = $photo['from'];
            
            /**
             * Solo se consderan las fotos no subidas por mi
             */
            
            $friendId = $from['id'];
            $friendName = $from['name'];
            
            if($friendId == $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
            
            /**
             * Se busca en la BD la info de amigo asociado al id dado
             */

            $this->Friend->recursive = -1;
            $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

            if(isset($existingFriend) && !empty($existingFriend))
            {
                /**
                 * Se busca si el like ya se encuentra en la BD
                 */

                $this->Tag->saveUniqueTag($existingFriend['Friend']['id'], $user_id, $sourceId, 'PHOTO');
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
                    $this->Tag->saveUniqueTag($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                 }
             }
        }        
    }
    
    /**
     * 
     * Se obtienen los likes de amigos en las publicaciones hechas por mi.
     * 
     * @param type $user_id
     * @return type
     */
    private function processPostLikes($user_id)
    {
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen los likes asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=posts{likes{id,username,pic,profile_type,name},message,id,from}');
        
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
            $from = $post['from'];
            
            /**
             * NO se consideran las post que no hayan sido subidas por mi
             */
            
            if($from['id'] != $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
             
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
                 * Si el like fue hecho por mi, no lo considero
                 */
                
                if($friendId == $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }
                
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $this->Friend->recursive = -1;
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

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
                    
                    $this->Like->saveUniqueLike($existingFriend['Friend']['id'], $user_id, $sourceId, 'POST');
                    
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
                        $this->Like->saveUniqueLike($this->Friend->id, $user_id, $sourceId, 'POST');
                    }
                }
            }
        }
    }

    /**
     * 
     * Se obtienen los comentarios de amigos sobre publicaciones hachas por mi
     * 
     * @param type $user_id
     * @return type
     */
    
    private function processPostComments($user_id)
    {
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen los comentarios asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=posts{comments{from,message,id},id,from}');
        
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
            $from = $post['from'];
            
            /**
             * NO se consideran las posts que no hayan sido subidas por mi
             */
            
            if($from['id'] != $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
            
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
                 * Si el comment fue hecho por mi, no lo considero
                 */
                
                if($friendId == $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }
                
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $this->Friend->recursive = -1;
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se busca si el comment ya se encuentra en la BD
                     */
                    
                    $this->Comment->saveUniqueComment($existingFriend['Friend']['id'], $user_id, $sourceId, 'POST');
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
                        $this->Comment->saveUniqueComment($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                    }
                }
            }
        }

    }
    
    /**
     * Se obtienen los likes de amigos sobre fotos hechas por mi
     * 
     * @param type $user_id
     * @return type
     */
    
    private function processPhotoLikes($user_id)
    {

        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen los likes asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=photos{likes{username,name,profile_type,id,pic},id,from}');
        
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
            $from = $photo['from'];
            
            /**
             * NO se consideran las photos que no hayan sido subidas por mi
             */
            
            if($from['id'] != $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
            
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
                 * Si el like fue hecho por mi, no lo considero
                 */
                
                if($friendId == $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $this->Friend->recursive = -1;
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

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
                    
                    $this->Like->saveUniqueLike($existingFriend['Friend']['id'], $user_id, $sourceId, 'PHOTO');
                    
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
                        $this->Like->saveUniqueLike($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                    }
                }
            }
        }
    }
    
    /**
     * Se obtienen los comentarios de amigos sobre fotos subidas por mi
     * 
     * @param type $user_id
     * @return type
     */
    
    private function processPhotoComments($user_id)
    {
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen los comentarios asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=photos{comments{from,message,id},id,from}');
        
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
            $from = $photo['from'];
            
            /**
             * NO se consideran las photos que no hayan sido subidas por mi
             */
            
            if($from['id'] != $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
            
            
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
                 * Si el comentario fue hecho por mi, no lo considero
                 */
                
                if($friendId == $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }
                
                
                /**
                 * Se busca en la BD la info de amigo asociado al id dado
                 */
                
                $this->Friend->recursive = -1;
                $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

                if(isset($existingFriend) && !empty($existingFriend))
                {
                    /**
                     * Se busca si el like ya se encuentra en la BD
                     */
                    
                    $this->Comment->saveUniqueComment($existingFriend['Friend']['id'], $user_id, $sourceId, 'PHOTO');
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
                        $this->Comment->saveUniqueComment($this->Friend->id, $user_id, $sourceId, 'PHOTO');
                    }
                }
            }
        }
    }
    
    /**
     * 
     * Se obtienen los tags de amigos no hechos por mi
     * 
     * @param type $user_id
     * @return type
     */
    private function processTagged($user_id)
    {
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        /**
         * Se obtienen los comentarios asociados a las fotos del usuario en sesion
         */
        
        $response = $this->fb->get('/me?fields=tagged{from,message,type}');
        
        $node = $response->getGraphNode();

        $tagged = $node->getField('tagged');
        
        if(empty($tagged)){
            return;
        }
        
        /**
         * Para cada tag se verifica si el amigo esta gusraddo y se crea un registro en Tag
         */
        
        foreach($tagged as $tag)
        {
            $sourceId = $tag['id'];
            $from = $tag['from'];
            
            /**
             * NO se consideran las photos que no hayan sido subidas por mi
             */
            
            $friendId = $from['id'];
            $friendName = $from['name'];
            
            if($friendId == $loggedUser['User']['facebook_user_id'])
            {
                continue;
            }
            
            /**
             * Se busca en la BD la info de amigo asociado al id dado
             */

            $this->Friend->recursive = -1;
            $existingFriend = $this->Friend->findByUserIdAndFacebookUserId($user_id, $friendId);

            if(isset($existingFriend) && !empty($existingFriend))
            {
                /**
                 * Se busca si el like ya se encuentra en la BD
                 */

                $this->Tag->saveUniqueTag($existingFriend['Friend']['id'], $user_id, $sourceId, 'TAG');
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
                    $this->Tag->saveUniqueTag($this->Friend->id, $user_id, $sourceId, 'TAG');
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
            
            $this->redirect('refreshData');
        }
    }

    function index() {
        $this->redirect("login");
    }
    
    function logout()
    {
        unset($_SESSION['facebook_access_token']);
                
        $this->redirect('login');
    }
    
    private function setRedirecUrl($url)
    {
        $_SESSION['redirect_url'] = $url;
    }
    
    private function getRedirectUrl()
    {
        if(isset($_SESSION['redirect_url']) && !empty($_SESSION['redirect_url']))
        {
            return $_SESSION['redirect_url'];
        }
        
        return false;
    }
    
    function loading()
    {
        $this->autoLayout = false;
        
        $redirect_url = $this->getRedirectUrl();
        
        $this->set('redirect_url', $redirect_url);
        
    }
    
    function refresh()
    {
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token'])){
            $accessToken = $_SESSION['facebook_access_token'];
        }else{
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

                $this->User->create();
                if(!$this->User->save($user))
                {
                    $this->log("Hubo un error al guardar el usuario");
                }
                else
                {
                    $this->processPhotoLikes($this->User->id);
                    $this->processPhotoComments($this->User->id);

                    $this->processPostLikes($this->User->id);
                    $this->processPostComments($this->User->id);
                    
                    $this->processPhotoTags($this->User->id);
                    
                    $this->processTagged($this->User->id);
                }
            }
            else
            {
                $this->processPhotoLikes($user['User']['id']);
                $this->processPhotoComments($user['User']['id']);
                
                $this->processPostLikes($user['User']['id']);
                $this->processPostComments($user['User']['id']);
                
                $this->processPhotoTags($user['User']['id']);
                
                $this->processTagged($user['User']['id']);
                
                
            }
            
            $this->redirect('showSelectedUsers');
            
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
        }
        
    }
    
    function friendDetails($friend_id = null)
    {
        $friend = $this->Friend->findById($friend_id);
        
        //debug($friend);
        
        $this->set('friend', $friend);
        
    }
    
    function interactionsOrder($friend0, $friend1)
    {
        if ($friend0['Friend']['interactions'] == $friend1['Friend']['interactions']) {
            return 0;
        }
        return ($friend0['Friend']['interactions'] > $friend1['Friend']['interactions']) ? -1 : 1;
    }
    
    
    function modifySelectedUsers()
    {
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token'])){
            $accessToken = $_SESSION['facebook_access_token'];
        }else{
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
             * Se genera la lista de amigos ordenados segun el numero de interacciones que han hecho
             */
            
            
            $friends = $this->Friend->findAllByUserId($user['User']['id']);
            
            foreach($friends as &$friend)
            {
                $likes = count($friend['Like']);
                $comments = count($friend['Comment']);
                $tags = count($friend['Tag']);
                
                $friend['Friend']['interactions'] = $likes + $comments + $tags;
            }
            
            usort($friends, array( $this, 'interactionsOrder' ));
            
            /*
            $friends = $this->Friend->find('all', array(
                'fields' => array('COUNT(*) AS Total', 'Friend.*'),
                'conditions' => array(
                    'Friend.user_id' => $user['User']['id']
                ),
                'joins' => array(
                    
                    array(
                        'table' => 'comments',
                        'alias' => 'CommentJoin',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'CommentJoin.friend_id = Friend.id',
                            'Friend.id IS NULL'
                        )
                    ),
                    array(
                        'table' => 'likes',
                        'alias' => 'LikeJoin',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'LikeJoin.friend_id = Friend.id',
                            'Friend.id IS NULL'
                        )
                    ),
                    array(
                        'table' => 'tags',
                        'alias' => 'TagJoin',
                        'type' => 'LEFT',
                        'conditions' => array(
                            'TagJoin.friend_id = Friend.id',
                            'Friend.id IS NULL'
                        )
                    ),
                ),
                'group' => 'Friend.id',
                'order' => 'Total DESC',
                'limit' => 50
                
            ));
             * 
             */
            
            //debug($friends);
            
            $this->set('friends', $friends);
            
            
            if(!empty($this->data))
            {
                
                $selectedFriends = array();
                
                foreach($this->data as $option)
                {
                    if(isset($option['friend_id']))
                    {
                        $selectedFriends[] = array(
                            'user_id' => $user['User']['id'],
                            'friend_id' => $option['friend_id'],
                            'leaf' => $option['leaf']
                        );
                    }
                }
                
                if(count($selectedFriends) > 8)
                {
                    $this->Session->setFlash(__('Debes seleccionar a los mas 8 usuarios.'));
                }
                else
                {
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
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token'])){
            $accessToken = $_SESSION['facebook_access_token'];
        }else{
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
            
            //debug($selectedUsers);
            
            $this->set('selectedUsers', $selectedUsers);
            
        }   
        
    }
    
    
    private function getLogoutUrl()
    {
        $url = Router::url(array('controller'=>'home', 'action'=>'logout'), true);
        
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token']))
        {
            $accessToken = $_SESSION['facebook_access_token'];

            $helper = $this->fb->getRedirectLoginHelper();

            return $helper->getLogoutUrl($accessToken, $url);
            
        }        
        
        return "";
    }

    private function getLogoutUrl2()
    {
        return Router::url(array('controller'=>'home', 'action'=>'logout'), true);
        
    }
    
    
    function getEmotionMingleTreeData($user_id = null)
    {
        
    }
    
    
}
