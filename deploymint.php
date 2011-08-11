<?php
/**
 * @package DeployMint 
 * @version 0.1
 */
/*
Plugin Name: DeployMint
Plugin URI: http://markmaunder.com/
Description: DeployMint: A staging and deployment system for Wordpress 
Author: Mark Maunder <mmaunder@gmail.com>
Version: 0.1
Author URI: http://markmaunder.com/
*/



require('deploymintClass.php');
register_activation_hook(__FILE__, 'deploymint::installPlugin');
deploymint::setup();

?>
