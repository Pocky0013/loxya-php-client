<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Contrat injectable: toute communication HTTP passe par ce transport. */
interface Transport
{
    /** @param list<string> $headers */
    public function send(string $method, string $path, array $headers, ?array $payload): HttpResponse;
}
