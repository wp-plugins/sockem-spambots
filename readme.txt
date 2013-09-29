=== Plugin Name ===
Contributors: blobfolio
Donate link: http://www.blobfolio.com/donate.html
Tags: comment, comment, spam, captcha, junk, trackback, pingback
Requires at least: 3.6
Tested up to: 3.6.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A more seamless approach to deflecting the vast majority of SPAM comments.

== Description ==

CAPTCHA fields inhibit both human and robot participation in important kitty-related discussions.  Sock'Em SPAMbots exists to take a more seamless approach to SPAM blocking, placing the burden on the robots, not the humans.  Any combination of the following can be enabled:

  * Javascript: require basic Javascript support, and in the process prove the user visited the actual comment form (instead of just submitting straight to WP).
  * Cookies: require basic cookie support, and again, prove the user visited the site before submitting a comment.
  * Invisible field: generic formbots will often populate all form fields with gibberish, so we can assume that if text is added to an invisible field, something robotic is happening!
  * Disable trackbacks or pingbacks independently of one another.

This plugin is in BETA.  It does what it is supposed to on our servers, but in the wild it may behave... wildly!  Please let us know if you experience any issues!

== Installation ==

1. Unzip the archive and upload the entire `sockem-spambots` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Review and change the settings by selecting 'SockEm SPAMbots' in the 'Settings' menu in WordPress.

== Frequently Asked Questions ==

= Is this plugin compatible with WPMU? =

The plugin is only meant to be used with single-site WordPress installations.  Some features may still work under multi-site environments, however it would be safer to use some other plugin that is specifically marked WPMU-compatible instead.

= Does the Javascript test have any additional dependencies, like jQuery? =

Nope.  We like things as lightweight as possible, so the Javascript is naked as the day it was born.

= What happens to comments that failed to pass the enabled Sock'Em SPAMbots test(s) =

If a comment fails to pass one or more of the Sock'Em SPAMbots tests, the comment is rejected outright and an error is returned to the (human or robot) user explaining what went wrong.  Humans can take the appropriate action and resubmit the comment if they so desire, while robots will likely just go bother someone else.

= Why are there settings to disable trackbacks and pingbacks?  Doesn't WP offer this itself? =

WordPress lumps the two together.  We've separated them so you can be more selective.  It is worth pointing out that Sock'Em only affects comments that would otherwise be allowed, so if you have disabled both via the WP discussion settings, then the Sock'Em options have no effect.

= What happens after Sock'Em SPAMbots test(s) are passed? =

WordPress continues doing whatever it would normally do with the comment based on your settings and any other relevant plugins you have installed (e.g. Akismet).

== Screenshots ==

1. All options are easily configurable via a settings page.

== Changelog ==

= 0.5.0 =
* Sock'Em SPAMbots is born!

== Upgrade Notice ==

= 0.5.0 =
Initial release.