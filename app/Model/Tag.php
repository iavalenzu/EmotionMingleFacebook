<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


class Tag extends AppModel {
    
    public $belongsTo = array(
        'Friend' => array(
            'className' => 'Friend',
        ),
        'User' => array(
            'className' => 'User',
        )
    );    
    
    
    public function saveUniqueTag($friend_id = null, $user_id = null, $source_id = null, $type = null)
    {
        if(is_null($friend_id) || is_null($user_id) || is_null($source_id) || is_null($type)){
            return;
        }
        
        $existingTag = $this->find('first', array(
            'conditions' => array(
                'Tag.friend_id' => $friend_id,
                'Tag.user_id' => $user_id,
                'Tag.source' => $source_id,
                'Tag.type' => $type
            ),
            'recursive' => -1
        ));
        
        if(!isset($existingTag) || empty($existingTag))
        {
            $newTag = array(
                'friend_id' => $friend_id,
                'user_id' => $user_id,
                'source' => $source_id,
                'type' => $type
            );

            $this->create();
            $this->save($newTag);

        }
        
    }
    
}
