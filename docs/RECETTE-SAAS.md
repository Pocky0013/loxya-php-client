# Recette SaaS contrôlée — Loxya PHP Client

**Statut : protocole et diagnostic préparés ; recette réelle à exécuter sur une instance autorisée.**  
**Portée :** client PHP indépendant `electraudio/loxya-php-client`, routes internes non officielles.  
**Sécurité :** les tests automatiques n'effectuent **aucune connexion au SaaS**. Le diagnostic CLI n'utilise que des lectures, hormis `POST /api/session` pour l'authentification.

## 1. Objectif et niveaux de validation

Ne pas assimiler une CI verte à une vérification des réponses d'une instance Loxya réelle.

| Niveau | Ce qu'il valide | État |
|---|---|---|
| Tests simulés | Signatures PHP, séquences HTTP, erreurs, formats fictifs et sécurité | Couvert par la CI |
| Diagnostic SaaS non destructif | Session, listes, pages de détail et types des champs d'un échantillon | Outil disponible, exécution non attestée par cette PR |
| Recette manuelle approfondie | Cas de pagination, recherche, fiches incomplètes, 401/403/404 et droits selon l'instance | À effectuer sur une instance autorisée |
| Écritures contrôlées | Création, modification et relations sur fiches fictives | **Non exécuté** ; autorisation explicite distincte nécessaire |

Les données réelles peuvent changer et les routes n'ont aucune garantie de compatibilité dans le temps. L'absence de problème sur une instance ne vaut pas certification universelle.

## 2. Préparation de l'environnement

- Utiliser PHP CLI 8.2 ou supérieur et Composer 2, avec `curl` et `json`.
- Exécuter `composer install` depuis le dépôt du client PHP.
- Fournir `LOXYA_BASE_URL`, `LOXYA_USERNAME` et `LOXYA_PASSWORD` depuis **l'environnement du processus** ou un chargeur de secrets externe. Le SDK et son outil de diagnostic ne lisent pas directement un fichier `.env`.
- Employer un compte d'intégration autorisé. Pour la recette de lecture, préférer des droits de lecture et une instance dédiée si possible.
- Ne jamais placer les identifiants en arguments de commande, dans l'historique du shell, les logs ou Git.

Aucun déploiement du SDK dans une application consommatrice n'est nécessaire pour exécuter cet outil : il s'utilise depuis le dépôt et ses propres dépendances.

## 3. Diagnostic automatisé en lecture seule

```sh
php bin/loxya-read-check.php --help
php bin/loxya-read-check.php --check
```

`--help` n'effectue aucun accès réseau et ne demande pas d'identifiants ; `--check` exige une configuration Loxya déjà chargée.

La vérification appelle, dans cet ordre :

1. `POST /api/session` pour obtenir la session, puis `GET /api/session` ;
2. `GET /api/companies?page=1&limit=1` puis, si la première entrée possède un ID entier positif, `GET /api/companies/{id}` ;
3. `GET /api/beneficiaries?page=1&limit=1` puis, dans les mêmes conditions, `GET /api/beneficiaries/{id}`.

Le diagnostic ne lance **aucune création, modification, suppression ou synchronisation**. Il n'essaie pas d'énumérer toutes les pages.

Sa sortie contient **uniquement** des statuts, un nombre d'entrées de la page et les **types** des champs connus (`string`, `integer`, `null`, `absent`, etc.). Les IDs, noms, adresses, coordonnées, JWT, corps des erreurs HTTP et valeurs personnelles ne sont pas affichés. Sur un échec, l'outil ne divulgue qu'une catégorie d'erreur, ou le statut HTTP.

Le rapport de types provient d'un **échantillon limité** et n'atteste pas les formats de toutes les fiches. Une valeur `absent` n'implique pas qu'un champ ne soit jamais fourni par Loxya.

## 4. Recette manuelle de lecture

