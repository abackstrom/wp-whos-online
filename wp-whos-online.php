<?php
/*
Plugin Name: Who's Online
Plugin URI: http://www.plymouth.edu/
Description: Sidebar widget to log when a user was last online
Version: 0.1
Author: Adam Backstrom
Author URI: http://blogs.bwerp.net/
License: GPL2
*/

/*  Copyright 2011  Adam Backstrom <adam@sixohthree.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('template_redirect', 'wpwhosonline_update');

function wpwhosonline_enqueue() {
	add_action( 'wp_head', 'wpwhosonline_pageoptions_js', 20 );

	wp_enqueue_script( 'wpwhosonline', plugins_url('wp-whos-online.js', __FILE__), array('jquery'), 1 );
	wp_enqueue_style(  'wpwhosonline_css', plugins_url('wp-whos-online.css', __FILE__), null, 1 );
}
add_action('wp_enqueue_scripts', 'wpwhosonline_enqueue');

// our own ajax call
add_action( 'wp_ajax_wpwhosonline_ajax_update', 'wpwhosonline_ajax_update' );

// hook into p2 ajax calls, if they're there
add_action( 'wp_ajax_prologue_latest_posts', 'wpwhosonline_update' );
add_action( 'wp_ajax_prologue_latest_comments', 'wpwhosonline_update' );

/**
 * Update a user's "last online" timestamp.
 */
function wpwhosonline_update() {
	if( !is_user_logged_in() )
		return null;

	global $user_ID;

	update_user_meta( $user_ID, 'wpwhosonline_timestamp', time() );
}//end wpwhosonline_update

/**
 * Echo json listing all authors who have had their "last online" timestamp updated
 * since the client's last update.
 */
