# Checklist de publication du SDK — depuis GitHub

**Versions publiées :** [`v0.1.0`](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.1.0) (12.11.2026) et [`v0.2.0`](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0) (14.11.2026). Les tags historiques sont légers ; **les prochains tags seront annotés** par GitHub Actions. Ne pas retoucher les tags publiés.

**Pour une future version :** utiliser le [guide GitHub Actions](PUBLICATION-GITHUB-ACTIONS.md), sans copie locale ni commande Git sur l'ordinateur du mainteneur.

## 1. Préparation dans le SDK

- [ ] Obtenir l'autorisation explicite de préparer la version demandée (par exemple `v0.3.0`).
- [ ] Faire relire et fusionner les PR concernées uniquement vers `main` du **SDK**.
- [ ] Vérifier la compatibilité des signatures publiques et les impacts sur les intégrateurs.
- [ ] Mettre à jour le README et les contrats concernés ; ne mentionner **Validé** que pour des parcours confirmés.
- [ ] Ajouter une entrée datée `## [X.Y.Z] - AAAA-MM-JJ` dans `CHANGELOG.md`, tout en conservant `## [Unreleased]`.
- [ ] Créer `docs/RELEASE-NOTES-vX.Y.Z.md` avec titre, nouveautés, compatibilité, limites, statut de validation SaaS et consignes Composer. Retirer les mentions « notes candidates » de son en-tête.
- [ ] Ne versionner aucun identifiant, donnée client, capture SaaS ou secret.

La politique de versionnement figure dans [VERSIONING.md](VERSIONING.md). Les routes Loxya sont internes et non officielles, indépendamment du numéro de version du paquet.

## 2. Vérification technique obligatoire

- [ ] Vérifier que l'exécution du workflow `Verification du client PHP` est verte pour la PR documentaire/de version.
- [ ] Contrôler les prérequis Composer : `electraudio/loxya-php-client`, PHP `^8.2`, extensions `curl` et `json`, licence PolyForm Internal Use License 1.0.0 (`proprietary` dans Composer).
- [ ] Contrôler que le contrat public et les erreurs HTTP/écritures conservent les garanties prévues.
- [ ] Décrire les recettes SaaS réellement réalisées ou les scénarios laissés **En test**.
- [ ] Confirmer que **le commit exact** à publier a été identifié sur `main`.

Le [workflow de publication](../.github/workflows/publish-release.yml) appelle directement la **même matrice CI** PHP 8.2 / 8.4 / 8.5, avec `composer validate --strict --no-check-publish`, `composer install`, tests PHP, syntaxe, vérification des signatures et tests du contrôle de release. Aucun accès SaaS n'est effectué.

## 3. Contrôle sans publication

Depuis **GitHub → Actions → Publication manuelle du SDK → Run workflow** :

- [ ] Choisir **main**, renseigner la nouvelle version au format `vX.Y.Z`.
- [ ] Choisir le mode **`verifier`** et lancer le workflow.
- [ ] Confirmer que `preparer` et `tests` sont verts.
- [ ] Si une étape échoue, **corriger la PR/les documents avant de recommencer**, sans tag ni Release.

Ce mode n'écrit aucun tag et ne crée aucune Release.

## 4. Publication après autorisation spécifique

- [ ] Confirmer l'accord du mainteneur pour **ce numéro de version** et le commit courant de `main`.
- [ ] Depuis la même page GitHub Actions, choisir **main**, renseigner la version et sélectionner **`publier`**.
- [ ] Laisser l'option **`prerelease`** cochée tant que le SDK reste expérimental.
- [ ] Attendre que `preparer`, `tests` et `publier` soient tous verts.
- [ ] Vérifier dans **Releases** que la GitHub Release existe, n'est pas un brouillon, pointe sur le bon commit et utilise les notes attendues.
- [ ] Vérifier que le tag distant est **annoté** et pointe sur le même commit validé.
- [ ] Si le tag a été créé mais que la Release a échoué, suivre la procédure de reprise du [guide](PUBLICATION-GITHUB-ACTIONS.md). Ne pas recréer ni déplacer un tag publié.

**La publication n'est jamais déclenchée automatiquement par une fusion.** Le workflow est déclenché manuellement, et `publier` doit être choisi volontairement. Packagist et les applications consommatrices sont hors de cette automatisation.

## 5. Après publication

- [ ] Fournir le numéro, l'URL de Release, le SHA et les limites connues aux intégrateurs.
- [ ] Vérifier que le README sur `main` reflète l'état de publication ; si besoin, corriger par une **nouvelle PR documentaire**, sans modifier le commit déjà tagué.
- [ ] Mettre à jour chaque application consommatrice **dans son propre dépôt et après autorisation spécifique**, par exemple avec `^0.3.0` dans Composer et un `composer.lock` révisé.
- [ ] Ne pas confondre publication du SDK, mise à jour de la dépendance d'un portail et déploiement d'application.

Pour retrouver les étapes détaillées et les erreurs possibles, lire [PUBLICATION-GITHUB-ACTIONS.md](PUBLICATION-GITHUB-ACTIONS.md).
