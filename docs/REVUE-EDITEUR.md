# Utilisation des routes HTTP internes de Loxya

**Note technique - novembre 2026**

Le paquet `electraudio/loxya-php-client` permet à une application PHP de lire et de modifier des contacts Loxya sans accès en écriture à sa base de données. Il s'agit d'un client non officiel, installable avec Composer et indépendant de l'application qui l'utilise.

## Routes utilisées

| Opération | Route |
|---|---|
| Connexion et lecture de session | `POST /api/session`, `GET /api/session` |
| Bénéficiaires | `GET/POST /api/beneficiaries`, `GET/PUT /api/beneficiaries/{id}` |
| Sociétés | `GET/POST /api/companies`, `GET/PUT /api/companies/{id}` |
| Relation bénéficiaire-société | `PUT /api/beneficiaries/{id}` avec `company_id` (identifiant ou `null`) |

La connexion envoie `identifier` et `password` au format JSON. Le JWT renvoyé est conservé en mémoire puis transmis dans `Authorization: Bearer ...`.

Le client vérifie TLS, refuse les redirections et les hôtes non conformes à la configuration. Les requêtes de lecture peuvent être rejouées une fois après une nouvelle authentification en cas de `401`. Les écritures ne sont jamais rejouées automatiquement et sont suivies d'une relecture de la fiche.

## Vérifications effectuées

Des essais sur une instance SaaS Loxya ont confirmé l'authentification, la lecture, la création et la modification de fiches fictives de bénéficiaires et de sociétés, ainsi que leur association et dissociation. Ces tests ne constituent pas une garantie de compatibilité avec toutes les versions de Loxya.

La bibliothèque actuelle est testée avec un transport simulé. L'installation Composer et la compatibilité avec l'instance cible seront vérifiées séparément lors de l'intégration.

## Points à confirmer avec Loxya

1. L'utilisation de ces routes par un client externe sur une instance SaaS est-elle autorisée et sous quelles conditions ?
2. Les structures des données et les droits nécessaires sont-ils identiques dans les versions SaaS et publiques ?
3. Existe-t-il des limites d'appels ou des recommandations concernant le renouvellement des sessions ?
4. Une méthode d'authentification propre aux intégrations est-elle prévue ?
5. Quelles garanties de compatibilité ou étapes de migration sont envisagées pour la future API publique ?
6. La distribution d'un client PHP non officiel, éventuellement en open source, est-elle envisageable ?

Le paquet ne modifie ni les événements ni les documents de facturation. Il ne contient aucun code source repris de Loxya.
