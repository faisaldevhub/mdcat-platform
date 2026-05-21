<?php
/**
 * Plugin Name: MDCAT Platform
 * Plugin URI: https://mdcatinsecond.com
 * Description: Custom MDCAT Learning Platform
 * Version: 1.0.0
 * Author: Faisal Dev Hub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 */

define('MDCAT_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('MDCAT_PLATFORM_URL', plugin_dir_url(__FILE__));
define('MDCAT_PLATFORM_VERSION', '1.0.0');

/**
 * Load Core Files
 */

require_once MDCAT_PLATFORM_PATH . 'includes/class-loader.php';
require_once MDCAT_PLATFORM_PATH . 'includes/class-activator.php';
require_once MDCAT_PLATFORM_PATH . 'includes/class-deactivator.php';

/**
 * Activation Hook
 */

register_activation_hook(__FILE__, ['MDCAT_Platform_Activator', 'activate']);

/**
 * Deactivation Hook
 */

register_deactivation_hook(__FILE__, ['MDCAT_Platform_Deactivator', 'deactivate']);

/**
 * Initialize Plugin
 */

function mdcat_platform_init() {
    MDCAT_Platform_Loader::init();
}

mdcat_platform_init();