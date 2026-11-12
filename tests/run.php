<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Electraudio\Loxya\Client;
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\Factory;
use Electraudio\Loxya\HttpResponse;
use Electraudio\Loxya\ProtocolException;
use Electraudio\Loxya\Transport;
use Electraudio\Loxya\TransportException;

final class FakeTransport implements Transport
{
    /** @var list<array{method:string,path:string,status:int,body:string,fail?:bool}> */
    private array $items = [];
    /** @var list<array{method:string,path:string,headers:array,payload:?array}> */
    public array $calls = [];

    public function queue(string $method, string $path, int $status, array $body): self
    {
        return $this->queueRaw($method, $path, $status, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function queueRaw(string $method, string $path, int $status, string $body): self
    {
        $this->items[] = compact('method', 'path', 'status', 'body');
        return $this;
    }

    public function queueTransportFailure(string $method, string $path): self
    {
        $this->items[] = ['method' => $method, 'path' => $path, 'status' => 0, 'body' => '', 'fail' => true];
        return $this;
    }

    public function send(string $method, string $path, array $headers, ?array $payload): HttpResponse
    {
        $item = array_shift($this->items);
        if ($item === null || $item['method'] !== $method || $item['path'] !== $path) {
            throw new LogicException('Appel HTTP non prevu: '.$method.' '.$path);
        }
        $this->calls[] = compact('method', 'path', 'headers', 'payload');
        if ($item['fail'] ?? false) {
            throw new TransportException('Echec de transport HTTPS simule.');
        }
        return new HttpResponse($item['status'], $item['body']);
    }

    public function remaining(): int { return count($this->items); }
}

function ensure(bool $cond, string $message): void
{
    if (!$cond) throw new RuntimeException($message);
}
function login(FakeTransport $transport): FakeTransport
{
    return $transport->queue('POST', '/session', 200, ['id' => 31, 'token' => 'FAKE_JWT']);
}
function client(FakeTransport $transport): Client
{
    return new Client($transport, 'faux_utilisateur', 'faux_mot_de_passe');
}
$testCount = 0;
function test(string $name, Closure $run): void
{
    global $testCount;
    $run();
    $testCount++;
    echo '[OK] '.$name."\n";
}

test('Authentification et GET en Bearer; jeton absent de la reponse metier', function (): void {
    $f = login(new FakeTransport())->queue('GET', '/session', 200, ['id' => 31, 'name' => 'staff']);
    $res = client($f)->session();
    ensure(($res['id'] ?? null) === 31, 'ID session');
    ensure(($f->calls[0]['payload']['password'] ?? null) === 'faux_mot_de_passe', 'Payload login');
    ensure(in_array('Authorization: Bearer FAKE_JWT', $f->calls[1]['headers'], true), 'Bearer absent');
    ensure(!isset($res['token']), 'JWT diffuse');
    ensure($f->remaining() === 0, 'Requetes non consommees');
});

test('Lecture paginee encodee et contrat data + pagination', function (): void {
    $path = '/companies?page=2&limit=30&search=Caf%C3%A9%20D.%20A_B';
    $f = login(new FakeTransport())->queue('GET', $path, 200, [
        'data' => [['id' => 42, 'legal_name' => 'Café D. A_B']],
        'pagination' => ['currentPage' => 2, 'total' => ['items' => 1, 'pages' => 2]],
    ]);
    $res = client($f)->companies(2, 30, 'Café D. A_B');
    ensure(($res['data'][0]['id'] ?? null) === 42, 'Pagination');
});

test('Creation d un beneficiaire: un POST seulement puis GET', function (): void {
    $b = ['first_name' => 'Marie', 'last_name' => 'Exemple', 'country' => 'CH'];
    $f = login(new FakeTransport())
        ->queue('POST', '/beneficiaries', 201, ['id' => 88] + $b)
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88] + $b);
    $res = client($f)->createBeneficiary($b);
    ensure(($res['id'] ?? null) === 88, 'ID cree');
    ensure(count(array_filter($f->calls, static fn ($x) => $x['path'] === '/beneficiaries' && $x['method'] === 'POST')) === 1, 'Double creation');
});

