# Loxya PHP Client (non officiel)

Bibliothèque PHP **autonome** pour communiquer avec les routes HTTP internes de Loxya. Projet expérimental développé pour valider une intégration SaaS ; **il n'est ni une API officielle Loxya ni une extension approuvée par l'éditeur**.

**Périmètre v0.1.0 :** authentification, bénéficiaires, sociétés et association bénéficiaire-société. Ni SQL, ni Bexio, ni portail, ni suppression de fiche. Aucune tâche planifiée et aucune synchronisation automatique de contacts.

## Organisation

```text
loxya-php-client/
├── composer.json               # paquet autonome, autoload PSR-4
├── src/                        # client métier + transport HTTPS + exceptions
├── bin/loxya                   # diagnostic CLI sans portail
├── config.example.php          # exemple public SANS secrets
├── config.local.php            # configuration privée, exclue de Git
├── bootstrap.php               # autoload hors Composer pour tests CLI directs
├── tests/                      # tests HTTP simulés, sans aucune connexion réseau
└── docs/                       # portée, exploitation, transmission développeurs
```

**Prérequis :** PHP >= 8.2, `ext-curl`, `ext-json`. Aucun framework et aucune base de données nécessaires.

## Démarrage sur Infomaniak, sans Composer

Déposer le dossier **en dehors de `sites/` et de toute racine Web**, par exemple `~/loxya-php-client/`.

```bash
cd ~/loxya-php-client
cp config.example.php config.local.php
chmod 600 config.local.php
# Modifier localement base_url, allowed_host, username et password.
php tests/run.php
php bin/loxya check
php bin/loxya companies --search=ElectraudioPoC
php bin/loxya beneficiary 88
```

**Important :** ne jamais transmettre `config.local.php` au développeur Loxya, ni le placer dans le dépôt ou une archive partageable. Utiliser un compte Loxya dédié avec les seuls droits utiles. `allowed_host` doit être **le nom d'hôte précis** présent dans `base_url` : le transport refuse toute redirection et tout changement d'hôte.

Pour utiliser un fichier de configuration indépendant de l'installation :

```bash
export LOXYA_CONFIG="$HOME/private/loxya-config.php"
php bin/loxya check
```

L'outil CLI conserve des commandes de **preuve de concept** (`poc-*`) limitées aux fiches fictives marquées. Il faut toujours `--confirm` pour les exécuter. Voir `php bin/loxya help`. Des POST/PUT touchent la véritable instance; ne les lancer qu'avec autorisation explicite.

## Installation Composer depuis un dépôt GitHub

L'objectif est un dépôt séparé, proposé sous le nom `Pocky0013/loxya-php-client`, **privé initialement**. Il n'a **pas encore été créé ni publié**. Quand le dépôt existera et disposera du tag `0.1.0`, ajouter dans le `composer.json` **du projet utilisateur** :

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/Pocky0013/loxya-php-client" }
  ],
  "require": {
    "electraudio/loxya-php-client": "^0.1"
  }
}
```

Puis `composer update electraudio/loxya-php-client` avec des identifiants GitHub adaptés au dépôt privé, stockés exclusivement dans le gestionnaire de secrets de l'environnement. Plus tard, si le paquet est publié sur Packagist, la déclaration `repositories` ne sera plus nécessaire.

> **Ne pas intégrer au portail avant validation de l'éditeur Loxya, revue de sécurité, tests DEV et approbation de la procédure Composer / déploiement Infomaniak.**

## Utiliser le client depuis n'importe quel PHP

```php
<?php
require __DIR__.'/vendor/autoload.php';

use Electraudio\Loxya\Factory;

// Le secret est fourni par l'application consommatrice, hors du depot Git.
$loxya = Factory::fromFile('/chemin/prive/loxya-config.php');

$session = $loxya->session();
$companies = $loxya->companies(page: 1, limit: 30, search: 'Exemple');
$company = $loxya->company(42);
$beneficiary = $loxya->beneficiary(88);

// Ces ecritures sont explicites : le consommateur fournit ses controles
// d'autorisation, la confirmation de l'utilisateur et l'anti-doublon.
// $new = $loxya->createCompany(['legal_name' => 'Test', 'country' => 'CH']);
// $after = $loxya->updateCompany(42, ['note' => 'Exemple']);
// $after = $loxya->setBeneficiaryCompany(88, 42, null);
```

Interface publique :

| Méthode | Fonction |
|---|---|
| `session()` | Authentifie puis lit la session courante |
| `beneficiaries($page, $limit, $search)` | Liste paginée |
| `beneficiary($id)` | Fiche individuelle |
| `createBeneficiary($fields)` / `updateBeneficiary($id, $fields)` | Création / modification explicites |
| `companies($page, $limit, $search)` | Liste paginée |
| `company($id)` | Fiche individuelle |
| `createCompany($fields)` / `updateCompany($id, $fields)` | Création / modification explicites |
| `setBeneficiaryCompany($beneficiaryId, $newCompanyId, $expectedCompanyId)` | Association ou dissociation contrôlée |

## Garanties et limites

- **Protocole :** HTTPS et validation TLS obligatoires ; serveur fixé par `allowed_host`, sans redirection HTTP. Identifiant et mot de passe uniquement dans le POST HTTPS `/api/session`; JWT transmis en Bearer, jamais retourné par l'API publique du connecteur.
- **Session :** jeton JWT gardé uniquement en mémoire PHP. Une lecture GET renvoyant HTTP 401 provoque une unique reconnexion et un unique rejeu du GET. Aucun rejeu automatique de POST ou PUT, même après erreur ou expiration.
- **Écriture :** réponse suivie d'un GET ciblé; le client ne prétend pas fournir une transaction entre deux systèmes. Pour les mutations génériques, vérifier les champs métier retournés avant de confirmer un succès à l'utilisateur.
- **Conflits :** pour `company_id`, comparaison préalable avec l'ancienne valeur attendue. Ce contrôle **n'est pas atomique** ; Loxya ne fournit pas ici de mécanisme `If-Match` connu.
- **Sécurité applicative :** droits du portail, consentement, journalisation, dédoublonnage et arbitrage des champs relèvent du consommateur. Le client n'accorde jamais de droits supplémentaires à l'utilisateur Loxya.
- **Confidentialité :** exceptions sans contenu des réponses ni texte des recherches. Éviter de journaliser les arguments des méthodes contenant des données clients.
- **Compatibilité :** API interne non documentée comme contrat stable. Les routes, schémas, ACL, modalités d'authentification et comportements peuvent changer. Tests de non-régression à chaque évolution.
- **Absence d'appel SaaS dans CI :** les tests utilisent un `Transport` simulé ; aucun identifiant réel n'est utilisé.

## Exécuter les contrôles

```bash
php tests/run.php
php tests/lint.php
```

Aucun test automatisé n'envoie d'écriture réelle. Les essais précédents de bénéficiaires, sociétés et relations ont été validés manuellement par l'exploitant sur `electraudio.loxya.app` le 12 novembre 2026 ; **la présente refactorisation Composer n'a pas encore été revalidée sur le SaaS**.

## Publication et compatibilité éditeur

Avant de rendre le dépôt public : faire valider par Loxya le principe d'utilisation de ses routes internes, le nom du projet, les contraintes de charge, les ACL, la compatibilité Premium/SaaS et la politique en cas de changement d'API. Définir ensuite une licence pour **notre propre code**, sans recopier le code source Loxya ni présumer de droits sur sa variante Premium.

Documentation de référence : `docs/REVUE-EDITEUR.md` et PDF **`docs/Revue-technique-API-interne-Loxya.pdf`** (deux pages).
