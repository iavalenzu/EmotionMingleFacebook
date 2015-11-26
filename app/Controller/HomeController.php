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

        $this->fb = new Facebook\Facebook(array(
            'app_id' => '791535754293026',
            'app_secret' => 'c20f2a9cc413df08251eccc086c1310c',
            'default_graph_version' => 'v2.2',
        ));
        
        if(is_null($this->fb) || empty($this->fb))
        {
            $this->log("No puedo inicializar el objecto Facebook");
            $this->Session->setFlash(__('Ocurrio un error, intenta mas tarde!'));
            $this->redirect('logout');
        }

        $this->set("logout_url", $this->getLogoutUrl2());
        
    }

    private function checkSession($redirect = true)
    {
        if(isset($_SESSION['facebook_access_token']) && !empty($_SESSION['facebook_access_token'])){
            return $_SESSION['facebook_access_token'];
        }else{
            if($redirect){
                $this->redirect("logout");
            }
        }
    }
    
    
    function login() 
    {
        if($this->checkSession(false)){
            $this->redirect("home");
        }
        
        $helper = $this->fb->getRedirectLoginHelper();
        
        if(is_null($helper) || empty($helper)){
            $this->log("Login: No puedo crear el RedirectLoginHelper");
        }

        $permissions = array('email', 'user_likes', 'user_photos', 'user_posts', 'user_friends', 'user_about_me'); // optional

        $loginCallbackUrl = Router::url(array('controller'=>'home', 'action'=>'loginCallback'), true);
        
        $loginUrl = $helper->getLoginUrl($loginCallbackUrl, $permissions);

        if(is_null($loginUrl) || empty($loginUrl)){
            $this->log("Login: No puedo obtener Facebook Login Url");
        }
        
        $this->set("loginUrl", $loginUrl);
    }

    function home()
    {
        $this->checkSession();
        
        $this->redirect('refreshData');
        
    }
    
    function refreshData() 
    {
        $this->checkSession();
        
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
    
    private function processPhotoTags($user_id = null)
    {

        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessPhotoTags: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);

        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessPhotoTags: El loggedUser es nulo o vacio!");
            return;
        }
        
        try{
        
            /**
             * Se obtienen las fotos asociadas al usuario en sesion
             */

            $response = $this->fb->get('/me?fields=photos.limit(400){id,from}');

            $node = $response->getGraphNode();

            $photos = $node->getField('photos');

            if(is_null($photos) || empty($photos)){
                return;
            }

            /**
             * Para cada foto se obtienen los likes asociados
             */

            foreach($photos as $photo)
            {
                if(!isset($photo['id']) || empty($photo['id'])){
                    continue;
                }
                
                $sourceId = $photo['id'];
                
                if(!isset($photo['from']) || empty($photo['from'])){
                    continue;
                }
                
                $from = $photo['from'];

                /**
                 * Solo se consderan las fotos no subidas por mi
                 */

                if(!isset($from['id']) || empty($from['id'])){
                    continue;
                }
                
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

        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessPhotoTags: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessPhotoTags: " . $e->getMessage());
            $this->redirect("logout");
            
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
        
        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessPostLikes: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessPostLikes: El loggedUser es nulo o vacio!");
            return;
        }
        
        
        try{
        
            /**
             * Se obtienen los likes asociados a las fotos del usuario en sesion
             */

            $response = $this->fb->get('/me?fields=posts.limit(400){likes.limit(100){id,username,pic,profile_type,name},message,id,from}');

            $node = $response->getGraphNode();

            $posts = $node->getField('posts');

            if(is_null($posts) || empty($posts)){
                return;
            }

            /**
             * Para cada foto se obtienen los likes asociados
             */

            foreach($posts as $post)
            {
                if(!isset($post['id']) || empty($post['id'])){
                    continue;
                }
                
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

                if(is_null($likes) || empty($likes))
                {
                    continue;
                }

                /**
                 * Para cada like se obtiene la info del amigo que hizo el like
                 */

                foreach($likes as $like)
                {
                    if(!isset($like['id']) || empty($like['id'])){
                        continue;
                    }
                    
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
        
        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessPostLikes: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessPostLikes: " . $e->getMessage());
            $this->redirect("logout");
            
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
        
        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessPostComments: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);

        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessPostComments: El loggedUser es nulo o vacio!");
            return;
        }
        
        
        try{
        
            /**
             * Se obtienen los comentarios asociados a las fotos del usuario en sesion
             */

            $response = $this->fb->get('/me?fields=posts.limit(400){comments.limit(100){from,message,id},id,from}');

            $node = $response->getGraphNode();

            $posts = $node->getField('posts');

            if(is_null($posts) || empty($posts)){
                return;
            }

            /**
             * Para cada foto se obtienen los likes asociados
             */

            foreach($posts as $post)
            {
                if(!isset($post['id']) || empty($post['id'])){
                    continue;
                }
                
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

                    if(is_null($from) || empty($from)){
                        continue;
                    }

                    if(!isset($from['id']) || empty($from['id'])){
                        continue;
                    }
                    
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
        
        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessPostComments: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessPostComments: " . $e->getMessage());
            $this->redirect("logout");
            
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
        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessPhotoLikes: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessPhotoLikes: El loggedUser es nulo o vacio!");
            return;
        }
        
        try{
        
            /**
             * Se obtienen los likes asociados a las fotos del usuario en sesion
             */

            $response = $this->fb->get('/me?fields=photos.limit(400){likes.limit(100){username,name,profile_type,id,pic},id,from}');

            $node = $response->getGraphNode();

            $photos = $node->getField('photos');

            if(is_null($photos) || empty($photos)){
                return;
            }

            /**
             * Para cada foto se obtienen los likes asociados
             */

            foreach($photos as $photo)
            {
                if(!isset($photo['id']) || empty($photo['id'])){
                    continue;
                }
                
                $sourceId = $photo['id'];
                
                if(!isset($photo['from']) || empty($photo['from'])){
                    continue;
                }
                
                $from = $photo['from'];

                if(!isset($from['id']) || empty($from['id'])){
                    continue;
                }
                
                /**
                 * NO se consideran las photos que no hayan sido subidas por mi
                 */

                if($from['id'] != $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }

                $likes = $photo->getField('likes');

                if(is_null($likes) || empty($likes))
                {
                    continue;
                }

                /**
                 * Para cada like se obtiene la info del amigo que hizo el like
                 */

                foreach($likes as $like)
                {
                    if(!isset($like['id']) || empty($like['id'])){
                        continue;
                    }
                    
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
        
        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessPhotoLikes: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessPhotoLikes: " . $e->getMessage());
            $this->redirect("logout");
            
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
        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessPhotoComments: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);
        
        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessPhotoComments: El loggedUser es nulo o vacio!");
            return;
        }
        
        
        try{
        
            /**
             * Se obtienen los comentarios asociados a las fotos del usuario en sesion
             */

            $response = $this->fb->get('/me?fields=photos.limit(400){comments.limit(100){from,message,id},id,from}');

            $node = $response->getGraphNode();

            $photos = $node->getField('photos');

            if(is_null($photos) || empty($photos)){
                return;
            }

            /**
             * Para cada foto se obtienen los likes asociados
             */

            foreach($photos as $photo)
            {
                if(!isset($photo['id']) || empty($photo['id']))
                {
                    continue;
                }
                
                $sourceId = $photo['id'];
                
                
                if(!isset($photo['from']) || empty($photo['from']))
                {
                    continue;
                }
                
                $from = $photo['from'];

                
                if(!isset($from['id']) || empty($from['id']))
                {
                    continue;
                }
                
                /**
                 * NO se consideran las photos que no hayan sido subidas por mi
                 */

                if($from['id'] != $loggedUser['User']['facebook_user_id'])
                {
                    continue;
                }


                $comments = $photo->getField('comments');

                if(is_null($comments) || empty($comments))
                {
                    continue;
                }

                /**
                 * Para cada like se obtiene la info del amigo que hizo el like
                 */

                foreach($comments as $comment)
                {

                    $from = $comment->getField('from');
                    
                    if(is_null($from) || empty($from)){
                        continue;
                    }
 
                    if(!isset($from['id']) || empty($from['id']))
                    {
                        continue;
                    }

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
        
        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessPhotoComments: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessPhotoComments: " . $e->getMessage());
            $this->redirect("logout");
            
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
        if(is_null($user_id) || empty($user_id)){
            $this->log("ProcessTagged: El user_id es nulo o vacio!");
            return;
        }
        
        $this->User->recursive = -1;
        $loggedUser = $this->User->findById($user_id);

        if(is_null($loggedUser) || empty($loggedUser)){
            $this->log("ProcessTagged: El loggedUser es nulo o vacio!");
            return;
        }
        
        
        try{
        
            /**
             * Se obtienen los comentarios asociados a las fotos del usuario en sesion
             */

            $response = $this->fb->get('/me?fields=tagged.limit(400){from,message,type}');

            $node = $response->getGraphNode();

            $tagged = $node->getField('tagged');

            if(is_null($tagged) || empty($tagged)){
                return;
            }

            /**
             * Para cada tag se verifica si el amigo esta gusraddo y se crea un registro en Tag
             */

            foreach($tagged as $tag)
            {
                if(!isset($tag['id']) || empty($tag['id'])){
                    continue;
                }

                $sourceId = $tag['id'];
                
                if(!isset($tag['from']) || empty($tag['from'])){
                    continue;
                }
                
                $from = $tag['from'];

                /**
                 * NO se consideran las photos que no hayan sido subidas por mi
                 */
                
                if(!isset($from['id']) || empty($from['id'])){
                    continue;
                }

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
        
        } catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            // When Graph returns an error
            $this->log("ProcessTagged: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            $this->log("ProcessTagged: " . $e->getMessage());
            $this->redirect("logout");
            
        }         
        
        
    }
    
    function loginCallback() 
    {
        $this->autoRender = false;

        $helper = $this->fb->getRedirectLoginHelper();

        if(is_null($helper) || empty($helper)){
            $this->log("LoginCallback: El helper es nulo o vacio!");
            $this->redirect('logout');
        }
        
        try 
        {
            $accessToken = $helper->getAccessToken();
            
            if (isset($accessToken) && !empty($accessToken)) 
            {
                // Logged in!
                $_SESSION['facebook_access_token'] = (string) $accessToken;

                // Now you can redirect to another page and use the
                // access token from $_SESSION['facebook_access_token']

                $this->redirect('refreshData');
            }
            else
            {
                $this->log("LoginCallback: El accessToken no esta definido!");
                $this->redirect('logout');
            }
            
        } 
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("LoginCallback: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("LoginCallback: " . $e->getMessage());
            $this->redirect("logout");
        }

    }

    function index() 
    {
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
        $this->checkSession();

        $this->autoLayout = false;
        
        $redirect_url = $this->getRedirectUrl();
        
        $this->set('redirect_url', $redirect_url);
        
    }
    
    function refresh()
    {
        $accessToken = $this->checkSession();
                    
        // Sets the default fallback access token so we don't have to pass it to each request
        $this->fb->setDefaultAccessToken($accessToken);

        try 
        {
            $response = $this->fb->get('/me?fields=id,name,picture');

            $userNode = $response->getGraphUser();
            
            $userId = $userNode->getId();
            $userName = $userNode->getName();
            $userPicture = $userNode->getPicture();

            $user = $this->User->findByFacebookUserId($userId);

            if(!isset($user) || empty($user))
            {
                $user = array(
                    'name' => $userName,
                    'facebook_user_id' => $userId,
                    'api_key' => mt_rand (100000 , 999999),
                    'pic' => $userPicture['url']
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
            
        } 
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("Refresh: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("Refresh: " . $e->getMessage());
            $this->redirect("logout");
        }
        
    }
    
    function friendDetails($friend_id = null)
    {
        $this->checkSession();

        $friend = $this->Friend->findById($friend_id);
        
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
        try
        {
            $accessToken = $this->checkSession();

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

                /**
                 * Se ordenan segun el numero de interacciones de mayor a menor
                 */
                usort($friends, array( $this, 'interactionsOrder' ));

                $this->set('friends', $friends);

                $this->SelectedFriend->recursive = -1;
                $currentSelectedFriends = $this->SelectedFriend->findAllByUserId($user['User']['id']);

                $this->set('currentSelectedFriends', $currentSelectedFriends);

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
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("ModifySelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("ModifySelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
        }
        
        
        
    }

    function showSelectedUsers()
    {
        try
        {
        
            $accessToken = $this->checkSession();

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
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("ShowSelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("ShowSelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
        }
        
        
        
    }
    
    function account()
    {
        try
        {
        
            $accessToken = $this->checkSession();

            // Sets the default fallback access token so we don't have to pass it to each request
            $this->fb->setDefaultAccessToken($accessToken);

            $response = $this->fb->get('/me?fields=id,name');

            $userNode = $response->getGraphUser();

            $userId = $userNode->getId();

            $user = $this->User->findByFacebookUserId($userId);

            $this->set('user', $user);
        
        } 
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("Account: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("Account: " . $e->getMessage());
            $this->redirect("logout");
        }
        
        
        
    }
    
    function showTree()
    {
        try
        {
        
            $accessToken = $this->checkSession();

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
        catch (Facebook\Exceptions\FacebookResponseException $e) 
        {
            $this->log("ShowSelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
        } 
        catch (Facebook\Exceptions\FacebookSDKException $e) 
        {
            $this->log("ShowSelectedUsers: " . $e->getMessage());
            $this->redirect("logout");
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
    
    private function getLeafValue($leaf = null, $user_id = null)
    {
        $selectedFriend = $this->SelectedFriend->findByUserIdAndLeaf($user_id, $leaf);
        
        if(empty($selectedFriend)){
            return 0;
        }
        
        $friend = $this->Friend->findById($selectedFriend['SelectedFriend']['friend_id']);
        
        if(empty($friend)){
            return 0;
        }
        
        return $friend['Friend']['interactions'];
    }
    
    function getEmotionMingleTreeData($user_api_key = null)
    {
        $this->autoRender = false;
        $this->response->type('json');
        
        $user = $this->User->findByApiKey($user_api_key);

        $leaf1 = 0;
        $leaf2 = 0;
        $leaf3 = 0;
        $leaf4 = 0;
        $leaf5 = 0;
        $leaf6 = 0;
        $leaf7 = 0;
        $leaf8 = 0;
        
        
        if($user)
        {
            $leaf1 = $this->getLeafValue(1, $user['User']['id']);
            $leaf2 = $this->getLeafValue(2, $user['User']['id']);
            $leaf3 = $this->getLeafValue(3, $user['User']['id']);
            $leaf4 = $this->getLeafValue(4, $user['User']['id']);
            $leaf5 = $this->getLeafValue(5, $user['User']['id']);
            $leaf6 = $this->getLeafValue(6, $user['User']['id']);
            $leaf7 = $this->getLeafValue(7, $user['User']['id']);
            $leaf8 = $this->getLeafValue(8, $user['User']['id']);
        }
        
	$leafs_values = array(
	    "Leaf1" => $leaf1,
	    "Leaf2" => $leaf2,
	    "Leaf3" => $leaf3,
	    "Leaf4" => $leaf4,
	    "Leaf5" => $leaf5,
	    "Leaf6" => $leaf6,
	    "Leaf7" => $leaf7,
	    "Leaf8" => $leaf8
	);
        
        $json = json_encode($leafs_values);
        $this->response->body($json);               
    }
    
    
    function privacy_policy()
    {
        
    } 
    
}
