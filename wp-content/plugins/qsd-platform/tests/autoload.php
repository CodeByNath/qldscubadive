<?php

declare(strict_types=1);

// Test-time PSR-4 autoloader for QSD\Platform\ → src/, mirroring the one in
// qsd-platform.php. Tests stub the WordPress functions they need and require
// this instead of a Composer vendor/autoload.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'QSD\\Platform\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