test('Modification societe: PUT puis lecture de controle', function (): void {
    $f = login(new FakeTransport())
        ->queue('PUT', '/companies/42', 200, ['id' => 42, 'note' => 'Nouvelle note'])
        ->queue('GET', '/companies/42', 200, ['id' => 42, 'note' => 'Nouvelle note']);
    ensure(client($f)->updateCompany(42, ['note' => 'Nouvelle note'])['note'] === 'Nouvelle note', 'Note non relue');
});

test('Liaison beneficiaire vers entreprise par company_id', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 200, ['id' => 42, 'legal_name' => 'Societe de test'])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => null])
        ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => 42])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => 42]);
    ensure(client($f)->setBeneficiaryCompany(88, 42, null)['company_id'] === 42, 'Relation non confirmee');
    ensure(($f->calls[3]['payload'] ?? null) === ['company_id' => 42], 'Payload de relation incorrect');
});

test('Refus si relation source changee; aucune ecriture', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 200, ['id' => 42])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => 99]);
    try { client($f)->setBeneficiaryCompany(88, 42, null); }
    catch (LogicException) {
        ensure(count($f->calls) === 3, 'Ecriture accidentelle');
        return;
    }
    throw new RuntimeException('Conflit non bloque');
});

test('Un seul rejeu de GET sur HTTP 401 apres reconnexion', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 401, ['error' => 'expired'])
        ->queue('POST', '/session', 200, ['token' => 'JWT2'])
        ->queue('GET', '/companies/42', 200, ['id' => 42]);
    ensure(client($f)->company(42)['id'] === 42, 'Re-authentification GET');
    ensure(count($f->calls) === 4, 'Nombre d appels incorrect');
});

test('Jamais de rejeu de PUT sur HTTP 401', function (): void {
    $f = login(new FakeTransport())->queue('PUT', '/companies/42', 401, ['error' => 'expired']);
    try { client($f)->updateCompany(42, ['note' => 'abc']); }
    catch (HttpException $e) {
        ensure($e->status === 401 && count($f->calls) === 2, 'PUT relance a tort');
        return;
    }
    throw new RuntimeException('HTTP 401 non remonte');
});

test('Le message d erreur HTTP ne divulgue pas la recherche', function (): void {
    $path = '/companies?page=1&limit=30&search=nomprive';
    $f = login(new FakeTransport())->queue('GET', $path, 403, ['error' => 'refused']);
    try { client($f)->companies(1, 30, 'nomprive'); }
    catch (HttpException $e) {
        ensure(!str_contains($e->getMessage(), 'nomprive'), 'Donnee dans exception');
        return;
    }
    throw new RuntimeException('HTTP 403 non remonte');
});

test('Validation des champs et prevention de payload heterogene', function (): void {
    $f = new FakeTransport();
    $c = client($f);
    foreach ([
        fn () => $c->createCompany(['country' => 'CH']),
        fn () => $c->updateBeneficiary(88, ['registration_id' => 'ne-doit-pas-passer']),
        fn () => $c->updateCompany(42, ['company_id' => 3]),
    ] as $invalid) {
        try { $invalid(); } catch (InvalidArgumentException) { continue; }
        throw new RuntimeException('Validation locale absente');
    }
    ensure($f->calls === [], 'Une requete inattendue a ete envoyee');
});

