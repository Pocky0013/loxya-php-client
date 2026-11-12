<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/**
 * Ecriture transmise, mais non confirmee. L'effet distant est potentiellement
 * deja applique : le consommateur doit examiner l'etat avant tout nouvel essai.
 *
 * Cette exception reste une ProtocolException pour conserver les catches existants.
 */
final class WriteNotConfirmedException extends ProtocolException
{
    public function __construct(
        public readonly string $operation,
        public readonly string $resource,
        public readonly ?int $resourceId,
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'Ecriture Loxya envoyee, mais resultat non confirme. Verification manuelle requise ; ne pas retenter automatiquement.',
            0,
            $previous,
        );
    }
}
