<?php
//----------------------------------------------------------------------
//  Sock'Em SPAMbots settings
//----------------------------------------------------------------------
// display a form so authorized WP users can configure Sock'Em SPAMbots
// settings
//
// @since 0.5.0



//--------------------------------------------------
// Check permissions

//let's make sure this page is being accessed through WP
if (!function_exists('current_user_can'))
	die('Sorry');
//and let's make sure the current user has sufficient permissions
elseif(!current_user_can('manage_options'))
	wp_die(__('You do not have sufficient permissions to access this page.'));



//--------------------------------------------------
// Basic variables we'll be needing...

$sockem_options = sockem_get_option();
$statuses = array('open','closed');



//--------------------------------------------------
//Process submitted data!

if(getenv("REQUEST_METHOD") === 'POST')
{
	//AAAAAARRRRGH DIE MAGIC QUOTES!!!!  Haha.
	$_POST = stripslashes_deep($_POST);

	//bad nonce, don't save
	if(!wp_verify_nonce($_POST['_wpnonce'],'sockem-settings'))
		echo '<div class="error fade"><p>Sorry the form had expired.  Please try again.</p></div>';
	else
	{
		//we might have something to do to the debug log...
		if(file_exists($sockem_options['debug_log']))
		{
			//if debugging is disabled, we need to delete the file
			if(!array_key_exists('sockem_debug', $_POST) || intval($_POST['sockem_debug']) !== 1)
			{
				if(false === @unlink($sockem_options['debug_log']))
					echo '<div class="error fade"><p>The Sock\'Em SPAMbots debug log could not be deleted.  Please manually remove <code>' . esc_html($sockem_options['debug_log']) . '</code></p></div>';
				else
					echo '<div class="updated fade"><p>The Sock\'Em SPAMbots debug log has been removed.</p></div>';
			}
			//otherwise if the user opted to empty the file, we need to try to do that
			elseif(array_key_exists('sockem_empty_debug_log', $_POST) && intval($_POST['sockem_empty_debug_log']) === 1)
			{
				if(false === @file_put_contents($sockem_options['debug_log'], ''))
					echo '<div class="error fade"><p>The Sock\'Em SPAMbots debug log could not be emptied.  Please make sure the server is allowed to write changes to it.</p></div>';
				else
					echo '<div class="updated fade"><p>The Sock\'Em SPAMbots debug log has been emptied.</p></div>';
			}
		}
		//if debugging is enabled, let's make sure we can write to the file
		elseif(array_key_exists('sockem_debug', $_POST) && intval($_POST['sockem_debug']) === 1 && false === @file_put_contents($sockem_options['debug_log'], ''))
			echo '<div class="error fade"><p>The Sock\'Em SPAMbots log could not be created.  Please make sure the server is allowed to write changes to ' . esc_html($sockem_options['debug_log']) . '</p></div>';

		//are we updating comment or ping statuses?
		//since $statuses only contains "open" and "closed", we don't need to escape these strings
		$tmp = array();
		if(array_key_exists('sockem_comment_status', $_POST) && in_array($_POST['sockem_comment_status'], $statuses))
			$tmp['comment_status'] = "`comment_status`='{$_POST['sockem_comment_status']}'";
		if(array_key_exists('sockem_ping_status', $_POST) && in_array($_POST['sockem_ping_status'], $statuses))
			$tmp['ping_status'] = "`ping_status`='{$_POST['sockem_ping_status']}'";
		//we are indeed!
		if(count($tmp))
		{
			//update the database
			global $wpdb;
			$wpdb->query("UPDATE `{$wpdb->prefix}posts` SET " . implode(', ', $tmp));

			//and print a success message
			foreach($tmp AS $k=>$v)
				echo '<div class="updated fade"><p>The ' . str_replace('_', ' ', $k) . "es of your existing posts have been set to {$_POST['sockem_' . $k]}.</p></div>";
		}

		//sanitize the results (there isn't really anything to validate)
		foreach(array('test_js','test_cookie','test_filler','test_speed','disable_trackbacks','disable_pingbacks','debug','exempt_users') AS $key)
			$sockem_options[$key] = array_key_exists("sockem_$key", $_POST) && intval($_POST["sockem_$key"]) === 1;

		//save for later
		update_option('sockem_options', $sockem_options);

		echo '<div class="updated fade"><p>Sock\'Em SPAMbots\' settings have been successfully saved.</p></div>';
	}
}



//-------------------------------------------------
// Warn about unprotected plugins

//bbpress
if(is_plugin_active('bbpress/bbpress.php'))
	echo '<div class="error fade"><p>Warning: bbPress forum posts are outside the purview of this plugin; Sock\'Em SPAMbots only helps protect against SPAM in regular comments.</p></div>';
