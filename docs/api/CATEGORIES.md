# Catégories et sous-catégories

[Référence PHP](README.md) · [Matériel](MATERIEL.md)

**En test** : structure confirmée dans le code public Loxya, recette SaaS à réaliser.

## `categories(): array`

Lit les catégories de matériel et leurs sous-catégories, avec leurs libellés.

**HTTP interne :** `GET /api/categories`. Retour : liste JSON racine, non paginée.

```php
$categories = $client->categories();
$byId = array_column($categories, null, 'id');
$material = $client->material(103);
$categoryId = $material['category_id'] ?? null;
$categoryName = is_int($categoryId) ? ($byId[$categoryId]['name'] ?? null) : null;
```

**Retour fictif partiel :**

```json
[
  {
    "id": 3,
    "name": "Éclairage",
    "sub_categories": [
      {"id": 12, "name": "Projecteurs", "category_id": 3}
    ]
  }
]
```

Pour résoudre `sub_category_id` d'un matériel, chercher cet ID parmi `categories[*].sub_categories`. Les lignes de matériel dans `event($id)['materials']` exposent `category_id` et parfois `material.category_id` / `material.sub_category_id`.

Une catégorie peut avoir `sub_categories: []`. Si la relation `sub_categories` est absente ou mal formée, le SDK lève `ProtocolException` au lieu de conclure que la catégorie n'a pas de sous-catégories.

Le SDK n'ajoute aucun nom fictif et ne modifie pas le classement Loxya. Erreurs HTTP et de transport remontées directement.
