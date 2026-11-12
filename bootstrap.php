<?php
declare(strict_types=1);

/**
 * Autoload PSR-4 minimal pour la CLI sans installation Composer.
 * En tant que dependance Composer, utiliser simplement vendor/autoload.php.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Electraudio\\Loxya\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__.'/src/'.substr($class, strlen($prefix)).'.php';
    if (is_file($file)) {
        require $file;
    }
});
