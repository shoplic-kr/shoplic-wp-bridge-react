<?php
/**
 * Plugin Name:  Shoplic WP Bridge React
 * Plugin URI:   https://shoplic.kr
 * Description:  워드프레스에서 리액트를 쉽게 사용할 수 있도록 만든 플러그인입니다.
 * Author:       쇼플릭
 * Author URI:   https://shoplic.kr
 * Requires PHP: 7.4
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Version:      0.0.3
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