Effectuer ces vérifications **sur les seules données auxquelles l'intégrateur est autorisé à accéder**. Noter les observations sans copier les réponses nominatives ni les identifiants dans une issue publique.

| Cas | Procédure | Attendu |
|---|---|---|
| Authentification | Diagnostic `--check` | Session accessible, aucun jeton affiché |
| Listes | Lire les deux types avec `limit=1` | `data` sous forme de liste, `pagination` sous forme de tableau |
| Pagination | Lire une page vide et, si possible, une page hors plage | Comportement et métadonnées consignés, sans supposer le nombre de pages |
| Recherche | Essayer un terme ordinaire et un terme accentué | Encodage correct ; constater le comportement du moteur Loxya |
| Détails | Consulter une fiche de chaque type | Types et présence de champs cohérents |
| Relation | Examiner des bénéficiaires liés, non liés et incomplets | `company_id` entier, `null`, absent ou inattendu distingués |
| Ressource absente | Consulter volontairement un ID inexistant, sans effacer de fiche | Traitement HTTP 404 ou autre réponse effectivement constatée |
| Droits | Tester un compte déjà restreint, si disponible | 403 ou comportement réel documenté ; ne pas modifier les droits pour provoquer une erreur |

**Tracer hors Git :** date et version Loxya si connue, instance (identifiant interne non public), version PHP, commit du SDK, tests exécutés, statut succès/échec, écarts de types. Ne conserver aucune valeur nominative.

## 5. Écritures — scénario distinct, non exécuté par le diagnostic

Ce parcours **n'est pas autorisé par la seule exécution de P4**. Il nécessite l'accord spécifique du responsable de l'instance, un compte avec droits appropriés et des **fiches explicitement fictives**. Aucun script de cette PR n'écrit dans les contacts Loxya.

Lors d'une future recette autorisée :

1. Sélectionner une instance et un jeu d'essai fictif, identifier les fiches de test hors Git.
2. Créer une société fictive avec `createCompany()` ; vérifier que le résultat renvoie un ID valide et que la relecture confirme la création.
3. Modifier un champ de cette société avec `updateCompany()` ; confirmer la valeur par lecture ciblée.
4. Créer puis modifier un bénéficiaire fictif, en relisant à chaque étape.
5. Tester son association et sa dissociation avec `setBeneficiaryCompany()`, y compris le refus d'un `expectedCompanyId` devenu obsolète.
6. En cas de panne ou de réponse perdue après POST/PUT, considérer le résultat comme **incertain**, rechercher la fiche et demander une vérification manuelle ; **ne jamais rejouer automatiquement l'écriture**.
7. Noter les résultats de manière anonymisée et laisser les fiches intactes en l'absence d'une autorisation séparée de nettoyage.

**Important :** les méthodes d'écriture du chantier P3 vérifient désormais strictement les champs relus (ID et valeurs transmises) et signalent les écarts avec `WriteNotConfirmedException`. Cette comparaison doit encore être confrontée aux normalisations réelles du SaaS ; **ce protocole n'atteste aucune écriture réelle**, et un écart ne signifie pas nécessairement que la modification a échoué.

## 6. Critères d'acceptation et suite

- [x] Outil de lecture sans dépendance à une application particulière.
- [x] CI sans identifiants, ni appels SaaS.
- [x] Tests simulés sur sorties anonymes et cas vide/ID invalide.
- [ ] Diagnostic `--check` exécuté sur une instance réelle autorisée et observations consignées.
- [ ] Recette manuelle des cas limites de lecture documentée.
- [ ] Autorisation distincte obtenue avant **toute** écriture de test.
- [ ] Tests d'écriture réels effectués après revue des protections P3, puis résultats anonymisés.

Ne modifier les mentions **Validé / En test / En projet** du README qu'en fonction d'observations attestées. Ce chantier ne publie ni tag, ni package Packagist et ne modifie pas les droits d'une application consommatrice.
