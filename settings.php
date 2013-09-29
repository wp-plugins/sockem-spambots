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
		//sanitize the results (there isn't really anything to validate)
		foreach(array('test_js','test_cookie','test_filler') AS $key)
			$sockem_options[$key] = array_key_exists("sockem_$key", $_POST) && intval($_POST["sockem_$key"]) === 1;

		//save for later
		update_option('sockem_options', $sockem_options);

		echo '<div class="updated fade"><p>Sock\'Em SPAMbots\' settings have been successfully saved.</p></div>';
	}
}



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
					<h3 class="hndle">User Comment Validation Methods</h3>
					<div class="inside">
						<p>SPAMbots are usually simple, lightweight, automated scripts, lacking robust features (and common decency).  The following modifications to the comment process can help trip them up <strong><em>without</em></strong> interfering with your human visitors at all.</p>

						<blockquote>
							<p><label for="sockem_test_js"><input type="checkbox" name="sockem_test_js" id="sockem_test_js" value="1" <?php echo ($sockem_options['test_js'] === true ? 'checked=checked' : ''); ?> /> Require Javascript</label><br>
							<span class="description">A small piece of Javascript code is run on comment pages, adding an extra field to submissions.  If a SPAMbot does not support Javascript or does not bother visiting the actual comment page, the comment submission will fail.</span></p>

							<p><label for="sockem_test_cookie"><input type="checkbox" name="sockem_test_cookie" id="sockem_test_cookie" value="1" <?php echo ($sockem_options['test_cookie'] === true ? 'checked=checked' : ''); ?> /> Require Cookies</label><br>
							<span class="description">A tiny cookie <a href="http://en.wikipedia.org/wiki/HTTP_cookie" title="Wikipedia entry: HTTP cookie" target="_blank">[?]</a> is set when the comment form is loaded, and verified when the comment form is submitted.  If a SPAMbot does not support cookies or does not bother visiting the comment page, the comment submission will fail.</span></p>

							<p><label for="sockem_test_filler"><input type="checkbox" name="sockem_test_filler" id="sockem_test_filler" value="1" <?php echo ($sockem_options['test_filler'] === true ? 'checked=checked' : ''); ?> /> Invisible Field</label><br>
							<span class="description">An invisible text field is added to comment forms.  Generic formbots will try to populate the field anyway, and by doing so, their comments will be rejected.</span></p>
						</blockquote>
					</div>
				</div><!--.postbox-->

				<!-- comment validation methods -->
				<div class="postbox">
					<h3 class="hndle">Automated Comment Features</h3>
					<div class="inside">
						<p>Some kinds of comments are actually supposed to come from robots, though their usefulness is questionable.</p>

						<blockquote>
							<p><label for="sockem_disable_trackbacks"><input type="checkbox" name="sockem_disable_trackbacks" id="sockem_disable_trackbacks" value="1" <?php echo ($sockem_options['sockem_disable_trackbacks'] === true ? 'checked=checked' : ''); ?> /> Disable Trackbacks</label><br>
							<span class="description">Trackbacks <a href="http://en.wikipedia.org/wiki/Trackbacks" title="Wikipedia entry: Trackbacks" target="_blank">[?]</a> are intended to provide a means for authors to keep track of who links to their posts, but more often than not this system is used to send SPAM that bypasses the usual safeguards (e.g. those sexy options above).  Keep them if you want them, but this feature is mostly used for evil.</span></p>

							<p><label for="sockem_disable_pingbacks"><input type="checkbox" name="sockem_disable_pingbacks" id="sockem_disable_pingbacks" value="1" <?php echo ($sockem_options['sockem_disable_pingbacks'] === true ? 'checked=checked' : ''); ?> /> Disable Pingbacks</label><br>
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