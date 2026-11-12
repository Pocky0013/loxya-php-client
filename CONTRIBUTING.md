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

## Avant d'ajouter une méthode

1. Vérifier le comportement de la route concernée sur les éléments disponibles, sans inventer de schéma ni de garantie de stabilité.
2. Documenter méthode HTTP, chemin, paramètres, pagination éventuelle, structure de réponse, droits nécessaires et erreurs observées.
3. Ajouter des tests au transport simulé, avec fixtures entièrement fictives, y compris données manquantes, 401/403/404 et réponses mal formées.
4. Pour les POST/PUT, ne jamais répéter automatiquement une écriture ; relire et qualifier tout résultat ambigu.
5. Préserver l'interface publique existante, ou signaler clairement toute incompatibilité avant fusion.
6. Exécuter `composer lint`, `composer test` et vérifier la CI sur les versions PHP déclarées.
7. Développer sur une branche dédiée, ouvrir une PR et attendre l'accord du mainteneur avant fusion ou publication.

Les identifiants Loxya restent uniquement dans la configuration de l'application appelante. Ne jamais déposer de secrets, de JWT, de données personnelles réelles ou de captures de réponses privées.

## Évolution vers une API officielle

Si Loxya publie une API officielle, comparer d'abord les contrats HTTP, l'authentification et les réponses avec les méthodes actuelles. Une migration doit minimiser les ruptures pour les applications consommatrices ; **ne pas ajouter d'abstraction spéculative avant de disposer d'une API vérifiée**.

## Licence

La licence du dépôt est [PolyForm Internal Use License 1.0.0](LICENSE.md). Le code est public et utilisable/modifiable en interne selon cette licence, mais la redistribution et la commercialisation nécessitent une autorisation distincte. Il ne s'agit pas d'une licence open source OSI. Ne pas changer la licence ou le champ Composer `proprietary` sans décision du titulaire des droits.
