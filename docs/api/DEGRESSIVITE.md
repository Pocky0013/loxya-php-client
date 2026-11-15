# Barèmes dégressifs de location

[Référence PHP](README.md) · [Matériel](MATERIEL.md)

**En test** : route et sérialisation confirmées dans le code public Loxya, recette SaaS à réaliser.

## `degressiveRates(): array`

Liste les barèmes et leurs paliers sans calculer de coefficient ni de prix.

**HTTP interne :** `GET /api/degressive-rates`. Réponse : liste JSON racine, non paginée.

```php
$rates = array_column($client->degressiveRates(), null, 'id');
$material = $client->material(103);
$rateId = $material['degressive_rate_id'] ?? null;
$rate = is_int($rateId) ? ($rates[$rateId] ?? null) : null;
if ($rate !== null) {
    foreach ($rate['tiers'] as $tier) {
        $fromDay = $tier['from_day'];
        $isRate = $tier['is_rate'];
        $value = $tier['value'];
    }
}
```

**Exemple fictif :**

```json
[
  {
    "id": 1,
    "name": "Standard",
    "is_used": true,
    "tiers": [
      {"from_day": 1, "is_rate": true, "value": "100"},
      {"from_day": 3, "is_rate": false, "value": "2.00"}
    ]
  }
]
```

- `from_day` : premier jour d'application du palier.
- `is_rate` : `true` lorsque `value` représente un pourcentage ; `false` lorsque c'est une valeur fixe.
- `value` : décimal transmis par Loxya, généralement sous forme de chaîne.

**Attention à la différence avec SQL :** le modèle Loxya peut insérer un palier virtuel à partir du jour 1 (`is_rate: true`, `value: "100"`) lorsque le premier palier stocké commence plus tard. Cette entrée peut donc ne pas correspondre à une ligne physique de la table des paliers.

Le champ `material.degressive_rate_id` référence le barème catalogue. Les champs contextualisés d'une ligne événementielle (`degressive_rate`, `unit_price_period`) ne doivent pas être confondus avec cette référence.

Lorsque la facturation est désactivée sur Loxya, cette route peut répondre HTTP 404. Le SDK conserve cette erreur, ne l'interprète pas comme une liste de barèmes vide et n'applique aucune règle de tarif externe.

[Contrat technique](../projet/contrats/CONTRAT-LECTURE-REFERENTIELS.md).
