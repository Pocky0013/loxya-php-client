# Matériel et prix catalogue

[Référence PHP](README.md) · [Événements](EVENEMENTS.md)

**Validé en lecture sur une instance SaaS (v0.2.0).** Autres environnements en test.

## `material(int $id): array`

Lit une fiche individuelle du catalogue.

| Paramètre | Type | Description |
| --- | --- | --- |
| `id` | `int` | ID positif de l'article |

```php
$material = $client->material(103);
$rentalPrice = $material['rental_price'] ?? null;
```

**Retour attendu :** fiche PHP associative sans transformation des données Loxya.

```json
{
  "id": 103,
  "reference": "TEST-103",
  "name": "Projecteur fictif",
  "rental_price": "32.50",
  "replacement_price": "890.00"
}
```

*Exemple fictif et partiel.* `rental_price` a été observé comme **chaîne décimale**. Un prix peut également être absent ou `null` selon l'instance ; ne jamais l'assimiler automatiquement à zéro.

**HTTP interne :** `GET /api/materials/{id}`. La liste complète du catalogue n'est pas implémentée.

## Prix dans un événement

La fiche d'événement peut déjà contenir le prix catalogue : inutile alors de relire chaque article.

```php
$event = $client->event(42);
foreach ($event['materials'] ?? [] as $line) {
    $catalogue = $line['material']['rental_price'] ?? null;
    $prixEvenement = $line['unit_price'] ?? null;
}
```

| Champ | Sens |
| --- | --- |
| `material.rental_price` | Tarif location du catalogue |
| `material.replacement_price` | Valeur de remplacement |
| `unit_price` | Montant lié à la ligne événement, **pas** forcément le tarif catalogue |

Le SDK ne calcule ni remise, TVA, dégressivité ni total. [Exceptions](ERREURS.md) · [Contrat technique](../projet/contrats/CONTRAT-LECTURE-MATERIEL.md).
