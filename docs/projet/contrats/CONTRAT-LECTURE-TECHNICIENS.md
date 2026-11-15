# Contrat de lecture des techniciens et assignations

**15 novembre 2026 · En test.** Routes et structures confirmées dans le code public Loxya à la révision `2ce970474d8bd76e0f9da739c10fb33eb445e587` ; lecture réelle sur l'instance SaaS à réaliser. Aucun secret ou exemple réel dans ce dépôt.

| Route interne GET | Méthode PHP | Retour et relations |
| --- | --- | --- |
| `/technicians` | `technicians()` | Paginé ; objets techniciens et `roles` |
| `/technicians/{id}` | `technician()` | Fiche avec `roles` |
| `/technicians/{id}/events` | `technicianAssignments()` | Liste de pivots : `id`, `event_id`, `technician_id`, `period`, `role` et `event` |
| `/technicians/with-assignments` | `techniciansWithAssignmentsInPeriod()` | Liste non paginée, chaque technicien contient `events` et `roles` |
| `/events/{id}` | `eventAssignments()` | Fiche événement, relation `technicians` contenant `role`, `period` et `technician` |
| `/roles` | `roles()` | Référentiel des rôles |

Le modèle `EventTechnician` définit son propre `id` (pivot), distinct de l'ID de l'événement. Il sérialise le rôle comme un **objet** et les dates comme `period.start` / `period.end`. La valeur de `role` peut être nulle. Le sens d'imbrication change selon la route (événement ou technicien).

**Période :** la route `with-assignments` sélectionne les assignations à des événements dont la période recoupe la fenêtre demandée. Elle ne filtre pas strictement `assignment.period`. Le contrôleur peut limiter la durée de la recherche et renvoyer **HTTP 416**. Il renvoie aussi les techniciens sans affectation. `/technicians/{id}/events` renvoie toutes les assignations sans filtre temporel.

**Règles SDK :** aucun calcul de fuseau horaire, aucune interprétation de plusieurs assignations, aucun stockage, aucune écriture. Les anomalies de champs imbriqués déclenchent des exceptions plutôt qu'une liste vide pouvant être interprétée comme une suppression.

**Recette SaaS à conduire :** listes avec et sans assignations ; plusieurs pivots sur un événement ; rôle nul et rôle présent ; frontières de dates et chevauchements ; événement supprimé ; droits restreints ; grande période (416) ; comparaisons des IDs avec les écrans Loxya. Aucun test réel effectué lors de cette PR.
