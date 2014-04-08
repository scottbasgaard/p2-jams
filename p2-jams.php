<?php
/**
 * Plugin Name: P2 Jams
 * Plugin URI: http://scottbasgaard.com/
 * Description: "P2 Jams" is a way to show what everybody is listening to on P2.
 * Version: 1.0.1
 * Author: Scott Basgaard
 * Author URI: http://scottbasgaard.com/
 * License: GPLv2 or later
 */

/*  Copyright 2014  Scott Basgaard  (email : mail@scottbasgaard.com)

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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname( __FILE__ ) . '/includes/widgets.php' );

/**
 * P2 Jams Class
 * 
 * Start listening to some music!
 *
 * @class 		P2_Jams
 * @version		1.0
 */
class P2_Jams {

	/**
	 * @var P2_Jams The single instance of the class
	 * @since 1.0
	 */
	protected static $_instance = null;
	
	/**
	 * Main P2 Jams Instance
	 *
	 * Only load one instance of P2 Jams
	 *
	 * @since 1.0
	 * @static
	 * @see P2_Jams()
	 * @return P2_Jams - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
 
	/**
	 * __construct function
	 *
	 * @access public
	 */
    public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'user_contactmethods', array( $this, 'add_lastfm_user' ) );
		add_action( 'wp_ajax_p2_jams', array( $this, 'ajax_check_jams' ) );
    }
	
	/**
	 * Register and enqueue CSS and JS
	 *
	 * @access public
	 */
	public function enqueue_scripts() {
		
		$p2_jams_data = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'ajaxnonce' => wp_create_nonce( 'p2-jams-nonce' )
		);

		wp_enqueue_script( 'p2-jams', plugins_url( 'js/p2-jams.js' , __FILE__ ), array('jquery') );
		wp_localize_script( 'p2-jams', 'p2_jams', $p2_jams_data );
		
		wp_enqueue_style( 'p2-jams', plugins_url( 'css/style.css' , __FILE__ ) );
	}
	
	/**
	 * Add Last.fm username to the edit profile page
	 *
	 * @access private
	 * @return array
	 */
	public function add_lastfm_user( $fields ) {
		$fields['p2_jams_lastfm'] = __( 'Last.fm Username', 'p2-jams' );
		return $fields;
	}
	
	/**
	 * Get a user's Last.fm username if it exists
	 *
	 * @access private
	 * @return mixed
	 */
	private function get_lastfm_user( $user_id ) {
		return get_the_author_meta( 'p2_jams_lastfm', $user_id );;
	}
	
	/**
	 * Return current song from scrobble API
	 *
	 * @access private
	 * @return boolean
	 */
	private function get_jamming( $user_id = false ) {
		
		if ( ! $user_id || ! $lastfm_user = $this->get_lastfm_user( $user_id ) )
			return false;
		
		// Scrobble API Request
		$request = wp_remote_get( "http://ws.audioscrobbler.com/1.0/user/". $lastfm_user ."/recenttracks.rss" );
		$data = wp_remote_retrieve_body( $request );
		$rss = simplexml_load_string( $data );
		$count = 1;
		
		// Get latest song and return it
		for ( $i=0; $i < $count; $i++ ) {
			
			$url = $rss->channel->item[$i]->link;
			$title = $rss->channel->item[$i]->title;
			$pubDate = $rss->channel->item[$i]->pubDate;
			$pubDate_time = strtotime($pubDate);
			
			// Only return song if it's being listened to
			if ( $this->is_jamming( $pubDate_time ) ) {
				return '<span>' . __('Jaming to:', 'p2-jams' ) . '</span> <a title="' . esc_attr( $title ) . '" href="' . esc_url( $url ) . '" target="_blank">' . $title . '</a>';
			}
				
		}
		
		return false;
		
	}
	
	/**
	 * Only show songs that are currently being listened to, otherwise ignore
	 *
	 * @access private
	 * @param string $ptime a timestamp
	 * @return boolean
	 */
	private function is_jamming( $ptime ) {
		
		$etime = time() - $ptime;
		
		// If < 1 we know the user is currently jamming to the song
		if ( $etime < 1 )
			return true;
		
		$a = array(	12 * 30 * 24 * 60 * 60	=>  'year',
					30 * 24 * 60 * 60		=>  'month',
					24 * 60 * 60			=>  'day',
					60 * 60				=>  'hour',
					60					=>  'minute',
					1					=>  'second'
		);
		
		foreach ( $a as $secs => $str ) {
			$d = $etime / $secs;
			if ( $d >= 1 ) {
				$r = round( $d );
				if ( $r < 3 && $str == "second" ) return true; // User is jamming now
			}
		}
		
		return false; // User isn't jamming to anything
	}
	
	/**
	 * Return an HTML list of all listeners.
	 *
	 * @access public
	 * @param bool $items_as_array return array of items only
	 * @return mixed
	 */
	public function get_jammers( $items_as_array = false ) {
		
		$output = false;
		$items = array();
	
		$users_args = array();
	    $users = get_users( $users_args );
		
		if ( $users ) {
			
			if ( ! $items_as_array )
				$output .= '<ul id="p2-jams">';
			
			foreach ( $users as $user ) {
				
				if ( $listener = $this->get_jamming( $user->ID ) ) {
					$output .= '<li data-p2-jams="' . esc_attr( $user->ID ) . '">';
					$output .= get_avatar( $user->user_email, 32 );
					$output .= '<p>' . $listener . '</p>';
					$output .= '</li>';
					
					if ( $items_as_array ) {
						$items[] = array( $output, $user->ID );
						$output = '';
					}
				}
				
			}
			
			if ( ! $items_as_array )
				$output .= '</ul>';
			else
				$output = $items;
		}
		
		if ( $output )
			return $output;
		
		return false;
			
	}

	/**
	 * Handle AJAX Requests
	 *
	 * @access public
	 */
	public function ajax_check_jams() {
		check_ajax_referer( 'p2-jams-nonce', 'security' );
		echo json_encode( $this->get_jammers( true ) );
		exit;
	}
	
}

/**
 * Returns the main instance of P2_Jams
 *
 * @since  1.0
 * @return P2_Jams
 */
function P2_Jams() {
	return P2_jams::instance();
}

// Global for backwards compatibility.
$GLOBALS['p2_jams'] = P2_Jams();