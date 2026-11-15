# Changelog — Loxya PHP Client

Historique des évolutions du SDK PHP non officiel. Le format suit les principes de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet applique une [politique de versionnement](docs/projet/publications/VERSIONING.md).

## [Unreleased]

- Refonte documentaire : référence PHP par méthode et guide de démarrage ; mémoire technique classée sous docs/projet/.

## [0.2.0] - 2026-11-14

**Préversion publiée le 14 novembre 2026** : [GitHub Release v0.2.0](https://github.com/Pocky0013/loxya-php-client/releases/tag/v0.2.0), tag sur le commit `2b555ebbc8132ec813a401acb8a500bb2ba2da8d`.

- P6.2 : `material(int $id)` lit une fiche du catalogue via `GET /api/materials/{id}`, sans modification des données reçues.
- P6.2 : documentation de `materials[*].material.rental_price` dans les événements et de `rental_price` / `replacement_price` sur les fiches catalogue. Aucun calcul de prix d'événement, de remise ou de dégressivité.
- P6.2 : tests simulés pour prix absent/nul, distinction catalogue/facturation, identifiants, 401, 403/404, formats invalides et signature publique ; **recette SaaS réelle réussie sur l'instance Electraudio le 14 novembre 2026** pour les prix présents.

- Lecture de la liste des bookings sur une période via `bookingsInPeriod(string $startDate, string $endDate)` : réponse JSON à la racine, non paginée.
- Lecture de la fiche complète d'un événement avec `event(int $id)` : le tableau `materials` contient les références et quantités affectées.
- Tests de transport simulé pour le parcours liste → détail → matériel, validation des dates, erreurs de lecture et compatibilité des signatures.
- Contrat documenté à partir de deux routes observées dans des traces SaaS autorisées, sans conserver de données réelles dans Git.
- P6.1 : validation SaaS réelle du parcours `bookingsInPeriod()` → `event()` → `materials` : 16 bookings, 8 détails, références et quantités des matériaux confirmées.
- P6.2 : lecture de `material($id)` validée sur une fiche réelle, concordance des prix catalogue avec le matériel imbriqué d'un événement de 42 lignes ; prix de location observés en `string`, valeur de remplacement parfois `null`.

**Portée de validation :** les fonctionnalités P6.1/P6.2 sont validées pour les parcours observés sur l'instance SaaS Electraudio, et restent en test sur d'autres environnements ainsi que pour les cas limites (prix absent, divergence de prix, `materials: []`, erreurs HTTP réelles). Aucun appel HTTP d'écriture métier n'est ajouté. `v0.1.0` ne contient pas P6. La version `v0.2.0` est publiée. Les versions suivantes suivront le [processus GitHub Actions](docs/projet/publications/PUBLICATION-GITHUB-ACTIONS.md), avec contrôle et publication séparés.

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
