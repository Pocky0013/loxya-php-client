# Contribution et périmètre du Loxya PHP Client

Ce dépôt est un **client PHP indépendant et réutilisable** pour certaines routes HTTP internes de Loxya. Il n'est pas officiel, ne fournit pas une API REST hébergée et ne remplace pas une éventuelle API officielle de l'éditeur.

## Responsabilités du paquet

- Construire les requêtes et gérer les réponses des **routes vérifiées** de Loxya.
- Fournir des méthodes publiques PHP documentées : paramètres, données retournées, exceptions et limitations.
- Gérer l'authentification, le transport HTTPS, la sécurité des secrets et les erreurs de protocole.
- Conserver, sauf contrat explicite contraire, les données brutes des réponses sans imposer de modèle métier tiers.
- Exécuter les tests simulés et la validation syntaxique sans dépendance au SaaS.

## Hors périmètre

- Base de données applicative, cache persistant, référentiel de contacts ou stockage de copies locales.
- Utilisateurs, permissions Web, interfaces, facturation et logique métier d'une application consommatrice.
- Correspondances automatiques entre produits tiers, migrations de données et synchronisations planifiées.
- Serveur REST/proxy public ou toute route supposée existante sans vérification.
- Écriture sur des données réelles, suppression ou import massif sans autorisation spécifique et contrôle préalable.

**Electraudio Sàrl est le mainteneur et le premier utilisateur, pas une dépendance d'exécution du paquet.** Le namespace `Electraudio\Loxya` et le nom Composer restent stables pour préserver les installations existantes.

## Maintien de l'état du projet

Le [tableau de statut du README](README.md#état-du-projet) représente les fonctions effectivement utilisables, celles en test et celles encore en projet. Toute PR ajoutant une route, une méthode, une validation SaaS substantielle ou une première version publiée doit le mettre à jour dans le même changement.

Ne qualifier une fonctionnalité de **Validé** que si elle est implémentée, testée en CI et a fait l'objet d'une vérification réelle contrôlée. Si seules des réponses simulées ont été testées, utiliser **En test**. Ne jamais confondre un résultat de CI avec une garantie de compatibilité de l'API non officielle.

## Avant d'ajouter une méthode

1. Vérifier le comportement de la route concernée sur les éléments disponibles, sans inventer de schéma ni de garantie de stabilité.
2. Documenter méthode HTTP, chemin, paramètres, pagination éventuelle, structure de réponse, droits nécessaires et erreurs observées.
3. Ajouter des tests au transport simulé, avec fixtures entièrement fictives, y compris données manquantes, 401/403/404 et réponses mal formées.
4. Pour les POST/PUT, ne jamais répéter automatiquement une écriture ; relire et qualifier tout résultat ambigu.
5. Préserver l'interface publique existante, ou signaler clairement toute incompatibilité avant fusion.
6. Exécuter `composer lint`, `composer test` et vérifier la CI sur les versions PHP déclarées.
7. Développer sur une branche dédiée, ouvrir une PR et attendre l'accord du mainteneur avant fusion ou publication.

Les identifiants Loxya restent uniquement dans la configuration de l'application appelante. Ne jamais déposer de secrets, de JWT, de données personnelles réelles ou de captures de réponses privées.

## Versions du SDK

La première version envisagée est `v0.1.0`, **non publiée**. Toute PR touchant une signature publique, une exception, une contrainte de plateforme ou la compatibilité doit mettre à jour [CHANGELOG.md](CHANGELOG.md) et le contrôle `composer api-check`. Les règles et le processus sont décrits dans [docs/VERSIONING.md](docs/VERSIONING.md) et [docs/RELEASE-CHECKLIST.md](docs/RELEASE-CHECKLIST.md).

Une CI réussie n'est pas une validation de l'instance SaaS. Toute publication (tag Git, GitHub Release ou Packagist) exige une approbation distincte.

## Recette SaaS contrôlée

Les tests automatisés ne doivent jamais utiliser d'identifiants SaaS. La [procédure générique](docs/RECETTE-SAAS.md) et le diagnostic `php bin/loxya-read-check.php --check` permettent une vérification de lecture sur une instance autorisée, sans exposer les valeurs sources. Les écritures de test exigent une autorisation explicite distincte, sur des fiches fictives.

## Évolution vers une API officielle

Si Loxya publie une API officielle, comparer d'abord les contrats HTTP, l'authentification et les réponses avec les méthodes actuelles. Une migration doit minimiser les ruptures pour les applications consommatrices ; **ne pas ajouter d'abstraction spéculative avant de disposer d'une API vérifiée**.

## Licence

La licence du dépôt est [PolyForm Internal Use License 1.0.0](LICENSE.md). Le code est public et utilisable/modifiable en interne selon cette licence, mais la redistribution et la commercialisation nécessitent une autorisation distincte. Il ne s'agit pas d'une licence open source OSI. Ne pas changer la licence ou le champ Composer `proprietary` sans décision du titulaire des droits.
