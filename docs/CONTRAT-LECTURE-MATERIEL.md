# P6.2 — Contrat de lecture du matériel et des prix catalogue

**Statut : en test, candidat non publié.**
**Périmètre : SDK PHP générique `electraudio/loxya-php-client`, lecture seule.**

Sources : [routes du serveur Loxya](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Config/routes.php), [modèle `Material`](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Models/Material.php) et [modèle `EventMaterial`](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Models/EventMaterial.php). La route catalogue est présente dans le code public, mais sa forme et les autorisations n'ont pas encore été vérifiées sur l'instance SaaS. Les routes sont internes, non officielles et non versionnées.

## Objectif et limites

- Lire le **prix de location catalogue** d'un article Loxya : `rental_price`.
- Conserver également les champs bruts comme `replacement_price`, sans mise en forme ni calcul.
- Si le matériel est déjà présent dans une fiche `event($id)`, utiliser `$event['materials'][$i]['material']['rental_price']` lorsque ce champ existe : **aucun appel HTTP supplémentaire n'est nécessaire**.
- Pour consulter une fiche indépendamment d'un événement, utiliser la méthode `material(int $id): array`.
- **Ne pas calculer** les montants facturés d'un événement, les remises, la dégressivité, les jours d'utilisation ou les taxes.
- Ne pas ajouter de synchronisation, de stockage, d'écriture, de liste complète du catalogue ni d'interface métier.

## 1. Fiche catalogue individuelle

**Signature :** `material(int $id): array`

**Route :** `GET /api/materials/{id}` avec `id` entier strictement positif, validé avant tout appel réseau.

**Réponse :** objet JSON brut (tableau associatif PHP). Les champs du modèle Loxya pertinents sont :

| Champ | Sens | Particularité |
|---|---|---|
| `id` | Identifiant Loxya du matériel | Entier dans le modèle |
| `reference` | Référence catalogue | Chaîne dans le modèle |
| `name` | Désignation | Chaîne dans le modèle |
| `rental_price` | Prix de location catalogue | Décimal dans le modèle ; son format JSON doit être vérifié sur le SaaS |
| `replacement_price` | Valeur de remplacement catalogue | Décimal facultatif dans le modèle |
| Autres champs | Informations catalogue complémentaires | Préservés sans filtrage ni transformation |

Les champs tarification peuvent être absents ou `null` selon la configuration, les droits et la version de l'instance. **Absent n'est pas équivalent à zéro**. Le SDK ne transforme pas une valeur `null`, ne force pas le prix à `float` et n'infère pas la devise.

Exemple fictif, illustratif et non validé comme forme exhaustive :

```json
{
  "id": 103,
  "reference": "TEST-103",
  "name": "Projecteur fictif",
  "rental_price": "32.50",
  "replacement_price": "890.00"
}
```

La méthode passe par le transport existant, réutilise la session JWT et peut renouveler une **unique fois** un GET après HTTP 401. Les erreurs HTTP 403, 404 et autres sont propagées par `HttpException`, sans texte du corps distant dans le message. Une réponse qui n'est pas un objet JSON est refusée par `ProtocolException`.

## 2. Prix catalogue dans le détail d'un événement

`event(int $id)` existe déjà dans P6.1 et retourne une fiche avec des lignes `materials`. Le code public de Loxya sérialise chaque ligne `EventMaterial` avec un objet `material` provenant de la fiche matériel.

```php
$event = $client->event(42);
foreach ($event['materials'] ?? [] as $line) {
    $material = $line['material'] ?? null;
    if (!is_array($material) || !array_key_exists('rental_price', $material)) {
        continue; // Tarif catalogue non expose.
    }

    $prixCatalogue = $material['rental_price']; // Peut etre null.
}
```

**Ne pas confondre :**

- `materials[*].material.rental_price` : tarif catalogue du matériel (donnée recherchée).
- `materials[*].unit_price` et `unit_price_period` : montants attachés au contexte de l'événement, **hors périmètre** de P6.2.
- `materials[*].material.replacement_price` : valeur de remplacement du catalogue, **pas** un prix de location.

Les lignes d'événements n'exigent aucune modification du SDK : leurs champs supplémentaires sont déjà conservés sans transformation par `event()`. Le prix catalogue reflète la fiche transmise par Loxya au moment de la lecture ; le SDK ne crée pas d'historique tarifaire.

## 3. Validation et sécurité

- Seules des lectures de ressources sont ajoutées ; `POST /api/session` reste limité à l'authentification.
- Les identifiants non positifs sont rejetés avant réseau.
- Les tests automatisés utilisent uniquement des réponses fictives, jamais un compte SaaS.
- Les tests vérifient prix présent, `null` et absent, fidélité de la réponse brute, 401, 403, 404, JSON inattendu et absence d'écritures.
- Les erreurs sont remontées sans divulguer de réponse distante ni de secret.
- Les éventuels droits d'accès spécifiques à la route doivent être vérifiés sur l'instance autorisée en environnement DEV.

## 4. Recette SaaS restant à effectuer

1. Sur un matériel du catalogue dont le prix est connu, confirmer que `material($id)` retourne un `rental_price` identique au catalogue Loxya.
2. Sur un événement utilisant cet article, contrôler la présence et la signification de `materials[*].material.rental_price`.
3. Identifier un article sans tarif et noter si le champ est absent ou `null`, sans assimiler cela à zéro.
4. Vérifier les droits du compte de service et l'absence d'écriture métier.
5. Ne conserver dans un compte-rendu public que les types et résultats anonymisés, jamais de montants, fiches, JWT ou captures complètes.

Après tests simulés, la fonctionnalité demeure **En test** tant que cette recette SaaS n'est pas achevée. Toute éventuelle publication sous `v0.2.0` demande une approbation explicite distincte.
