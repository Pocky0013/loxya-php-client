# Checklist de publication — candidat v0.1.0

**Statut : préparation uniquement.** Les commandes de publication ci-dessous ne sont pas exécutées lors de la préparation P5.

## 1. Revue avant publication

- [ ] PR P5 fusionnée et commit de `main` identifié.
- [ ] README, `CHANGELOG.md`, contrats de lecture/écriture, statut des fonctionnalités et politique de versionnement à jour.
- [ ] Signature publique des méthodes, noms des paramètres et exceptions examinés pour les intégrateurs.
- [ ] Composer : `electraudio/loxya-php-client`, namespace `Electraudio\Loxya\`, PHP `^8.2`, extensions `curl` / `json`.
- [ ] Licence PolyForm Internal Use 1.0.0, `composer.json` à `"license": "proprietary"`, aucune modification implicite des conditions de redistribution.
- [ ] Aucun secret, JWT, fichier `.env`, valeur de contact réelle, résultat privé ou trace sensible dans les fichiers versionnés.
- [ ] Aucune route Loxya non vérifiée présentée comme prise en charge ou officiellement stable.

## 2. Tests sans réseau SaaS

Exécuter dans un clone du commit candidat :

```sh
composer validate --strict --no-check-publish
composer install --no-interaction --prefer-dist --no-progress
composer lint
composer test
composer api-check
php bin/loxya-read-check.php --help
```

- [ ] CI réussie sur PHP 8.2, 8.4 et 8.5.
- [ ] Contrôle des signatures publiques réussi et aucune dépendance au SaaS dans les tests.
- [ ] Les nouveaux scripts et leurs dépendances sont installables depuis le dépôt.
- [ ] Contrôler l'absence de changements non documentés dans les exceptions et la confirmation des écritures.

## 3. Limites et vérification SaaS

- [ ] Lorsque la politique de publication l'exige, diagnostic `php bin/loxya-read-check.php --check` exécuté sur une instance autorisée, avec observations **anonymisées et conservées hors Git public**.
- [ ] Recherche, pagination, champs manquants, relations et réponse 404 examinés selon [RECETTE-SAAS.md](RECETTE-SAAS.md), ou explicitement laissés au statut **En test**.
- [ ] Aucune écriture réelle sans accord distinct, et seulement sur des fiches fictives autorisées.
- [ ] La comparaison stricte des écritures est indiquée comme **à valider en conditions réelles**, sans transformer une incertitude en faux succès.
- [ ] Vérifier les conditions d'usage des routes HTTP internes et les limites de la licence avant toute diffusion élargie.

L'absence de recette SaaS exhaustive ne doit **pas** être présentée comme une validation. Décider explicitement si une version expérimentale avec les limites documentées peut être publiée.

## 4. Publication uniquement après approbation explicite

- [ ] Obtenir l'accord du responsable sur le **commit exact** et sur le nom de tag `v0.1.0`.
- [ ] Vérifier qu'aucun tag `v0.1.0` n'existe.
- [ ] **Avant le tag**, transformer l'en-tête du bloc candidat de `CHANGELOG.md` en `## [0.1.0] - AAAA-MM-JJ`, conserver un `## [Unreleased]` vide, et mettre le statut du README à jour pour indiquer une version publiée.
- [ ] Fusionner le dernier commit documentaire ; vérifier la CI **sur ce commit exact**, puis le confirmer à nouveau comme cible du tag.
- [ ] Créer et pousser le tag Git annoté uniquement après cet accord :

```sh
git fetch --tags origin
git checkout main
git pull --ff-only origin main
git tag -a v0.1.0 -m "Loxya PHP Client v0.1.0"
git push origin v0.1.0
```

- [ ] Vérifier que le tag cible le bon commit, que les artefacts de CI sont valides et que les notes de version signalent les fonctionnalités **En test**.
- [ ] GitHub Release éventuelle, puis Packagist éventuel, **chacune sur décision spécifique**.

Le champ `version` de Composer ne doit pas être modifié : Composer déduit la version du tag.

## 5. Communication aux intégrateurs

Fournir le tag, le SHA, la liste des fonctionnalités utilisables, les prérequis PHP, les limites des routes non officielles, la documentation des exceptions et les liens aux notes de version. Le projet consommateur pourra utiliser `composer require electraudio/loxya-php-client:^0.1.0` avec la déclaration VCS requise et mettre à jour **son propre** `composer.lock`.

Le SDK ne déploie ni ne modifie aucun projet consommateur.
