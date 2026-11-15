# Installer et utiliser le SDK

[Accueil](../../README.md) · [Référence PHP](../api/README.md)

## Installation

Pré-requis : **PHP 8.2+**, `curl`, `json` et **Composer 2**. Depuis l'application PHP consommatrice :

```bash
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:^0.2.0
```

La préversion `v0.2.0` comprend `bookingsInPeriod()`, `event()` et `material()`. Conserver le `composer.lock` de l'application ; réserver `dev-main` aux tests volontaires de développements non publiés.

## Configuration

Prévoir un compte Loxya disposant des droits requis. Fournir **dans l'application**, sans committer d'identifiants :

```dotenv
LOXYA_BASE_URL=https://votre-instance.loxya.app
LOXYA_USERNAME=utilisateur_integration
LOXYA_PASSWORD=mot_de_passe
```

Le SDK utilise les variables déjà chargées (environnement du serveur, `$_ENV`, `$_SERVER`). Il ne lit pas lui-même de fichier `.env`. Si nécessaire, l'application peut installer `vlucas/phpdotenv` et exécuter `Dotenv\Dotenv::createImmutable(__DIR__)->load()` avant de créer le client.

## Première lecture

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

$client = Factory::fromEnvironment();
$session = $client->session();
$companies = $client->companies(page: 1, limit: 20);
```

**Retour attendu :** `session()` produit un tableau PHP sans jeton JWT. `companies()` produit `['data' => [...], 'pagination' => [...]]`. Les champs des fiches et les sous-clés de pagination ne sont pas tous garantis.

La configuration peut également être fournie explicitement à `Factory::fromArray(['base_url' => ..., 'username' => ..., 'password' => ...])`.

## Principes d'utilisation

- Le transport est HTTPS, le jeton est gardé en mémoire et le SDK n'a pas de stockage applicatif.
- Les réponses JSON sont renvoyées sous forme de tableaux PHP **sans transformation métier**.
- Un GET peut être rejoué **une seule fois** après HTTP 401. Aucun POST/PUT n'est relancé automatiquement.
- **Écritures en test** : après une erreur, contrôler l'état distant avant tout nouvel essai.

Voir [les exceptions](../api/ERREURS.md), [l'exemple événements / matériel](SCENARIOS.md) et la [mémoire technique](../projet/README.md).
