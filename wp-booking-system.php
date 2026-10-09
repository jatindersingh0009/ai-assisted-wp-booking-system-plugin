<?php
/**
 * Plugin Name:       WP Booking System
 * Plugin URI:        https://www.linkedin.com/in/jatindersingh0009
 * Description:       Online booking and appointment management system with PayPal and Stripe payment integration.
 * Version:           3.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jatinder Singh
 * Author URI:        https://www.linkedin.com/in/jatindersingh0009
 * License:           GPL v2 or later
 * Text Domain:       wp-booking-system
 * Domain Path:       /languages
 * Network:           true
 */

defined( 'ABSPATH' ) || exit;

define( 'WPBS_VERSION', '1.0.0' );
define( 'WPBS_PLUGIN_FILE', __FILE__ );
define( 'WPBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPBS_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPBS_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( 'WPBS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPBS_Deactivator', 'deactivate' ) );

require_once WPBS_PLUGIN_DIR . 'includes/class-wp-booking-system.php';

function wpbs_run() {
	$plugin = WPBS_Plugin::get_instance();
	$plugin->run();
}
wpbs_run();
