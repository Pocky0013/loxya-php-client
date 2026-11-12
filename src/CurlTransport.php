<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Transport HTTPS sans redirection ni nouvelle tentative automatique. */
final class CurlTransport implements Transport
{
    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        string $allowedHost,
        private readonly int $connectTimeout = 10,
        private readonly int $timeout = 30,
    ) {
        if (!function_exists('curl_init')) {
            throw new \InvalidArgumentException('Extension PHP ext-curl requise.');
        }
        $parts = parse_url($baseUrl);
        $normalizedHost = strtolower(trim($allowedHost));
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== $normalizedHost
            || $normalizedHost === '' || !preg_match('/^[a-z0-9.-]+$/D', $normalizedHost)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
            || ($parts['port'] ?? 443) !== 443
            || $connectTimeout < 1 || $connectTimeout > 60
            || $timeout < $connectTimeout || $timeout > 120) {
            throw new \InvalidArgumentException('URL HTTPS, hote autorise ou temporisations invalides.');
        }
        $this->baseUrl = 'https://'.$normalizedHost;
    }

    public function send(string $method, string $path, array $headers, ?array $payload): HttpResponse
    {
        if (!in_array($method, ['GET', 'POST', 'PUT'], true)
            || !preg_match('#^/[a-z][a-z0-9/-]*(?:\?[a-z0-9%._~=&-]+)?$#iD', $path)
            || str_contains($path, '//') || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Methode HTTP ou chemin non autorise.');
        }
        $handle = curl_init($this->baseUrl.'/api'.$path);
        if ($handle === false) {
            throw new TransportException('Initialisation HTTP impossible.');
        }

        $settings = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'electraudio/loxya-php-client',
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $settings[CURLOPT_PROTOCOLS_STR] = 'https';
        } else {
            $settings[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($payload !== null) {
            $settings[CURLOPT_POSTFIELDS] = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        }
        curl_setopt_array($handle, $settings);
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($handle);
        curl_close($handle);

        if ($body === false || $errorNumber !== 0 || $status === 0) {
            // Le detail de curl_error() peut contenir des donnees de connexion.
            throw new TransportException('Echec de transport HTTPS (cURL '.(int)$errorNumber.').');
        }
        return new HttpResponse($status, $body);
    }
}
