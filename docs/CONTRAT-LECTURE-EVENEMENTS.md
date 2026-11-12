# P6.1 — Contrat de lecture des événements et du matériel affecté

**Statut : candidat non publié (PR #9), en test.**  
**Périmètre : SDK PHP générique `electraudio/loxya-php-client`, lecture seule.**  
**Sources :** formes observées dans deux captures réseau HAR d'une instance SaaS autorisée (analysées localement, non déposées dans Git) et [code public de Loxya](https://github.com/Loxya/Loxya/blob/2ce970474d8bd76e0f9da739c10fb33eb445e587/server/src/App/Config/routes.php).

Les routes utilisées sont **internes et non officielles**. Le fait qu'elles existent sur une instance ne garantit ni leur présence sur d'autres instances ni la pérennité de leurs réponses. Le périmètre d'utilisation doit rester conforme aux autorisations de l'instance SaaS.

## Parcours couvert

1. **Lister les événements d'une période** avec `bookingsInPeriod($startDate, $endDate)`.
2. **Lire la fiche d'un événement identifié** avec `event($id)`.
3. **Lire les lignes de matériel affecté** dans `$event['materials']`, notamment `id`, `reference`, `name` et `quantity`.

Aucune synchronisation, stockage applicatif, transformation métier, connexion Bexio ou modification d'un événement n'appartient à ce contrat. Les deux méthodes sont des lectures GET ; la connexion `POST /api/session` existe déjà dans le SDK pour établir la session.

## 1. Liste des événements d'une période

`bookingsInPeriod(string $startDate, string $endDate): array`

**Route :** `GET /api/bookings?paginated=0&period[start]=AAAA-MM-JJ&period[end]=AAAA-MM-JJ`, avec encodage des paramètres par `http_build_query`. Les deux dates sont obligatoires au format strict `YYYY-MM-DD`, et la date de fin doit être supérieure ou égale à celle de début.

**Format observé :** tableau JSON à la racine, même lorsque la liste est vide (`[]`). Les éléments observés sont des objets contenant notamment :

| Champ | Type observé | Signification |
|---|---|---|
| `id` | entier | Identifiant de l'événement pour la lecture détaillée |
| `entity` | chaîne | Type de booking (observé : `event`) |
| `title` | chaîne | Intitulé |
| `operation_period` | objet | Période de l'événement |
| `mobilization_period` | objet | Période de mobilisation |
| `has_materials` | booléen | Indique la présence de matériel, sans le détailler |

**Important :** ce mode est **non paginé**, par opposition au `{data, pagination}` des contacts. Le décodeur du SDK n'accepte une réponse JSON en tableau racine **que pour cette méthode** : les méthodes existantes conservent leur validation d'objet. Les requêtes trop larges doivent être évitées en usage opérationnel pour ne pas surcharger l'instance.

L'implémentation renvoie la liste telle quelle, sans masquer ni filtrer d'autres types éventuels. Une application ne souhaitant que des événements peut vérifier `entity === 'event'` dans chaque entrée.

## 2. Fiche et matériel affecté

`event(int $id): array`

**Route :** `GET /api/events/{id}`, `id` entier positif.

La fiche événement observée est un objet JSON et contient `materials`, une liste des **lignes de matériel réellement affecté à l'événement**.

Chaque ligne observée a notamment :

| Champ | Type observé | Contenu |
|---|---|---|
| `id` | entier | Identifiant du matériel |
| `reference` | chaîne | Référence du matériel |
| `name` | chaîne | Désignation |
| `quantity` | entier | Quantité réservée pour cet événement |
| `material` | objet | Détails du matériel lié (champs conservés sans transformation) |

Exemple **fictif** du contrat, sans valeur copiée depuis le SaaS :

```json
{
  "id": 42,
  "title": "Evenement fictif",
  "materials": [
    {
      "id": 103,
      "name": "Projecteur fictif",
      "reference": "TEST-103",
      "quantity": 6,
      "material": {"id": 103, "name": "Projecteur fictif"}
    }
  ]
}
```

Un tableau `materials: []` signifie que la réponse ne contient aucune ligne affectée ; un champ `materials` absent ou de type inattendu ne doit pas être assimilé à un événement sans matériel.

### Routes à ne pas confondre

- `GET /api/bookings/event/{id}/summary` : vue **résumée** du planning ; fournit notamment `has_materials` et `materials_count`, **mais pas les lignes** `materials`.
- `GET /api/materials/while-event/{id}` : **catalogue de matériel et disponibilités** dans le contexte de l'événement ; ce n'est **pas** la liste des articles réservés.
- `GET /api/events/{id}/missing-materials` : liste des **matériels manquants uniquement**, pas la liste complète des réservations.

## 3. Sécurité, validation et limites

- Aucune donnée réelle des fichiers HAR, en-tête `Authorization`, cookie, JWT, valeur de client ni ID réel n'est conservé dans ce dépôt.
- Les paramètres incorrects sont rejetés **avant réseau** ; les erreurs HTTP restent explicites et ne reproduisent pas les corps de réponse.
- Une requête GET peut être rejouée **une seule fois après un 401** afin de renouveler la session. Aucune écriture de ressource n'est créée.
- Les tests CI ne contactent jamais Loxya : ils utilisent des réponses **entièrement fictives** via `FakeTransport`.
- La structure des deux routes a été observée sur **une instance SaaS**, sans validation systématique des droits restrictifs, des périodes très étendues, des fiches sans matériel ou d'autres versions.

## 4. Critères avant fusion

- [x] Tag Git `v0.1.0` publié sur le commit de référence sans les méthodes P6.
- [x] Deux routes pertinentes observées avec succès en lecture seule dans les traces SaaS autorisées.
- [x] Types de liste et de matériel relevés sans conserver les valeurs personnelles.
- [ ] CI verte sur PHP 8.2, 8.4 et 8.5 et contrat de signatures validé pour ce commit.
- [ ] Revue de la restriction d'utilisation des routes internes, puis accord explicite avant fusion.
- [ ] Prévoir les éventuelles variantes de droits et de version Loxya dans les futurs tests de recette.

Les méthodes non vérifiées de recherche courte `/events`, de bookings paginés et les autres vues de détails restent hors de cette PR. Elles pourront être investiguées dans un chantier distinct (suivi [issue #7](https://github.com/Pocky0013/loxya-php-client/issues/7)). Ce développement prépare une version ultérieure à `v0.1.0`, selon [la politique de versionnement](VERSIONING.md).