test('Configuration incomplete refusee avant tout appel HTTP', function (): void {
    try {
        Factory::fromArray(['base_url' => 'https://demo.loxya.app', 'username' => 'integration']);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Mot de passe manquant accepte');
});

if (extension_loaded('curl')) {
    test('Configuration depuis un tableau et hote deduit de l URL', function (): void {
        $client = Factory::fromArray([
            'base_url' => 'https://demo.loxya.app',
            'username' => 'integration',
            'password' => 'mot-de-passe-de-test',
        ]);
        ensure($client instanceof Client, 'Client absent');
        try {
            Factory::fromArray([
                'base_url' => 'http://demo.loxya.app',
                'username' => 'integration',
                'password' => 'mot-de-passe-de-test',
            ]);
        } catch (InvalidArgumentException) {
            return;
        }
        throw new RuntimeException('URL non HTTPS acceptee');
    });
}

if (extension_loaded('curl')) {
    test('Variables d environnement chargees par Dotenv dans $_ENV', function (): void {
        $keys = ['LOXYA_BASE_URL', 'LOXYA_USERNAME', 'LOXYA_PASSWORD'];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null];
        }
        try {
            $_ENV['LOXYA_BASE_URL'] = 'https://demo.loxya.app';
            $_ENV['LOXYA_USERNAME'] = 'integration';
            $_ENV['LOXYA_PASSWORD'] = 'mot-de-passe-de-test';
            ensure(Factory::fromEnvironment() instanceof Client, 'Variables non prises en compte');
            $_ENV['LOXYA_PASSWORD'] = '';
            try {
                Factory::fromEnvironment();
            } catch (InvalidArgumentException) {
                return;
            }
            throw new RuntimeException('Un mot de passe vide a ete accepte');
        } finally {
            foreach ($previous as $key => [$existed, $value]) {
                if ($existed) $_ENV[$key] = $value;
                else unset($_ENV[$key]);
            }
        }
    });

    test('Variables fournies par le serveur via getenv', function (): void {
        $values = [
            'LOXYA_BASE_URL' => 'https://demo.loxya.app',
            'LOXYA_USERNAME' => 'integration',
            'LOXYA_PASSWORD' => 'mot-de-passe-de-test',
        ];
        $before = [];
        foreach ($values as $key => $value) {
            $before[$key] = [getenv($key), array_key_exists($key, $_ENV), $_ENV[$key] ?? null,
                array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key.'='.$value);
        }
        try {
            ensure(Factory::fromEnvironment() instanceof Client, 'getenv non pris en compte');
        } finally {
            foreach ($before as $key => [$old, $inEnv, $envValue, $inServer, $serverValue]) {
                if ($old === false) putenv($key);
                else putenv($key.'='.$old);
                if ($inEnv) $_ENV[$key] = $envValue;
                if ($inServer) $_SERVER[$key] = $serverValue;
            }
        }
    });
}

