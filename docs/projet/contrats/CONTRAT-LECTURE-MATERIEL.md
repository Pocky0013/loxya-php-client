# P6.2 — Contrat de lecture du matériel et des prix catalogue

**Statut : validé en lecture sur l'instance SaaS Electraudio le 14 novembre 2026 ; autres cas et environnements en test.**
**Périmètre : SDK PHP générique `electraudio/loxya-php-client`, lecture seule.**

Sources : [routes du serveur Loxya](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Config/routes.php), [modèle `Material`](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Models/Material.php) et [modèle `EventMaterial`](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Models/EventMaterial.php). La route catalogue figure dans le code public et a été exécutée avec succès sur l'instance SaaS Electraudio avec un compte de service autorisé (révision SDK `5a0501b`). Les routes sont internes, non officielles et non versionnées.

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
| `rental_price` | Prix de location catalogue | Décimal dans le modèle ; **chaîne décimale (`string`) observée** sur la fiche réelle testée |
| `replacement_price` | Valeur de remplacement catalogue | **Chaîne décimale (`string`) observée** sur la fiche réelle testée ; **`null` observé** sur d'autres lignes d'événement |
| Autres champs | Informations catalogue complémentaires | Préservés sans filtrage ni transformation |

Les champs tarifaires peuvent être absents ou `null` selon la configuration, les droits et la version de l'instance. Dans la recette du 14 novembre 2026, `rental_price` était **présent et de type `string`** sur les 42 lignes examinées ; un `rental_price` absent ou `null` **n'a pas été observé**. Des `replacement_price: null` ont été observés. **Absent n'est pas équivalent à zéro**. Le SDK ne transforme pas une valeur `null`, ne force pas le prix à `float` et n'infère pas la devise.

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
- L'accès de lecture à la route a été confirmé avec le compte de service Electraudio depuis DEV ; les droits restrictifs et variantes d'instance restent à vérifier.

## 4. Résultats de la recette SaaS du 14 novembre 2026

Essais effectués en SSH depuis le Portail Electraudio DEV (PHP CLI 8.4.27) avec la révision SDK `5a0501b080c97420bfe0cb75c646dfa4dc33a31e`. Diagnostic via un script de l'application consommatrice et sans écriture de ressource Loxya.

- [x] `event($id)` : prix `materials[*].material.rental_price` présent sur **42 lignes**, valeur de type `string` au format décimal.
- [x] `materials[*].material.replacement_price` : `string` pour certaines lignes, **`null`** pour d'autres.
- [x] `materials[*].unit_price` : disponible séparément ; sur l'échantillon observé, ses montants correspondaient aux prix catalogue, sans prouver leur équivalence systématique.
- [x] `material(int $id)` : fiche catalogue réelle récupérée, avec ID, référence, désignation, `rental_price` et `replacement_price`.
- [x] Concordance entre les champs de prix catalogue de la fiche directe et du même matériel imbriqué dans un événement.
- [x] Route de lecture accessible au compte de service autorisé, sans aucune écriture métier.
- [x] Tests simulés et contrat public PHP validés par la CI SDK [#43623390003](https://github.com/Pocky0013/loxya-php-client/actions/runs/43623390003) sur PHP 8.2, 8.4 et 8.5.

**Points ouverts :**

- [ ] `rental_price` absent ou `null` sur un article réel : non rencontré dans l'échantillon.
- [ ] Différence réelle entre `unit_price` de l'événement et `material.rental_price` : non testée.
- [ ] Confrontation indépendante des montants avec l'interface graphique native de Loxya : non attestée.
- [ ] Erreurs réelles 401/403/404 et droits restrictifs : tests simulés uniquement.
- [ ] Autres instances SaaS ou versions de Loxya : non vérifiées.

**Conclusion : P6.2 est validé sur l'instance SaaS Electraudio pour les scénarios observés**, sans garantie de stabilité sur d'autres installations. Les réponses restent brutes : les chaînes décimales ne sont pas converties en `float` et les règles de facturation de l'événement ne sont pas reproduites. La fonction fait partie de la préversion [v0.2.0 publiée](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0) ; les prochaines publications utiliseront un processus de versionnement distinct.
