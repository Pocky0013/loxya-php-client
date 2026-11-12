# Contrat de confirmation des écritures de contacts

**Périmètre :** `electraudio/loxya-php-client`, ressources `companies` et `beneficiaries`.  
**Statut :** sécurité vérifiée par tests simulés ; comportement réel sur différentes versions SaaS encore en test.  
**API :** routes HTTP internes de Loxya, non officielles, sans garantie de stabilité.

Ce contrat concerne la **bibliothèque PHP générique** et non une application particulière. Les opérations mutantes sont **explicites** : aucun mécanisme de synchronisation automatique, aucune suppression ou réécriture en arrière-plan.

## Méthodes et séquences HTTP

| Méthode | Séquence | Retour en cas de confirmation |
|---|---|---|
| `createCompany(array $fields): array` | `POST /api/companies` puis `GET /api/companies/{id}` | Fiche relue |
| `updateCompany(int $id, array $fields): array` | `PUT /api/companies/{id}` puis `GET /api/companies/{id}` | Fiche relue |
| `createBeneficiary(array $fields): array` | `POST /api/beneficiaries` puis `GET /api/beneficiaries/{id}` | Fiche relue |
| `updateBeneficiary(int $id, array $fields): array` | `PUT /api/beneficiaries/{id}` puis `GET /api/beneficiaries/{id}` | Fiche relue |
| `setBeneficiaryCompany(int $beneficiaryId, ?int $newCompanyId, ?int $expectedCompanyId): array` | Lectures de contrôle, éventuellement `PUT`, puis relecture | Fiche relue |

La création exige un ID positif de type entier dans la réponse au POST pour identifier **la fiche à relire**. L'ID de la relecture doit correspondre exactement à celui de la réponse de création ou de l'argument de mise à jour.

Le client valide les noms de champs connus et refuse les structures de valeurs imbriquées avant l'appel HTTP. `company_id`, lorsqu'il est envoyé, doit être un entier strictement positif ou `null`. Les types et formats spécifiques des autres champs restent à confirmer sur chaque version SaaS.

## Confirmation après écriture

Après une réponse réussie au POST ou PUT, le SDK contrôle :

1. Que la fiche cible a pu être relue.
2. Que son champ `id` est bien le même entier que l'ID ciblé.
3. Que **chaque champ transmis** est présent dans la relecture.
4. Que sa valeur et son type sont strictement identiques (`===`) à ceux demandés, y compris la distinction entre `null` et champ absent.

Les champs **non transmis** ne sont pas comparés. Les réponses conservent par ailleurs les champs supplémentaires renvoyés par le SaaS.

**Limite importante :** Loxya peut normaliser une saisie (espaces, numéros, types, valeurs implicites). Une comparaison stricte peut alors produire un statut **non confirmé bien que l'écriture ait réussi**. C'est volontairement prudent : l'exception ne prouve **jamais** un échec. La validation sur le SaaS réel doit préciser, champ par champ, les éventuelles normalisations avant d'envisager une politique de comparaison plus souple.

## Résultats incertains et erreurs

| Situation | Comportement de la bibliothèque |
|---|---|
| POST réussi mais réponse sans ID entier positif | `WriteNotConfirmedException` (`reason=invalid_response_id`), aucune seconde création |
| POST/PUT accepté mais GET de relecture en erreur | `WriteNotConfirmedException` (`reason=readback_failed`), cause initiale accessible par `getPrevious()` |
| Fiche relue avec un autre ID / ID absent | `WriteNotConfirmedException` (`reason=id_mismatch`) |
| Au moins un champ absent ou différent | `WriteNotConfirmedException` (`reason=fields_mismatch`) |
| HTTP 401, 403, 429, 5xx sur POST/PUT | `HttpException` avec statut ; aucune répétition automatique |
| Transport interrompu pendant le POST/PUT | `TransportException` ; effet distant potentiellement déjà appliqué |
| JSON de réponse d'écriture invalide | `ProtocolException` ; effet distant potentiellement déjà appliqué |
| Conflit de relation avant PUT | `LogicException` ; aucune écriture envoyée |
| Payload invalide en local | `InvalidArgumentException` avant réseau |

`WriteNotConfirmedException` **étend `ProtocolException`**, préservant les gestionnaires existants qui interceptent déjà cette dernière. Ses propriétés publiques en lecture seule permettent d'identifier le contexte, **sans exposer de données personnelles** :

```php
try {
    $updated = $client->updateCompany(42, ['note' => 'Note de test fictive']);
} catch (\Electraudio\Loxya\WriteNotConfirmedException $e) {
    // Ne pas rappeler updateCompany automatiquement.
    // operation='update', resource='companies', resourceId=42,
    // reason='readback_failed'|'id_mismatch'|'fields_mismatch'.
    // Prévoir une vérification manuelle ciblée.
}
```

La propriété `resourceId` est `null` lorsqu'une création n'a pas renvoyé d'ID utilisable. Les messages d'exception ne contiennent ni payload ni valeur distante. Les propriétés et la cause éventuelle doivent rester dans un contexte d'exploitation autorisé.

## Relation bénéficiaire–société

`setBeneficiaryCompany()` vérifie la valeur de `company_id` avant la modification et **refuse** l'écriture si elle ne correspond pas à la valeur `expectedCompanyId` fournie par le consommateur. Cette vérification est un **contrôle optimiste non atomique** : aucune garantie transactionnelle ni ETag n'est connu sur la route utilisée.

La méthode ne déclenche pas de PUT si la relation est déjà identique. Si elle écrit, elle utilise la confirmation stricte de `updateBeneficiary()` avant de rendre la fiche relue.

## Procédure d'incident pour une application consommatrice

1. Ne **jamais** rejouer automatiquement le POST ou PUT, même après un timeout, une réponse 5xx ou un échec de relecture.
2. Conserver l'opération en état « à examiner », sans enregistrer de faux succès ni de faux échec.
3. Si un ID cible est connu, relire explicitement la fiche ; sinon effectuer une recherche de doublons contrôlée avec les critères de l'application.
4. Comparer l'état distant réel au résultat attendu, en tenant compte des éventuelles normalisations de Loxya.
5. Ne proposer une nouvelle écriture qu'après une décision explicite de l'application ou de l'opérateur.

**Validation :** les nouveaux tests unitaires sont exécutés avec un transport simulé, sans identifiants SaaS et sans appel réel. Une recette sur **fiches fictives autorisées** reste nécessaire pour valider les conversions de champs observées sur le serveur avant de promouvoir les écritures au statut « Validé ».
