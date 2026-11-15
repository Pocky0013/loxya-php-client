# Recette d'intégration P6.1 / P6.2 — événements, matériel et prix catalogue

**Statut : P6.1 et P6.2 validés sur l'instance SaaS Electraudio pour les parcours observés le 14 novembre 2026 ; scénarios limites et autres instances en test.** La recette est en lecture seule ; `bookingsInPeriod()`, `event()` et `material()` sont **publiées depuis `v0.2.0`** (révision de recette SaaS `5a0501b`) et absentes de `v0.1.0`. Le test a été exécuté via un script de l'application consommatrice, pas nécessairement via le diagnostic CLI générique.

## 1. Préparer l'application

- Utiliser une instance Loxya pour laquelle l'accès aux routes internes de lecture est autorisé.
- Vérifier la version du SDK déjà présente dans le `composer.lock` de l'application. Une mise à jour vers un commit `dev-main` doit être **volontaire, testée et versionnée dans le dépôt de l'application**. Ne pas changer les dépendances directement sur le serveur de production.
- Vérifier que la configuration `LOXYA_BASE_URL`, `LOXYA_USERNAME` et `LOXYA_PASSWORD` est déjà chargée par l'application. Ne pas recopier ces valeurs dans un ticket GitHub ni dans des exemples.

## 2. Parcours en lecture seule

1. Sélectionner une période courte contenant au moins un événement connu.
2. Appeler `$client->bookingsInPeriod($startDate, $endDate)` ; confirmer qu'une **liste** d'objets est renvoyée et que les champs `id`, `entity` et `has_materials` sont typés comme attendu.
3. Choisir un événement dont `entity` vaut `event` et l'`id` est un entier positif ; appeler `$client->event($id)`.
4. Vérifier que la fiche contient `materials` sous forme de **liste** ; si ce champ est absent, noter **« réponse non conforme ou droits insuffisants »**, pas **« aucun matériel »**.
5. Pour une fiche contenant du matériel, vérifier le type des champs `id`, `reference`, `name`, `quantity` et `material` ; constater la quantité d'au moins une ligne, sans publier de valeurs privées.
6. Répéter avec une période sans événements ou un événement sans matériel si cela est possible sans modifier les données.
7. Pour une ligne contenant un objet `material`, relever uniquement si `rental_price` est présent, son type (`string`, nombre ou `null`) et si la valeur correspond au prix catalogue connu. Ne pas relever de prix privés ni de réponse brute dans les rapports de test.
8. Avec un ID fictif ou une référence autorisée, tester `material($id)` et vérifier si `rental_price` et `replacement_price` sont retournés. Comparer le prix catalogue de la fiche à celui du matériel imbriqué dans l'événement, sans calculer de total ni exploiter `unit_price`.
9. Tester un article sans tarif si possible ; distinguer **champ absent** et **champ `null`**. Un tarif nul ne doit pas être interprété automatiquement comme zéro.
10. Tester le comportement des permissions et erreurs dans un contexte autorisé, sans retirer des droits existants ni forcer des échecs d'authentification.

Ne pas lancer une série de centaines de lectures sur de larges périodes. Ne pas enregistrer la réponse complète d'un événement dans les logs ou la CI : elle peut comporter contacts, prix, collaborateurs et informations commerciales.

## 3. Critères de réussite

- [x] `bookingsInPeriod()` : 16 bookings récupérés en novembre 2026, 16 événements reconnus.
- [x] `event()` : 8 fiches détaillées récupérées, jusqu'à 42 lignes de matériel.
- [x] `materials` : références, désignations et `quantity` récupérées pour les lignes observées. Les lignes sont restituées sans filtrage des références par le SDK.
- [x] `materials[*].material.rental_price` : présent sur les 42 lignes de l'événement examiné, type `string` décimal.
- [x] `material($id)` : une fiche catalogue réelle lue sur l'instance avec `rental_price` et `replacement_price` ; montants catalogue concordants entre deux lectures.
- [x] `replacement_price` : chaîne décimale ou `null` sur les lignes examinées.
- [x] Pas de conversion, calcul de facturation ou substitution de `unit_price` à `rental_price`.
- [x] Aucune écriture métier effectuée dans le parcours de recette.
- [x] Version exacte du SDK `5a0501b080c97420bfe0cb75c646dfa4dc33a31e` et environnement PHP CLI 8.4.27 consignés ; aucune donnée de connexion exposée dans ce compte-rendu.
- [ ] Événement détaillé avec `materials: []` confirmé en SaaS.
- [ ] `rental_price` absent ou `null` observé en SaaS.
- [ ] `unit_price` réellement différent de `material.rental_price` vérifié.
- [ ] HTTP 401/403/404 réels et restrictions de droits explorés.
- [ ] Autres instances et autres versions de Loxya vérifiées.

Les traces réseau initiales et la **recette SaaS réelle du 14 novembre 2026** confirment les parcours P6.1/P6.2 sur l'instance Electraudio. Les tests simulés de CI couvrent d'autres formats et erreurs sans prouver leur comportement réel. La confrontation des montants avec l'interface Loxya n'a pas été attestée séparément. Cette validation ne constitue pas une certification d'API officielle.

## 4. Publication

La préversion `v0.2.0` a été **publiée le 14 novembre 2026** : [Release GitHub](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0). Une application consommatrice peut désormais choisir `^0.2.0` et actualiser son propre `composer.lock`. Toute modification d'une application reste un chantier distinct du SDK.
