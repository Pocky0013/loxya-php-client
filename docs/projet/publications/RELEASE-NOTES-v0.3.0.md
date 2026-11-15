# Loxya PHP Client v0.3.0

## Nouvelles lectures HTTP

Cette préversion expérimentale ajoute des méthodes en lecture seule pour accéder aux ressources liées des techniciens et du matériel : techniciens, rôles, assignations par technicien ou événement, assignations par période, catégories et sous-catégories, barèmes dégressifs et leurs paliers.

Les réponses conservent les identifiants distincts des assignations, des techniciens et des événements, ainsi que les objets de rôle, d'événement, de technicien, de catégorie et les paliers dégressifs renvoyés par Loxya.

## Interface PHP ajoutée

- `technicians()`, `technician()`, `technicianAssignments()`, `techniciansWithAssignmentsInPeriod()`
- `eventAssignments()`, `roles()`, `categories()`, `degressiveRates()`

Aucune signature existante n'est retirée ni modifiée. Prérequis inchangés : PHP 8.2+, `ext-curl`, `ext-json`.

## Compatibilité et limites

- Le SDK utilise des routes internes Loxya **non officielles et non versionnées**.
- Les nouvelles lectures sont **En test** : routes identifiées dans le code source public et couvertes par des simulations de transport, mais pas encore validées sur une instance SaaS avec des données réelles.
- La requête d'assignations par période sélectionne les événements recoupant l'intervalle, pas strictement les horaires des assignations ; Loxya peut refuser les périodes trop longues par HTTP 416.
- Les rôles peuvent être `null`, les techniciens sans affectation peuvent avoir `events: []`. Les identifiants de pivots doivent être conservés.
- La lecture HTTP de barèmes peut inclure un palier virtuel jour 1, absent des lignes physiques SQL.
- En l'absence d'un champ relationnel attendu, le SDK remonte une erreur de protocole au lieu de déduire qu'aucun enregistrement n'existe.
- Aucun accès SQL, appel CalDAV, synchronisation de données ni calcul tarifaire n'est ajouté.

## Installation

Depuis un projet déclarant le dépôt GitHub comme référentiel Composer VCS :

```sh
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:^0.3.0
```

La contrainte `^0.3.0` fonctionne une fois le tag `v0.3.0` effectivement publié. Les applications consommatrices doivent mettre à jour leur propre `composer.lock` séparément.

Pour les détails de chaque méthode, voir [la référence PHP](../../api/README.md). Pour la validation et ses limites, voir [le suivi technique](../ETAT-DU-PROJET.md).
