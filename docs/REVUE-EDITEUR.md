# Note pour l'éditeur Loxya - 12 novembre 2026

## Objet

Valider l'utilisation expérimentale des routes HTTP internes de Loxya par un client PHP autonome depuis l'hébergement Infomaniak d'Electraudio. Le code est écrit indépendamment du code de Loxya. Aucune écriture SQL n'est réalisée; la connexion de lecture SQL existante pour les événements n'est pas modifiée.

## Tests réellement validés dans le SaaS

Instance de test : `https://electraudio.loxya.app` ; compte technique dédié, sans diffusion d'identifiants.

- `POST /api/session` : authentification réussie et jeton JWT.
- `GET /api/session` : identification session (ID utilisateur 31 dans l'essai).
- `GET /api/beneficiaries` : liste paginée.
- `GET /api/companies` : liste paginée.
- `POST /api/beneficiaries` : création d'un bénéficiaire fictif (ID 88), puis `GET /api/beneficiaries/88`.
- `PUT /api/beneficiaries/88` : modification d'une note de test, confirmée par relecture.
- `POST /api/companies` : création d'une société fictive (ID 42), puis `GET /api/companies/42`.
- `PUT /api/companies/42` : modification de la note et de l'adresse, confirmée par relecture.
- `PUT /api/beneficiaries/88` avec `company_id=42`, puis `company_id=null` : association et dissociation confirmées par GET et par l'interface utilisateur.

Aucune suppression de fiche ni opération sur devis/événements/factures n'est prévue.

## Fonctionnement de la bibliothèque

- Identifiant et mot de passe dans un fichier de configuration privé (extérieur à Git et à la racine web).
- Authentification via JSON `identifier`/`password` ; JWT conservé seulement en mémoire et transmis dans `Authorization: Bearer ...`.
- Base URL HTTPS et hôte de destination fixés par la configuration; certificat TLS vérifié, redirections désactivées.
- Un seul point d'accès pour les applications : `Electraudio\Loxya\Client` (construction via `Factory`).
- GET paginés (`page`, `limit`, `search`) ; POST/PUT explicites. Après mutation, relecture GET ciblée.
- Une erreur 401 sur GET peut déclencher une reconnexion et un unique GET supplémentaire ; POST et PUT ne sont jamais réessayés automatiquement.
- En cas de doute sur le résultat d'une écriture, intervention humaine plutôt que répétition de la demande.
- Bibliothèque PHP 8.2+ sans dépendance du portail, distribuable par Composer; CLI et tests de simulation fournis.

## Questions soumises à Loxya

1. L'utilisation de ces routes internes sur une instance SaaS Premium est-elle acceptable pour un compte technique dédié, et à quelles conditions ?
2. Les chemins et champs consultés sont-ils identiques dans la version SaaS déployée et le dépôt public, ou existe-t-il un schéma de compatibilité à privilégier ?
3. Quelles limites de volume/fréquence et quelles règles de sessions/JWT recommandez-vous ?
4. Existe-t-il un point d'extension pérenne, une méthode d'authentification machine ou un calendrier de l'API publique ?
5. Pouvez-vous confirmer les droits nécessaires à la lecture/écriture des sociétés, bénéficiaires et de `company_id` ?
6. Un client tiers autonome et, à terme, un paquet PHP public non officiel vous conviendraient-ils (nom, licence, avertissements, marque) ?

**Statut :** preuve technique validee sur SaaS en novembre 2026 ; nouvelle bibliothèque prête pour revue statique, non encore testée en authentification réelle; aucune garantie de stabilité contractuelle.
