<?php
/*
Plugin Name: Sock'Em SPAMbots
Plugin URI: http://wordpress.org/extend/plugins/sockem-spambots/
Description: A seamless approach to deflecting the vast majority of SPAM comments.
Version: 0.5.0
Author: Blobfolio, LLC
Author URI: http://www.blobfolio.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

	Copyright © 2013  Blobfolio, LLC  (email: hello@blobfolio.com)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/



//----------------------------------------------------------------------
//  Variable handling
//----------------------------------------------------------------------

//--------------------------------------------------
// Get an option
//
// @since 0.5.0
//
// @param $option option_name, or null for all
// @param $refresh whether to run get_option() or not
// @return option_value or null
function sockem_get_option($option=null, $refresh=false){

	static $sockem_options;

	//(re)query options
	if(!is_array($sockem_options) || $refresh === true)
	{
		//load the saved settings
		$sockem_options = get_option('sockem_options', array());
		if(!is_array($sockem_options))
			$sockem_options = array();

		//the default settings
		$sockem_defaults = array('test_js'=>true,					//require js support to submit comment
								 'test_cookie'=>true,				//require cookie support to submit comment
								 'test_filler'=>true,				//require leaving this field empty to submit comment
								 'salt'=>sockem_make_salt(),		//a salt used to make hashes less predictable one site to the next
								 'algo'=>'sha512',					//the hasing algorithm to use
								 'sockem_disable_trackbacks'=>true,	//whether to disable trackback support
								 'sockem_disable_pingbacks'=>false	//whether to disable pingback support
								 );

		//supply any missing settings with defaults
		$tmp = array_diff(array_keys($sockem_defaults), array_keys($sockem_options));
		if(count($tmp))
		{
			foreach($tmp AS $key)
				$sockem_options[$key] = $sockem_defaults[$key];

			update_option('sockem_options', $sockem_options);
		}

		//strip out any unnecessary settings if for some reason they exist...
		$tmp = array_diff(array_keys($sockem_options), array_keys($sockem_defaults));
		if(count($tmp))
		{
			foreach($tmp AS $key)
				unset($sockem_options[$key]);

			update_option('sockem_options', $sockem_options);
		}

		//make sure the hashing algorithm is supported
		$tmp = hash_algos();
		if(!in_array($sockem_options['algo'], $tmp))
		{
			//let's run through preferences until we find one that matches
			foreach(array('sha512','sha384','sha256','sha224','sha1','md5') AS $algo)
			{
				if(in_array($algo, $tmp))
				{
					$sockem_options['algo'] = $algo;
					update_option('sockem_options', $sockem_options);
					break;
				}
			}
		}

		//clear $tmp
		unset($tmp);

	}//end (re)query options

	//return all options?
	if(is_null($option))
		return $sockem_options;
	//return a specific option
	elseif(array_key_exists($option, $sockem_options))
		return $sockem_options[$option];
	//return nothing
	else
		return null;
}

//--------------------------------------------------
// Generate a random salt
//
// @since 0.5.0
//
// @param length
// @return string
function sockem_make_salt($length=15){
	$length = (int) $length;
	if($length <= 0)
		return '';

	//possible characters
	$soup = array_merge(range('a','z'), range(0,9), range('A','Z'), array('!',';','.','_','@','#','$','%','^','&','?',':'));

	//pre-shuffle to mitigate selection bias
	shuffle($soup);

	//pick entries at random, one letter at a time to allow doubling up
	$salt = '';
	for($x=0; $x<$length; $x++)
		$salt .= $soup[array_rand($soup, 1)];

	//return the combination
	return $salt;
}

//--------------------------------------------------
// Generate Javascript hash
//
// @since 0.5.0
//
// @param $post->ID
// @return hash
function sockem_make_js_hash($post_id=0){

	$sockem_options = sockem_get_option();

	//the hash is site_url()|salt|post_id|date
	return hash($sockem_options['algo'], site_url() . '|' . $sockem_options['salt'] . '|' . intval($post_id) . '|' . date('Y-m-d'));
}

//----------------------------------------------------------------------  end variables



//----------------------------------------------------------------------
//  Sock'Em SPAMbots WP backend
//----------------------------------------------------------------------
//functions relating to the wp-admin pages, e.g. settings

//--------------------------------------------------
// Create a Settings->Sock'Em SPAMbots menu item
//
// @since 0.5.0
//
// @param n/a
// @return true
function sockem_settings_menu(){
	add_options_page("Sock'Em SPAMbots", "Sock'Em SPAMbots", 'manage_options', 'sockem-settings', 'sockem_settings');
	return true;
}
add_action('admin_menu', 'sockem_settings_menu');

//--------------------------------------------------
// Create a plugin page link to the settings too.
//
// @since 0.5.0
//
// @param $links
// @return $links + settings link
function sockem_plugin_settings_link($links) {
  $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=sockem-settings')) . '">Settings</a>';
  return $links;
}
add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'sockem_plugin_settings_link' );

//--------------------------------------------------
// The Settings->Sock'Em SPAMbots page
//
// this is an external file (settings.php)
//
// @since 0.5.0
//
// @param n/a
// @return true
function sockem_settings(){
	require_once(dirname(__FILE__) . '/settings.php');
	return true;
}

//----------------------------------------------------------------------  end WP backend stuff



//----------------------------------------------------------------------
//  Comment form modification(s)
//----------------------------------------------------------------------

//--------------------------------------------------
// Comment form modification(s), based on enabled
// settings
//
// @since 0.5.0
//
// @param post id
// @return true (content is echoed)
function sockem_comment_form($post_id=0){

	//read the options
	$sockem_options = sockem_get_option();

	//store output so we can release it in one go
	ob_start();

	//javascript required: add a field via javascript
	if($sockem_options['test_js'] === true)
		echo "\n<script>document.write('" . '<input type="hidden" name="sockem_js" value="' . sockem_make_js_hash($post_id) . '" />' . "')</script><noscript><p class=\"form-allowed-tags\">Your browser must have Javascript support enabled to leave comments.</noscript>\n";

	//filler test?
	if($sockem_options['test_filler'] === true)
		echo "\n" . '<input type="text" name="sockem_filler" value="" style="display: none; speak: none;" placeholder="Do not type anything here, please." />';

	echo COOKIE_DOMAIN;

	//echo the modifications, if any
	echo ob_get_clean();

	return true;
}
add_action('comment_form', 'sockem_comment_form');

//--------------------------------------------------
// Cookie support needs to be done before headers
// are sent
//
// @since 0.5.0
//
// @param n/a
// @return true
function sockem_test_cookie(){

	//if cookie support is required...
	if(sockem_get_option('test_cookie') === true && !array_key_exists('sockem_cookie', $_COOKIE))
		setcookie('sockem_cookie', 1, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

	return true;
}
add_action('send_headers', 'sockem_test_cookie');

//--------------------------------------------------
// Validate comment form submissions, based on
// enabled settings
//
// @since 0.5.0
//
// @param commentdata
// @return true
function sockem_comment_form_validation($commentdata){

	//read the options
	$sockem_options = sockem_get_option();

	//validation for regular comments
	if(!strlen($commentdata['comment_type']))
	{
		//javascript required: make sure the field is present and correct
		if($sockem_options['test_js'])
		{
			if(!array_key_exists('sockem_js', $_POST) || $_POST['sockem_js'] !== sockem_make_js_hash($commentdata['comment_post_ID']))
				wp_die('<strong>Error</strong>: Javascript support must be enabled to leave comments.');
		}

		//cookie support?
		if($sockem_options['test_cookie'])
		{
			if(!array_key_exists('sockem_cookie', $_COOKIE) || intval($_COOKIE['sockem_cookie']) !== 1)
				wp_die('<strong>Error</strong>: Cookie support must be enabled to leave comments.');
		}

		//filler test?
		if($sockem_options['test_filler'])
		{
			if(!array_key_exists('sockem_filler', $_POST) || strlen($_POST['sockem_filler']))
				wp_die('<strong>Error</strong>: Invisible text fields should be left blank.');
		}
	}//end regular comments
	//trackbacks?
	elseif($commentdata['comment_type'] === 'trackback' && $sockem_options['sockem_disable_trackbacks'] === true)
		wp_die('<strong>Error</strong>: Trackbacks have been disabled.');
	//pingbacks?
	elseif($commentdata['comment_type'] === 'pingback' && $sockem_options['sockem_disable_pingbacks'] === true)
		wp_die('<strong>Error</strong>: Pingbacks have been disabled.');

	//if we haven't died, then our job is done.
	return $commentdata;
}
if(!is_admin())
	add_filter('preprocess_comment', 'sockem_comment_form_validation', 1);

//----------------------------------------------------------------------  end comment form modification(s)