test('Temporisations non numeriques refusees', function (): void {
    try {
        Factory::fromArray([
            'base_url' => 'https://demo.loxya.app',
            'username' => 'integration',
            'password' => 'mot-de-passe-de-test',
            'timeout' => '30 secondes',
        ]);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Temporisation non numerique acceptee');
});


test('Une reponse de session contenant un token ne le retourne pas', function (): void {
    $f = login(new FakeTransport())->queue('GET', '/session', 200, [
        'id' => 31, 'name' => 'staff', 'token' => 'JWT_A_NE_PAS_EXPOSER',
    ]);
    $session = client($f)->session();
    ensure(($session['id'] ?? null) === 31, 'Session non restituee');
    ensure(!array_key_exists('token', $session), 'JWT expose dans session()');
    ensure($f->remaining() === 0, 'Requetes de session en attente');
});

test('Liste de beneficiaires vide avec pagination conservee', function (): void {
    $pagination = ['currentPage' => 1, 'total' => ['items' => 0, 'pages' => 0]];
    $f = login(new FakeTransport())->queue('GET', '/beneficiaries?page=1&limit=100', 200, [
        'data' => [], 'pagination' => $pagination,
    ]);
    $list = client($f)->beneficiaries(1, 100);
    ensure($list === ['data' => [], 'pagination' => $pagination], 'Liste vide incorrecte');
});

test('Structures invalides de listes refusees explicitement', function (): void {
    $invalid = [
        ['pagination' => ['currentPage' => 1]],
        ['data' => [], 'pagination' => 'invalide'],
        ['data' => ['cle' => ['id' => 42]], 'pagination' => []],
        ['data' => [42], 'pagination' => []],
        ['data' => [['unexpected']], 'pagination' => []],
    ];
    foreach ($invalid as $body) {
        $f = login(new FakeTransport())->queue('GET', '/companies?page=1&limit=30', 200, $body);
        try {
            client($f)->companies();
        } catch (ProtocolException) {
            ensure(count($f->calls) === 2, 'Appel supplementaire apres liste invalide');
            continue;
        }
        throw new RuntimeException('Liste mal formee acceptee');
    }
});

test('JSON mal forme, vide, scalaire ou liste refuse pour une fiche', function (): void {
    foreach (['', 'not-json', '[]', '"hello"', 'null', '{}'] as $body) {
        $f = login(new FakeTransport())->queueRaw('GET', '/companies/42', 200, $body);
        try {
            client($f)->company(42);
        } catch (ProtocolException) {
            ensure(count($f->calls) === 2, 'Requete supplementaire sur JSON invalide');
            continue;
        }
        throw new RuntimeException('Reponse JSON invalide acceptee : '.$body);
    }
});

test('Erreurs HTTP des lectures transmises sans corps distant', function (): void {
    foreach ([403, 404, 429, 500] as $status) {
        $f = login(new FakeTransport())->queue('GET', '/beneficiaries/88', $status, [
            'message' => 'Donnees privees NE_PAS_EXPOSER', 'token' => 'JWT_PRIVE',
        ]);
        try {
            client($f)->beneficiary(88);
        } catch (HttpException $e) {
            ensure($e->status === $status && $e->path === '/beneficiaries/88', 'Statut ou chemin incorrect');
            ensure(!str_contains($e->getMessage(), 'NE_PAS_EXPOSER'), 'Corps HTTP divulgue');
            ensure(!str_contains($e->getMessage(), 'JWT_PRIVE'), 'Jeton divulgue');
            ensure(count($f->calls) === 2, 'Erreur HTTP rejouee');
            continue;
        }
        throw new RuntimeException('HTTP '.$status.' non remonte');
    }
});

test('Deux erreurs 401 sur GET ne provoquent qu une seule reconnexion', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 401, ['error' => 'expired'])
        ->queue('POST', '/session', 200, ['token' => 'JWT2'])
        ->queue('GET', '/companies/42', 401, ['error' => 'still expired']);
    try {
        client($f)->company(42);
    } catch (HttpException $e) {
        ensure($e->status === 401, 'Second 401 non remonte');
        ensure(count($f->calls) === 4, 'Plus d une reconnexion GET');
        ensure($f->remaining() === 0, 'Requetes inattendues');
        return;
    }
    throw new RuntimeException('Second 401 accepte');
});

test('Un POST refuse avec 401 n est jamais rejoue', function (): void {
    $f = login(new FakeTransport())->queue('POST', '/companies', 401, ['error' => 'expired']);
    try {
        client($f)->createCompany(['legal_name' => 'Fictif', 'country' => 'CH']);
    } catch (HttpException $e) {
        ensure($e->status === 401 && count($f->calls) === 2, 'POST repete ou relecture');
        return;
    }
    throw new RuntimeException('HTTP 401 sur POST accepte');
});

test('Echec de transport pendant POST ou PUT : pas de nouvel essai', function (): void {
    foreach (['POST', 'PUT'] as $method) {
        $path = $method === 'POST' ? '/companies' : '/companies/42';
        $f = login(new FakeTransport())->queueTransportFailure($method, $path);
        try {
            if ($method === 'POST') {
                client($f)->createCompany(['legal_name' => 'Fictif', 'country' => 'CH']);
            } else {
                client($f)->updateCompany(42, ['note' => 'Test fictif']);
            }
        } catch (TransportException $e) {
            ensure(count($f->calls) === 2, 'Ecriture rejouee apres erreur transport');
            ensure(!str_contains($e->getMessage(), 'Fictif'), 'Donnees divulguees');
            continue;
        }
        throw new RuntimeException('Erreur de transport '.$method.' non remontee');
    }
});

