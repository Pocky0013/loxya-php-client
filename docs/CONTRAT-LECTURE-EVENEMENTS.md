# P6.1 — Contrat candidat de lecture des événements et bookings

**Statut : En test, uniquement en branche brouillon. Aucun appel SaaS effectué et aucune nouvelle route déclarée validée.**  
**Paquet :** `electraudio/loxya-php-client`, SDK PHP réutilisable et indépendant.  
**Source exploratoire :** code public [Loxya/Loxya, commit `2ce970474d8bd76e0f9da739c10fb33eb445e587`](https://github.com/Loxya/Loxya/tree/2ce970474d8bd76e0f9da739c10fb33eb445e587) (consulté le 12 novembre 2026).

Les informations ci-dessous sont **des observations de code source public**, pas un contrat officiel de l'éditeur, ni une preuve que les mêmes routes, réponses ou droits sont disponibles sur une instance Loxya SaaS. La branche doit **rester non fusionnée** tant que `v0.1.0` n'est pas figée par un tag et que le périmètre d'accès aux nouvelles routes n'est pas confirmé.

## Méthodes PHP proposées

| Méthode | Route HTTP interne | Contrat actuellement attendu |
|---|---|---|
| `eventSuggestions(?string $search = null): array` | `GET /api/events` | `{count:int, data:list<objet>}`, recherche courte non paginée |
| `event(int $id): array` | `GET /api/events/{id}` | JSON objet, représentation détaillée de l'événement |
| `bookings(int $page = 1, int $limit = 30, ?string $search = null, bool $archived = false): array` | `GET /api/bookings` | `{data:list<objet>, pagination:objet}` ; filtre d'archives explicite |
| `eventBooking(int $id): array` | `GET /api/bookings/event/{id}` | JSON objet, représentation « booking » d'un événement |

**Toutes les méthodes sont en lecture seule.** Le POST `/api/session` sert uniquement à l'authentification JWT déjà présente. Aucune route de création, modification, suppression ou archivage n'est ajoutée.

## Différences importantes avec les contacts

### Recherche courte `/events`

Dans le [contrôleur public des événements](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/EventController.php), `getAll()` sélectionne au plus **10 événements**, filtrés notamment sur la présence de matériel, et renvoie :

```json
{
  "count": 58,
  "data": [
    {"id": 42, "title": "Exemple fictif"}
  ]
}
```

Cet exemple est **synthétique**, non extrait de l'instance SaaS. Le compteur `count` peut être supérieur au nombre de résultats de `data` ; **il ne faut pas utiliser cette route pour garantir une liste exhaustive**.

La méthode candidate `eventSuggestions()` vérifie un entier `count` non négatif, une liste `data` d'objets et conserve les champs JSON supplémentaires. Le champ de recherche est facultatif ; la chaîne Unicode doit contenir entre 2 et 180 caractères si elle n'est pas vide et est encodée avec `http_build_query`. Le code serveur accepte un terme scalaire ou un tableau de termes ; la méthode candidate ne supporte **qu'un terme scalaire** pour éviter de figer un format multi-termes non testé.

### Liste paginée `/bookings`

Le [BookingController public](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Controllers/BookingController.php) utilise une liste paginée lorsque `paginated` n'est pas fourni, avec **30 éléments maximum par page** dans le code étudié. `BaseController::paginate()` retourne `data` et `pagination`. La liste est filtrée sur les événements non archivés par défaut ; `archived=true` demande les événements archivés. L'option `archived=false` de la méthode conserve donc le comportement par défaut du serveur, sans ajouter de filtre au chemin HTTP.

Exemple de forme de réponse **entièrement fictif** :

```json
{
  "data": [{"id": 42, "type": "event", "title": "Exemple fictif"}],
  "pagination": {"perPage": 30, "currentPage": 1, "total": {"items": 58, "pages": 2}}
}
```

Les paramètres `page` et `limit` sont des entiers ; `page >= 1`, `1 <= limit <= 30`. Le texte de recherche est encodé comme pour les contacts. Le paramètre d'archives est envoyé uniquement lorsque `archived=true` pour éviter de forcer un comportement serveur non demandé.

Une page hors plage peut renvoyer une erreur HTTP de type **416** dans la version du contrôleur examinée ; c'est à vérifier sur le SaaS. La bibliothèque préserve les valeurs du JSON brut et ne crée aucun modèle de réservation, ni rapprochement métier.

### Détails

Les routes `/events/{id}` et `/bookings/event/{id}` représentent des vues distinctes. L'identifiant doit être entier et strictement positif ; le SDK ne mélange pas leurs champs et ne déduit aucun autre type de booking. Le segment `event` est fondé sur `Event::TYPE = 'event'` dans le code public examiné, non sur une énumération arbitraire saisie par l'appelant.

## Tests déjà simulés

La CI vérifie sans réseau : la forme des listes, les compteurs différents de la longueur de `data`, la préservation des champs non reconnus, les recherches encodées, les pages vides, les limites et IDs incorrects, les erreurs de protocole, les routes de détail et un unique rejeu GET après 401. Le contrôle `composer api-check` inclut les signatures proposées.

La CI **ne prouve pas** qu'une route est accessible en SaaS et ne contient aucun identifiant réel. Les exceptions HTTP ne contiennent pas de corps de réponse distant. Les accès POST/PUT aux événements et bookings ne sont pas disponibles.

## Conditions avant fusion

- [ ] Le tag `v0.1.0` pointe vers le commit de publication approuvé, **sans les ajouts P6.1**.
- [ ] Le périmètre d'accès aux nouvelles routes HTTP internes sur le SaaS est explicitement confirmé avec l'éditeur ou dans l'autorisation applicable.
- [ ] Une recette **de lecture seulement** sur instance autorisée confirme les méthodes et chemins, les droits, la structure de `count`/`data` et de `pagination`, et les erreurs 401/403/404/416.
- [ ] Les limites réelles et éventuelles différences avec le code public sont reportées dans la documentation, puis les signatures corrigées si nécessaire.
- [ ] Aucun nom, identifiant réel, jeton, adresse ou donnée personnelle n'est conservé dans les tests ou issues.
- [ ] Le changelog, les statuts README, `composer api-check` et la CI PHP 8.2/8.4/8.5 sont vérifiés.
- [ ] Toute fusion future est explicitement validée et prépare une évolution `0.2.0` selon le versionnement du paquet.

Référence chantier : [issue #7](https://github.com/Pocky0013/loxya-php-client/issues/7). L'étude préalable des autres ressources est dans [PR #8](https://github.com/Pocky0013/loxya-php-client/pull/8).
