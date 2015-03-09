<?php
/*
Plugin Name: Who's Online
Plugin URI: http://wordpress.org/extend/plugins/wp-whos-online/
Description: Sidebar widget to log when a user was last online
Version: 0.7
Author: Annika Backstrom
Author URI: https://sixohthree.com/
License: GPL2
*/

/*  Copyright 2011  Annika Backstrom <annika@sixohthree.com>

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

class WP_Whos_Online_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'widget_wpwhosonline', "Who's Online", "Who's reading your P2 blog right now? Keep track."
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'] . $args['before_title'] . "Users" . $args['after_title'];
		echo '<ul class="wpwhosonline-list">';
		wpwhosonline_list_authors();
		echo '</ul>';
		echo $args['after_widget'];
	}

	public static function register() {
		register_widget( __CLASS__ );
	}

	public function form( $instance ) {
	}

	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}
}

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
add_action('template_redirect', 'wpwhosonline_update');

/**
 * Echo json listing all authors who have had their "last online" timestamp updated
 * since the client's last update.
 */
function wpwhosonline_ajax_update() {
	global $wpdb;

	// update timestamp of user who is checking
	wpwhosonline_update();

	$load_time = strtotime($_GET['load_time'] . ' GMT');
	$users = wpwhosonline_recents( "meta_value=$load_time" );

	if( count($users) == 0 ) {
		die( '0' );
	}

	$now = time();

	$latest = 0;
	$return = array();
	foreach($users as $user) {
		$row = array();

		$last_online_ts = get_user_meta( $user->ID, 'wpwhosonline_timestamp', true );
		if( $last_online_ts > $latest )
			$latest = $last_online_ts;

		$row['user_id'] = $user->ID;
		$row['html'] = wpwhosonline_user( $last_online_ts, $user );
		$row['timestamp'] = $last_online_ts;

		$return[] = $row;
	}

	echo json_encode( array('users' => $return, 'latestupdate' => gmdate('Y-m-d H:i:s', $latest)) );
	exit;
}

function wpwhosonline_pageoptions_js() {
	global $page_options;
?><script type='text/javascript'>
// <![CDATA[
var wpwhosonline = {
	'ajaxUrl': "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
	'wpwhosonlineLoadTime': "<?php echo gmdate( 'Y-m-d H:i:s' ); ?>",
	'getwpwhosonlineUpdate': '0',
	'isFirstFrontPage': "<?php echo is_home(); ?>"
};
// ]]>
</script><?php
}

function wpwhosonline_usersort( $a, $b ) {
	$ts_a = get_user_meta( $a->ID, 'wpwhosonline_timestamp', true );
	$ts_b = get_user_meta( $b->ID, 'wpwhosonline_timestamp', true );

	if( $ts_a == $ts_b ) {
		return 0;
	}

	return ($ts_a < $ts_b) ? 1 : -1;
}

function wpwhosonline_recents( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'meta_key' => 'wpwhosonline_timestamp',
		'meta_value' => time() - 604800, // 1 week
		'meta_compare' => '>',
		'count_total' => false,
	));

	$users = get_users( $args );
	foreach( $users as $user ) {
		// grab all these values, or you'll anger usort by modifying
		// an array mid-execution.
		get_user_meta( $user->ID, 'wpwhosonline_timestamp', true );
	}
	usort( $users, 'wpwhosonline_usersort' );

	return $users;
}

function wpwhosonline_list_authors() {
	$users = wpwhosonline_recents();

	$html = '';

	foreach( $users as $user ) {
		$last_online_ts = get_user_meta( $user->ID, 'wpwhosonline_timestamp', true );
		$item = wpwhosonline_user( $last_online_ts, $user );
		$class = wpwhosonline_class( $last_online_ts );

		$item = '<li id="wpwhosonline-' . $user->ID . '" class="wpwhosonline-row ' . $class . '" data-wpwhosonline="' .
			esc_attr( $last_online_ts ) . '">' . $item . '</li>';
		$html .= $item;
	}

	echo $html;
}

/**
 * Return HTML for a single blog user for the widget.
 *
 * @uses apply_filters() Calls 'wpwhosonline_author_link' on the author link element
 * @return string HTML for the user row
 */
function wpwhosonline_user( $last_online_ts, $user ) {
	$avatar = get_avatar( $user->user_email, 32 );
	$name = $user->display_name;
	$link = '<a href="' . get_author_posts_url( $user->ID, $user->user_nicename ) . '" title="' . esc_attr( sprintf(__("Posts by %s"), $user->display_name) ) . '">' . $name . '</a>';

	$link = apply_filters( 'wpwhosonline_author_link', $link, $user );

	// this should always exist; we queried using this meta
	if ( ! $last_online_ts ) {
		continue;
	}

	$now = time();
	if ( $now - $last_online_ts < 120 ) {
		$last_online = 'Online now!';
	} else {
		$last_online = human_time_diff( $now, $last_online_ts ) . ' ago';
	}

	$last_online_title = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $last_online_ts );

	if ( $last_online ) {
		$last_online = '<span title="Last online: ' . esc_attr( $last_online_title ) . '">' . $last_online . '</a>';
	}

	return $avatar . $link . '<br>' . $last_online;
}

function wpwhosonline_class( $lastonline ) {
	$diff = time() - $lastonline;
	if( $diff > 7200 ) {
		return 'wpwhosonline-ancient';
	} elseif( $diff > 600 ) {
		return 'wpwhosonline-recent';
	} else {
		return 'wpwhosonline-active';
	}
}

add_action( 'widgets_init', 'WP_Whos_Online_Widget::register' );
