<?php
/*
Plugin Name: Sock'Em SPAMbots
Plugin URI: http://wordpress.org/extend/plugins/sockem-spambots/
Description: A seamless approach to deflecting the vast majority of SPAM comments.
Version: 0.9.0
Author: Blobfolio, LLC
Author URI: http://www.blobfolio.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

	Copyright © 2015  Blobfolio, LLC  (email: hello@blobfolio.com)

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
		$sockem_defaults = array('test_js'=>true,										//require js support to submit comment
								 'test_cookie'=>true,									//require cookie support to submit comment
								 'test_filler'=>true,									//require leaving this field empty to submit comment
								 'test_speed'=>true,									//test speediness
								 'test_expiration'=>true,								//test expiration
								 'test_speed_seconds'=>5,								//the number of seconds before a submission is allowed
								 'test_expiration_seconds'=>14400,						//the number of seconds a submission is allowed
								 'test_links'=>true,									//test for excessive number of links
								 'test_links_max'=>5,									//the number of links allowed
								 'test_length'=>false,									//test comment length
								 'test_length_max'=>1500,								//maximum comment length
								 'exempt_users'=>true,									//exempt logged-in users from tests
								 'disable_comment_author_links'=>false,					//disable comment author links
								 'salt'=>sockem_make_salt(),							//a salt used to make hashes less predictable one site to the next
								 'algo'=>'sha512',										//the hasing algorithm to use
								 'disable_trackbacks'=>true,							//whether to disable trackback support
								 'disable_pingbacks'=>false,							//whether to disable pingback support
								 'debug'=>false,										//generate a debug log
								 'debug_log'=>dirname(__FILE__) . '/sockem_debug.log'	//location of log file
								 );

		//if we need to push changes to WP
		$changed = false;

		//supply any missing settings with defaults
		$tmp = array_diff(array_keys($sockem_defaults), array_keys($sockem_options));
		if(count($tmp))
		{
			foreach($tmp AS $key)
				$sockem_options[$key] = $sockem_defaults[$key];

			$changed = true;
		}

		//strip out any unnecessary settings if for some reason they exist...
		$tmp = array_diff(array_keys($sockem_options), array_keys($sockem_defaults));
		if(count($tmp))
		{
			foreach($tmp AS $key)
				unset($sockem_options[$key]);

			$changed = true;
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
					$changed = true;
					break;
				}
			}
		}

		//make sure debug log path is still good
		if(!is_dir(pathinfo($sockem_options['debug_log'], PATHINFO_DIRNAME)))
		{
			$sockem_options['debug_log'] = $sockem_defaults['debug_log'];
			$changed = true;
		}

		//save changes, if any
		if($changed)
			update_option('sockem_options', $sockem_options);

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

//--------------------------------------------------
// Generate Cookie hash
//
// @since 0.5.0
//
// @param n/a
// @return hash
function sockem_make_cookie_hash(){

	$sockem_options = sockem_get_option();

	//the hash is site_url()|salt|date, chopped so as not to fill the cookie jar
	return substr(hash($sockem_options['algo'], site_url() . '|' . $sockem_options['salt'] . '|' . date('Y-m-d')), 0, 10);
}

//--------------------------------------------------
// Generate hash for speed tests
//
// @since 0.6.0
//
// @param timestamp
// @return hash
function sockem_make_speed_hash($timestamp=0){

	$sockem_options = sockem_get_option();

	//default to NOW
	if($timestamp <= 0)
		$timestamp = current_time('timestamp');

	//return a hash of site_url()|salt|timestamp|date, plus the clean timestamp
	return hash($sockem_options['algo'], site_url() . '|' . $sockem_options['salt'] . '|' . intval($timestamp) . '|' . date('Y-m-d')) . ',' . intval($timestamp);
}

//--------------------------------------------------
// Validate speed hash
//
// @since 0.6.0
//
// @param hash
// @param mode (speed or expiration)
// @return true/false
function sockem_validate_speed_hash($hash='', $mode='speed'){

	$sockem_options = sockem_get_option();

	//make sure hash looks hashish
	if(!strlen($hash) || !is_string($hash) || !preg_match('/^.+,\d+$/', $hash))
		return false;

	//split the hash into its components
	list($hash_hash, $hash_time) = explode(',', $hash);

	//make sure the hash is itself valid
	if($hash !== sockem_make_speed_hash($hash_time))
		return false;

	//now we pass or fail depending on how much time has elapsed
	if($mode === 'speed')
		return current_time('timestamp') - $hash_time > $sockem_options['test_speed_seconds'];
	else
		return current_time('timestamp') - $hash_time < $sockem_options['test_expiration_seconds'];
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

	//if we can trust the user, leave!
	if($sockem_options['exempt_users'] === true && is_user_logged_in())
		return true;

	//store output so we can release it in one go
	ob_start();

	//javascript required: add a field via javascript
	if($sockem_options['test_js'] === true)
		echo "\n<script>document.write('" . '<input type="hidden" name="sockem_js" value="' . sockem_make_js_hash($post_id) . '" />' . "')</script><noscript><p class=\"form-allowed-tags\">Your browser must have Javascript support enabled to leave comments.</noscript>\n";

	//filler test?
	if($sockem_options['test_filler'] === true)
		echo "\n" . '<input type="text" name="sockem_filler" value="" style="display: none; speak: none;" placeholder="Do not type anything here, please." />';

	//speed test?
	if($sockem_options['test_speed'] === true)
		echo "\n" . '<input type="hidden" name="sockem_speed" value="' . esc_attr(sockem_make_speed_hash()) . '" />';

	//expiration
	if($sockem_options['test_expiration'] === true)
		echo "\n" . '<input type="hidden" name="sockem_expiration" value="' . esc_attr(sockem_make_speed_hash()) . '" />';

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

	//if we're not testing cookies, leave
	if(sockem_get_option('test_cookie') !== true)
		return true;

	//save the cookie if we need to
	$v = sockem_make_cookie_hash();
	if(!array_key_exists('sockem_cookie', $_COOKIE) || $_COOKIE['sockem_cookie'] !== $v)
		setcookie('sockem_cookie', $v, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

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
	//store statuses for debugging
	$debug = array();
	//store errors, if any, to present to commenter
	$errors = array();

	//validation for regular comments
	if(!strlen($commentdata['comment_type']))
	{
		//if we can trust the user, leave!
		if($sockem_options['exempt_users'] === true && is_user_logged_in())
		{
			//log this exemption, if applicable
			if($sockem_options['debug'] === true)
				sockem_debug_log($commentdata, array('[PASS] Logged in users are exempt from tests.'));

			//and leave!
			return $commentdata;
		}

		//javascript required: make sure the field is present and correct
		if($sockem_options['test_js'] === true)
		{
			if(!array_key_exists('sockem_js', $_POST) || $_POST['sockem_js'] !== sockem_make_js_hash($commentdata['comment_post_ID']))
			{
				$debug[] = '[FAIL] Javascript support must be enabled to leave comments.';
				$errors[] = 'Javascript support must be enabled to leave comments.';
			}
			else
				$debug[] = '[PASS] Javascript is enabled.';
		}
		else
			$debug[] = '[N/A] Javascript is not required.';

		//cookie support?
		if($sockem_options['test_cookie'] === true)
		{
			if(!array_key_exists('sockem_cookie', $_COOKIE) || $_COOKIE['sockem_cookie'] !== sockem_make_cookie_hash())
			{
				$debug[] = '[FAIL] Cookie support must be enabled to leave comments.';
				$errors[] = 'Cookie support must be enabled to leave comments.';
			}
			else
				$debug[] = '[PASS] Cookies are enabled.';
		}
		else
			$debug[] = '[N/A] Cookies are not required.';

		//filler test?
		if($sockem_options['test_filler'] === true)
		{
			if(!array_key_exists('sockem_filler', $_POST) || strlen($_POST['sockem_filler']))
			{
				$debug[] = '[FAIL] Invisible text fields should be left blank.';
				$errors[] = 'Invisible text fields should be left blank.';
			}
			else
				$debug[] = '[PASS] Invisible text field is empty.';
		}
		else
			$debug[] = '[N/A] Honeypot is not enabled.';

		//speed test?
		if($sockem_options['test_speed'] === true)
		{
			if(!array_key_exists('sockem_speed', $_POST) || !sockem_validate_speed_hash($_POST['sockem_speed']))
			{
				$debug[] = '[FAIL] Comment submitted too quickly.';
				$errors[] = 'Comment was submitted too quickly.';
			}
			else
				$debug[] = '[PASS] Comment was not hastily submitted.';
		}
		else
			$debug[] = '[N/A] Comment speed is not tested.';

		//expiration test?
		if($sockem_options['test_expiration'] === true)
		{
			if(!array_key_exists('sockem_expiration', $_POST) || !sockem_validate_speed_hash($_POST['sockem_expiration'], 'expiration'))
			{
				$debug[] = '[FAIL] Comment form has expired.';
				$errors[] = 'The comment form expired. Please reload the post and try again.';
			}
			else
				$debug[] = '[PASS] Comment form has not expired.';
		}
		else
			$debug[] = '[N/A] Comment expiration is not tested.';

		//link test?
		if($sockem_options['test_links'] === true)
		{
			$link_count = sockem_count_links($commentdata['comment_content']);
			if($link_count > $sockem_options['test_links_max'])
			{
				$debug[] = "[FAIL] Comment contains excessive links ($link_count).";
				$errors[] = "Comments may only contain up to {$sockem_options['test_links_max']} links.";
			}
			else
				$debug[] = "[PASS] Comment did not contain excessive links ($link_count).";
		}
		else
			$debug[] = '[N/A] Comment link count is not tested.';

		//length?
		if($sockem_options['test_length'] === true)
		{
			$comment_length = (int) strlen($commentdata['comment_content']);
			if($comment_length > $sockem_options['test_length_max'])
			{
				$debug[] = "[FAIL] Comment is too long ($comment_length).";
				$errors[] = "Comments cannot exceed {$sockem_options['test_length_max']} characters in length.";
			}
			else
				$debug[] = "[PASS] Comment was not too long ($comment_length).";
		}
		else
			$debug[] = '[N/A] Comment length is not tested.';

	}//end regular comments
	//trackbacks?
	elseif($commentdata['comment_type'] === 'trackback' && $sockem_options['disable_trackbacks'] === true)
	{
		$debug[] = '[FAIL] Trackbacks have been disabled.';
		$errors[] = 'Trackbacks have been disabled.';
	}
	//pingbacks?
	elseif($commentdata['comment_type'] === 'pingback' && $sockem_options['disable_pingbacks'] === true)
	{
		$debug[] = '[FAIL] Pingbacks have been disabled.';
		$errors[] = 'Pingbacks have been disabled.';
	}

	//one last status to add if all is well
	if(!count($errors))
		$debug[] = "[PASS] Sock'EM SPAMbots has taken no action.";

	//submit to debug log, if applicable
	if($sockem_options['debug'] === true)
		sockem_debug_log($commentdata, $debug);

	//if there are errors, we stop here.
	if(count($errors))
		wp_die('<strong>Error</strong>: ' . implode('<br><strong>Error</strong>: ', $errors));

	return $commentdata;
}
if(!is_admin())
	add_filter('preprocess_comment', 'sockem_comment_form_validation', 1);



//-------------------------------------------------
// Count the number of links and link-like things
//
// @param content
// @return count
function sockem_count_links($text){
	$count = 0;

	//use wordpress' function to make things clickable
	$text = make_clickable($text);

	//now count actual anchors
	$count += (int) preg_match_all('/\<a\s[^>]+>/ui', $text, $tmp);

	//also look for fake [url] tags
	$count += (int) preg_match_all('/\[url[^a-z0-9_-]/ui', $text, $tmp);

	return $count;
}

//-------------------------------------------------
// Disable comment author links?
//
// @since 0.9.0
//
// @param link
// @return link
function sockem_disable_comment_author_link( $author_link ){ return strip_tags( $author_link ); }
if(sockem_get_option('disable_comment_author_links') === true)
	add_filter( 'get_comment_author_link', 'sockem_disable_comment_author_link' );

//----------------------------------------------------------------------  end comment form modification(s)



//----------------------------------------------------------------------
//  Debugging
//----------------------------------------------------------------------

//--------------------------------------------------
// Log comment information
//
// @since 0.6.0
//
// @param commentdata
// @param (array/string) status
// @return true
function sockem_debug_log(&$commentdata, $status){

	//read options
	$sockem_options = sockem_get_option();

	//if debugging is disabled, do nothing
	if($sockem_options['debug'] !== true)
		return true;

	//build the message

	//basic date/user info
	$message = "\n\n\n--------------------\ndate: " . date('r', current_time('timestamp')) . "\nip: {$_SERVER['REMOTE_ADDR']}\nua: {$_SERVER['HTTP_USER_AGENT']}\nreferrer: {$_SERVER['HTTP_REFERER']}";

	//the commentdata
	if(is_array($commentdata))
	{
		$message .= "\ncommentdata:";
		foreach($commentdata AS $k=>$v)
			$message .= "\n\t'" . strtoupper($k) . "'\t=>\t" . sockem_format_debug_value($v);
	}

	//let's save $_POST too!
	if(is_array($_POST))
	{
		$message .= "\npost:";
		foreach($_POST AS $k=>$v)
			$message .= "\n\t'" . strtoupper($k) . "'\t=>\t" . sockem_format_debug_value($v);
	}

	//let's save $_COOKIE too
	if(is_array($_COOKIE))
	{
		$message .= "\ncookie:";
		foreach($_COOKIE AS $k=>$v)
			$message .= "\n\t'" . strtoupper($k) . "'\t=>\t" . sockem_format_debug_value($v);
	}

	//the status
	if(is_string($status))
		$message .= "\nstatus: $status";
	elseif(is_array($status))
	{
		foreach($status AS $s)
			$message .= "\nstatus: $s";
	}

	//save it
	@file_put_contents($sockem_options['debug_log'], $message, FILE_APPEND | LOCK_EX);

	return true;
}

//--------------------------------------------------
// Format debug value
//
// @since 0.6.0
//
// @param value
// @return value
function sockem_format_debug_value($v=''){
	//strip slashes
	$v = stripslashes($v);

	//sanitize spacing
	$v = preg_replace('/\s+/u', ' ', $v);

	//cut long values short
	if(strlen($v) > 200)
		$v = trim(substr($v, 0, 200)) . '...';

	return $v;
}

//----------------------------------------------------------------------  end debugging