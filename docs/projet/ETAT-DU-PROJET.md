# État des validations — 15 novembre 2026

[Sommaire projet](README.md) · [Méthodes publiques](../api/README.md)

| Domaine | État | À vérifier |
| --- | --- | --- |
| Session | Validé | Droits et instance |
| Lecture sociétés / bénéficiaires | Disponible ; parcours initial vérifié | Pagination, recherche et champs manquants |
| Écritures contacts et association | **En test** | Normalisation des valeurs, confirmation en SaaS |
| Bookings d'une période et événements | **Validé sur une instance SaaS**, v0.2.0 | Autres instances, permissions, cas sans matériel |
| Matériel et prix catalogue | **Validé sur une instance SaaS**, v0.2.0 | Prix absents, écarts tarif événement/catalogue |
| Techniciens, rôles et assignations | **En test**, cible v0.3.0 | Lecture SaaS, IDs pivots, rôles facultatifs, limites de période |
| Catégories et sous-catégories | **En test**, cible v0.3.0 | Catégories et liens vers le matériel sur SaaS |
| Barèmes dégressifs et paliers | **En test**, cible v0.3.0 | Valeurs, paliers virtuels et conditions de facturation |
| Catalogue complet / recherche globale événements | **En projet** | Non implémenté |

## Recettes connues

Le **14 novembre 2026**, une lecture SaaS contrôlée a permis de récupérer **16 bookings**, d'examiner **8 événements** et leurs lignes de matériel. La lecture des prix a été vérifiée sur un événement contenant **42 lignes** ; certains `replacement_price` étaient `null`. Les tests CI s'appuient sur des réponses simulées, sur PHP 8.2, 8.4 et 8.5.

Voir les [contrats événements](contrats/CONTRAT-LECTURE-EVENEMENTS.md), [matériel](contrats/CONTRAT-LECTURE-MATERIEL.md) et la [recette intégrateur](recettes/RECETTE-INTEGRATEUR-P6.md).

## Travaux ouverts

- Vérifier les événements sans matériel et avec droits limités.
- Reproduire des erreurs HTTP réelles et valider d'autres instances SaaS.
- Vérifier les prix manquants et écarts entre prix catalogue et événement.
- Terminer les tests d'écriture sur des fiches fictives autorisées.
- Étudier séparément la recherche globale d'événements et le catalogue complet.
- Recetter en lecture seule les nouveaux contrats des techniciens, catégories et barèmes (voir les fichiers dédiés).
- Contrôler la granularité et les doublons d'assignations lors de recherches par fenêtres temporelles.

Versions publiées : [v0.1.0](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.1.0) et [v0.2.0](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0), préversions expérimentales. Prochaines versions : [procédure de publication](publications/PUBLICATION-GITHUB-ACTIONS.md).
