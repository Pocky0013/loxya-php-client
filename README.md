# Loxya PHP Client

Bibliothèque PHP non officielle pour accéder depuis une application PHP aux routes HTTP internes de [Loxya](https://loxya.com/). Elle encapsule l'authentification, le transport HTTPS, les méthodes des ressources prises en charge et le traitement des erreurs. **Les sociétés et les bénéficiaires sont les premières ressources implémentées** ; les parcours de lecture d'événements et de matériel ont également été validés sur une instance SaaS, les autres environnements restant en test. La bibliothèque n'est liée à aucun logiciel consommateur.

Il s'agit d'un **client PHP (SDK)**, pas d'un serveur REST, d'une API publique officielle ni d'un proxy à déployer. Chaque application l'installe avec Composer, fournit sa propre configuration Loxya et traite les réponses selon ses besoins.

Les routes utilisées ne sont **ni publiques ni versionnées officiellement** et peuvent changer sans préavis. L'utilisation requiert un compte Loxya autorisé avec les droits nécessaires ; aucun accès SQL n'est requis. Les évolutions sont limitées aux routes dont le comportement a été vérifié. Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour le périmètre architectural.

## État du projet

*Dernière mise à jour : 14 novembre 2026. **`v0.1.0` et `v0.2.0` sont publiées en préversions expérimentales.** Aucun paquet Packagist ; publication des prochaines versions préparée via GitHub Actions.*

| Fonctionnalité | Statut | Ce qui est disponible |
|---|---|---|
| Authentification et session | **Validé** | Connexion `POST /api/session`, JWT en mémoire, `session()` et gestion du 401 en lecture. |
| Consultation des sociétés | **Validé** | `companies()` et `company()` : liste et fiche par ID. |
| Consultation des bénéficiaires | **Validé** | `beneficiaries()` et `beneficiary()` : liste, fiche par ID et relation `company_id` dans les réponses. |
| Pagination et recherche | **En test** | Paramètres utilisables ; les formats et cas limites doivent encore être vérifiés sur diverses instances SaaS. |
| Diagnostic SaaS en lecture seule | **En test** | `--check` pour les Contacts ; nouveau `--check-p6` (période de 1 à 7 jours) pour un échantillon événements/matériel. Rapports anonymisés, tests simulés ; la nouvelle commande n'a pas encore été exécutée sur le SaaS. |
| Création et modification des fiches | **En test** | `createCompany()`, `updateCompany()`, `createBeneficiary()`, `updateBeneficiary()` : essais fictifs réalisés, validations de bout en bout à compléter. |
| Association bénéficiaire–société | **En test** | `setBeneficiaryCompany()` : association/dissociation, vérification préalable non atomique, relecture après écriture. |
| Événements : liste, détail et matériel affecté (P6.1) | **Validé sur une instance SaaS — autres environnements en test** | `bookingsInPeriod()` et `event()` : sur l'instance Electraudio, 16 bookings et 8 fiches détaillées lus avec succès ; références, désignations et quantités disponibles. Non inclus dans `v0.1.0`. |
| Prix catalogue et fiche matériel (P6.2) | **Validé sur une instance SaaS — autres environnements en test** | Sur 42 lignes d'un événement, `material.rental_price` présent et de type `string` ; `replacement_price` parfois `null`. Lecture réelle réussie avec `material($id)` et tarif concordant avec l'événement. |
| Autres ressources Loxya | **En projet** | Recherche globale d'événements, listes paginées et consultation du catalogue complet, parcs : à étudier séparément. |
| Version `v0.1.0` | **Préversion publiée** | [Release GitHub](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.1.0) sur le commit `efa99bf8` ; contacts, sans méthodes P6.1. |
| Version `v0.2.0` | **Préversion publiée** | [Release GitHub](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0) sur le commit `2b555ebb` ; lectures événements, matériel et prix catalogue. |
| Packagist | **En projet** | Installation depuis GitHub/VCS ; inscription Packagist non décidée. |

**Lecture des statuts :** **Validé sur une instance SaaS** = parcours effectivement exécuté avec succès sur l'instance Electraudio et couvert en CI ; **autres environnements en test** = compatibilité externe et cas limites non attestés ; **En test** = parcours implémenté, recette réelle incomplète ; **En projet** = non implémenté ou non publié. Aucune validation n'atteste la stabilité universelle des routes internes de Loxya.

Consultez le [contrat de lecture](docs/CONTRAT-LECTURE-CONTACTS.md), le [contrat des écritures](docs/CONTRAT-ECRITURES-CONTACTS.md) et la [recette SaaS contrôlée](docs/RECETTE-SAAS.md). Les versions et les évolutions sont suivies dans le [changelog](CHANGELOG.md), les [notes de v0.2.0](docs/RELEASE-NOTES-v0.2.0.md), la [politique de versionnement](docs/VERSIONING.md), la [checklist de publication](docs/RELEASE-CHECKLIST.md) et la [publication guidée GitHub Actions](docs/PUBLICATION-GITHUB-ACTIONS.md). Ce tableau est à mettre à jour lors de chaque évolution des capacités du paquet.

## Licence

Le code est distribué sous la [PolyForm Internal Use License 1.0.0](LICENSE.md). Les particuliers et les entreprises peuvent l'utiliser et le modifier pour leurs propres besoins internes, y compris dans un cadre professionnel. La redistribution du connecteur ou de ses versions modifiées, ainsi que leur revente, nécessitent une autorisation distincte du titulaire des droits.

Cette licence est à **code source disponible**, mais n'est pas une licence open source au sens de l'OSI. Le champ `"license": "proprietary"` de `composer.json` reste utilisé pour la compatibilité avec Composer ; les conditions de licence applicables sont celles du fichier `LICENSE.md`.

Copyright © 2026 Electraudio Sàrl.

## Installation

PHP 8.2+, extensions `curl` et `json`, Composer 2.

Tant que le paquet n'est pas référencé sur Packagist, ajoutez ce dépôt public à votre application comme dépôt VCS :

```bash
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:dev-main
```

Aucune authentification GitHub n'est nécessaire pour télécharger ce dépôt public. Après publication sur Packagist et création d'une version, la déclaration VCS ne sera plus nécessaire.

Composer installe le paquet pendant la préparation de l'application ou son déploiement, pas à chaque ouverture de page.

**Les préversions `v0.1.0` et `v0.2.0` sont publiées.** Pour disposer des méthodes P6.1/P6.2, une application disposant du dépôt VCS peut choisir :

```bash
composer require electraudio/loxya-php-client:^0.2.0
```

Le numéro de version provient des **tags Git** ; ne pas ajouter de champ `version` dans `composer.json`. Chaque application doit conserver son propre `composer.lock` pour figer la révision installée. `dev-main` reste disponible pour les essais volontaires de développements non encore publiés. **`^0.1.0` ne récupère pas automatiquement `0.2.0`** : le changement de contrainte doit être explicitement demandé dans l'application consommatrice.

### Diagnostic P6 en lecture seule (sur `main`, non publié dans `v0.2.0`)

La commande optionnelle inspecte les types de réponses déjà accessibles par le SDK, **sans modifier l'API PHP publique** :

```sh
php bin/loxya-read-check.php --help
php bin/loxya-read-check.php --check          # Diagnostic Contacts inchangé
php bin/loxya-read-check.php --check-p6 2026-11-14 2026-11-16
```

Le diagnostic P6 lit une période explicite de **7 jours civils maximum** via `bookingsInPeriod()`, puis **au plus une** fiche `event()` et **une** fiche `material()` si des identifiants valides sont présents. Il affiche des statuts, des compteurs et des types (`string`, `null`, `absent`...), jamais les noms, identifiants, tarifs, JWT ou réponses JSON complètes. Le diagnostic ne calcule aucun prix. Les identifiants de connexion restent fournis par l'environnement et les tests CI utilisent uniquement un transport simulé. **Une période trop large est refusée avant toute requête HTTP.** Cette extension en PR reste **En test** jusqu'à un essai autorisé sur SaaS et une future publication. Lire la [recette SaaS](docs/RECETTE-SAAS.md).

## Configuration

Créez un compte utilisateur Loxya dédié à l'intégration. Renseignez les variables suivantes dans **le fichier `.env` de votre application**, en dehors de la racine Web :

```dotenv
LOXYA_BASE_URL=https://votre-instance.loxya.app
LOXYA_USERNAME=utilisateur_integration
LOXYA_PASSWORD=votre_mot_de_passe
```

Un [`.env.example`](.env.example) sans secret est fourni. Ne placez jamais le véritable `.env` dans Git ou dans un dossier accessible par HTTP ; limitez ses permissions aux processus qui en ont besoin. Le nom d'utilisateur et le mot de passe correspondent à **Loxya**, pas à GitHub.

La bibliothèque lit les **variables d'environnement déjà chargées**. Elle ne lit pas elle-même un fichier `.env`, afin de ne pas imposer un format ou un chargeur particulier à l'application qui l'utilise.

Si votre application ne charge pas encore ses fichiers `.env`, vous pouvez employer [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv) :

```bash
composer require vlucas/phpdotenv
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

// À appeler une seule fois au démarrage si l'application ne charge pas déjà .env.
Dotenv\Dotenv::createImmutable(__DIR__)->load();
```

Dans un framework qui charge déjà les variables d'environnement, cette étape n'est pas nécessaire. Les variables d'environnement fournies directement par l'hébergeur fonctionnent aussi, sans fichier `.env`.

Si l'application possède déjà son propre chargeur d'environnement (par exemple un fichier `config.env` ou un gestionnaire de secrets), conservez-le : il suffit que les trois variables soient disponibles lors de l'appel à `Factory::fromEnvironment()`.

## Utilisation

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

// Le .env est déjà chargé par l'application.
$loxya = Factory::fromEnvironment();

// Consulter des sociétés ou des bénéficiaires.
$companies = $loxya->companies(page: 1, limit: 30);
$company = $loxya->company(123);
$beneficiary = $loxya->beneficiary(456);

// Créer une société (opération d'écriture explicite).
$newCompany = $loxya->createCompany([
    'legal_name' => 'Société exemple',
    'country' => 'CH',
]);
```

Si votre application conserve ses secrets dans un coffre ou un autre système de configuration, `Factory::fromArray()` accepte également un tableau contenant `base_url`, `username` et `password`. Le paquet ne crée aucun fichier de configuration PHP.

### Méthodes principales

| Méthode | Usage |
|---|---|
| `session()` | Vérifier la connexion |
| `beneficiaries($page, $limit, $search)` | Rechercher des bénéficiaires |
| `beneficiary($id)` | Lire une fiche bénéficiaire |
| `createBeneficiary($fields)` / `updateBeneficiary($id, $fields)` | Créer / modifier un bénéficiaire |
| `companies($page, $limit, $search)` | Rechercher des sociétés |
| `company($id)` | Lire une fiche société |
| `createCompany($fields)` / `updateCompany($id, $fields)` | Créer / modifier une société |
| `setBeneficiaryCompany($beneficiaryId, $companyId, $expectedCompanyId)` | Associer / dissocier un bénéficiaire et une société |
| `bookingsInPeriod($startDate, $endDate)` / `event($id)` | Lire les événements d'une période, leurs fiches et leur matériel réservé (P6.1, disponible dès `v0.2.0`) |
| `material($id)` | Lire une fiche catalogue de matériel par ID, y compris le prix catalogue s'il est fourni (P6.2, disponible dès `v0.2.0`) |

Le **[contrat de lecture des contacts](docs/CONTRAT-LECTURE-CONTACTS.md)** décrit les champs connus, les identifiants propres aux deux types de fiches, la relation `company_id`, les données manquantes et le protocole de recette SaaS en lecture seule. Le **[contrat de confirmation des écritures](docs/CONTRAT-ECRITURES-CONTACTS.md)** précise les garanties et limites des POST/PUT, notamment les résultats incertains.

Les listes renvoient `data` (tableau de fiches) et `pagination` (métadonnées conservées sans transformation). Un format de liste inattendu entraîne une `ProtocolException`. Pour dissocier un bénéficiaire, utilisez `null` comme `$companyId`. Le dernier paramètre représente l'ancien `company_id` attendu et permet de détecter une modification concurrente, sans constituer un verrou transactionnel.

### P6.1 — Lecture des événements et de leur matériel (`v0.2.0`)

Ces méthodes sont **publiées depuis `v0.2.0`**, avec CI validée sur PHP 8.2, 8.4 et 8.5 et recette SaaS de lecture réelle sur l'instance Electraudio. Elles ne font pas partie du tag `v0.1.0`.

| Méthode candidate | Route | Réponse |
|---|---|---|
| `bookingsInPeriod(string $startDate, string $endDate)` | `GET /api/bookings?paginated=0&period[start]=…&period[end]=…` | **Liste JSON** des événements d'une période |
| `event(int $id)` | `GET /api/events/{id}` | **Fiche événement JSON**, dont la liste `materials` avec les quantités réservées |

Ces deux routes ont été observées en capture réseau puis **testées en lecture seule sur l'instance SaaS Electraudio** le 14 novembre 2026 : 16 bookings, 8 fiches détaillées et des listes comprenant de 1 à 42 lignes de matériel. Les réponses sont conservées sans transformation dans le SDK. `materials[*].id` correspond à l'identifiant catalogue, tout comme `materials[*].material.id` lorsqu'il est présent ; les données de la ligne de réservation sont à distinguer de cette fiche. Les cas limites non observés restent en test.

```php
// Disponible depuis v0.2.0 (pas dans v0.1.0).
$bookings = $client->bookingsInPeriod('2027-05-01', '2027-05-31');
foreach ($bookings as $booking) {
    if (($booking['entity'] ?? null) !== 'event') {
        continue;
    }
    $id = $booking['id'] ?? null;
    if (!is_int($id) || $id < 1) {
        continue;
    }
    $event = $client->event($id);
    $materials = $event['materials'] ?? null;
    if (!is_array($materials) || !array_is_list($materials)) {
        throw new \UnexpectedValueException('Materiel de l evenement non disponible.');
    }
    foreach ($materials as $line) {
        $reference = $line['reference'] ?? null;
        $quantity = $line['quantity'] ?? null;
        // Prix catalogue actuel si expose par Loxya, sans calcul de facturation.
        $rentalPrice = $line['material']['rental_price'] ?? null;
        // Integrer ces informations selon les besoins de l'application.
    }
}
```

### P6.2 — Prix catalogue du matériel (`v0.2.0`)

La lecture d'un événement restitue déjà les données brutes de chaque ligne `materials`. Quand Loxya inclut `materials[*].material.rental_price`, c'est le **prix catalogue de location du matériel**, distinct du tarif éventuellement saisi sur l'événement (`unit_price`). Aucune nouvelle requête n'est nécessaire dans ce cas. Les champs `rental_price` et `replacement_price` sont facultatifs : conserver la différence entre absent, `null` et un montant (`string` ou `number` selon la réponse SaaS).

Pour consulter une fiche catalogue indépendamment d'un événement, une méthode de lecture simple est ajoutée :

| Méthode candidate | Route | Réponse |
|---|---|---|
| `material(int $id)` | `GET /api/materials/{id}` | Fiche JSON brute d'un article ; `rental_price` et `replacement_price` lorsqu'ils sont exposés |

```php
$material = $client->material(103);
$prixCatalogue = $material['rental_price'] ?? null; // valeur source ou null

$event = $client->event(42);
foreach ($event['materials'] ?? [] as $line) {
    $prixCatalogue = $line['material']['rental_price'] ?? null;
    // Ne pas utiliser $line['unit_price'] pour le prix catalogue.
}
```

La méthode `material()` renvoie les valeurs de Loxya **sans arrondi, conversion monétaire, calcul de dégressivité, remise ou total d'événement**. **Validée en lecture réelle sur l'instance SaaS Electraudio** le 14 novembre 2026 ; le prix catalogue obtenu correspond à celui de l'article dans l'événement testé. Sur les 42 lignes d'événement examinées, `rental_price` était une chaîne décimale ; certaines valeurs `replacement_price` étaient `null`. La comparaison directe avec l'interface graphique de Loxya et les cas de prix manquant restent à tester. La méthode est publiée depuis **`v0.2.0`** et absente du tag `v0.1.0`.

Les routes de recherche courte, de pagination globale, de liste complète du catalogue, de disponibilités et les variantes des fiches bookings restent à étudier. Lire le [contrat P6.1](docs/CONTRAT-LECTURE-EVENEMENTS.md), le [contrat P6.2](docs/CONTRAT-LECTURE-MATERIEL.md) et la [recette générique P6](docs/RECETTE-INTEGRATEUR-P6.md) avant une mise à jour d'application.

## Fonctionnement et limites

Le client s'authentifie auprès de `POST /api/session`, conserve le JWT uniquement en mémoire et l'envoie dans les requêtes suivantes. Les connexions utilisent HTTPS, avec vérification TLS et sans redirection vers un autre serveur. L'hôte est déduit de l'URL configurée.

La méthode `session()` ne renvoie jamais le champ `token` de la réponse de session. Une lecture GET peut être renouvelée une fois après une erreur HTTP 401. **Les écritures POST et PUT ne sont jamais répétées automatiquement**, car une réponse perdue ne signifie pas nécessairement que l'opération a échoué. Après chaque création ou modification, une relecture confirme **l'ID de la fiche et chaque champ envoyé**, avec une comparaison stricte (types compris). Si un champ diffère, manque ou ne peut être relu, une `WriteNotConfirmedException` (également une `ProtocolException`) est levée : l'écriture a peut-être bien été appliquée. L'application doit vérifier la fiche avant toute nouvelle tentative. Aucune normalisation ou répétition implicite n'est effectuée.

**Séparation des responsabilités :** le paquet échange avec Loxya et conserve les réponses distantes sans les adapter à un modèle métier tiers. L'application utilisatrice reste responsable des autorisations locales, du stockage, du traitement des données personnelles, de la recherche de doublons, des confirmations avant écriture et de toute synchronisation métier. Le paquet ne possède pas de base de données, ne synchronise pas automatiquement les contacts et ne propose aucune suppression.

Pour étendre le connecteur à d'autres ressources, il faut d'abord vérifier les routes et leurs contrats, puis ajouter des méthodes documentées et des tests simulés. Une éventuelle API officielle pourra être prise en charge ultérieurement, sans garantie de compatibilité anticipée.

## Diagnostic en lecture seule

Un diagnostic générique permet de vérifier une instance Loxya autorisée **sans écrire de fiches** et sans afficher de données personnelles. L'environnement du processus doit déjà fournir les trois variables `LOXYA_*`.

```bash
php bin/loxya-read-check.php --help
php bin/loxya-read-check.php --check
```

La commande `--check` effectue la connexion puis des lectures limitées à une page par ressource et à une fiche d'exemple. Le statut des champs affiché concerne seulement cet échantillon. Voir [docs/RECETTE-SAAS.md](docs/RECETTE-SAAS.md).

## Développement

```bash
composer install
composer test
composer lint
composer api-check
```

Les tests utilisent un transport HTTP simulé : ils ne contactent pas Loxya et n'ont pas besoin d'identifiants réels. `composer api-check` vérifie les signatures publiques prévues pour la première version candidate, sans requête réseau. La CI vérifie PHP 8.2, 8.4 et 8.5. Les scénarios couvrent aussi les réponses JSON invalides, les listes vides, les 401 répétés, les erreurs HTTP et les résultats ambigus d'écriture. Les opérations de base ont été validées manuellement sur une instance SaaS le 12 novembre 2026 et les parcours P6.1/P6.2 le 14 novembre 2026 ; toute application consommatrice doit vérifier la compatibilité avec sa propre instance et ses droits. Le diagnostic P6 provient d'un script de l'application consommatrice, distinct de `bin/loxya-read-check.php`.

Une [note technique](docs/Revue-technique-API-interne-Loxya.pdf) résume les routes et le fonctionnement pour l'équipe Loxya. L'utilisation des routes internes a été autorisée pour l'intégration initiale Electraudio, sans garantie de stabilité et sans présumer des conditions d'utilisation applicables aux autres utilisateurs. Une vérification de compatibilité reste nécessaire lors des évolutions de Loxya.
