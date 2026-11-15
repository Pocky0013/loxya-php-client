<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$errors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'md') continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) continue;
    $body = file_get_contents($path);
    if (!is_string($body)) { $errors[] = "Cannot read $path"; continue; }
    preg_match_all('~\]\(([^)]+)\)~', $body, $links);
    foreach ($links[1] as $url) {
        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $url)) continue;
        $local = explode('#', explode('?', $url, 2)[0], 2)[0];
        if ($local === '') continue;
        if (!is_file(dirname($path) . DIRECTORY_SEPARATOR . rawurldecode($local))) {
            $errors[] = "Broken link: " . substr($path, strlen($root) + 1) . " -> $url";
        }
    }
}
$client = file_get_contents($root . '/src/Client.php');
$index = file_get_contents($root . '/docs/api/README.md');
if (!is_string($client) || !is_string($index)) {
    $errors[] = 'Client or API index missing';
} else {
    preg_match_all('/^\s*public function ([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $client, $methods);
    foreach ($methods[1] as $name) {
        if ($name !== '__construct' && !str_contains($index, '`' . $name . '(')) {
            $errors[] = "Undocumented method: $name";
        }
    }
}
if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "Documentation links and API index OK.\n";
