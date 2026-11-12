# Changelog — Loxya PHP Client

Historique des évolutions du SDK PHP non officiel. Le format suit les principes de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet applique une [politique de versionnement](docs/VERSIONING.md).

## [Unreleased]

### En test — candidat P6.1 (non publié)

- Méthodes de lecture proposées : `eventSuggestions()`, `event()`, `bookings()` et `eventBooking()`.
- Contrats séparés pour la recherche courte d'événements (`count` + `data`) et les bookings paginés (`data` + `pagination`).
- Ajout de tests simulés et contrôle des nouvelles signatures PHP.
- **Aucune validation des routes nouvelles sur le SaaS ; aucune écriture.** Fonctionnalités non destinées au tag `v0.1.0`.

## [0.1.0] - 2026-11-12

**Première version expérimentale du SDK.** Ces notes décrivent le contenu du commit de publication ; la disponibilité de la version dépend de la présence du tag Git `v0.1.0`.

#### Ajouté

- Installation Composer (`electraudio/loxya-php-client`), namespace `Electraudio\Loxya\` et compatibilité PHP `^8.2`.
- Authentification Loxya par session, jeton Bearer conservé en mémoire et consultation de la session.
- Lecture des sociétés et bénéficiaires (liste paginée, recherche et fiche individuelle).
- Création et mise à jour explicites de ces deux ressources ; association ou dissociation d'un bénéficiaire et d'une société.
- Transport HTTPS, interface de transport remplaçable, exceptions HTTP, protocole et transport.
- Diagnostic CLI en lecture seule, tests sans accès SaaS et CI PHP 8.2, 8.4 et 8.5.
- Documentation de l'interface publique, du contrat de lecture, du contrat d'écriture et de la recette SaaS.

#### Fiabilisé

- Un seul rejeu des requêtes GET après un HTTP 401 ; aucun rejeu automatique des écritures POST/PUT.
- Relecture après écriture avec contrôle des champs transmis et de l'ID source ; exception `WriteNotConfirmedException` en cas de résultat incertain.
- Validation des paramètres, des réponses de listes et des champs d'écriture autorisés.
- Protection du jeton dans `session()` et réduction des données exposées dans les messages d'erreur.

#### Limites connues

- Routes HTTP internes Loxya **non officielles et non versionnées**, sans garantie de stabilité.
- Écritures **En test** : la comparaison stricte peut signaler une opération non confirmée si le SaaS normalise une valeur.
- Recette complète des écritures réelles sur fiches fictives non réalisée dans le cadre des tests automatisés.
- Aucun support des suppressions, événements, matériel ou réservations ; aucune synchronisation automatique.
- Aucune version stable 1.x ni publication Packagist ; une GitHub Release peut accompagner le tag après vérification de la CI.
