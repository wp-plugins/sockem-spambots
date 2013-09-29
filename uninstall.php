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

//remove options
delete_option('sockem_options');

return true;
?>