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
    
}

