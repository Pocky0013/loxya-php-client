<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Reponse minimale et independante de toute bibliotheque HTTP. */
final class HttpResponse
{
    public function __construct(public readonly int $status, public readonly string $body) {}
}