function wpwhosonline_ajax_update() {
	global $wpdb;

	// update timestamp of user who is checking
	wpwhosonline_update();

	$load_time = strtotime($_GET['load_time'] . ' GMT');
	$authors = $wpdb->get_results($wpdb->prepare("SELECT user_id, meta_value AS wpwhosonline FROM $wpdb->usermeta
		WHERE meta_key = 'wpwhosonline_timestamp' AND meta_value > %d", $load_time));

	if( count($authors) == 0 ) {
		die( '0' );
	}

	$now = time();

	$latest = 0;
	foreach($authors as $author) {
		if( $author->wpwhosonline > $latest )
			$latest = $author->wpwhosonline;

		$author->wpwhosonline_unix = (int)$author->wpwhosonline;
		if( $now - $author->wpwhosonline_unix < 120 ) {
			$author->wpwhosonline = 'Online now!';
		} else {
			$author->wpwhosonline = strftime( '%d %b %Y %H:%M:%S %Z', $author->wpwhosonline );
		}
	}

	echo json_encode( array('authors' => $authors, 'latestupdate' => gmdate('Y-m-d H:i:s', $latest)) );
	exit;
}

function wpwhosonline_pageoptions_js() {
	global $page_options;
?><script type='text/javascript'>
// <![CDATA[
var wpwhosonline = {
	'ajaxUrl': "<?php echo esc_js( get_bloginfo( 'wpurl' ) . '/wp-admin/admin-ajax.php' ); ?>",
	'wpwhosonlineLoadTime': "<?php echo gmdate( 'Y-m-d H:i:s' ); ?>",
	'getwpwhosonlineUpdate': '0',
	'isFirstFrontPage': "<?php echo is_home(); ?>"
};
// ]]>
</script><?php
}

/**
 * Custom version of wp_list_authors() for the wp-whos-online plugin.
 *
 * optioncount (boolean) (false): Show the count in parenthesis next to the
 *		author's name.
 * exclude_admin (boolean) (true): Exclude the 'admin' user that is installed by
 *		default.
 * show_fullname (boolean) (false): Show their full names.
 * hide_empty (boolean) (true): Don't show authors without any posts.
 * feed (string) (''): If isn't empty, show links to author's feeds.
 * feed_image (string) (''): If isn't empty, use this image to link to feeds.
 * echo (boolean) (true): Set to false to return the output, instead of echoing.
 * avatar_size' => 
 *
 * @param array $args The argument array.
 * @return null|string The output, if echo is set to false.
 */
function wpwhosonline_list_authors($args = '') {
	global $wpdb;

	$defaults = array(
		'optioncount' => false, 'exclude_admin' => true,
		'show_fullname' => false, 'hide_empty' => true,
		'feed' => '', 'feed_image' => '', 'feed_type' => '', 'echo' => true,
		'avatar_size' => 0, 'wpwhosonline' => 0
	);

	$r = wp_parse_args( $args, $defaults );
	extract($r, EXTR_SKIP);

	$return = '';

	/** @todo Move select to get_authors(). */
	$authors = $wpdb->get_results("SELECT ID, user_nicename from $wpdb->users " . ($exclude_admin ? "WHERE user_login <> 'admin' " : '') . "ORDER BY display_name");

	$author_count = array();
	foreach ((array) $wpdb->get_results("SELECT DISTINCT post_author, COUNT(ID) AS count FROM $wpdb->posts WHERE post_type = 'post' AND " . get_private_posts_cap_sql( 'post' ) . " GROUP BY post_author") as $row) {
		$author_count[$row->post_author] = $row->count;
	}

	foreach ( (array) $authors as $author ) {
		$author = get_userdata( $author->ID );
		$posts = (isset($author_count[$author->ID])) ? $author_count[$author->ID] : 0;
		$name = $author->display_name;

		if ( $show_fullname && ($author->first_name != '' && $author->last_name != '') )
			$name = "$author->first_name $author->last_name";

		if ( $avatar_size > 0 )
			$name = get_avatar( $author->ID, $avatar_size) . " " . $name;

		if ( !($posts == 0 && $hide_empty) ) {
			$return .= '<li>';
		}

		if ( $posts == 0 ) {
			if ( !$hide_empty )
				$link = $name;
		} else {
			$link = '<a href="' . get_author_posts_url($author->ID, $author->user_nicename) . '" title="' . sprintf(__("Posts by %s"), esc_attr($author->display_name)) . '">' . $name . '</a>';

			if ( (! empty($feed_image)) || (! empty($feed)) ) {
				$link .= ' ';
				if (empty($feed_image))
					$link .= '(';
				$link .= '<a href="' . get_author_feed_link($author->ID) . '"';

				if ( !empty($feed) ) {
					$title = ' title="' . $feed . '"';
					$alt = ' alt="' . $feed . '"';
					$name = $feed;
					$link .= $title;
				}

				$link .= '>';

				if ( !empty($feed_image) )
					$link .= "<img src=\"$feed_image\" style=\"border: none;\"$alt$title" . ' />';
				else
					$link .= $name;

				$link .= '</a>';

				if ( empty($feed_image) )
					$link .= ')';
			}

			if ( $optioncount )
				$link .= ' ('. $posts . ')';
		}

		if ( $wpwhosonline ) {
			$now = time();

			$wpwhosonline_time = get_user_meta( $author->ID, 'wpwhosonline_timestamp', true );
			if( $wpwhosonline_time ) {
				if( $now - $wpwhosonline_time < 120 ) {
					$wpwhosonline_time = 'Online now!';
				} else {
					$wpwhosonline_time = strftime( '%d %b %Y %H:%M:%S %Z', $wpwhosonline_time );
				}
			} else {
				$wpwhosonline_time = '';
			}
			$link .= '<br /><span id="wpwhosonline-' . $author->ID . '" title="Last online timestamp">' . $wpwhosonline_time . '</span>';
		}

		if ( !($posts == 0 && $hide_empty) )
			$return .= $link . '</li>';
	}
	if ( !$echo )
		return $return;
	echo $return;
}

function widget_wpwhosonline_init() {

  // Check for the required plugin functions. This will prevent fatal
  // errors occurring when you deactivate the dynamic-sidebar plugin.
  if ( !function_exists('wp_register_sidebar_widget') )
    return;

  // This is the function that outputs the Authors code.
  function widget_wpwhosonline($args) {
    extract($args);

    echo $before_widget . $before_title . "Users" . $after_title;
?>
<ul>
<?php wpwhosonline_list_authors('optioncount=1&exclude_admin=0&show_fullname=1&hide_empty=0&avatar_size=32&wpwhosonline=1'); ?>
</ul>
<?php
    echo $after_widget;
  }

  // This registers our widget so it appears with the other available
  // widgets and can be dragged and dropped into any active sidebars.
  wp_register_sidebar_widget( 'widget_wpwhosonline', "Who's Online", 'widget_wpwhosonline' );
}

// Run our code later in case this loads prior to any required plugins.
add_action('plugins_loaded', 'widget_wpwhosonline_init');
