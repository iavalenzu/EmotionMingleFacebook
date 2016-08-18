<?php 

class FacebookHelper extends AppHelper 
{
    public function __construct(View $view, $settings = array()) 
    {
        parent::__construct($view, $settings);
        debug($settings);
    }
    
    public function getFacebookLoginUrl()
    {
        
    }
    
}