//buddy press
if(is_plugin_active('buddypress/bp-loader.php'))
	echo '<div class="error fade"><p>Warning: BuddyPress has its own commenting system outside the purview of this plugin; Sock\'Em SPAMbots only helps protect against SPAM in regular comments.</p></div>';



//--------------------------------------------------
// Output the form!
?>
<style type="text/css">
	.form-table {
		clear: left!important;
	}
	.logo {
		width: 100%;
		height: auto;
	}
</style>

<div class="wrap">

	<h2>Sock'Em SPAMbots: Settings</h2>

	<div class="metabox-holder has-right-sidebar">

		<form id="form-sockem-settings" method="post" action="<?php echo esc_url(admin_url('options-general.php?page=sockem-settings')); ?>">
		<?php wp_nonce_field('sockem-settings'); ?>

		<div class="inner-sidebar">

			<!-- Debug -->
			<div class="postbox">
				<h3 class="hndle">Debugging</h3>
				<div class="inside">
					<p><label for="sockem_debug"><input type="checkbox" id="sockem_debug" name="sockem_debug" value="1" <?php echo ($sockem_options['debug'] === true ? 'checked=checked' : ''); ?> /> Enable debugging</label><br>
					<span class="description">Comment details and Sock'Em test results will be logged to <code><?php echo esc_html($sockem_options['debug_log']); ?></code>.  This can be useful if you want to make sure the plugin is working correctly, but you should not leave debugging enabled indefinitely.</span></p>

					<div class="sockem-debug-options" style="display: <?php echo ($sockem_options['debug'] === true ? 'block' : 'none'); ?>">
					<?php
					//if the log exists, let's present some options
					if(file_exists($sockem_options['debug_log']))
					{
						echo '<p><label for="sockem_empty_debug_log"><input type="checkbox" id="sockem_empty_debug_log" name="sockem_empty_debug_log" value="1" /> Empty log</label><br>
						<span class="description">Click <a href="' . esc_url(plugins_url('sockem_debug.log', __FILE__)) . '" title="View debug log" target="_blank">here</a> to view the log</span></p>';
					}
					?>
					</div>
				</div>
			</div><!--.postbox-->

			<!-- mass update -->
			<div class="postbox">
				<h3 class="hndle">Mass Update</h3>
				<div class="inside">
					<p><select name="sockem_comment_status" id="sockem_comment_status"><option value=""> --- </option><?php
					foreach($statuses AS $s)
						echo "<option value=\"$s\">$s</option>";
					?></select><label for="sockem_comment_status"> Comment Status</label></p>

					<p><select name="sockem_ping_status" id="sockem_ping_status"><option value=""> --- </option><?php
					foreach($statuses AS $s)
						echo "<option value=\"$s\">$s</option>";
					?></select><label for="sockem_ping_status"> Ping Status</label></p>

					<p class="description">Changes to your blog's comment settings don't always apply retroactively.  The above options allow you to update the per-post settings for all existing posts (irrespective of post type).</p>
				</div>
			</div><!--.postbox-->

			<!-- About Us -->
			<div class="postbox">
				<div class="inside">
					<a href="http://www.blobfolio.com/donate.html" title="Blobfolio, LLC" target="_blank"><img src="<?php echo esc_url(plugins_url('blobfolio.png', __FILE__)); ?>" class="logo" alt="Blobfolio logo"></a>

					<p>We hope you find this plugin useful.  If you do, you might be interested in our other plugins, which are also completely free (and useful).</p>
					<ul>
						<li><a href="http://wordpress.org/plugins/apocalypse-meow/" target="_blank" title="Apocalypse Meow">Apocalypse Meow</a>: a simple, light-weight collection of tools to help protect wp-admin, including password strength requirements and brute-force log-in prevention.</li>
						<li><a href="http://wordpress.org/plugins/look-see-security-scanner/" target="_blank" title="Look-See Security Scanner">Look-See Security Scanner</a>: verify the integrity of a WP installation by scanning for unexpected or modified files.</li>
					</ul>
				</div>
			</div><!--.postbox-->

		</div><!--.inner-sidebar-->

		<div id="post-body-content" class="has-sidebar">
			<div class="has-sidebar-content">

				<!-- comment validation methods -->
				<div class="postbox">
					<h3 class="hndle">Comment Validation Methods</h3>
					<div class="inside">
						<p>SPAMbots are usually simple, lightweight, automated scripts, lacking robust features (and common decency).  The following modifications to the comment process can help trip them up <strong><em>without</em></strong> interfering with your human visitors at all.</p>

						<blockquote>
							<p><label for="sockem_exempt_users"><input type="checkbox" name="sockem_exempt_users" id="sockem_exempt_users" value="1" <?php echo ($sockem_options['exempt_users'] === true ? 'checked=checked' : '') ?> /> Trust Authenticated Users</label><br>
							<span class="description">Unless you have a SPAM registration problem (which is a whole other pickle), you can probably assume that any user who has successfully logged into WordPress is human, and exempt them from the other tests.</span></p>

							<p><label for="sockem_test_js"><input type="checkbox" name="sockem_test_js" id="sockem_test_js" value="1" <?php echo ($sockem_options['test_js'] === true ? 'checked=checked' : ''); ?> /> Require Javascript</label><br>
							<span class="description">A small piece of Javascript code is run on comment pages, adding an extra field to submissions.  If a SPAMbot does not support Javascript or does not bother visiting the actual comment page, the comment submission will fail.</span></p>

							<p><label for="sockem_test_cookie"><input type="checkbox" name="sockem_test_cookie" id="sockem_test_cookie" value="1" <?php echo ($sockem_options['test_cookie'] === true ? 'checked=checked' : ''); ?> /> Require Cookies</label><br>
							<span class="description">A tiny cookie <a href="http://en.wikipedia.org/wiki/HTTP_cookie" title="Wikipedia entry: HTTP cookie" target="_blank">[?]</a> is set when the comment form is loaded, and verified when the comment form is submitted.  If a SPAMbot does not support cookies or does not bother visiting the comment page, the comment submission will fail.</span></p>

							<p><label for="sockem_test_filler"><input type="checkbox" name="sockem_test_filler" id="sockem_test_filler" value="1" <?php echo ($sockem_options['test_filler'] === true ? 'checked=checked' : ''); ?> /> Honeypot</label><br>
							<span class="description">An invisible text field is added to comment forms.  Generic formbots will try to populate the field anyway, and by doing so, their comments will be rejected.</span></p>

							<p><label for="sockem_test_speed"><input type="checkbox" name="sockem_test_speed" id="sockem_test_speed" value="1" <?php echo ($sockem_options['test_speed'] === true ? 'checked=checked' : ''); ?> /> Haste Makes SPAM</label><br>
							<span class="description">Human beings will take a few seconds, at the very least, to fill out and submit a comment form.  This test will reject comments submitted in 5 seconds or less from the time the page was generated.</span></p>
						</blockquote>
					</div>
				</div><!--.postbox-->

				<!-- comment validation methods -->
				<div class="postbox">
					<h3 class="hndle">Automated Comment Features</h3>
					<div class="inside">
						<p>Some kinds of comments are actually supposed to come from robots, though their usefulness is questionable.</p>

						<blockquote>
							<p><label for="sockem_disable_trackbacks"><input type="checkbox" name="sockem_disable_trackbacks" id="sockem_disable_trackbacks" value="1" <?php echo ($sockem_options['disable_trackbacks'] === true ? 'checked=checked' : ''); ?> /> Disable Trackbacks</label><br>
							<span class="description">Trackbacks <a href="http://en.wikipedia.org/wiki/Trackbacks" title="Wikipedia entry: Trackbacks" target="_blank">[?]</a> are intended to provide a means for authors to keep track of who links to their posts, but more often than not this system is used to send SPAM that bypasses the usual safeguards (e.g. those sexy options above).  Keep them if you want them, but this feature is mostly used for evil.</span></p>

							<p><label for="sockem_disable_pingbacks"><input type="checkbox" name="sockem_disable_pingbacks" id="sockem_disable_pingbacks" value="1" <?php echo ($sockem_options['disable_pingbacks'] === true ? 'checked=checked' : ''); ?> /> Disable Pingbacks</label><br>
							<span class="description">Pingbacks <a href="http://en.wikipedia.org/wiki/Pingback" title="Wikipedia entry: Pingbacks" target="_blank">[?]</a> are similar to Trackbacks in that their purpose is to notify authors of links to their posts, however there is at least a degree of authentication, so they are not as frequently abused.  If you wanted to keep one kind of automated comment enabled, keep pingbacks.</span></p>

						</blockquote>
					</div>
				</div><!--.postbox-->

			</div><!-- .has-sidebar-content -->
		</div><!-- .has-sidebar -->


		<p class="submit"><input type="submit" name="submit" value="Save" /></p>
		</form>

	</div><!-- /metabox-holder has-right-sidebar -->
</div><!-- /wrap -->

<script>

//-------------------------------------------------
// Toggle visibility of debug options based on the
// status of debugging itself
jQuery('#sockem_debug').click(function(){

	if(jQuery(this).prop('checked'))
		jQuery('.sockem-debug-options').css({display:'block'});
	else
		jQuery('.sockem-debug-options').css({display:'none'});

});

</script>