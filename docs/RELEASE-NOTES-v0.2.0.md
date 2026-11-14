# Loxya PHP Client v0.2.0 — notes candidates

**Statut : en préparation. Le tag `v0.2.0` et la GitHub Release ne sont pas publiés.**

Cette future version expérimentale du SDK PHP non officiel ajoute les premières lectures d'événements, du matériel affecté et des fiches catalogue, **sans modifier les signatures du contrat public existant de `v0.1.0`**.

## Nouveautés

- `bookingsInPeriod(string $startDate, string $endDate): array` : liste non paginée des bookings sur une période, via `GET /api/bookings` avec `paginated=0`, `period[start]` et `period[end]`.
- `event(int $id): array` : fiche complète d'un événement via `GET /api/events/{id}`. Le tableau `materials` fournit les lignes de matériel affecté, avec notamment leur référence, `quantity` et, s'il est présent, `material.rental_price` (prix catalogue).
- `material(int $id): array` : lecture de la fiche catalogue brute via `GET /api/materials/{id}` avec notamment `rental_price` et `replacement_price` lorsqu'ils sont exposés. Les prix d'événement et les calculs de facturation sont hors périmètre.
- Validation locale de dates `YYYY-MM-DD` et des identifiants positifs ; listes JSON à la racine autorisées **uniquement** pour la lecture par période.
- Tests simulés sans réseau sur PHP 8.2, 8.4 et 8.5, incluant listes vides, matériel, formats inattendus, 401 et erreurs HTTP.

## Limites et sécurité

Les deux routes de P6.1 ont été observées via des captures réseau d'une instance SaaS autorisée ; la route de fiche matériel P6.2 figure dans le code source public de Loxya et reste à tester sur l'instance SaaS. Elles sont **internes, non officielles, susceptibles d'évoluer et non certifiées sur toutes les instances**. Les données reçues sont transmises brutes au consommateur, sans synchronisation, stockage, suppression ni écriture de ressources.

La lecture d'une période **ne retourne pas** les lignes de matériel. Pour obtenir celles-ci, utiliser `event($id)` pour chaque événement pertinent, en limitant raisonnablement le nombre de requêtes.

Cette version ne contient pas les autres routes candidates de recherche courte, de bookings paginés, de liste complète du catalogue et de parcs : elles restent à l'étude. Les écritures existantes sur les contacts restent **En test** et aucune nouvelle écriture n'est ajoutée.

La licence PolyForm Internal Use License 1.0.0, la contrainte PHP `^8.2` et le nom Composer `electraudio/loxya-php-client` restent inchangés.

## Mise à niveau Composer

La mise à niveau vers `^0.2.0` sera possible **après la publication du tag**, depuis le dépôt VCS GitHub :

```sh
composer require electraudio/loxya-php-client:^0.2.0
```

La contrainte `^0.1.0` ne récupère pas automatiquement une version `0.2.x`. Chaque application consommatrice doit mettre à jour **son** `composer.lock`, tester en développement et utiliser sa propre procédure de déploiement. Aucune application n'est modifiée par la publication du SDK.

Documentation : [contrat événements](CONTRAT-LECTURE-EVENEMENTS.md), [contrat matériel et prix catalogue](CONTRAT-LECTURE-MATERIEL.md), [recette de lecture P6.1/P6.2](RECETTE-INTEGRATEUR-P6.md), [politique de versionnement](VERSIONING.md).