test('Creation sans ID fiable : pas de nouveau POST', function (): void {
    foreach ([[], ['id' => '42'], ['id' => 0]] as $created) {
        $f = login(new FakeTransport())->queue('POST', '/companies', 201, $created);
        try {
            client($f)->createCompany(['legal_name' => 'Fictif', 'country' => 'CH']);
        } catch (ProtocolException $e) {
            ensure(count($f->calls) === 2, 'Creation renvoyee');
            ensure(str_contains($e->getMessage(), 'relecture manuelle'), 'Ambiguite non expliquee');
            continue;
        }
        throw new RuntimeException('Reponse de creation sans ID valide acceptee');
    }
});

test('Pagination, recherche et identifiants invalides refuses avant le reseau', function (): void {
    $f = new FakeTransport();
    $c = client($f);
    foreach ([
        fn () => $c->companies(0),
        fn () => $c->companies(1, 0),
        fn () => $c->companies(1, 101),
        fn () => $c->companies(1, 30, 'a'),
        fn () => $c->companies(1, 30, str_repeat('a', 181)),
        fn () => $c->beneficiaries(1, 30, "\xFF"),
        fn () => $c->company(0),
        fn () => $c->beneficiary(-1),
    ] as $invalid) {
        try {
            $invalid();
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('Parametre de lecture invalide accepte');
    }
    ensure($f->calls === [], 'Connexion effectuee avant validation');
});

test('Relation deja identique : aucune ecriture', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 200, ['id' => 42])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => 42]);
    $res = client($f)->setBeneficiaryCompany(88, 42, 42);
    ensure($res['company_id'] === 42 && count($f->calls) === 3, 'Ecriture inutile');
});

test('Dissociation de societe suivie de relecture', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => 42])
        ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => null])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => null]);
    $res = client($f)->setBeneficiaryCompany(88, null, 42);
    ensure(array_key_exists('company_id', $res) && $res['company_id'] === null, 'Dissociation non confirmee');
    ensure(($f->calls[2]['payload'] ?? []) === ['company_id' => null], 'Payload de dissociation incorrect');
});

test('Relation inconnue : aucune ecriture', function (): void {
    $f = login(new FakeTransport())->queue('GET', '/beneficiaries/88', 200, ['id' => 88]);
    try {
        client($f)->setBeneficiaryCompany(88, null, null);
    } catch (ProtocolException) {
        ensure(count($f->calls) === 2, 'Ecriture malgre relation inconnue');
        return;
    }
    throw new RuntimeException('Absence de company_id acceptee');
});

if (extension_loaded('curl')) {
    test('URLs HTTPS hostiles et delais hors bornes refuses', function (): void {
        $valid = [
            'base_url' => 'https://demo.loxya.app',
            'username' => 'integration',
            'password' => 'mot-de-passe-de-test',
        ];
        foreach ([
            'http://demo.loxya.app',
            'https://user:pass@demo.loxya.app',
            'https://demo.loxya.app/api',
            'https://demo.loxya.app?target=other',
            'https://demo.loxya.app:8443',
        ] as $url) {
            try {
                Factory::fromArray(array_replace($valid, ['base_url' => $url]));
            } catch (InvalidArgumentException) {
                continue;
            }
            throw new RuntimeException('URL invalide acceptee : '.$url);
        }
        foreach ([
            ['connect_timeout' => 0],
            ['timeout' => 121],
            ['connect_timeout' => 31, 'timeout' => 30],
        ] as $invalid) {
            try {
                Factory::fromArray(array_replace($valid, $invalid));
            } catch (InvalidArgumentException) {
                continue;
            }
            throw new RuntimeException('Temporisation invalide acceptee');
        }
    });
}

echo $testCount." tests isoles reussis (aucune requete reseau).\n";
