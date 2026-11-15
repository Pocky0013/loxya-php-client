# Sociétés

[Référence PHP](README.md) · [Bénéficiaires](BENEFICIAIRES.md)

**Lectures disponibles ; écritures en test.** Les réponses restent des tableaux PHP non normalisés.

## `companies(int $page = 1, int $limit = 30, ?string $search = null): array`

| Paramètre | Type | Description |
| --- | --- | --- |
| `page` | `int` | Page ≥ 1, défaut 1 |
| `limit` | `int` | De 1 à 100, défaut 30 |
| `search` | `?string` | Recherche facultative de 2 à 180 caractères |

```php
$listing = $client->companies(page: 1, limit: 20, search: 'Exemple');
```

**Retour attendu :** `data` (liste de sociétés) et `pagination` (métadonnées non retraitées).

```json
{
  "data": [{"id": 42, "legal_name": "Exemple SA", "country": "CH"}],
  "pagination": {"currentPage": 1, "total": {"items": 1, "pages": 1}}
}
```

*Exemple fictif ; les sous-clés de pagination ne sont pas garanties.* **HTTP :** `GET /api/companies`.

## `company(int $id): array`

```php
$company = $client->company(42);
```

**Paramètre :** ID `int` strictement positif. **Retour attendu :** fiche associative, par exemple `['id' => 42, 'legal_name' => 'Exemple SA']` (fictif). **HTTP :** `GET /api/companies/{id}`.

## `createCompany(array $fields): array`

**En test ; écrit sur Loxya.** Champs requis : `legal_name`, `country`. Champs facultatifs autorisés notamment `phone`, `street`, `postal_code`, `locality` et `note`.

```php
// Ne pas lancer sur de vraies données sans autorisation.
$created = $client->createCompany([
    'legal_name' => 'Entreprise de démonstration',
    'country' => 'CH',
]);
```

**Retour attendu si confirmé :** fiche créée avec `id`, relue et vérifiée champ par champ. **HTTP :** `POST /api/companies` puis `GET /api/companies/{id}`.

## `updateCompany(int $id, array $fields): array`

**En test.** ID positif et tableau `fields` non vide de champs autorisés.

```php
$updated = $client->updateCompany(42, ['note' => 'Note fictive']);
```

**Retour attendu si confirmé :** fiche relue après `PUT /api/companies/{id}`. En cas de `WriteNotConfirmedException`, l'écriture peut avoir réussi ; **ne pas réessayer automatiquement**.

[Exceptions](ERREURS.md) · [Contrat de lecture](../projet/contrats/CONTRAT-LECTURE-CONTACTS.md) · [Contrat des écritures](../projet/contrats/CONTRAT-ECRITURES-CONTACTS.md).
