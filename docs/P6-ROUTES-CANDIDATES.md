# P6 — étude des routes de lecture supplémentaires

**Statut : En projet — aucune méthode PHP ajoutée.**  
**Périmètre : SDK PHP indépendant ; lecture seule ; aucune dépendance à un logiciel consommateur.**  
**Source : [code public Loxya/Loxya](https://github.com/Loxya/Loxya/tree/2ce970474d8bd76e0f9da739c10fb33eb445e587) au commit `2ce9704` (étudié le 12 novembre 2026).**

Ce document est une **étude exploratoire du code public** de l'éditeur. Les routes peuvent différer entre une installation open source et l'offre SaaS. Leur présence dans le code ne vaut **ni validation SaaS, ni autorisation d'accès, ni contrat de stabilité**. Ne jamais les interroger sur une instance réelle sans confirmation du périmètre autorisé.

## 1. Routes candidates — pas encore implémentées

| Domaine | Routes lues dans le code public | Format ou risque à clarifier | Priorité proposée |
|---|---|---|---|
| Événements, liste courte | `GET /api/events` | Le contrôleur public renvoie `count` + `data`, avec une limite de dix éléments et certains filtres ; **pas une pagination générale garantie** | Étudier |
| Événement individuel | `GET /api/events/{id}` | Format détaillé et droits à vérifier selon les versions | P6.1 |
| Liste des bookings | `GET /api/bookings` | Semble proposer une pagination et des filtres (période, parc, archive, recherche) ; pourrait être mieux adaptée à la liste générale des événements | **P6.1 : vérifier d'abord** |
| Booking individuel | `GET /api/bookings/{entity}/{id}` | `entity` est un type défini par l'éditeur, non une chaîne arbitraire ; inventaire et droits à confirmer | Étudier |
| Matériel | `GET /api/materials`, `GET /api/materials/{id}` | Paramètre `paginated` et filtres optionnels, quantités liées à une période ; contrat à confirmer | P6.2 |
| Parcs | `GET /api/parks`, `GET /api/parks/list`, `GET /api/parks/{id}` | Formes paginées ou listes simples potentiellement différentes | P6.3 |

Références publiques de l'éditeur :

- [Définition des routes](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Config/routes.php).
- [Contrôleur événements](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/EventController.php).
- [Contrôleur bookings](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/BookingController.php).
- [Contrôleur matériel](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/MaterialController.php).
- [Contrôleur parcs](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/ParkController.php).

## 2. Décision d'architecture : ne pas copier le modèle des contacts

Les méthodes actuelles `companies()` et `beneficiaries()` valident une réponse `data` + `pagination`. Or le code public des événements utilise, pour `GET /events`, une réponse `count` + `data`, avec un volume limité. La route `/bookings` publique semble mieux adaptée à des résultats paginés et filtrés, mais cela **reste à confirmer sur le SaaS**.

Il serait incorrect de réutiliser directement la fonction privée `getListing()` de `Client` pour ces nouvelles ressources sans preuve. Les méthodes futures devront :

1. Valider localement uniquement les paramètres et le format **observés** pour leur route.
2. Préserver les valeurs JSON sources, sans construire un référentiel, un cache ni une logique métier.
3. Documenter les différences de pagination et de recherche (notamment tableau de termes de recherche, filtre de période, statut d'archivage).
4. Respecter les frontières de permissions, les erreurs et les réponses vides.
5. Ne jamais inventer des opérations d'écriture ni introduire une API interne générique qui contournerait les routes explicitement vérifiées.

## 3. Parcours de validation P6.1 — événements / bookings

**Étape préalable obligatoire :** vérifier auprès de l'éditeur ou dans le cadre de l'autorisation reçue que la lecture de ces nouvelles routes est permise sur l'instance SaaS cible. Le droit confirmé pour les sociétés et bénéficiaires ne doit pas être étendu par simple analogie.

Après confirmation :

- Tester manuellement, avec un compte autorisé et en **lecture seule**, les routes candidates sur l'instance : disponibilité, droits, 401/403/404, schéma de liste et détail.
- Déterminer si `/events` est uniquement une recherche courte et si `/bookings` constitue réellement la bonne liste paginée pour les événements de l'instance.
- Relever les **noms et types de champs, sans valeurs de personnes ni identifiants réels**, et documenter un échantillon synthétique.
- Vérifier les dates, filtres, indicateurs d'archivage et l'identification de la ressource ; ne pas réutiliser sans preuve les formats de recherche des contacts.
- Définir ensuite seulement les méthodes publiques PHP appropriées, probablement dédiées aux événements/bookings, avec signatures et erreurs explicites.
- Ajouter les tests simulés et une PR ciblée : aucune connexion SaaS dans la CI ; tests PHP 8.2/8.4/8.5 et mise à jour de `composer api-check`.
- Mettre à jour `CHANGELOG.md` et le tableau de statut du README, sans déclarer les fonctionnalités validées avant une recette réelle.

**Aucun test de ce document ne crée, modifie, archive ou supprime un événement ou un matériel.**

## 4. Versions et séquencement

Le contenu de `v0.1.0` doit être **figé par tag avant toute fusion d'une nouvelle famille de méthodes**. Les ajouts de ressources constituent une nouvelle évolution de l'interface PHP, à préparer pour une version `0.2.0` selon la politique du projet. Une investigation documentaire peut être conduite en branche sans toucher au tag initial.

Issue de suivi : [#7 — P6 : vérifier les routes avant extension](https://github.com/Pocky0013/loxya-php-client/issues/7).
