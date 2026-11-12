<?php
declare(strict_types=1);

$dir = dirname(__DIR__);
$files = array_merge(glob($dir.'/src/*.php'), glob(__DIR__.'/*.php'), [$dir.'/bootstrap.php', $dir.'/config.example.php', $dir.'/bin/loxya']);
foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file);
    passthru($command, $code);
    if ($code !== 0) exit($code);
}
echo "Validation syntaxique terminee.\n";
