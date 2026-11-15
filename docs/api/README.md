# Référence des méthodes PHP

[Accueil](../../README.md) · [Installation](../guides/DEMARRAGE.md) · [Erreurs](ERREURS.md)

Les méthodes ci-dessous appartiennent à `Electraudio\Loxya\Client`. On obtient un client avec `Factory::fromEnvironment()` ou `Factory::fromArray()`. Il s'agit d'un **SDK PHP non officiel**, basé sur des routes internes non versionnées de Loxya. Les exemples de réponses sont **fictifs et partiels**.

## Connexion et lectures

| Méthode | Usage / retour | Détails |
| --- | --- | --- |
| `session()` | Session sans JWT · fiche | [Session](SESSION.md) |
| `bookingsInPeriod(string $startDate, string $endDate)` | Bookings d'une période · liste | [Événements](EVENEMENTS.md) |
| `event(int $id)` | Fiche d'événement et matériel · fiche | [Événements](EVENEMENTS.md) |
| `material(int $id)` | Fiche catalogue et prix · fiche | [Matériel](MATERIEL.md) |
| `companies(int $page = 1, int $limit = 30, ?string $search = null)` | Liste/recherche de sociétés · `data` + `pagination` | [Sociétés](SOCIETES.md) |
| `company(int $id)` | Fiche société | [Sociétés](SOCIETES.md) |
| `beneficiaries(int $page = 1, int $limit = 30, ?string $search = null)` | Liste/recherche de bénéficiaires · `data` + `pagination` | [Bénéficiaires](BENEFICIAIRES.md) |
| `beneficiary(int $id)` | Fiche bénéficiaire | [Bénéficiaires](BENEFICIAIRES.md) |

Les lectures d'événements et de matériel ont été validées sur **une instance SaaS** ; la compatibilité avec d'autres instances reste en test.

## Écritures sur les contacts

**En test :** n'utiliser qu'avec des autorisations adaptées et examiner les résultats non confirmés.

| Méthode | Usage / retour confirmé | Détails |
| --- | --- | --- |
| `createCompany(array $fields)` | Créer société · fiche relue | [Sociétés](SOCIETES.md) |
| `updateCompany(int $id, array $fields)` | Modifier société · fiche relue | [Sociétés](SOCIETES.md) |
| `createBeneficiary(array $fields)` | Créer bénéficiaire · fiche relue | [Bénéficiaires](BENEFICIAIRES.md) |
| `updateBeneficiary(int $id, array $fields)` | Modifier bénéficiaire · fiche relue | [Bénéficiaires](BENEFICIAIRES.md) |
| `setBeneficiaryCompany(int $beneficiaryId, ?int $newCompanyId, ?int $expectedCompanyId)` | Associer/dissocier · fiche relue | [Bénéficiaires](BENEFICIAIRES.md) |

Une fiche relue signifie que le SDK a pu contrôler strictement l'ID et les champs transmis. Une exception après écriture **n'est pas la preuve que l'écriture a échoué** : ne pas réessayer automatiquement. Voir [les erreurs](ERREURS.md).

## Hors périmètre actuel

Recherche globale d'événements, liste de tout le catalogue, calcul de facturation et synchronisation automatique : **non implémentés**. Voir les [travaux et validations](../projet/README.md).
