# Loxya PHP Client

Bibliothèque PHP pour consulter et modifier les bénéficiaires et les sociétés de [Loxya](https://loxya.com/) depuis une autre application.

Ce client utilise les routes HTTP internes de Loxya. **Il n'est pas officiel** : ces routes peuvent évoluer sans préavis. Il faut un compte Loxya disposant des droits nécessaires. Aucun accès SQL n'est requis.

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

**Portail Electraudio :** les paramètres peuvent rester dans le `config.env` existant, chargé par le bootstrap du portail. Il suffit d'y ajouter `LOXYA_BASE_URL`, `LOXYA_USERNAME` et `LOXYA_PASSWORD`. Aucun deuxième fichier de configuration n'est nécessaire.

## Utilisation

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

// Le .env est déjà chargé par l'application.
$loxya = Factory::fromEnvironment();

// Consulter des sociétés ou des bénéficiaires.
$companies = $loxya->companies(page: 1, limit: 30);
$company = $loxya->company(42);
$beneficiary = $loxya->beneficiary(88);

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

Les listes renvoient `data` (tableau de fiches) et `pagination` (métadonnées conservées sans transformation). Un format de liste inattendu entraîne une `ProtocolException`. Pour dissocier un bénéficiaire, utilisez `null` comme `$companyId`. Le dernier paramètre représente l'ancien `company_id` attendu et permet de détecter une modification concurrente, sans constituer un verrou transactionnel.

## Fonctionnement et limites

Le client s'authentifie auprès de `POST /api/session`, conserve le JWT uniquement en mémoire et l'envoie dans les requêtes suivantes. Les connexions utilisent HTTPS, avec vérification TLS et sans redirection vers un autre serveur. L'hôte est déduit de l'URL configurée.

La méthode `session()` ne renvoie jamais le champ `token` de la réponse de session. Une lecture GET peut être renouvelée une fois après une erreur HTTP 401. **Les écritures POST et PUT ne sont jamais répétées automatiquement**, car une réponse perdue ne signifie pas nécessairement que l'opération a échoué. Une relecture est faite après chaque création ou modification.

L'application utilisatrice reste responsable de ses permissions, de la recherche de doublons, des confirmations avant écriture et du rapprochement des identités. Le paquet ne synchronise pas automatiquement les contacts et ne gère aucune suppression.

## Développement

```bash
composer install
composer test
composer lint
```

Les tests utilisent un transport HTTP simulé : ils ne contactent pas Loxya et n'ont pas besoin d'identifiants réels. La CI vérifie PHP 8.2, 8.4 et 8.5. Les scénarios couvrent aussi les réponses JSON invalides, les listes vides, les 401 répétés, les erreurs HTTP et les résultats ambigus d'écriture. Les opérations de base ont été validées manuellement sur une instance SaaS le 12 novembre 2026 ; cette version de la bibliothèque reste à éprouver dans son application consommatrice.

Une [note technique](docs/Revue-technique-API-interne-Loxya.pdf) résume les routes et le fonctionnement pour l'équipe Loxya. L'utilisation des routes internes a été confirmée pour le projet Electraudio, sans garantie de stabilité d'une API officielle. Une vérification de compatibilité reste nécessaire lors des évolutions de Loxya.
