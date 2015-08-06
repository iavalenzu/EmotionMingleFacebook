<?php 

echo $this->Html->link(
    $this->Html->image("log_in_with_facebook.png", array("alt" => "Log in with facebook")),
    $loginUrl,
    array('escape' => false)
);


?>