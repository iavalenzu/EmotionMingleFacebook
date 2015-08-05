<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class SelectedFriend extends AppModel {
    
    public $belongsTo = array(
        'Friend' => array(
            'className' => 'Friend',
        ),
        'User' => array(
            'className' => 'User',
        )
    );    
    
    
    public function clearSelectedFriendFromUser($user_id = null)
    {
        if(is_null($user_id)){
            return;
        }
        
        $this->deleteAll(array(
            'SelectedFriend.user_id' => $user_id
        ), false);    
        
    }
    
}
