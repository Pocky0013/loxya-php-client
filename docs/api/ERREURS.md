# Exceptions et écritures incertaines

[Référence PHP](README.md)

| Situation | Exception | Suite à donner |
| --- | --- | --- |
| ID, date, pagination ou payload invalide | `\InvalidArgumentException` | Corriger les paramètres (avant réseau) |
| Erreur HTTP | `Electraudio\Loxya\HttpException` | Examiner `status`, `method` et `path` |
| Connexion / transport | `TransportException` | Vérifier réseau ; écriture potentiellement appliquée |
| JSON / structure inattendue | `ProtocolException` | Ne pas interpréter une réponse incertaine |
| Écriture sans confirmation | `WriteNotConfirmedException` | **Relire et vérifier avant tout nouvel essai** |
| Conflit d'association | `\LogicException` | Relire la relation |

`WriteNotConfirmedException` est une `ProtocolException`. Ses propriétés en lecture seule sont `operation`, `resource`, `resourceId` (éventuellement `null`) et `reason`.

```php
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\ProtocolException;
use Electraudio\Loxya\TransportException;
use Electraudio\Loxya\WriteNotConfirmedException;

try {
    $updated = $client->updateCompany(42, ['note' => 'Note fictive']);
} catch (WriteNotConfirmedException $e) {
    // Peut déjà avoir été modifié : vérifier avant tout nouvel essai.
} catch (HttpException $e) {
    // $e->status, $e->method, $e->path.
} catch (TransportException $e) {
    // Effet distant potentiellement déjà appliqué.
} catch (ProtocolException $e) {
    // Réponse inattendue.
}
```

**Important :** le SDK peut rejouer un **GET une seule fois** après HTTP 401, mais **jamais un POST/PUT**. Après écriture, il relit la fiche et compare strictement l'ID et les champs envoyés. Si Loxya normalise une valeur, l'opération peut être signalée non confirmée bien qu'elle ait réussi.

Voir [le contrat technique des écritures](../projet/contrats/CONTRAT-ECRITURES-CONTACTS.md).
