<?php
/**
 * Plugin Name: QSD Platform
 * Description: Core application platform.
 * Version: 1.0.1
 * Text Domain: qsd-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('QSD_PLUGIN_VERSION')) {
    define('QSD_PLUGIN_VERSION', '1.0.1');
}

define('QSD_PLUGIN_FILE', __FILE__);
define('QSD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('QSD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QSD_APP_PATH', QSD_PLUGIN_PATH . 'app/');
define('QSD_APP_URL', QSD_PLUGIN_URL . 'app/');
define('QSD_DIST_PATH', QSD_PLUGIN_PATH . 'dist/');
define('QSD_DIST_URL', QSD_PLUGIN_URL . 'dist/');
define('QSD_ATOMIC_ENGINE_PATH', QSD_PLUGIN_PATH . 'atomic-engine/');
define('QSD_ATOMIC_ENGINE_URL', QSD_PLUGIN_URL . 'atomic-engine/');

if (file_exists(QSD_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once QSD_PLUGIN_PATH . 'vendor/autoload.php';
}

require_once QSD_APP_PATH . 'bootstrap/init.php';
