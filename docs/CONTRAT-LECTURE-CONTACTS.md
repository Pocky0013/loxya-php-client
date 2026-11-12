# Contrat de lecture des contacts Loxya (P2)

**Statut :** contrat PHP actuel documenté, en attente de recette fonctionnelle complémentaire sur le SaaS.  
**Périmètre :** paquet `electraudio/loxya-php-client`, sociétés et bénéficiaires, lecture seule.  
**Date :** 12 novembre 2026. Les routes HTTP sont internes, non officielles et susceptibles d'évoluer.

Ce document décrit **ce que la bibliothèque fournit réellement à une application consommatrice**, sans imposer de modèle de référentiel, de tables SQL, de logique de rapprochement ou de synchronisation. Il ne constitue pas un contrat de stabilité garanti par l'éditeur.

## 1. Interface PHP publique existante

| Appel | Résultat |
|---|---|
| `Client::companies(int $page = 1, int $limit = 30, ?string $search = null): array` | Liste de sociétés : `data` + `pagination` |
| `Client::company(int $id): array` | Fiche société brute |
| `Client::beneficiaries(int $page = 1, int $limit = 30, ?string $search = null): array` | Liste de bénéficiaires : `data` + `pagination` |
| `Client::beneficiary(int $id): array` | Fiche bénéficiaire brute |

Ces quatre méthodes ne transforment **ni les champs ni leurs valeurs** : elles rendent des tableaux PHP issus du JSON retourné par Loxya. Les champs inconnus restent présents. Aucun champ d'identité centrale, d'origine Bexio, de statut de rapprochement ou de synchronisation n'est ajouté.

Paramètres : `page >= 1`, `1 <= limit <= 100` ; `search` facultatif, entre 2 et 180 caractères Unicode s'il n'est pas vide. La bibliothèque encode la recherche dans l'URL mais ne définit **pas** la sémantique du moteur de recherche Loxya.

## 2. Format des résultats et validation effective

**Listes :**

```php
[
    'data' => [/* liste de tableaux associatifs de contacts */],
    'pagination' => [/* métadonnées du serveur, non transformées */],
]
```

La bibliothèque exige `data` comme **liste** de tableaux associatifs et `pagination` comme **tableau** ; elle accepte une liste vide. Elle ne valide pas les clés internes de `pagination` et ne calcule pas un total ou un nombre de pages. Sur les premières réponses observées dans les tests d'intégration, les clés `currentPage` et `total.items` / `total.pages` ont été utilisées ; elles **ne doivent pas être considérées comme garanties** par le client.

**Fiches individuelles :** un tableau associatif `array<string, mixed>` ; le client vérifie que la réponse HTTP est JSON et que son niveau racine est un objet décodable en tableau associatif non vide. Il ne garantit pas la présence d'un `id` ou des coordonnées d'une fiche lue. Une application qui crée un lien durable doit vérifier ces champs elle-même.

**Exemple entièrement fictif**, représentatif des formes utilisées par les tests, et non un extrait de données du SaaS :

```json
{
  "data": [
    {"id": 42, "legal_name": "Exemple SA", "country": "CH"},
    {"id": 55, "legal_name": "Association fictive", "country": "CH", "phone": null}
  ],
  "pagination": {"currentPage": 1, "total": {"items": 2, "pages": 1}}
}
```

Les réponses brutes peuvent contenir d'autres champs, des champs `null` ou des champs absents. Ne pas confondre **absent**, **présent avec `null`** et **présent avec une chaîne vide**. Le client ne complète pas ces différences.

## 3. Inventaire des champs connus

**Attention :** la liste ci-dessous provient des noms acceptés dans les méthodes d'écriture du client et des premiers essais décrits dans le projet. Ce n'est **pas un schéma des réponses GET certifié par le SaaS**. La présence, le type précis et la possibilité de valeurs nulles de chaque champ restent à confirmer par une recette de lecture réelle, sans exporter de données personnelles.

| Famille | Champs connus |
|---|---|
| Identifiant source | `id` (à vérifier sur chaque fiche, entier strictement positif souhaité pour un lien durable) |
| Société | `legal_name`, `is_public_entity`, `registration_id`, `vat_number`, `invoice_identifier`, `service_code` |
| Bénéficiaire | `first_name`, `last_name`, `email`, `reference`, `company_id` |
| Coordonnées communes possibles | `phone`, `street`, `additional_street`, `postal_code`, `administrative_area`, `locality`, `country`, `note` |

Un `email` de société, un rôle de personne, des dates `created_at` / `updated_at`, un indicateur d'archivage ou un objet de société imbriqué ne sont **pas** des champs garantis par ce contrat. Ne pas les déduire des champs de Bexio ou du référentiel.

Pour présenter ou rapprocher une fiche : considérer chaque valeur comme **non fiable et facultative** avant validation locale. Conserver la valeur source brute si une copie est nécessaire ; les règles d'affichage, de formatage des numéros, d'adresses et de dédoublonnage appartiennent à l'application.

## 4. Identité, espaces d'ID et relations

