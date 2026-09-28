<?php
/**
 * Plugin Name: QSD Platform
 * Description: Queensland Scuba Diving business platform — Stations, lifecycle, Platform IDs, API, and the Admin Station.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Text Domain: qsd-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('QSD_PLUGIN_VERSION')) {
    define('QSD_PLUGIN_VERSION', '1.0.0');
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

// PSR-4 autoloader for QSD\Platform\ → src/. No Composer install needed.
spl_autoload_register(static function (string $class): void {
    $prefix = 'QSD\\Platform\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = QSD_PLUGIN_PATH . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once QSD_APP_PATH . 'bootstrap/init.php';
