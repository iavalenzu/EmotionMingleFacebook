<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Friend extends AppModel {
    
    
    public $hasMany = array(
        'Like' => array(
            'className' => 'Like',
        ),
        'Tag' => array(
            'className' => 'Tag',
        ),
        'Comment' => array(
            'className' => 'Comment',
        ),
    );
    
    public function afterFind($results, $primary = false) 
    {
        foreach ($results as $key => $val) 
        {
            $likes = $this->Like->find('count', array(
                    'conditions' => array('Like.friend_id' => $val['Friend']['id'])
            ));            

            $comments = $this->Comment->find('count', array(
                    'conditions' => array('Comment.friend_id' => $val['Friend']['id'])
            ));            
            
            $tags = $this->Tag->find('count', array(
                    'conditions' => array('Tag.friend_id' => $val['Friend']['id'])
            ));            
            
            $results[$key]['Friend']['interactions'] = $likes + $comments + $tags;
        }
        
        return $results;
    }    
    
    
}

