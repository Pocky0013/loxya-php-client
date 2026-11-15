# Bénéficiaires

[Référence PHP](README.md) · [Sociétés](SOCIETES.md)

**Lectures disponibles ; écritures et association en test.** Les ID de bénéficiaire et de société sont des espaces distincts.

## `beneficiaries(int $page = 1, int $limit = 30, ?string $search = null): array`

| Paramètre | Type | Description |
| --- | --- | --- |
| `page` | `int` | Page ≥ 1, défaut 1 |
| `limit` | `int` | De 1 à 100, défaut 30 |
| `search` | `?string` | Recherche facultative de 2 à 180 caractères |

```php
$listing = $client->beneficiaries(page: 1, limit: 20, search: 'Exemple');
```

**Retour attendu :** `data` (liste de fiches) et `pagination` (métadonnées brutes).

```json
{
  "data": [{"id": 42, "first_name": "Camille",
            "last_name": "Exemple", "company_id": 12}],
  "pagination": {"currentPage": 1}
}
```

*Exemple fictif.* **HTTP :** `GET /api/beneficiaries`.

## `beneficiary(int $id): array`

```php
$person = $client->beneficiary(42);
$companyId = $person['company_id'] ?? null;
```

**Paramètre :** ID entier positif. **Retour attendu :** fiche associative. `company_id` peut être un ID, `null` ou **absent** ; absence ne signifie pas dissociation. **HTTP :** `GET /api/beneficiaries/{id}`.

## `createBeneficiary(array $fields): array`

**En test ; écrit sur Loxya.** Champs obligatoires : `first_name`, `last_name`, `country`. Champs optionnels notamment `email`, `phone`, `company_id`, `street`, `postal_code` et `note`.

```php
$created = $client->createBeneficiary([
    'first_name' => 'Camille',
    'last_name' => 'Exemple',
    'country' => 'CH',
]);
```

**Retour attendu si confirmé :** fiche créée avec `id`, relue après `POST /api/beneficiaries`. Ne pas lancer sur de vraies données sans autorisation.

## `updateBeneficiary(int $id, array $fields): array`

**En test.** ID positif, champs autorisés et tableau non vide.

```php
$updated = $client->updateBeneficiary(42, ['phone' => '+41 00 000 00 00']);
```

**Retour attendu si confirmé :** fiche relue après `PUT /api/beneficiaries/{id}`.

## `setBeneficiaryCompany(int $beneficiaryId, ?int $newCompanyId, ?int $expectedCompanyId): array`

**En test.** Vérifie l'ancienne valeur de `company_id` avant une éventuelle écriture.

| Paramètre | Type | Description |
| --- | --- | --- |
| `beneficiaryId` | `int` | ID du bénéficiaire |
| `newCompanyId` | `?int` | Nouvelle société, ou `null` pour dissocier |
| `expectedCompanyId` | `?int` | Société précédemment lue, ou `null` si non liée |

```php
// Associer seulement si company_id vaut actuellement null.
$linked = $client->setBeneficiaryCompany(42, 12, null);
// Dissocier seulement si company_id vaut encore 12.
$unlinked = $client->setBeneficiaryCompany(42, null, 12);
```

**Retour attendu si confirmé :** fiche relue après contrôle du lien. `LogicException` si la relation a changé. Le contrôle est **non atomique** et ne fournit pas de transaction. Une erreur après écriture ne signifie pas toujours échec.

[Exceptions](ERREURS.md) · [Lecture des contacts](../projet/contrats/CONTRAT-LECTURE-CONTACTS.md) · [Contrat d'écriture](../projet/contrats/CONTRAT-ECRITURES-CONTACTS.md).
