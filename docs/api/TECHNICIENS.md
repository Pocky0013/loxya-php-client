# Techniciens, rôles et assignations

[Référence PHP](README.md) · [Événements](EVENEMENTS.md) · [Erreurs](ERREURS.md)

**En test** : routes HTTP identifiées dans le code public de Loxya et tests simulés. Une recette en lecture sur l'instance SaaS est encore requise.

Les données JSON retournées restent celles de Loxya, sans traitement iCalendar ni modification de dates.

## `technicians(int $page = 1, int $limit = 30, ?string $search = null): array`

Liste paginée des techniciens et de leurs rôles. Recherche facultative (2 à 180 caractères).

```php
$result = $client->technicians(1, 30, 'Alex');
foreach ($result['data'] as $technician) {
    $id = $technician['id'];
    $name = $technician['full_name'];
    foreach ($technician['roles'] as $role) {
        echo $role['name'];
    }
}
```

**HTTP interne :** `GET /api/technicians?page=1&limit=30&search=Alex`.

**Retour :** objet `{data: [...], pagination: {...}}`. La liste de rôles attribuables à un technicien ne représente pas les assignations à des événements.

## `technician(int $id): array`

Lit la fiche d'un technicien et ses rôles via `GET /api/technicians/{id}`.

```php
$technician = $client->technician(9);
$roles = $technician['roles'];
```

## `technicianAssignments(int $id): array`

Lit les assignations du technicien via `GET /api/technicians/{id}/events`, en **liste JSON racine**. Cette route n'accepte pas de filtre de période ; elle peut inclure tout l'historique.

```php
foreach ($client->technicianAssignments(9) as $assignment) {
    $assignmentId = $assignment['id']; // ID de la relation, pas de l'événement.
    $eventId = $assignment['event_id'];
    $start = $assignment['period']['start'];
    $end = $assignment['period']['end'];
    $role = $assignment['role']['name'] ?? null;
    $title = $assignment['event']['title'] ?? null;
}
```

## `techniciansWithAssignmentsInPeriod(string $startDate, string $endDate): array`

Lit les techniciens et leurs assignations sur une période YYYY-MM-DD.

**HTTP interne :** `GET /api/technicians/with-assignments?paginated=0&period[start]=…&period[end]=…`.

```php
$technicians = $client->techniciansWithAssignmentsInPeriod('2027-05-01', '2027-05-31');
foreach ($technicians as $technician) {
    foreach ($technician['events'] as $assignment) {
        $event = $assignment['event'];
        $period = $assignment['period'];
        $role = $assignment['role'];
    }
}
```

**Retour :** liste JSON racine, non paginée. Un technicien sans affectation peut avoir `events: []`. Loxya sélectionne les **événements qui recoupent la période**, pas nécessairement les assignations dont les horaires recoupent exactement la période. Vérifier `assignment.period` pour appliquer un filtre horaire strict.

Loxya limite la durée des recherches et peut répondre **HTTP 416** lorsque la fenêtre est trop longue ; le SDK remonte cette erreur (il ne renvoie pas `[]`). Les fenêtres de recherche chevauchantes peuvent ramener le même engagement plusieurs fois.

## `eventAssignments(int $id): array`

Retourne les assignations depuis `event($id)['technicians']`, en s'appuyant sur la route existante `GET /api/events/{id}`.

```php
foreach ($client->eventAssignments(42) as $assignment) {
    $assignmentId = $assignment['id'];
    $technicianId = $assignment['technician_id'];
    $name = $assignment['technician']['full_name'] ?? null;
    $roleName = $assignment['role']['name'] ?? null;
    $period = $assignment['period'];
}
```

**Retour :** liste JSON. Si le champ `technicians` est absent ou mal formé, une `ProtocolException` est levée au lieu de fabriquer une liste vide.

## `roles(): array`

Liste des rôles d'assignation (`id` et `name`, notamment) via `GET /api/roles`.

```php
foreach ($client->roles() as $role) {
    echo $role['id'].': '.$role['name'];
}
```

## Structure d'assignation

Exemple fictif d'assignation orientée technicien :

```json
{
  "id": 811,
  "event_id": 42,
  "technician_id": 9,
  "period": {
    "start": "2027-05-11 08:00:00",
    "end": "2027-05-11 17:00:00",
    "isFullDays": false
  },
  "role": {"id": 7, "name": "Technicien son"},
  "event": {"id": 42, "title": "Événement fictif"}
}
```

Pour les assignations orientées événement, `event` est remplacé par `technician` ; `role` peut être `null`. **Ne pas confondre `assignment.id` avec `assignment.event_id`.** Les dates sont des chaînes renvoyées sans conversion de fuseau horaire par le SDK.

**Erreurs :** `InvalidArgumentException` avant réseau pour les paramètres invalides, HTTP 401/403/404/416, exceptions de transport et `ProtocolException` pour les réponses imbriquées incomplètes. La fonctionnalité techniciens peut être désactivée dans Loxya, entraînant un 404.

[Contrat technique](../projet/contrats/CONTRAT-LECTURE-TECHNICIENS.md).
