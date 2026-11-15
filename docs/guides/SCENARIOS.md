# Parcours : événements avec du matériel

[Accueil](../../README.md) · [API événements](../api/EVENEMENTS.md) · [API matériel](../api/MATERIEL.md)

Objectif : obtenir les événements d'une période et identifier ceux dont la fiche comporte au moins une ligne de matériel réservé. Disponible depuis **v0.2.0**.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

$client = Factory::fromEnvironment();
$bookings = $client->bookingsInPeriod('2027-05-01', '2027-05-31');

foreach ($bookings as $booking) {
    if (($booking['entity'] ?? null) !== 'event') {
        continue;
    }
    $id = $booking['id'] ?? null;
    if (!is_int($id) || $id < 1) {
        continue;
    }

    $event = $client->event($id);
    $materials = $event['materials'] ?? null;
    if (!is_array($materials) || !array_is_list($materials)) {
        continue; // Format inconnu, pas une preuve de "sans matériel".
    }
    if ($materials === []) {
        continue;
    }

    echo "Événement #{$id} : " . count($materials) . " lignes\n";
    foreach ($materials as $line) {
        $reference = $line['reference'] ?? null;
        $quantity = $line['quantity'] ?? null;
        $rentalPrice = $line['material']['rental_price'] ?? null;
        // Valeurs brutes à utiliser selon les besoins de l'application.
    }
}
```

**Retour attendu :** `bookingsInPeriod()` renvoie une **liste** ; `event()` renvoie une **fiche** qui peut exposer `materials`. Les quantités, références et prix catalogue sont facultatifs dans les traitements défensifs.

Une référence comme `SANS_Materiel` peut représenter un article valable sans objet physique. Le **filtrage métier** (par exemple les événements devant donner lieu à une offre Bexio) appartient à l'application consommatrice, **pas au SDK**. Le SDK ne crée ni offre ni synchronisation.

Le prix `material.rental_price` est un prix catalogue et ne doit pas être confondu avec `unit_price` propre à l'événement. Les dates et ID ci-dessus sont fictifs ; aucune garantie n'est donnée sur les champs supplémentaires.
