# Recette d'intégration P6.1 — lecture des événements et du matériel

**Statut : protocole pour une application consommatrice en environnement de développement.** Pas d'accès en écriture ; les méthodes `bookingsInPeriod()` et `event()` sont disponibles sur le `main` du SDK, mais pas dans `v0.1.0`.

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
7. Tester le comportement des permissions et erreurs dans un contexte autorisé, sans retirer des droits existants ni forcer des échecs d'authentification.

Ne pas lancer une série de centaines de lectures sur de larges périodes. Ne pas enregistrer la réponse complète d'un événement dans les logs ou la CI : elle peut comporter contacts, prix, collaborateurs et informations commerciales.

## 3. Critères de réussite

- [ ] Requête par période réussie avec liste d'événements.
- [ ] Lecture détaillée d'un événement réussie.
- [ ] Matériel réservé accessible via `materials` avec `quantity` cohérente.
- [ ] Aucune écriture métier envoyée.
- [ ] Aucune fuite de jeton ou de données personnelles dans les logs, GitHub ou les captures partagées.
- [ ] Version exacte du SDK et résultat consignés de manière anonymisée.

Les **traces SaaS étudiées** ont déjà confirmé les schémas des deux routes sur une instance. Cette recette concerne la **nouvelle intégration consommatrice** et la détection des écarts de configuration, sans constituer une certification d'API officielle.

## 4. Publication

Après validation et accord explicite, préparer `v0.2.0`, puis mettre à jour l'application avec une contrainte `^0.2.0` et un nouveau `composer.lock`. Toute modification d'une application reste un chantier distinct de la bibliothèque PHP.
