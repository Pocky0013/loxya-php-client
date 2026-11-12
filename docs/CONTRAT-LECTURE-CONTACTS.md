# Contrat technique de lecture — sociétés et bénéficiaires

**Statut :** contrat de la bibliothèque PHP, vérifié par des tests simulés.  
**Version :** état du client en novembre 2026 ; validation du comportement SaaS encore partielle.  
**Portée :** routes HTTP internes Loxya non officielles et non versionnées. Aucune garantie de stabilité par l'éditeur.

Cette documentation décrit l'interface **PHP générique** du paquet `electraudio/loxya-php-client`. Elle ne prescrit aucun modèle de données, système de stockage ou mécanisme de synchronisation pour les applications consommatrices.

## 1. Méthodes publiques de lecture

| Méthode | Route Loxya | Retour |
|---|---|---|
| `companies(int $page = 1, int $limit = 30, ?string $search = null): array` | `GET /api/companies` | `data` et `pagination` |
| `company(int $id): array` | `GET /api/companies/{id}` | Fiche société brute |
| `beneficiaries(int $page = 1, int $limit = 30, ?string $search = null): array` | `GET /api/beneficiaries` | `data` et `pagination` |
| `beneficiary(int $id): array` | `GET /api/beneficiaries/{id}` | Fiche bénéficiaire brute |

Les méthodes sont accessibles sur `Electraudio\Loxya\Client`, obtenu par `Factory::fromEnvironment()` ou `Factory::fromArray($config)`.

La validation locale exige `page >= 1`, `1 <= limit <= 100`, un `id > 0` et, si elle est renseignée, une recherche de 2 à 180 caractères Unicode. La recherche est encodée dans l'URL, **sans garantie sur les règles de recherche appliquées par Loxya**.

## 2. Données renvoyées

Les méthodes renvoient les **tableaux PHP issus du JSON Loxya sans normalisation des champs**. Les champs supplémentaires restent présents, les champs absents ne sont pas créés et les valeurs `null` ne deviennent pas des chaînes vides.

Une réponse de liste comporte :

```php
[
    'data' => [/* liste de fiches (tableaux associatifs) */],
    'pagination' => [/* metadonnees du serveur */],
]
```

Le client exige que `data` soit une liste de tableaux associatifs et que `pagination` soit un tableau. **Il ne valide pas la structure interne de `pagination`** et ne recalcule pas les totaux. Une liste vide est autorisée.

Exemple **entièrement fictif**, utilisé comme forme illustrative dans les tests :

```json
{
  "data": [
    {"id": 42, "legal_name": "Entreprise exemple", "country": "CH"},
    {"id": 55, "legal_name": "Association fictive", "phone": null}
  ],
  "pagination": {"currentPage": 1, "total": {"items": 2, "pages": 1}}
}
```

Les clés `currentPage`, `total.items` et `total.pages` apparaissent dans les exemples connus, mais leur présence **n'est pas garantie par la validation du SDK**. Le programme appelant doit vérifier les clés utilisées.

Les méthodes de détail renvoient un tableau associatif pour une réponse JSON de type objet non vide. Elles ne garantissent pas que chaque fiche contienne `id`, `legal_name`, `first_name` ou des coordonnées. Une réponse JSON vide ou mal formée provoque une `ProtocolException`.

## 3. Noms de champs connus et limites

Ces noms proviennent **des champs actuellement acceptés en écriture par le client** et des premiers essais. Ils ne constituent pas une description exhaustive ni un schéma de lecture GET certifié par l'éditeur.

| Type | Champs connus |
|---|---|
| Identifiant | `id` |
| Sociétés | `legal_name`, `is_public_entity`, `registration_id`, `vat_number`, `invoice_identifier`, `service_code` |
| Bénéficiaires | `first_name`, `last_name`, `email`, `reference`, `company_id` |
| Coordonnées et remarques | `phone`, `street`, `additional_street`, `postal_code`, `administrative_area`, `locality`, `country`, `note` |

