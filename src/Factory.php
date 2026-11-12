<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Point de construction commun pour une application, la CLI ou le portail. */
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

    /** Utile pour l'integration a des secrets geres par une autre application. */
    public static function fromArray(array $config): Client
    {
        $baseUrl = (string)($config['base_url'] ?? '');
        $host = (string)($config['allowed_host'] ?? '');
        $user = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        if ($baseUrl === '' || $host === '' || $user === '' || $password === ''
            || str_contains($user, 'REMPLACER') || str_contains($password, 'REMPLACER')) {
            throw new \InvalidArgumentException('Configurer base_url, allowed_host, username et password.');
        }
        return new Client(new CurlTransport(
            $baseUrl,
            $host,
            (int)($config['connect_timeout'] ?? 10),
            (int)($config['timeout'] ?? 30),
        ), $user, $password);
    }
}
