<?php
/*
Plugin Name: WP Spykey
Description: This plugin will tell you when each of your users had logged in & give you an detailed information about the visitors on your website.
Version: 1.0
Author: GeroNikolov
Author URI: http://geronikolov.com
License: GPLv2
*/

class WP_SPYKEY {
	function __construct() {
		// Add scripts and styles for the Back-end part
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_js' ), "1.0.0", "true" );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_css' ) );

		// Add WP_Login tracker
		add_action( 'wp_login', array( $this, 'track_login' ), 99 );
		add_action( 'wp_footer', array( $this, 'track_visit' ), 99 );

		// Register the Dashboad statistics page
		add_action( 'admin_menu', array( $this, 'wpsk_statistics_page' ) );

		// Register AJAX call for the Load More Users button
		add_action( 'wp_ajax_wpull_load_more_users', array( $this, 'wpull_load_more_users' ) );
		add_action( 'wp_ajax_nopriv_wpull_load_more_users', array( $this, 'wpull_load_more_users' ) );
	}

	// Register Admin JS
	function add_admin_JS() {
		wp_enqueue_script( 'wpull-admin-js', plugins_url( '/assets/scripts.js' , __FILE__ ), array('jquery'), '1.0', true );
	}
	// Register Admin CSS
	function add_admin_CSS( $hook ) {
		wp_enqueue_style( 'wpull-admin-css', plugins_url( '/assets/style.css', __FILE__ ), array(), '1.0', 'screen' );
	}

	// Register Dashboard statistics page function
	function wpsk_statistics_page() {
		add_options_page( 'WP Spykey Statistics', 'WPSK Statistics', 'manage_options', 'wpsk-statistics', array( $this, 'wpsk_statistics' ) );
	}
	// Register statistics page function
	function wpsk_statistics() { include( plugin_dir_path( __FILE__ ) . 'wpsk_statistics.php'); }

	// DB preparation
	function setup_db() {
		global $wpdb;

		$user_addresses_table = $wpdb->prefix ."user_addresses";

		if( $wpdb->get_var( "SHOW TABLES LIKE '$user_addresses_table'" ) != $user_addresses_table ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql_ = "
			CREATE TABLE $user_addresses_table (
			 	ID INT NOT NULL AUTO_INCREMENT,
				user_id INT,
				user_ip VARCHAR(255),
				user_country LONGTEXT,
				user_city LONGTEXT,
				page_id INT,
				lat_long VARCHAR(255),
				user_agent LONGTEXT,
				last_visit TIMESTAMP,
				PRIMARY KEY(ID)
			) $charset_collate;
			";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			dbDelta( $sql_ );
		}
	}

	// WP_Login tracker
	function track_login( $login ) {
		$date_time = date( "Y-m-d H:i:s" );

		$user_ = get_user_by( 'login', $login );
		$user_id = $user_->ID;
		$user_ip = $_SERVER[ "REMOTE_ADDR" ];
		$user_details = json_decode( file_get_contents( "http://ipinfo.io/{$user_ip}/json" ) );
		$page_id = get_queried_object_id();

		$wp_ull = get_user_meta( $user_id, "user_last_login", false );
		if ( empty( $wp_ull ) ) { add_user_meta( $user_id, "user_last_login", $date_time, false ); }
		else { update_user_meta( $user_id, "user_last_login", $date_time ); }

		global $wpdb;
		$table = $wpdb->prefix ."user_addresses";
		$data = array(
			"user_id" => $user_id,
			"user_ip" => $user_ip,
			"user_country" => !empty( $user_details->country ) ? $user_details->country : "Unknown",
			"user_city" => !empty( $user_details->city ) ? $user_details->city : "Unknown",
			"page_id" => $page_id,
			"lat_long" => !empty( $user_details->loc ) ? $user_details->loc : "Unknown",
			"user_agent" => $_SERVER[ "HTTP_USER_AGENT" ],
			"last_visit" => $date_time
		);
		$wpdb->insert( $table, $data );
	}

	// WP_Visit tracker
	function track_visit() {
		$date_time = date( "Y-m-d H:i:s" );

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			$wp_ull = get_user_meta( $user_id, "user_last_login", false );
			if ( empty( $wp_ull ) ) { add_user_meta( $user_id, "user_last_login", $date_time, false ); }
			else { update_user_meta( $user_id, "user_last_login", $date_time ); }
		}

		$user_ip = $_SERVER[ "REMOTE_ADDR" ];
		$user_details = json_decode( file_get_contents( "http://ipinfo.io/{$user_ip}/json" ) );
		$page_id = get_the_ID();

		global $wpdb;
		$table = $wpdb->prefix ."user_addresses";
		$data = array(
			"user_id" => $user_id,
			"user_ip" => $user_ip,
			"user_country" => !empty( $user_details->country ) ? $user_details->country : "Unknown",
			"user_city" => !empty( $user_details->city ) ? $user_details->city : "Unknown",
			"page_id" => $page_id,
			"lat_long" => !empty( $user_details->loc ) ? $user_details->loc : "Unknown",
			"user_agent" => $_SERVER[ "HTTP_USER_AGENT" ],
			"last_visit" => $date_time
		);
		$wpdb->insert( $table, $data );
	}

	// Load More Users
	function wpull_load_more_users() {
		$users_offset = $_POST[ "offset" ];
		$list_ = $_POST[ "list" ];

		$stack_ = "";

		if ( $list_ == "registered" ) {
			$args = array(
				'orderby' => 'ID',
				'order' => 'DESC',
				'number' => '50',
				'offset' => $users_offset
			);
			$users_ = get_users( $args );

			foreach ( $users_ as $user_ ) {
				$user_id = $user_->data->ID;
				$user_email = $user_->data->user_email;
				$user_last_login = get_user_meta( $user_id, "user_last_login", true );
				$user_meta = $this->get_user_information( $user_id, 1 );

				if ( !empty( $user_meta ) ) {
					$user_meta = $user_meta[ 0 ];
					$user_meta_details = $this->get_user_details( $user_meta->user_ip );
					$row_id = $user_meta->ID;

					$user_browser = false;
					if ( get_cfg_var( "browscap" ) ) { $user_browser = get_browser( $user_meta->user_agent ); }

					$page_title = $user_meta->page_id > 0 ? get_the_title( $user_meta->page_id ) : "Unknown page";

					$stack_ .= "
					<tr>
						<td>$user_id</td>
						<td>$user_email</td>
						<td>$user_last_login</td>
						<td>$user_meta->user_city</td>
						<td>$user_meta->user_country</td>
						<td>$page_title</td>
						<td>
					";

					if ( $user_meta->lat_long != "Unknown" ) { $stack_ .= "<a href='http://maps.google.com/maps?q={$user_meta->lat_long}' target='_blank' class='button button-primary button-large'>Map</a>"; }
					$stack_ .= "<button id='more-details-controller' class='button action' onclick='showHideDetails($row_id);'>Details</button>";

					$hostname = !empty( $user_meta_details->hostname ) ? $user_meta_details->hostname : "Unknown";
					$organisation = !empty( $user_meta_details->org ) ? $user_meta_details->org : "Unknown";
					$region = !empty( $user_meta_details->region ) ? $user_meta_details->region : "Unknown";
					$phone_code = !empty( $user_meta_details->phone ) ? $user_meta_details->phone : "Unknown";

					if ( $user_browser != false ) {
						$browser = $user_browser->browser;
						$browser_version = $user_browser->version;
						$platform = ucfirst( $user_browser->platform );
					}

					$stack_ .= "
					<div id='details-$row_id' class='details-container'>
						<p><strong>IP:</strong> $user_meta->user_ip</p>
						<p><strong>Last Visit:</strong> $user_meta->last_visit</p>
						<p><strong>Hostname:</strong> $hostname</p>
						<p><strong>Organisation:</strong> $organisation</p>
						<p><strong>Region:</strong> $region</p>
						<p><strong>Phone code:</strong> $phone_code</p>
						";

					if ( $user_browser != false ) {
						$stack_ .= "
							<p><strong>Browser:</strong> $browser</p>
							<p><strong>Browser Version:</strong> $browser_version</p>
							<p><strong>Platform/OS:</strong> $platform</p>
						";
					}

					$stack_ .= "</div>";

					$stack_ .= "</td>";
				}
			}
		} elseif ( $list_ == "unregistered" ) {
			$users_ = $this->get_unregistered_users( $users_offset );

			foreach ( $users_ as $user_meta ) {
				if ( !empty( $user_meta ) ) {
					$user_meta_details = $this->get_user_details( $user_meta->user_ip );
					$row_id = $user_meta->ID;

					$user_browser = false;
					if ( get_cfg_var( "browscap" ) ) { $user_browser = get_browser( $user_meta->user_agent ); }

					$region = !empty( $user_meta_details->region ) ? $user_meta_details->region : "Unknown";
					$visited_page = $user_meta->page_id > 0 ? get_the_title( $user_meta->page_id ) : "Unknown page";

					$stack_ .= "
					<tr>
						<td>$user_meta->user_ip</td>
						<td>$user_meta->last_visit</td>
						<td>$user_meta->user_city</td>
						<td>$region</td>
						<td>$user_meta->user_country</td>
						<td>$visited_page</td>
						<td>
					";

					if ( $user_meta->lat_long != "Unknown" ) { $stack_ .= "<a href='http://maps.google.com/maps?q={$user_meta->lat_long}' target='_blank' class='button button-primary button-large'>Map</a>"; }
					$stack_ .= "<button id='more-details-controller' class='button action' onclick='showHideDetails($row_id);'>Details</button>";

					$hostname = !empty( $user_meta_details->hostname ) ? $user_meta_details->hostname : "Unknown";
					$organisation = !empty( $user_meta_details->org ) ? $user_meta_details->org : "Unknown";
					$phone_code = !empty( $user_meta_details->phone ) ? $user_meta_details->phone : "Unknown";

					if ( $user_browser != false ) {
						$browser = $user_browser->browser;
						$browser_version = $user_browser->version;
						$platform = ucfirst( $user_browser->platform );
					}

					$stack_ .= "
					<div id='details-$row_id' class='details-container'>
						<p><strong>Hostname:</strong> $hostname</p>
						<p><strong>Organisation:</strong> $organisation</p>
						<p><strong>Phone code:</strong> $phone_code</p>
					";

					if ( $user_browser != false ) {
						$stack_ .= "
							<p><strong>Browser:</strong> $browser</p>
							<p><strong>Browser Version:</strong> $browser_version</p>
							<p><strong>Platform/OS:</strong> $platform</p>
						";
					}

					$stack_ .= "</div>";

					$stack_ .= "</td>";
				}
			}
		}

		echo $stack_;

		die();
	}

	// Get unregistered users
	function get_unregistered_users( $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix ."user_addresses";
		$sql = "SELECT * FROM $table WHERE user_id=0 ORDER BY ID DESC LIMIT 50 OFFSET $offset";
		$results = $wpdb->get_results( $sql, OBJECT );
		return $results;
	}

	// Get user information
	function get_user_information( $user_id = 0, $limit = 0 ) {
		$limit = $limit > 0 ? "LIMIT $limit" : "";
		global $wpdb;
		$table = $wpdb->prefix ."user_addresses";
		$sql = "SELECT * FROM $table WHERE user_id=$user_id ORDER BY ID DESC $limit";
		$results = $wpdb->get_results( $sql, OBJECT );
		return $results;
	}

	// Get user fetails
	function get_user_details( $user_ip ) { return json_decode( file_get_contents( "http://ipinfo.io/{$user_ip}/json" ) ); }
}

$tracker_ = new WP_SPYKEY;
$tracker_->setup_db();
?>
