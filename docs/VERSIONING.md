# Politique de versionnement et de compatibilité

## Objet

`electraudio/loxya-php-client` est un **client PHP indépendant**, dont les versions caractérisent **l'interface du paquet**. Ce n'est ni une API REST hébergée ni une API Loxya officielle. La stabilité des routes HTTP internes du SaaS n'est pas garantie par le versionnement de ce dépôt.

Le code source est public sous **PolyForm Internal Use License 1.0.0**, avec le champ Composer `proprietary`. Aucune modification de licence n'est incluse dans le versionnement.

## Numérotation

Les versions utilisent des **tags Git annotés** `vMAJEUR.MINEUR.CORRECTIF`. Le tag `v0.1.0` est reconnu par Composer comme `0.1.0`. Il ne faut **pas** ajouter une propriété `version` dans `composer.json`.

| Type d'évolution | Avant 1.0 | Exemple |
|---|---|---|
| Correction compatible | Incrément correctif | `0.1.0` → `0.1.1` |
| Nouvelle famille de fonctions ou rupture de contrat | Incrément mineur | `0.1.x` → `0.2.0` |
| Première version identifiée | `0.1.0` | Préversion publiée le 12 novembre 2026 |

Les versions 0.x restent expérimentales, mais **les changements incompatibles sont documentés** et ne sont pas introduits discrètement dans une version corrective. À partir de 1.0.0, appliquer les règles classiques de SemVer (majeur = incompatibilité, mineur = ajout compatible, correctif = correction compatible).

## Contrat public de v0.1.0

- `Factory::fromEnvironment()`, `Factory::fromArray()`.
- `Client` : `session`, `companies`, `company`, `beneficiaries`, `beneficiary`, `createCompany`, `updateCompany`, `createBeneficiary`, `updateBeneficiary`, `setBeneficiaryCompany`.
- `Transport::send()`, `CurlTransport`, `HttpResponse`.
- `LoxyaException`, `HttpException`, `ProtocolException`, `TransportException` et `WriteNotConfirmedException`.

Les méthodes `bookingsInPeriod()` et `event()` ajoutées à `main` dans P6.1 constituent le **périmètre candidat de `v0.2.0`** ; elles ne sont pas disponibles dans le tag `v0.1.0`.

Le contrôle hors réseau `composer api-check` surveille les noms, paramètres (y compris ceux utilisables en arguments nommés), types, valeurs par défaut, types de retour et certaines propriétés des exceptions.

Ce test **ne garantit pas** la stabilité du JSON distant, ni le comportement du SaaS. Les détails de lecture et d'écriture sont décrits dans `CONTRAT-LECTURE-CONTACTS.md` et `CONTRAT-ECRITURES-CONTACTS.md`.

## Processus

1. Travail en branche et PR, avec documentation et changelog à jour.
2. CI verte sur PHP 8.2, 8.4 et 8.5, validation Composer stricte et `composer api-check`.
3. Revue de sécurité et de compatibilité avec les applications consommatrices.
4. Recette SaaS contrôlée sur une instance autorisée lorsque nécessaire ; maintenir les fonctionnalités non confirmées au statut **En test**.
5. **Accord explicite** sur le commit et le tag avant publication ; GitHub Release éventuelle seulement après l'accord.
6. Publication Packagist éventuelle comme **décision séparée**, après vérification des droits et conditions applicables.

Le paquet est installable depuis GitHub via le tag expérimental `v0.1.0` (`^0.1.0` dans Composer) ou via `dev-main` pour un développement explicitement suivi. La version `v0.2.0` restera indisponible tant que son tag n'existera pas. Chaque application doit verrouiller la révision dans son propre `composer.lock` ; une contrainte `^0.1.0` ne monte pas automatiquement vers `0.2.x`. Aucun déploiement d'application ne fait partie de ce processus.
