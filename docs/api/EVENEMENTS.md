# Événements et réservations

[Référence PHP](README.md) · [Exemple complet](../guides/SCENARIOS.md)

**Validé en lecture sur une instance SaaS (v0.2.0).** Autres installations et cas limites en test.

Les assignations de techniciens de la fiche événement peuvent aussi être lues via [`eventAssignments($id)`](TECHNICIENS.md), sans requête HTTP supplémentaire.

## `bookingsInPeriod(string $startDate, string $endDate): array`

Liste les bookings d'une période.

| Paramètre | Type | Description |
| --- | --- | --- |
| `startDate` | `string` | Début, `YYYY-MM-DD` |
| `endDate` | `string` | Fin, `YYYY-MM-DD` (≥ début) |

```php
$bookings = $client->bookingsInPeriod('2027-05-01', '2027-05-31');
```

**Retour attendu :** liste PHP à la racine (non paginée), chaque élément étant un booking associatif. D'autres types que `entity = event` peuvent être présents.

```json
[
  {"id": 42, "entity": "event"},
  {"id": 43, "entity": "event"}
]
```

*Exemple fictif et partiel.* **HTTP interne :** `GET /api/bookings?paginated=0&period[start]=…&period[end]=…`. Dates invalides ou période inversée : `InvalidArgumentException` avant réseau.

## `event(int $id): array`

Lit la fiche complète de l'événement, incluant les lignes de matériel lorsque `materials` est exposé.

| Paramètre | Type | Description |
| --- | --- | --- |
| `id` | `int` | ID d'événement strictement positif |

```php
$event = $client->event(42);
$materials = $event['materials'] ?? null;
if (is_array($materials) && array_is_list($materials)) {
    foreach ($materials as $line) {
        $reference = $line['reference'] ?? null;
        $quantity = $line['quantity'] ?? null;
    }
}
```

**Retour attendu :** tableau associatif (fiche JSON brute). Forme fictive partielle :

```json
{
  "id": 42,
  "materials": [
    {"id": 103, "reference": "TEST-103", "quantity": 2,
     "material": {"id": 103, "rental_price": "32.50"}}
  ]
}
```

`materials[*].id` désigne un **article du catalogue**, non l'ID technique du pivot. `materials` peut être vide ou absent ; une clé absente **ne prouve pas** que l'événement n'a aucun matériel.

**HTTP interne :** `GET /api/events/{id}`. Les résumés de bookings et la route des disponibilités ne remplacent pas la liste des réservations.

**Erreurs :** ID invalide, HTTP 401/403/404, transport ou protocole. [Exceptions](ERREURS.md) · [Contrat technique](../projet/contrats/CONTRAT-LECTURE-EVENEMENTS.md).
