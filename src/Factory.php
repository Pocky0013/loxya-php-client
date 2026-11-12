<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

final class Factory
{
    /**
     * Lit les variables déjà chargées par l'application (Dotenv, serveur ou CI).
     * La bibliothèque ne lit pas les fichiers .env elle-même.
     */
    public static function fromEnvironment(): Client
    {
        return self::fromArray([
            'base_url' => self::environmentValue('LOXYA_BASE_URL'),
            'username' => self::environmentValue('LOXYA_USERNAME'),
            'password' => self::environmentValue('LOXYA_PASSWORD'),
        ]);
    }

    public static function fromArray(array $config): Client
    {
        $baseUrl = $config['base_url'] ?? null;
        $user = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === ''
            || !is_string($user) || trim($user) === ''
            || !is_string($password) || $password === '') {
            throw new \InvalidArgumentException('Configuration Loxya incomplète : URL, utilisateur ou mot de passe manquant.');
        }

        // L'hôte est fixé par l'URL configurée ; les redirections sont interdites.
        $host = parse_url($baseUrl, PHP_URL_HOST);
        return new Client(new CurlTransport(
            $baseUrl,
            is_string($host) ? $host : '',
            self::timeout($config['connect_timeout'] ?? 10, 'connect_timeout'),
            self::timeout($config['timeout'] ?? 30, 'timeout'),
        ), $user, $password);
    }

    private static function environmentValue(string $key): ?string
    {
        // phpdotenv remplit $_ENV ; les hébergeurs peuvent utiliser getenv().
        foreach ([$_ENV, $_SERVER] as $values) {
            if (isset($values[$key])) {
                return is_string($values[$key]) ? $values[$key] : null;
            }
        }
        $value = getenv($key);
        return $value === false ? null : $value;
    }

    private static function timeout(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^[0-9]+$/D', $value))) {
            throw new \InvalidArgumentException('Temporisation Loxya invalide : '.$name.'.');
        }
        return (int)$value;
    }
}
