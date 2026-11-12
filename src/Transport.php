<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Permet de remplacer le transport HTTP dans les tests. */
interface Transport
{
    /** @param list<string> $headers */
    public function send(string $method, string $path, array $headers, ?array $payload): HttpResponse;
}
