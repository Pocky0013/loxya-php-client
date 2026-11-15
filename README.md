# Loxya PHP Client

**Client PHP non officiel** pour accéder à certaines routes internes de [Loxya](https://loxya.com/). Il s'installe dans une application via Composer : **ce n'est pas une API REST publique ni un serveur à déployer**.

> **Attention :** les routes HTTP utilisées sont internes, non officielles et non versionnées. Leur compatibilité peut changer sans préavis. Un compte Loxya autorisé est nécessaire.

## Démarrer

Prérequis : **PHP 8.2+**, extensions `curl` et `json`, Composer 2.

Dans l'application PHP consommatrice :

```bash
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:^0.2.0
```

Variables d'environnement **chargées par l'application** (ne jamais publier de secrets) :

```dotenv
LOXYA_BASE_URL=https://votre-instance.loxya.app
LOXYA_USERNAME=utilisateur_integration
LOXYA_PASSWORD=mot_de_passe
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Electraudio\Loxya\Factory;

$client = Factory::fromEnvironment();
$bookings = $client->bookingsInPeriod('2027-05-01', '2027-05-31');
$event = $client->event(42); // ID fictif.
```

Le SDK ne charge pas de fichier `.env` lui-même. Voir le [guide d'installation](docs/guides/DEMARRAGE.md).

## Fonctionnalités

| Fonction | État | Référence |
| --- | --- | --- |
| Authentification et session | **Validé** | [`session()`](docs/api/SESSION.md) |
| Liste des bookings et détail des événements | **Validé sur une instance SaaS** | [`bookingsInPeriod()`, `event()`](docs/api/EVENEMENTS.md) |
| Fiche et tarif catalogue du matériel | **Validé sur une instance SaaS** | [`material()`](docs/api/MATERIEL.md) |
| Lecture des sociétés | **Disponible** | [`companies()`, `company()`](docs/api/SOCIETES.md) |
| Lecture des bénéficiaires | **Disponible** | [`beneficiaries()`, `beneficiary()`](docs/api/BENEFICIAIRES.md) |
| Création, modification et association de contacts | **En test** | [Méthodes d'écriture](docs/api/README.md#ecritures-sur-les-contacts) |
| Recherche globale d'événements, catalogue complet | **En projet** | Non implémenté |

**Validé sur une instance SaaS** ne garantit pas le comportement sur toutes les installations. Les écritures ont un niveau de validation différent des lectures.

## Accès rapide à la documentation

- **[Référence de toutes les méthodes PHP](docs/api/README.md)** — paramètres, exemple, retour, erreurs.
- [Installation et configuration](docs/guides/DEMARRAGE.md).
- [Exemple : récupérer les événements avec du matériel](docs/guides/SCENARIOS.md).
- [Sommaire de la documentation](docs/README.md).
- [Mémoire technique et suivi du projet](docs/projet/README.md) — contrats, recettes, architecture et publications.

## Version et licence

Préversion actuelle : **[v0.2.0](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0)** (inclut événements et matériel ; `v0.1.0` ne les inclut pas). Pour suivre des travaux non publiés, `dev-main` reste disponible volontairement. Voir le [changelog](CHANGELOG.md).

Licence [PolyForm Internal Use License 1.0.0](LICENSE.md) : code source disponible mais **non open source OSI**. Utilisation et adaptation internes autorisées ; redistribution / commercialisation soumises à autorisation distincte. Copyright © 2026 Electraudio Sàrl.
