# Publication guidée des versions depuis GitHub Actions

Le SDK est développé directement sur GitHub. **Aucun clone local, terminal ou accès SSH n'est nécessaire pour publier une nouvelle version.** Le dépôt du Portail Electraudio reste indépendant et n'est jamais modifié par ce workflow.

## Statut et principe

- `v0.1.0` : préversion publiée le 12 novembre 2026.
- `v0.2.0` : préversion publiée le 14 novembre 2026, au commit `2b555ebbc8132ec813a401acb8a500bb2ba2da8d` (tag léger créé manuellement depuis GitHub). **Ne pas recréer ni déplacer ce tag.**
- **Pour les versions suivantes** : le workflow [Publication manuelle du SDK](../../../.github/workflows/publish-release.yml) créera un **tag Git annoté** puis une GitHub Release, à partir du commit de `main` validé.

**Une fusion sur `main` ne publie jamais automatiquement une version.** Il faut choisir explicitement `publier` dans GitHub Actions, après les vérifications. Le workflow `ci.yml` est réutilisé comme passage obligatoire : PHP 8.2, 8.4 et 8.5, validation Composer, syntaxe, tests simulés, signatures publiques, contrôle du CLI et test du contrôle de release.

## 1. Préparer une version dans une PR du SDK

Exemple pour une future `v0.3.0` :

1. Finaliser et relire les fonctionnalités, leurs contrats et leurs tests, notamment les limites non vérifiées sur SaaS. Obtenir les autorisations de publication requises.
2. Conserver la section `## [Unreleased]` de `CHANGELOG.md` et créer **avant la publication** une section datée `## [0.3.0] - AAAA-MM-JJ` contenant les changements concernés.
3. Créer `docs/projet/publications/RELEASE-NOTES-v0.3.0.md`. Son titre doit inclure `Loxya PHP Client v0.3.0` et son début ne doit plus mentionner « notes candidates », « en préparation » ou « tag non publié ». Y résumer les ajouts, la compatibilité, les prérequis et les limites.
4. Actualiser le README, les exemples et la documentation concernée. La Release antérieure reste inchangée et chaque tag publié demeure immuable.
5. Faire passer la CI de la PR, la faire relire et fusionner **dans le SDK uniquement**, vers `main`.

Si les fichiers version/changelog sont absents ou mal préparés, le workflow refusera la publication.

## 2. Lancer la vérification sans publier

Depuis le [dépôt SDK sur GitHub](https://github.com/Pocky0013/loxya-php-client) :

1. Ouvrir **Actions** → **Publication manuelle du SDK** → **Run workflow**.
2. Choisir la branche **main** et saisir la nouvelle version, par exemple `v0.3.0`.
3. Choisir le mode **`verifier`** (valeur par défaut) et cliquer **Run workflow**.
4. Vérifier les jobs `preparer` puis `tests` : tous doivent être verts.

Ce mode valide la version, la documentation, le commit courant et **relance la même CI PHP que les PR**. Il ne crée ni tag ni Release. Il ne contacte pas Loxya SaaS.

## 3. Publier la version (autorisation distincte)

Après accord explicite sur la **version** et le **commit de `main`** :

1. Revenir dans **Actions** → **Publication manuelle du SDK** → **Run workflow**, branche **main**.
2. Saisir la même version, choisir **`publier`** et laisser **`prerelease`** coché tant que le SDK reste expérimental.
3. Le workflow vérifie à nouveau les documents et la version, exécute **tous les tests PHP sur le SHA exact** de l'exécution, puis vérifie que `main` n'a pas changé.
4. Il crée et pousse le tag **annoté** `vX.Y.Z` sur ce SHA, et crée la GitHub Release portant le même nom à partir de `docs/projet/publications/RELEASE-NOTES-vX.Y.Z.md`.
5. Vérifier dans **Releases** le tag, le commit, la préversion et les notes publiées.

La publication est **manuelle et explicite**, mais la création du tag et de la Release est automatique après les contrôles. Le job de publication dispose de `contents: write`, pas la CI de validation. Le workflow n'a pas besoin d'identifiants SaaS ni de jeton personnel : il utilise `GITHUB_TOKEN` pour GitHub.

## Sécurités et récupération après un échec

- Déclenchement manuel seulement (`workflow_dispatch`) depuis `main` ; aucune publication sur `push` / fusion / tag.
- Version strictement au format `vMAJEUR.MINEUR.CORRECTIF`, sans suffixe, et fichiers de notes/changelog exigés.
- Commit de `main` revérifié **avant** de créer le tag : relancer le workflow si `main` a changé.
- Un tag existant ne sera **jamais** déplacé. Si une Release existe déjà, la création en double est refusée.
- En cas d'échec **après création du tag mais avant la Release**, le workflow peut être relancé avec la même version **seulement si** le tag existant est annoté, pointe sur le même commit, et aucune Release n'existe encore. Si `main` a changé, arrêter et faire examiner la situation : ne pas forcer un tag.
- Si le push est refusé, vérifier les politiques de dépôt et les permissions GitHub Actions pour `GITHUB_TOKEN`. Ne pas communiquer de jeton secret ni contourner les protections.
- Si le workflow `publish-release.yml` n'apparaît pas dans Actions, vérifier qu'il est **fusionné sur la branche par défaut** et qu'Actions est activé.

**Important :** une CI verte ne garantit pas la compatibilité universelle avec les routes internes non officielles de Loxya. Les recettes réelles restent distinctes et limitées à leurs cas observés.

## Installation chez un intégrateur

Après la publication effective de la version, une application utilisant le dépôt VCS GitHub peut demander explicitement la nouvelle version, par exemple :

```sh
composer require electraudio/loxya-php-client:^0.3.0
```

Cette opération appartient au dépôt consommateur et modifie son `composer.lock` selon sa propre procédure. **La publication du SDK ne met à jour ni le Portail Electraudio DEV/PROD, ni une autre application.** Packagist n'est pas publié par le workflow et relève d'une décision séparée.
