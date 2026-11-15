# Loxya PHP Client v0.2.0

**Statut : préversion publiée le 14 novembre 2026** — [GitHub Release v0.2.0](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0), commit `2b555ebbc8132ec813a401acb8a500bb2ba2da8d`. Validation sur une instance SaaS pour les parcours décrits ci-dessous.

Cette version expérimentale du SDK PHP non officiel ajoute les premières lectures d'événements, du matériel affecté et des fiches catalogue, **sans modifier les signatures du contrat public existant de `v0.1.0`**.

## Nouveautés

- `bookingsInPeriod(string $startDate, string $endDate): array` : liste non paginée des bookings sur une période, via `GET /api/bookings` avec `paginated=0`, `period[start]` et `period[end]`.
- `event(int $id): array` : fiche complète d'un événement via `GET /api/events/{id}`. Le tableau `materials` fournit les lignes de matériel affecté, avec notamment leur référence, `quantity` et, s'il est présent, `material.rental_price` (prix catalogue).
- `material(int $id): array` : lecture de la fiche catalogue brute via `GET /api/materials/{id}` avec notamment `rental_price` et `replacement_price` lorsqu'ils sont exposés. Les prix d'événement et les calculs de facturation sont hors périmètre.
- Validation locale de dates `YYYY-MM-DD` et des identifiants positifs ; listes JSON à la racine autorisées **uniquement** pour la lecture par période.
- Tests simulés sans réseau sur PHP 8.2, 8.4 et 8.5, incluant listes vides, matériel, formats inattendus, 401 et erreurs HTTP.
- **Recette réelle le 14 novembre 2026** depuis une application consommatrice en DEV (PHP CLI 8.4.27, SDK `5a0501b`) : 16 bookings, 8 détails d'événement, matériel et quantités cohérents ; prix `material.rental_price` présent sur 42 lignes, de type `string` ; `replacement_price` tantôt une chaîne décimale, tantôt `null`. La méthode `material($id)` a retourné une fiche réelle concordant avec le prix catalogue du même matériel imbriqué dans l'événement.

## Limites et sécurité

Les routes P6.1 ont été observées en capture réseau puis testées en situation réelle ; la route P6.2 `GET /api/materials/{id}` a également été vérifiée avec succès sur l'instance SaaS Electraudio le 14 novembre 2026. Elles sont **internes, non officielles, susceptibles d'évoluer et non certifiées sur toutes les instances**. Les données reçues sont transmises brutes au consommateur, sans synchronisation, stockage, suppression ni écriture de ressources.

La lecture d'une période **ne retourne pas** les lignes de matériel. Pour obtenir celles-ci, utiliser `event($id)` pour chaque événement pertinent, en limitant raisonnablement le nombre de requêtes.

Ces validations ne couvrent pas le cas réel `materials: []`, un `rental_price` absent ou `null`, la divergence réelle `unit_price` / `rental_price`, les erreurs réelles 401/403/404 ni les autres instances. Aucune comparaison distincte avec l'interface graphique native Loxya n'a été attestée.

Cette version ne contient pas les autres routes candidates de recherche courte, de bookings paginés, de liste complète du catalogue et de parcs : elles restent à l'étude. Les écritures existantes sur les contacts restent **En test** et aucune nouvelle écriture n'est ajoutée.

La licence PolyForm Internal Use License 1.0.0, la contrainte PHP `^8.2` et le nom Composer `electraudio/loxya-php-client` restent inchangés.

## Mise à niveau Composer

La mise à niveau vers `^0.2.0` est **possible depuis la publication du tag**, depuis le dépôt VCS GitHub :

```sh
composer require electraudio/loxya-php-client:^0.2.0
```

La contrainte `^0.1.0` ne récupère pas automatiquement une version `0.2.x`. Chaque application consommatrice doit mettre à jour **son** `composer.lock`, tester en développement et utiliser sa propre procédure de déploiement. Aucune application n'est modifiée par la publication du SDK.

Documentation : [contrat événements](../contrats/CONTRAT-LECTURE-EVENEMENTS.md), [contrat matériel et prix catalogue](../contrats/CONTRAT-LECTURE-MATERIEL.md), [recette de lecture P6.1/P6.2](../recettes/RECETTE-INTEGRATEUR-P6.md), [politique de versionnement](VERSIONING.md).
