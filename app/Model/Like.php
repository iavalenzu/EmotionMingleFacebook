<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


class Like extends AppModel {
    
    public $belongsTo = array(
        'Friend' => array(
            'className' => 'Friend',
        ),
        'User' => array(
            'className' => 'User',
        )
    );    
    
    
    public function saveUniqueLike($friend_id = null, $user_id = null, $source_id = null, $type = null)
    {
        if(is_null($friend_id) || is_null($user_id) || is_null($source_id) || is_null($type)){
            return;
        }
        
        $existingLike = $this->find('first', array(
            'conditions' => array(
                'Like.friend_id' => $friend_id,
                'Like.user_id' => $user_id,
                'Like.source' => $source_id,
                'Like.type' => $type
            ),
            'recursive' => -1
        ));
        
        if(!isset($existingLike) || empty($existingLike))
        {
            $newLike = array(
                'friend_id' => $friend_id,
                'user_id' => $user_id,
                'source' => $source_id,
                'type' => $type
            );

            $this->create();
            $this->save($newLike);

        }
        
    }
    
}
