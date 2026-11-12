# Instructions pour le projet ChatGPT « Connecteur API Loxya »

Créer un Projet ChatGPT distinct du projet « Portail Electraudio » et y placer le code, le PDF de revue, ce document et, si possible, le lien GitHub du nouveau dépôt. Si l'option le permet, déplacer la conversation de preuve de concept dans le nouveau projet ; sinon démarrer une discussion et utiliser ce document comme contexte initial.

## Instructions du projet (proposition)

> Tu participes au développement d'un client PHP autonome et réutilisable pour les routes HTTP internes non officielles de Loxya. Dépôt cible : `Pocky0013/loxya-php-client`, bibliothèque Composer `electraudio/loxya-php-client`. PHP >= 8.2, PSR-4, aucun framework. Une seule couche de communication HTTPS dans `src/`, credentials privés non versionnés, TLS vérifié, pas de redirect, pas de SQL. Scope : authentification, bénéficiaires, sociétés, relations. Relecture GET après écriture. Reconnexion automatique seulement pour GET 401, jamais de retry automatique sur un POST ou PUT dont le résultat peut être incertain. Préserver le principe de consentement avant les écritures réelles. Tests hors réseau et documents d'intégration tenus à jour. Ne pas coupler ce paquet au portail Electraudio ou à Bexio. Aucune publication publique sans revue du développeur Loxya. Ne jamais committer de secrets ni de données clients. Une PR par changement substantiel et versionnage sémantique.

## Contexte de preuve de concept

Le 12 novembre 2026, essais manuels réussis sur `electraudio.loxya.app` depuis PHP CLI sur Infomaniak : session, listes, création/modification bénéficiaire de test ID 88, société de test ID 42, association puis dissociation par `company_id`. Ce résultat valide les routes sur la version alors déployée mais pas leur pérennité. Le développeur Loxya doit encore se prononcer sur la compatibilité et l'usage à long terme.

## Règles de projet

- Le dépôt GitHub du connecteur est la **source du code** ; le Projet ChatGPT sert au contexte, aux échanges et aux pièces jointes, pas à l'hébergement du logiciel.
- Éviter le mélange des chantiers avec le PM, les ACL et la synchronisation Bexio. L'intégration au portail sera une **application consommatrice distincte**, ultérieure.
- Les identifiants du compte technique sont dans un fichier de configuration privé du serveur; ne les demander ni les afficher dans une conversation.
- Garder la note de revue de deux pages à jour avant envoi à l'éditeur.