Les routes `companies` et `beneficiaries` forment **deux espaces d'identifiants différents**. Le nombre `42` dans les deux collections ne désigne pas une même fiche. Pour toute référence durable, utiliser au minimum :

```text
(instance Loxya configurée, type source: companies | beneficiaries, ID source)
```

Si une seule instance Loxya est exploitée, le couple `(type, id)` peut suffire dans le périmètre de cette instance. L'ID seul est insuffisant. Aucune clé synthétique ni clé du référentiel n'est créée par la bibliothèque.

La relation connue est `company_id` **sur la fiche bénéficiaire** :

- entier positif : identifiant dans l'espace `companies` (à valider par lecture ciblée avant d'afficher un lien) ;
- `null` : absence de société liée dans la réponse ;
- champ absent, type invalide ou ambigu : **relation inconnue**, ne pas interpréter comme une dissociation.

Une relation Loxya ne prouve pas une identité commune avec une fiche Bexio. Ne pas utiliser `company_id` comme identifiant de bénéficiaire, ni le numéro d'une fiche Bexio comme ID Loxya. La bibliothèque ne propose pas de liste inverse des bénéficiaires d'une société.

**Exemple de lecture sans écriture :**

```php
$companies = $client->companies(page: 1, limit: 30);
$beneficiary = $client->beneficiary(42);

if (array_key_exists('company_id', $beneficiary)) {
    $companyId = $beneficiary['company_id'];
    // Valider un entier positif ou null avant utilisation.
    // La décision de lier deux identités relève du portail.
}
```

## 5. Pagination et rapprochement côté application

L'application consommatrice doit :

1. préserver la provenance `(instance, type, id)` et vérifier l'ID avant toute liaison persistante ;
2. valider localement les métadonnées `pagination` dont elle dépend, plutôt que de les supposer toujours présentes ;
3. utiliser les méthodes de détail pour relire une fiche ciblée lorsque sa relation est nécessaire ;
4. traiter les coordonnées manquantes et les homonymes comme des situations à examiner, **jamais** comme une correspondance automatique ;
5. décider elle-même des droits, de la copie locale, des propositions de rapprochement, des confirmations et de l'audit.

Ce contrat n'implique **aucun balayage planifié**, aucun import automatique, aucune écriture distante et aucun transfert d'information entre Bexio et Loxya.

## 6. Erreurs et limites de sécurité

| Situation | Comportement client |
|---|---|
| Paramètre invalide (pagination, ID, recherche) | `InvalidArgumentException` avant l'appel distant |
| HTTP 401 sur GET | Nouvelle authentification puis **un seul** rejeu du GET |
| HTTP 401/403/404/429/5xx final | `HttpException` avec statut, méthode et chemin sans paramètres de recherche |
| Réponse non JSON / racine inattendue / liste mal formée | `ProtocolException` |
| Échec de transport HTTPS | `TransportException` |

Les messages d'erreur ne reprennent pas les corps HTTP distants. Ne jamais journaliser les réponses brutes avec données personnelles ou les en-têtes d'authentification. Le client ne garantit aucune stabilité des routes internes et ne rejoue **jamais** automatiquement les écritures POST/PUT.

## 7. État de validation et recette à effectuer

**Vérifié dans le code et la CI :** routes et signatures ci-dessus, conservation de l'intégralité des valeurs JSON de réponse, contrôles des listes, distinctions des espaces d'ID, cas de `company_id` présent/null/absent, erreurs simulées, PHP 8.2/8.4/8.5.

**Confirmé antérieurement sur l'instance Electraudio :** authentification, lecture des listes et des fiches, opérations fictives de POC ; diagnostic DEV en lecture seule ayant affiché 42 sociétés et 86 bénéficiaires. Ces chiffres sont une observation datée, pas une garantie sur la population actuelle.

**Non encore confirmé de manière systématique :** présence et types de chacun des champs sur des fiches variées, structure exacte des métadonnées de pagination aux limites, recherche sur caractères accentués, relation sur fiches incomplètes, droits et formats selon les versions SaaS.

### Protocole de recette manuelle (lecture seule, portail DEV)

- Exécuter le diagnostic existant `php scripts/contacts/loxya-check.php --check` sans enregistrer de données personnelles.
- Consulter une page de chaque type, puis une page vide / hors plage si possible ; vérifier l'absence de changement de format.
- Vérifier recherche et pagination avec quelques termes non sensibles, sans recopier les résultats.
- Contrôler à l'écran un bénéficiaire avec société, un bénéficiaire sans société et des fiches avec coordonnées absentes ; noter **uniquement** présence, types et nullabilité des champs, sans valeurs réelles.
- Pour un cas de fiche inexistante, vérifier le traitement 404 par l'interface, sans créer de donnée.
- Si un accès insuffisant existe déjà, vérifier l'affichage 403 ; **ne pas modifier des droits en production pour provoquer cette erreur**.
- Si un écart de format est observé, ouvrir un correctif ciblé dans la bibliothèque avant d'élargir l'intégration du portail.

Les fixtures sous `tests/fixtures/` sont **synthétiques** et ne prouvent pas à elles seules la forme du JSON réellement renvoyé par le SaaS.
