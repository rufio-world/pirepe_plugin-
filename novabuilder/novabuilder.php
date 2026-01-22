<?php
/**
 * Plugin Name: NovaBuilder
 * Description: A visual drag-and-drop page and theme builder for WordPress.
 * Version: 0.1.0
 * Author: NovaBuilder Team
 * Text Domain: novabuilder
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOVABUILDER_VERSION', '0.1.0' );

define( 'NOVABUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOVABUILDER_URL', plugin_dir_url( __FILE__ ) );

define( 'NOVABUILDER_META_KEY', '_novabuilder_data' );

define( 'NOVABUILDER_TEMPLATE_CPT', 'novabuilder_template' );

define( 'NOVABUILDER_TEXT_DOMAIN', 'novabuilder' );

require_once NOVABUILDER_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'NovaBuilder\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'NovaBuilder\\Plugin', 'instance' ) );
