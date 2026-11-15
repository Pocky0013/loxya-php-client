# Session

[Référence PHP](README.md)

## `session(): array`

**Validé.** Lit la session courante sans exposer le jeton d'authentification.

| Paramètre | Type | Rôle |
| --- | --- | --- |
| Aucun | — | Utilise le compte Loxya configuré |

```php
use Electraudio\Loxya\Factory;

$client = Factory::fromEnvironment();
$session = $client->session();
var_dump(array_key_exists('token', $session)); // bool(false)
```

**Retour attendu :** tableau PHP associatif contenant les données de session **sans clé `token`**. Les autres champs ne sont pas garantis par le SDK ; ils dépendent de Loxya.

**HTTP interne :** authentification `POST /api/session`, puis lecture `GET /api/session`. Les appels HTTP sont gérés par le SDK.

**Erreurs :** configuration invalide, erreur HTTP, transport ou JSON. [Gestion des exceptions](ERREURS.md).
