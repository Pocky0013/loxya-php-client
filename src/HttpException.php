<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Erreur HTTP explicite; le corps de la reponse reste volontairement masque. */
final class HttpException extends LoxyaException
{
    public function __construct(
        public readonly int $status,
        public readonly string $method,
        public readonly string $path,
    ) {
        parent::__construct(sprintf('HTTP %d sur %s /api%s', $status, $method, $path));
    }
}
