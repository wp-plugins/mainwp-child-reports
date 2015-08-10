<?php
/*
  Plugin Name: MainWP Child Reports
  Plugin URI: https://mainwp.com/
  Description: The MainWP Child Report plugin tracks Child sites for the MainWP Client Reports Extension. The plugin is only useful if you are using MainWP and the Client Reports Extension.
  Author: MainWP
  Author URI: https://mainwp.com
  Version: 0.0.1
 */

/**
 * Copyright (c) 2014 WP Stream Pty Ltd (https://wp-stream.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

class MainWP_WP_Stream {

	const VERSION = '0.0.1';

	public static $instance;

	public $db = null;

	public $network = null;

	public static $notices = array();

	private function __construct() {
		define( 'MAINWP_WP_STREAM_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'MAINWP_WP_STREAM_DIR', plugin_dir_path( __FILE__ ) );
		define( 'MAINWP_WP_STREAM_URL', plugin_dir_url( __FILE__ ) );
		define( 'MAINWP_WP_STREAM_INC_DIR', MAINWP_WP_STREAM_DIR . 'includes/' );

		// Load filters polyfill
		require_once MAINWP_WP_STREAM_INC_DIR . 'filter-input.php';

		// Load DB helper class
		require_once MAINWP_WP_STREAM_INC_DIR . 'db.php';
		$this->db = new MainWP_WP_Stream_DB;

		// Check DB and display an admin notice if there are tables missing
		add_action( 'init', array( $this, 'verify_database_present' ) );

		// Install the plugin
		add_action( 'mainwp_wp_stream_before_db_notices', array( __CLASS__, 'install' ) );

		// Load languages
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		// Load settings at the same priority as connectors to support exclusions
		require_once MAINWP_WP_STREAM_INC_DIR . 'settings.php';
		add_action( 'init', array( 'MainWP_WP_Stream_Settings', 'load' ), 9 );

		// Load network class
		if ( is_multisite() ) {
			require_once MAINWP_WP_STREAM_INC_DIR . 'network.php';
			$this->network = new MainWP_WP_Stream_Network;
		}

		// Load logger class
		require_once MAINWP_WP_STREAM_INC_DIR . 'log.php';
		add_action( 'plugins_loaded', array( 'MainWP_WP_Stream_Log', 'load' ) );

		// Load connectors after widgets_init, but before the default of 10
		require_once MAINWP_WP_STREAM_INC_DIR . 'connectors.php';
		add_action( 'init', array( 'MainWP_WP_Stream_Connectors', 'load' ), 9 );

		// Load query class
		require_once MAINWP_WP_STREAM_INC_DIR . 'query.php';
		require_once MAINWP_WP_STREAM_INC_DIR . 'context-query.php';
		
		// Add frontend indicator
		add_action( 'wp_head', array( $this, 'frontend_indicator' ) );

		if ( is_admin() ) {
			require_once MAINWP_WP_STREAM_INC_DIR . 'admin.php';
			add_action( 'plugins_loaded', array( 'MainWP_WP_Stream_Admin', 'load' ) );


			// Registers a hook that connectors and other plugins can use whenever a stream update happens
			add_action( 'admin_init', array( __CLASS__, 'update_activation_hook' ) );
                        
                        require_once MAINWP_WP_STREAM_INC_DIR . 'dashboard.php';
			add_action( 'plugins_loaded', array( 'MainWP_WP_Stream_Dashboard_Widget', 'load' ) );

			require_once MAINWP_WP_STREAM_INC_DIR . 'live-update.php';
			add_action( 'plugins_loaded', array( 'MainWP_WP_Stream_Live_Update', 'load' ) );

		}
                
	}

	static function fail_php_version() {
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );
		self::notice( __( 'MainWP Child Report requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'mainwp-child-reports' ) );
	}

	public static function i18n() {
		load_plugin_textdomain( 'mainwp-child-reports', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public static function install() {
		// Install plugin tables
		require_once MAINWP_WP_STREAM_INC_DIR . 'install.php';
		$update = MainWP_WP_Stream_Install::get_instance();
	}

	public function verify_database_present() {
		
		if ( apply_filters( 'mainwp_wp_stream_no_tables', false ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		global $wpdb;

		$database_message  = '';
		$uninstall_message = '';

		// Check if all needed DB is present
		$missing_tables = array();
		foreach ( $this->db->get_table_names() as $table_name ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
				$missing_tables[] = $table_name;
			}
		}

		if ( $missing_tables ) {
			$database_message .= sprintf(
				'%s <strong>%s</strong>',
				_n(
					'The following table is not present in the WordPress database:',
					'The following tables are not present in the WordPress database:',
					count( $missing_tables ),
					'mainwp_child_reports'
				),
				esc_html( implode( ', ', $missing_tables ) )
			);
		}

		if ( is_plugin_active_for_network( MAINWP_WP_STREAM_PLUGIN ) && current_user_can( 'manage_network_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the MainWP Child Reports plugin and activate it again.', 'mainwp-child-reports' ), network_admin_url( 'plugins.php#mainwp-child-reports' ) );
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the MainWP Child Reports plugin and activate it again.', 'mainwp-child-reports' ), admin_url( 'plugins.php#mainwp-child-reports' ) );
		}

		do_action( 'mainwp_wp_stream_before_db_notices' );

		if ( ! empty( $database_message ) ) {
			self::notice( $database_message );
			if ( ! empty( $uninstall_message ) ) {
				self::notice( $uninstall_message );
			}
		}
	}

	static function update_activation_hook() {
		MainWP_WP_Stream_Admin::register_update_hook( dirname( plugin_basename( __FILE__ ) ), array( __CLASS__, 'install' ), self::VERSION );
	}

	public static function is_valid_php_version() {
		return version_compare( PHP_VERSION, '5.3', '>=' );
	}

	public static function notice( $message, $is_error = true ) {
		if ( defined( 'WP_CLI' ) ) {
			$message = strip_tags( $message );
			if ( $is_error ) {
				WP_CLI::warning( $message );
			} else {
				WP_CLI::success( $message );
			}
		} else {
			// Trigger admin notices
			add_action( 'all_admin_notices', array( __CLASS__, 'admin_notices' ) );

			self::$notices[] = compact( 'message', 'is_error' );
		}
	}

	public static function admin_notices() {
		foreach ( self::$notices as $notice ) {
			$class_name   = empty( $notice['is_error'] ) ? 'updated' : 'error';
			$html_message = sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wpautop( $notice['message'] ) );
			echo wp_kses_post( $html_message );
		}
	}

	public function frontend_indicator() {
		$comment = sprintf( 'Stream WordPress user activity plugin v%s', esc_html( self::VERSION ) ); // Localization not needed

		$comment = apply_filters( 'mainwp_wp_stream_frontend_indicator', $comment );

		if ( ! empty( $comment ) ) {
			echo sprintf( "<!-- %s -->\n", esc_html( $comment ) ); // xss ok
		}
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

}

if ( MainWP_WP_Stream::is_valid_php_version() ) {
	$GLOBALS['mainwp_wp_stream'] = MainWP_WP_Stream::get_instance();
	register_activation_hook( __FILE__, array( 'MainWP_WP_Stream', 'install' ) );
} else {
	MainWP_WP_Stream::fail_php_version();
}