La **présence**, le **type** et la **nullabilité** de chaque champ sont à vérifier sur l'instance Loxya ciblée. Aucun champ comme `created_at`, `updated_at`, `archived`, un e-mail de société ou un objet société imbriqué n'est garanti par ce document.

## 4. Identifiants et relation native

Les ID numériques des sociétés et des bénéficiaires forment **deux espaces distincts** : `companies/42` et `beneficiaries/42` désignent deux ressources différentes.

Lorsqu'une application souhaite conserver une référence externe, une clé sans ambiguïté peut comprendre **l'instance Loxya**, le **type de ressource** (`companies` ou `beneficiaries`) et l'**ID** ; cette composition relève de l'application, pas du SDK. Le client ne génère aucune identité universelle.

Sur un bénéficiaire, `company_id` désigne, lorsqu'il est présent et valide, l'ID d'une société Loxya. Distinguer :

- `company_id` entier strictement positif : lien possible vers `company($id)` (à confirmer au besoin par relecture) ;
- `company_id: null` : relation explicitement absente dans la réponse ;
- clé `company_id` absente ou d'un type inattendu : **état non déterminé**, et non une preuve de dissociation.

Le SDK laisse ces valeurs brutes inchangées. Il ne propose pas de recherche inverse des bénéficiaires d'une société et ne déduit aucun lien avec des systèmes tiers.

```php
$client = \Electraudio\Loxya\Factory::fromEnvironment();
$person = $client->beneficiary(42); // Exemple fictif.
if (array_key_exists('company_id', $person) && is_int($person['company_id']) && $person['company_id'] > 0) {
    $company = $client->company($person['company_id']);
}
```

## 5. Exceptions et gestion des erreurs

| Situation | Résultat |
|---|---|
| Paramètre local invalide | `InvalidArgumentException` avant l'appel HTTP |
| HTTP 401 sur GET | Reconnexion et **un seul** nouvel essai GET |
| Autre erreur HTTP, ou second 401 | `HttpException` exposant statut, méthode et chemin (sans recherche) |
| Réponse JSON/structure de liste invalide | `ProtocolException` |
| Erreur cURL ou de transport | `TransportException` |

Le client n'expose pas le corps des réponses HTTP erronées dans ses exceptions. Les opérations POST/PUT **ne sont jamais rejouées automatiquement**, car une erreur de réseau après envoi rend leur résultat potentiellement ambigu. Les écritures actuellement implémentées sont documentées dans le README ; elles sont distinctes du présent contrat de lecture.

## 6. Validations et recette non destructive

**Automatisé :** les tests du dépôt utilisent un transport simulé et des fixtures synthétiques, indépendants du SaaS ; la CI exécute le lint et les tests sous PHP 8.2, 8.4 et 8.5. Ils vérifient notamment les listes, la conservation des champs inconnus, le traitement des valeurs absentes et `null`, les collisions d'ID entre types et les erreurs de protocole.

**Observé manuellement lors du POC initial :** authentification, lecture et quelques opérations sur des fiches fictives. Ces observations ne démontrent pas la compatibilité avec toutes les versions de Loxya.

**À confirmer sur toute instance autorisée, en lecture seule :**

1. Examiner la structure et les types des réponses de sociétés et de bénéficiaires (liste et détail).
2. Vérifier une page vide, une page hors plage et les valeurs de `pagination` utilisées.
3. Tester la recherche, notamment sur une chaîne accentuée, sans conserver de valeurs personnelles dans les journaux.
4. Examiner un bénéficiaire lié, non lié ou avec coordonnées manquantes ; vérifier les formats de `company_id`.
5. Vérifier la réponse à un ID inexistant (404), sans modifier de droits ni créer de fiches.

Ne publier ni identifiants, ni JWT, ni réponses nominatives dans les issues, tests ou documentations. **Les exemples et fixtures ne sont pas des captures du SaaS.**

Pour des règles de contribution et l'ajout futur de ressources vérifiées, voir [CONTRIBUTING.md](../CONTRIBUTING.md).
