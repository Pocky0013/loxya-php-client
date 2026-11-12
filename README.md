# Loxya PHP Client

Client PHP pour consulter et gérer les bénéficiaires et les sociétés de [Loxya](https://loxya.com/) depuis une autre application.

Ce paquet utilise les routes HTTP internes de Loxya. **Il n'est pas officiel** et ces routes peuvent évoluer. Il ne nécessite ni accès SQL ni installation particulière côté Loxya, mais un compte utilisateur disposant des droits adaptés.

## Installation

**Prérequis :** PHP 8.2 ou supérieur, extensions `curl` et `json`, Composer 2.

Le dépôt étant actuellement privé et non publié sur Packagist, ajoutez-le comme source VCS dans le projet qui doit l'utiliser :

```bash
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:dev-main
```

Il faut disposer d'un accès GitHub à ce dépôt pour l'installer. Le code et ses dépendances sont ensuite gérés normalement par Composer (`composer.lock` et `vendor/`). Quand une version stable sera publiée sur Packagist, la configuration VCS ne sera plus nécessaire.

> L'installation Composer s'effectue lors de la préparation ou du déploiement de l'application, **pas à chaque requête HTTP**. Le client ne contacte Loxya que lorsqu'une méthode est appelée.

## Configuration

Créez un compte utilisateur Loxya dédié à l'intégration, avec les droits de lecture et de modification nécessaires pour les bénéficiaires et les sociétés.

Copiez [`config.example.php`](config.example.php) dans un fichier privé de votre application, par exemple `config/loxya.php` :

```php
<?php
return [
    'base_url' => 'https://votre-instance.loxya.app',
    'username' => 'votre_identifiant_loxya',
    'password' => 'votre_mot_de_passe_loxya',
];
```

`base_url` est l'adresse de votre instance, **sans `/api`**. L'identifiant et le mot de passe sont ceux du compte Loxya, pas ceux de GitHub. Le client s'authentifie sur `/api/session`, conserve le JWT en mémoire et l'envoie dans les requêtes suivantes.

**Ne placez jamais ce fichier dans `vendor/` ou dans Git.** Conservez-le hors de la racine publique, avec les droits d'accès limités. Ajoutez notamment `/config/loxya.php` au `.gitignore` de votre application. Vous pouvez également fournir la configuration depuis votre gestionnaire de secrets avec `Factory::fromArray()`.

## Utilisation

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

$loxya = Factory::fromFile(__DIR__ . '/config/loxya.php');

// Lire une liste ou une fiche.
$companies = $loxya->companies(page: 1, limit: 30);
$beneficiaryId = 123; // A remplacer par un ID provenant de votre application.
$beneficiary = $loxya->beneficiary($beneficiaryId);

// Créer une société.
$newCompany = $loxya->createCompany([
    'legal_name' => 'Société exemple',
    'country' => 'CH',
]);

// Modifier une fiche existante.
$loxya->updateCompany((int)$newCompany['id'], ['phone' => '+41211234567']);

// Rattacher ce bénéficiaire si aucune société ne lui était liée.
$loxya->setBeneficiaryCompany($beneficiaryId, (int)$newCompany['id'], null);
```

Les écritures ci-dessus sont des exemples : dans une application réelle, récupérez les identifiants depuis vos propres données et vérifiez les droits de l'utilisateur avant de les exécuter.

### Méthodes disponibles

| Méthode | Utilisation |
|---|---|
| `session()` | Vérifier la session utilisateur Loxya |
| `beneficiaries($page, $limit, $search)` | Lister ou rechercher des bénéficiaires |
| `beneficiary($id)` | Lire un bénéficiaire |
| `createBeneficiary($fields)` | Créer un bénéficiaire |
| `updateBeneficiary($id, $fields)` | Modifier un bénéficiaire |
| `companies($page, $limit, $search)` | Lister ou rechercher des sociétés |
| `company($id)` | Lire une société |
| `createCompany($fields)` | Créer une société |
| `updateCompany($id, $fields)` | Modifier une société |
| `setBeneficiaryCompany($beneficiaryId, $companyId, $expectedCompanyId)` | Associer ou dissocier un bénéficiaire et une société |

Les listes utilisent la pagination native de Loxya : le résultat contient `data` et `pagination`. Pour dissocier une société, passez `null` comme deuxième argument de `setBeneficiaryCompany()`. Le troisième argument indique l'ancien `company_id` attendu afin d'éviter d'écraser silencieusement une relation modifiée ailleurs.

## Comportement et limites

- Le transport utilise HTTPS avec vérification TLS, sans redirection. L'hôte autorisé est déduit de `base_url`.
- Le client renouvelle automatiquement la session une seule fois lorsqu'une lecture renvoie `401`. Il ne répète **jamais** automatiquement une création ou une modification.
- Après une écriture, il relit la fiche concernée. L'application appelante reste responsable de vérifier les champs attendus et de gérer les éventuels conflits.
- Si une écriture échoue ou si sa réponse est perdue, **vérifiez la fiche dans Loxya avant de recommencer** : la modification peut avoir été enregistrée.
- Les permissions, la recherche de doublons et le rapprochement avec d'autres annuaires appartiennent à l'application qui utilise le client.
- Les routes étant internes à Loxya, une mise à jour du SaaS peut nécessiter une adaptation du paquet.

## Développement

```bash
composer install
composer test
composer lint
```

Les tests utilisent un transport HTTP simulé et ne se connectent pas à un serveur Loxya. Le détail des routes utilisées et les points restant à confirmer avec l'éditeur figurent dans [`docs/REVUE-EDITEUR.md`](docs/REVUE-EDITEUR.md).

Ce projet est actuellement privé, sans licence de redistribution. Une publication ouverte et une version stable seront envisagées après validation avec l'équipe Loxya.
