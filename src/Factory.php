<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

final class Factory
{
    public static function fromFile(string $configPath): Client
    {
        if (!is_file($configPath) || !is_readable($configPath)) {
            throw new \InvalidArgumentException('Configuration Loxya absente ou illisible.');
        }
        $config = require $configPath;
        if (!is_array($config)) {
            throw new \InvalidArgumentException('Configuration Loxya incorrecte (tableau attendu).');
        }
        return self::fromArray($config);
    }

    public static function fromArray(array $config): Client
    {
        $baseUrl = (string)($config['base_url'] ?? '');
        $user = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        if ($baseUrl === '' || trim($user) === '' || $password === '') {
            throw new \InvalidArgumentException('Configurer base_url, username et password.');
        }

        // L'hote est fixe par l'URL configuree ; CurlTransport refuse les redirections.
        $host = parse_url($baseUrl, PHP_URL_HOST);
        return new Client(new CurlTransport(
            $baseUrl,
            is_string($host) ? $host : '',
            (int)($config['connect_timeout'] ?? 10),
            (int)($config['timeout'] ?? 30),
        ), $user, $password);
    }
}
