<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Comment extends AppModel {
    
    public $belongsTo = array(
        'Friend' => array(
            'className' => 'Friend',
        ),
        'User' => array(
            'className' => 'User',
        )
    );    
    
    public function saveUniqueComment($friend_id = null, $user_id = null, $source_id = null, $type = null)
    {
        if(is_null($friend_id) || is_null($user_id) || is_null($source_id) || is_null($type)){
            return;
        }
        
        $existingComment = $this->find('first', array(
            'conditions' => array(
                'Comment.friend_id' => $friend_id,
                'Comment.user_id' => $user_id,
                'Comment.source' => $source_id,
                'Comment.type' => $type
            ),
            'recursive' => -1
        ));
        
        if(!isset($existingComment) || empty($existingComment))
        {
            $newComment = array(
                'friend_id' => $friend_id,
                'user_id' => $user_id,
                'source' => $source_id,
                'type' => $type
            );

            $this->Comment->create();
            $this->Comment->save($newComment);

        }
        
    }    
    
}
