# Loxya PHP Client

Bibliothèque PHP non officielle pour accéder depuis une application PHP aux routes HTTP internes de [Loxya](https://loxya.com/). Elle encapsule l'authentification, le transport HTTPS, les méthodes des ressources prises en charge et le traitement des erreurs. **Les sociétés et les bénéficiaires sont les premières ressources implémentées**, mais la bibliothèque n'est liée à aucun logiciel consommateur.

Il s'agit d'un **client PHP (SDK)**, pas d'un serveur REST, d'une API publique officielle ni d'un proxy à déployer. Chaque application l'installe avec Composer, fournit sa propre configuration Loxya et traite les réponses selon ses besoins.

Les routes utilisées ne sont **ni publiques ni versionnées officiellement** et peuvent changer sans préavis. L'utilisation requiert un compte Loxya autorisé avec les droits nécessaires ; aucun accès SQL n'est requis. Les évolutions sont limitées aux routes dont le comportement a été vérifié. Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour le périmètre architectural.

## État du projet

*Dernière mise à jour : 12 novembre 2026. **`v0.1.0` est publiée en préversion expérimentale** ; `v0.2.0` est en préparation. Pas de publication Packagist.*

| Fonctionnalité | Statut | Ce qui est disponible |
|---|---|---|
| Authentification et session | **Validé** | Connexion `POST /api/session`, JWT en mémoire, `session()` et gestion du 401 en lecture. |
| Consultation des sociétés | **Validé** | `companies()` et `company()` : liste et fiche par ID. |
| Consultation des bénéficiaires | **Validé** | `beneficiaries()` et `beneficiary()` : liste, fiche par ID et relation `company_id` dans les réponses. |
| Pagination et recherche | **En test** | Paramètres utilisables ; les formats et cas limites doivent encore être vérifiés sur diverses instances SaaS. |
| Diagnostic SaaS en lecture seule | **En test** | Outil CLI générique disponible ; vérification sur instance réelle à effectuer par chaque intégrateur. |
| Création et modification des fiches | **En test** | `createCompany()`, `updateCompany()`, `createBeneficiary()`, `updateBeneficiary()` : essais fictifs réalisés, validations de bout en bout à compléter. |
| Association bénéficiaire–société | **En test** | `setBeneficiaryCompany()` : association/dissociation, vérification préalable non atomique, relecture après écriture. |
| Événements : liste, détail et matériel affecté (P6.1) | **En test** | `bookingsInPeriod()` et `event()` intégrées à `main` par la PR #9 ; CI simulée et formes observées sur une instance SaaS, **pas encore publiées dans une version taguée**. |
| Autres ressources Loxya | **En projet** | Recherche globale d'événements, listes paginées, catalogue matériel et parcs : à étudier séparément. |
| Version `v0.1.0` | **Préversion publiée** | [Release GitHub](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.1.0) sur le commit `efa99bf8` ; contacts, sans méthodes P6.1. |
| Version `v0.2.0` | **En projet** | Préparation d'une version intégrant les lectures d'événements P6.1 ; aucun tag ni release à ce jour. |
| Packagist | **En projet** | Installation depuis GitHub/VCS ; inscription Packagist non décidée. |

**Lecture des statuts :** **Validé** = fonctionnalités testées en CI avec un transport simulé et observées lors d'un premier POC SaaS ; **En test** = méthodes déjà présentes, mais recette SaaS systématique encore incomplète ; **En projet** = non implémenté ou non publié. Une validation du SDK ne garantit **pas** la stabilité des routes internes de Loxya.

Consultez le [contrat de lecture](docs/CONTRAT-LECTURE-CONTACTS.md), le [contrat des écritures](docs/CONTRAT-ECRITURES-CONTACTS.md) et la [recette SaaS contrôlée](docs/RECETTE-SAAS.md). Les versions et les évolutions sont suivies dans le [changelog](CHANGELOG.md), les [notes de v0.1.0](docs/RELEASE-NOTES-v0.1.0.md), la [politique de versionnement](docs/VERSIONING.md) et la [checklist de publication](docs/RELEASE-CHECKLIST.md). Ce tableau est à mettre à jour lors de chaque évolution des capacités du paquet.

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

**La préversion `v0.1.0` est publiée.** Une application disposant du dépôt VCS peut utiliser cette version figée :

```bash
composer require electraudio/loxya-php-client:^0.1.0
```

Le numéro de version provient des **tags Git** ; ne pas ajouter de champ `version` dans `composer.json`. Chaque application doit conserver son propre `composer.lock` pour figer le commit installé. Pour essayer les méthodes P6.1 **avant** le futur tag `v0.2.0`, une application de développement peut utiliser `dev-main` et verrouiller le SHA dans son `composer.lock`. **`^0.1.0` ne récupère pas automatiquement `0.2.0`** : l'application devra décider explicitement du changement de contrainte après la publication du tag.

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

Le **[contrat de lecture des contacts](docs/CONTRAT-LECTURE-CONTACTS.md)** décrit les champs connus, les identifiants propres aux deux types de fiches, la relation `company_id`, les données manquantes et le protocole de recette SaaS en lecture seule. Le **[contrat de confirmation des écritures](docs/CONTRAT-ECRITURES-CONTACTS.md)** précise les garanties et limites des POST/PUT, notamment les résultats incertains.

Les listes renvoient `data` (tableau de fiches) et `pagination` (métadonnées conservées sans transformation). Un format de liste inattendu entraîne une `ProtocolException`. Pour dissocier un bénéficiaire, utilisez `null` comme `$companyId`. Le dernier paramètre représente l'ancien `company_id` attendu et permet de détecter une modification concurrente, sans constituer un verrou transactionnel.

### P6.1 — Lecture des événements et de leur matériel (candidat non publié)

Ces méthodes sont **fusionnées dans `main` depuis la PR #9**, avec tests PHP 8.2, 8.4 et 8.5 réussis, mais elles ne font **pas** partie du tag `v0.1.0` : leur première version candidate est `v0.2.0`.

| Méthode candidate | Route | Réponse |
|---|---|---|
| `bookingsInPeriod(string $startDate, string $endDate)` | `GET /api/bookings?paginated=0&period[start]=…&period[end]=…` | **Liste JSON** des événements d'une période |
| `event(int $id)` | `GET /api/events/{id}` | **Fiche événement JSON**, dont la liste `materials` avec les quantités réservées |

Ces deux routes ont été observées en **lecture seule** dans une capture réseau d'une instance Loxya SaaS autorisée. Les réponses sont conservées sans transformation dans le SDK.

```php
// Disponible sur main, puis dans v0.2.0 apres publication ; pas dans v0.1.0.
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
        // Integrer ces informations selon les besoins de l'application.
    }
}
```

Les routes de recherche courte, de pagination globale, de disponibilité du catalogue et les variantes des fiches bookings restent à étudier : elles ne sont **pas** ajoutées dans cette première étape. Lire le [contrat P6.1](docs/CONTRAT-LECTURE-EVENEMENTS.md) et la [recette générique P6.1](docs/RECETTE-INTEGRATEUR-P6.md) avant une mise à jour d'application.

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

Les tests utilisent un transport HTTP simulé : ils ne contactent pas Loxya et n'ont pas besoin d'identifiants réels. `composer api-check` vérifie les signatures publiques prévues pour la première version candidate, sans requête réseau. La CI vérifie PHP 8.2, 8.4 et 8.5. Les scénarios couvrent aussi les réponses JSON invalides, les listes vides, les 401 répétés, les erreurs HTTP et les résultats ambigus d'écriture. Les opérations de base ont été validées manuellement sur une instance SaaS le 12 novembre 2026 ; toute application consommatrice doit vérifier la compatibilité avec sa propre instance et ses droits.

Une [note technique](docs/Revue-technique-API-interne-Loxya.pdf) résume les routes et le fonctionnement pour l'équipe Loxya. L'utilisation des routes internes a été autorisée pour l'intégration initiale Electraudio, sans garantie de stabilité et sans présumer des conditions d'utilisation applicables aux autres utilisateurs. Une vérification de compatibilité reste nécessaire lors des évolutions de Loxya.
