# Exemple PHP — événements et matériel associé

[Accueil](../../README.md) · [API événements](../api/EVENEMENTS.md) · [API matériel](../api/MATERIEL.md)

Cet exemple montre comment **lire les bookings d'une période**, récupérer la fiche de chaque événement et consulter ses lignes de matériel lorsqu'elles sont disponibles. Il n'applique aucune règle métier ni filtre propre à une application. Méthodes disponibles depuis **v0.2.0**.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

$client = Factory::fromEnvironment();
$bookings = $client->bookingsInPeriod('2027-05-01', '2027-05-31');

foreach ($bookings as $booking) {
    // Les bookings peuvent correspondre à plusieurs types de ressources.
    if (($booking['entity'] ?? null) !== 'event') {
        continue;
    }

    $id = $booking['id'] ?? null;
    if (!is_int($id) || $id < 1) {
        continue;
    }

    $event = $client->event($id);
    echo "Événement #{$id}\n";

    if (!array_key_exists('materials', $event)
        || !is_array($event['materials'])
        || !array_is_list($event['materials'])) {
        // Champ absent ou structure inconnue : ne pas conclure à une liste vide.
        echo "  Matériel : information indisponible\n";
        continue;
    }

    foreach ($event['materials'] as $line) {
        $reference = $line['reference'] ?? null;
        $quantity = $line['quantity'] ?? null;
        $cataloguePrice = $line['material']['rental_price'] ?? null;

        // Valeurs brutes issues de Loxya, à utiliser seulement si présentes.
    }
}
```

**Retours attendus :** `bookingsInPeriod()` renvoie une **liste** PHP de bookings ; `event($id)` renvoie une **fiche** associative ; `materials`, lorsqu'il est disponible et au format attendu, est une liste de lignes de matériel qui peut être **vide**.

Le SDK ne filtre pas les lignes selon leur référence, leur catégorie ou un usage métier : il restitue les données fournies par Loxya. Le champ `material.rental_price` est un **prix catalogue**, distinct du champ événementiel `unit_price` lorsqu'il existe.

Les dates et identifiants sont fictifs. Voir les [formats de réponse](../api/EVENEMENTS.md) et les [tarifs catalogue](../api/MATERIEL.md).
