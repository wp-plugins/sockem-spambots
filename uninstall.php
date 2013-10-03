<?php
//----------------------------------------------------------------------
//  Sock'em SPAMbots uninstallation
//----------------------------------------------------------------------
// remove plugin data so as not to needlessly clutter a system that is
// no longer using Sock'em SPAMbots
//
// @since 0.5.0



//make sure WordPress is calling this page
if (!defined('WP_UNINSTALL_PLUGIN'))
	exit ();

//remove the debug log
if(file_exists(dirname(__FILE__) . '/sockem_debug.log'))
	@unlink(dirname(__FILE__) . '/sockem_debug.log');

//remove options
delete_option('sockem_options');

return true;
?>