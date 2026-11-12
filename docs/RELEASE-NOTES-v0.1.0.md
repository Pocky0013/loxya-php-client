# Loxya PHP Client v0.1.0

**Première version expérimentale** du client PHP non officiel pour les routes HTTP internes de Loxya.

## Disponible

- Installation via Composer depuis un dépôt Git VCS ; PHP `^8.2`, extensions `curl` et `json`.
- Authentification de session, JWT conservé en mémoire et gestion des réponses HTTP.
- Lecture paginée, recherche et consultation individuelle des **sociétés** et **bénéficiaires**.
- Création et modification explicites de sociétés et bénéficiaires.
- Association/dissociation d'un bénéficiaire avec sa société.
- Diagnostic CLI en lecture seule `php bin/loxya-read-check.php --check` (uniquement sur instance autorisée).
- CI simulée sans accès SaaS sur PHP 8.2, 8.4 et 8.5, avec contrôle des signatures publiques.

## Sécurité

Le client utilise HTTPS avec vérification TLS et sans redirection. Une lecture GET peut être répétée une seule fois après une erreur HTTP 401. **Aucune requête POST/PUT n'est automatiquement répétée.** La relecture après écriture vérifie les champs transmis ; une `WriteNotConfirmedException` indique un résultat incertain qui ne doit pas être rejoué automatiquement.

## Statuts et limitations

- **Validé initialement :** authentification et lecture des sociétés/bénéficiaires, sur une instance autorisée et avec tests simulés.
- **En test :** cas limites de recherche/pagination, diagnostic générique sur d'autres instances, et surtout **écritures**. Loxya peut normaliser les champs et faire échouer la comparaison stricte de relecture même si l'opération a réussi.
- **Non disponible :** événements, matériel, réservations, suppression, synchronisation automatique ou gestion métier des contacts.

**Important :** ce paquet n'est pas une API Loxya officielle. Les routes HTTP utilisées sont internes, non versionnées et susceptibles de changer. Leur usage dépend des autorisations et des conditions applicables à l'instance concernée.

Le code est public sous **PolyForm Internal Use License 1.0.0** (code source disponible, non OSI), pas une licence libre de redistribution/revente.

## Installer depuis le tag

Après la publication du tag Git `v0.1.0` :

```sh
composer config repositories.loxya vcs https://github.com/Pocky0013/loxya-php-client
composer require electraudio/loxya-php-client:^0.1.0
```

Le `composer.lock` de chaque application consommatrice détermine le commit installé. Le tag Git et la publication Packagist sont deux opérations différentes ; Packagist n'est **pas requis** pour une installation par VCS.

Documentation : [README](../README.md) · [Changelog](../CHANGELOG.md) · [Contrat de lecture](CONTRAT-LECTURE-CONTACTS.md) · [Contrat d'écriture](CONTRAT-ECRITURES-CONTACTS.md) · [Recette SaaS](RECETTE-SAAS.md).
