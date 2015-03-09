=== Who's Online ===

Contributors: abackstrom
Tags: p2, widget
Tested up to: 4.1.1
Requires at least: 3.1
Stable tag: 0.7

Who's reading your P2 blog right now? Keep track.

== Description ==

The "Who's Online" sidebar widget shows a list of recently-active
blog users. It has been built with Automattic's [P2
theme](http://p2theme.com/) in mind, but does not depend on P2
functionality.

The most recently active users bubble up to the top. After one week,
users fall off the bottom of the list and are hidden until they return.
As of this writing the list *does not* support live sorting; the user
order is fixed, and users missing from the list due to inactivity will
be added to the top if they come online.

== Installation ==

1. Upload the `wp-whos-online` directory to your `/wp-content/plugins` directory.
2. Activate the plugin on your Plugins menu.
2. Add the "Who's Online" widget to your sidebar using the Widgets menu.

== Screenshots ==

1. "Who's Online" in a P2 blog.

== Changelog ==

= 0.7 =

* Migrate to WordPress 2.8 widget API

= 0.6 =

* Style and HTML improvements based on theme testing
* New wpwhosonline_author_link filter to customize the author name/link

= 0.5 =

* Code modernizations for WordPress 3.3
* Sort list by activity date, hiding inactive users

= 0.1 =

* Initial release
