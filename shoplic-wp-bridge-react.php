<?php
/**
 * Plugin Name:  Shoplic WP Bridge React
 * Plugin URI:   https://shoplic.kr
 * Description:  It's a plugin designed to make using React easier in WordPress.
 * Author:       쇼플릭
 * Author URI:   https://shoplic.kr
 * Requires PHP: 7.4
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Version:      1.2.0
 * GitHub Plugin URI: https://github.com/shoplic-kr/shoplic-wp-bridge-react
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Constants
const SHOPLIC_WP_BRIDGE_REACT = 'shoplic-wp-bridge-react';
define('SHOPLOC_WP_BRIDGE_REACT_PATH', plugin_dir_path(__FILE__));

// Includes
require_once(SHOPLOC_WP_BRIDGE_REACT_PATH . 'includes/Functions.php');

// Bootstrap Function
function shoplic_wp_bridge_react(): \Shoplic\WPBridgeReact\ReactBridge {
    return \Shoplic\WPBridgeReact\ReactBridge::getInstance();
}
