<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../scripts/ReadOnlyProbe.php';

use Electraudio\Loxya\Client;
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\Factory;
use Electraudio\Loxya\HttpResponse;
use Electraudio\Loxya\ProtocolException;
use Electraudio\Loxya\Transport;
use Electraudio\Loxya\TransportException;
use Electraudio\Loxya\WriteNotConfirmedException;

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

test('P3 : creation et modification confirmees par les champs demandes', function (): void {
    $companyFields = ['legal_name' => 'Entreprise fictive', 'country' => 'CH', 'note' => null];
    $beneficiaryFields = ['first_name' => 'Alex', 'last_name' => 'Exemple', 'country' => 'CH', 'company_id' => null];
    $f = login(new FakeTransport())
        ->queue('POST', '/companies', 201, ['id' => 42])
        ->queue('GET', '/companies/42', 200, ['id' => 42] + $companyFields + ['phone' => '000'])
        ->queue('PUT', '/companies/42', 200, ['id' => 42])
        ->queue('GET', '/companies/42', 200, ['id' => 42, 'note' => 'Verification stricte'])
        ->queue('POST', '/beneficiaries', 201, ['id' => 88])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88] + $beneficiaryFields)
        ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'phone' => '021 000 00 00']);
    $c = client($f);
    ensure($c->createCompany($companyFields)['note'] === null, 'Creation societe non confirmee');
    ensure($c->updateCompany(42, ['note' => 'Verification stricte'])['note'] === 'Verification stricte',
        'Modification societe non confirmee');
    ensure($c->createBeneficiary($beneficiaryFields)['company_id'] === null, 'Creation beneficiaire non confirmee');
    ensure($c->updateBeneficiary(88, ['phone' => '021 000 00 00'])['phone'] === '021 000 00 00',
        'Modification beneficiaire non confirmee');
    ensure($f->remaining() === 0, 'Sequences de confirmation incompletes');
});

test('P3 : ID absent ou different lors de la relecture est incertain', function (): void {
    foreach ([['note' => 'Aucun ID'], ['id' => 99], ['id' => '42']] as $readback) {
        $f = login(new FakeTransport())
            ->queue('PUT', '/companies/42', 200, ['id' => 42])
            ->queue('GET', '/companies/42', 200, $readback);
        try {
            client($f)->updateCompany(42, ['note' => 'Note fictive']);
        } catch (WriteNotConfirmedException $e) {
            ensure($e->reason === 'id_mismatch', 'Cause ID non confirmee');
            ensure($e->operation === 'update' && $e->resourceId === 42, 'Contexte operation manquant');
            ensure(count($f->calls) === 3 && $f->remaining() === 0, 'Ecriture relancee');
            continue;
        }
        throw new RuntimeException('ID de relecture non verifie');
    }
});

test('P3 : champs absents, null ou types differents ne sont jamais confirms', function (): void {
    foreach ([
        ['id' => 42],
        ['id' => 42, 'note' => null],
        ['id' => 42, 'note' => 'Autre valeur'],
    ] as $readback) {
        $f = login(new FakeTransport())
            ->queue('PUT', '/companies/42', 200, ['id' => 42])
            ->queue('GET', '/companies/42', 200, $readback);
        try {
            client($f)->updateCompany(42, ['note' => 'Note privee']);
        } catch (WriteNotConfirmedException $e) {
            ensure($e->reason === 'fields_mismatch', 'Divergence de champ non detectee');
            ensure(!str_contains($e->getMessage(), 'Note privee'), 'Valeur du payload dans exception');
            ensure(count($f->calls) === 3 && $f->remaining() === 0, 'Ecriture relancee');
            continue;
        }
        throw new RuntimeException('Divergence de champs acceptee');
    }
    $f = login(new FakeTransport())
        ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => '42']);
    try {
        client($f)->updateBeneficiary(88, ['company_id' => 42]);
    } catch (WriteNotConfirmedException $e) {
        ensure($e->reason === 'fields_mismatch', 'Divergence de type non detectee');
        return;
    }
    throw new RuntimeException('Divergence de type acceptee');
});

test('P3 : creation avec valeur modifiee ne declenche pas de second POST', function (): void {
    $f = login(new FakeTransport())
        ->queue('POST', '/companies', 201, ['id' => 42])
        ->queue('GET', '/companies/42', 200, ['id' => 42, 'legal_name' => 'Autre societe', 'country' => 'CH']);
    try {
        client($f)->createCompany(['legal_name' => 'Societe fictive', 'country' => 'CH']);
    } catch (WriteNotConfirmedException $e) {
        ensure($e->operation === 'create' && $e->reason === 'fields_mismatch', 'Ecriture non confirmee');
        ensure(count(array_filter($f->calls, static fn ($x) => $x['method'] === 'POST' && $x['path'] === '/companies')) === 1,
            'Creation rejouee');
        ensure($f->remaining() === 0, 'Requetes inattendues');
        return;
    }
    throw new RuntimeException('Creation differente acceptee');
});

test('P3 : GET de relecture en echec apres PUT conserve la cause sans retry', function (): void {
    foreach ([403, 404, 500] as $status) {
        $f = login(new FakeTransport())
            ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88])
            ->queue('GET', '/beneficiaries/88', $status, ['secret' => 'VALEUR_SENSIBLE']);
        try {
            client($f)->updateBeneficiary(88, ['note' => 'PRIVE']);
        } catch (WriteNotConfirmedException $e) {
            ensure($e->operation === 'update' && $e->resource === 'beneficiaries'
                && $e->reason === 'readback_failed', 'Echec de lecture mal categorise');
            ensure($e->getPrevious() instanceof HttpException && $e->getPrevious()->status === $status,
                'Erreur HTTP initiale non accessible');
            ensure(!str_contains($e->getMessage(), 'PRIVE')
                && !str_contains($e->getMessage(), 'VALEUR_SENSIBLE'), 'Donnees divulguees');
            ensure(count($f->calls) === 3 && $f->remaining() === 0, 'PUT relance apres GET en echec');
            continue;
        }
        throw new RuntimeException('Lecture non confirmee acceptee');
    }
});

test('P3 : une creation sans ID valide signale un resultat incertain', function (): void {
    foreach ([['id' => '42'], ['id' => 0], ['status' => 'success']] as $body) {
        $f = login(new FakeTransport())->queue('POST', '/beneficiaries', 201, $body);
        try {
            client($f)->createBeneficiary(['first_name' => 'Alex', 'last_name' => 'Exemple', 'country' => 'CH']);
        } catch (WriteNotConfirmedException $e) {
            ensure($e->reason === 'invalid_response_id' && $e->resourceId === null,
                'Contexte de creation incertaine absent');
            ensure($e instanceof ProtocolException, 'Compatibilite ProtocolException perdue');
            ensure(count($f->calls) === 2 && $f->remaining() === 0, 'Creation rejouee');
            continue;
        }
        throw new RuntimeException('Creation sans ID consideree comme confirmee');
    }
});

test('P3 : les objets et company_id incorrects sont refuses avant la connexion', function (): void {
    $f = new FakeTransport();
    $c = client($f);
    foreach ([
        fn () => $c->createCompany(['legal_name' => 'Exemple', 'country' => 'CH', 'note' => ['nested']]),
        fn () => $c->updateBeneficiary(88, ['phone' => ['numbers' => ['021']]]),
        fn () => $c->updateBeneficiary(88, ['company_id' => '42']),
        fn () => $c->updateBeneficiary(88, ['company_id' => -1]),
        fn () => $c->updateBeneficiary(88, ['company_id' => 0]),
    ] as $invalid) {
        try {
            $invalid();
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('Payload non conforme accepte avant la connexion');
    }
    ensure($f->calls === [], 'Reseau utilise malgre payload invalide');
});

test('P3 : un champ null confirme doit etre present dans la relecture', function (): void {
    $f = login(new FakeTransport())
        ->queue('PUT', '/beneficiaries/88', 200, ['id' => 88])
        ->queue('GET', '/beneficiaries/88', 200, ['id' => 88, 'company_id' => null]);
    ensure(client($f)->updateBeneficiary(88, ['company_id' => null])['company_id'] === null,
        'Dissociation explicite non confirmee');
    ensure($f->remaining() === 0, 'Sequence de relecture non terminee');
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
            ensure($e instanceof ProtocolException, 'Reponse de creation invalide non signalee');
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


/** Charge uniquement des jeux de donnees entierement fictifs. */
function contactFixture(string $name): array
{
    if (!preg_match('/^[a-z-]+$/D', $name)) {
        throw new InvalidArgumentException('Nom de fixture invalide.');
    }
    $contents = file_get_contents(__DIR__.'/fixtures/'.$name.'.json');
    if ($contents === false) {
        throw new RuntimeException('Fixture introuvable : '.$name);
    }
    $value = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Fixture invalide : '.$name);
    }
    return $value;
}

test('Contrat de lecture : listes sources brutes et pagination preservees', function (): void {
    $companies = contactFixture('companies-page');
    $beneficiaries = contactFixture('beneficiaries-page');
    $f = login(new FakeTransport())
        ->queue('GET', '/companies?page=1&limit=30', 200, $companies)
        ->queue('GET', '/beneficiaries?page=1&limit=30', 200, $beneficiaries);
    $c = client($f);
    ensure($c->companies() === $companies, 'Donnees de societe transformees');
    ensure($c->beneficiaries() === $beneficiaries, 'Donnees de beneficiaires transformees');
    ensure($f->remaining() === 0, 'Appels manquants');
});

test('Contrat de lecture : meme ID numerique pour deux types de fiches distinctes', function (): void {
    $company = contactFixture('company-detail');
    $beneficiary = contactFixture('beneficiary-detail');
    ensure($company['id'] === $beneficiary['id'], 'Exemple non collisionnel');
    $f = login(new FakeTransport())
        ->queue('GET', '/companies/42', 200, $company)
        ->queue('GET', '/beneficiaries/42', 200, $beneficiary);
    $c = client($f);
    ensure(($c->company(42)['legal_name'] ?? null) === 'Exemple SA', 'Mauvaise route societe');
    ensure(($c->beneficiary(42)['first_name'] ?? null) === 'Camille', 'Mauvaise route beneficiaire');
    ensure($f->remaining() === 0, 'Routes confondues');
});

test('Contrat de lecture : company_id entier, null et absent restent differents', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/beneficiaries?page=1&limit=30', 200, contactFixture('beneficiaries-page'));
    $data = client($f)->beneficiaries()['data'];
    ensure($data[0]['company_id'] === 42, 'Relation presente alteree');
    ensure(array_key_exists('company_id', $data[1]) && $data[1]['company_id'] === null,
        'Relation nulle alteree');
    ensure(!array_key_exists('company_id', $data[2]), 'Relation absente convertie en null');
    ensure(!array_key_exists('last_name', $data[2]), 'Nom manquant complete artificiellement');
});

test('Contrat de lecture : champs supplementaires du serveur conserves sans transformation', function (): void {
    $company = contactFixture('company-detail');
    $company['extension_future_fictive'] = ['variable' => 123, 'active' => false];
    $f = login(new FakeTransport())->queue('GET', '/companies/42', 200, $company);
    ensure(client($f)->company(42) === $company, 'Champ inconnu perdu ou reinterprete');
});

test('Contrat de lecture : pagination et recherche ne reconstruisent pas la reponse', function (): void {
    $page = ['data' => [], 'pagination' => ['currentPage' => 5, 'total' => ['items' => 2, 'pages' => 1],
        'metadata_fictive' => 'preserved']];
    $f = login(new FakeTransport())
        ->queue('GET', '/companies?page=5&limit=1&search=Caf%C3%A9', 200, $page);
    ensure(client($f)->companies(5, 1, 'Café') === $page, 'Pagination calculee ou modifiee');
    ensure($f->remaining() === 0, 'Requete de page differente');
});


test('Diagnostic P4 : controles de lecture et aucune donnee source publiee', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/session', 200, ['id' => 31, 'name' => 'SECRET_SESSION', 'token' => 'SECRET_TOKEN'])
        ->queue('GET', '/companies?page=1&limit=1', 200, [
            'data' => [['id' => 42, 'legal_name' => 'SECRET_COMPANY']],
            'pagination' => ['currentPage' => 1, 'total' => ['items' => 1, 'pages' => 1]],
        ])
        ->queue('GET', '/companies/42', 200, [
            'id' => 42, 'legal_name' => 'SECRET_COMPANY', 'phone' => '+41 00 123 45 67',
        ])
        ->queue('GET', '/beneficiaries?page=1&limit=1', 200, [
            'data' => [['id' => 88, 'first_name' => 'SECRET_PERSON']],
            'pagination' => ['currentPage' => 1, 'total' => ['items' => 1, 'pages' => 1]],
        ])
        ->queue('GET', '/beneficiaries/88', 200, [
            'id' => 88, 'first_name' => 'SECRET_PERSON', 'last_name' => 'Fake',
            'company_id' => 42, 'email' => 'secret@example.invalid',
        ]);
    $report = ReadOnlyProbe::inspect(client($f));
    ensure($report['session'] === 'ok', 'Session non verifiee');
    ensure($report['companies']['detail'] === 'ok' && $report['beneficiaries']['detail'] === 'ok',
        'Details non verifies');
    ensure($report['companies']['field_types']['legal_name'] === 'string', 'Type societe incorrect');
    ensure($report['beneficiaries']['field_types']['company_id'] === 'integer', 'Type relation incorrect');
    ensure($report['companies']['pagination_types']['total.items'] === 'integer', 'Type total incorrect');

    $encoded = json_encode($report, JSON_THROW_ON_ERROR);
    foreach (['SECRET_SESSION', 'SECRET_TOKEN', 'SECRET_COMPANY', 'SECRET_PERSON', 'secret@example.invalid',
        '+41 00 123 45 67', '"42"', '"88"'] as $sensitive) {
        ensure(!str_contains($encoded, $sensitive), 'Information source divulguee dans le rapport');
    }
    foreach ($f->calls as $call) {
        ensure($call['method'] === 'GET' || ($call['method'] === 'POST' && $call['path'] === '/session'),
            'Diagnostic a declenche une ecriture non autorisee');
    }
    ensure($f->remaining() === 0, 'Requetes de diagnostic non consommees');
});

test('Diagnostic P4 : liste vide sans appel de detail', function (): void {
    $empty = ['data' => [], 'pagination' => ['currentPage' => 1, 'total' => ['items' => 0, 'pages' => 0]]];
    $f = login(new FakeTransport())
        ->queue('GET', '/session', 200, ['id' => 31])
        ->queue('GET', '/companies?page=1&limit=1', 200, $empty)
        ->queue('GET', '/beneficiaries?page=1&limit=1', 200, $empty);
    $report = ReadOnlyProbe::inspect(client($f));
    ensure($report['companies']['detail'] === 'skipped_empty', 'Societe inexistante relue');
    ensure($report['beneficiaries']['detail'] === 'skipped_empty', 'Beneficiaire inexistant relu');
    ensure($report['companies']['field_types'] === [], 'Fausse fiche detaillee');
    ensure(count($f->calls) === 4 && $f->remaining() === 0, 'Appel de detail non prevu');
});

test('Diagnostic P4 : ID invalide ne declenche pas de lecture de detail', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/session', 200, ['id' => 31])
        ->queue('GET', '/companies?page=1&limit=1', 200, [
            'data' => [['id' => '42', 'legal_name' => 'SECRET_NAME']],
            'pagination' => [],
        ])
        ->queue('GET', '/beneficiaries?page=1&limit=1', 200, [
            'data' => [['email' => 'secret@example.invalid']],
            'pagination' => [],
        ]);
    $report = ReadOnlyProbe::inspect(client($f));
    ensure($report['companies']['detail'] === 'skipped_invalid_id', 'ID chaine utilise');
    ensure($report['companies']['field_types']['id'] === 'string', 'Type ID chaine perdu');
    ensure($report['beneficiaries']['detail'] === 'skipped_invalid_id', 'Fiche sans ID relue');
    ensure($report['beneficiaries']['field_types']['id'] === 'absent', 'Absence ID non preservee');
    ensure(!str_contains(json_encode($report, JSON_THROW_ON_ERROR), 'SECRET_NAME'), 'Nom divulgue');
    ensure(count($f->calls) === 4 && $f->remaining() === 0, 'Lecture de detail sur ID incorrect');
});


test('P6.1 : bookings pagines conservent les donnees brutes et les metadonnees', function (): void {
    $body = ['data' => [['id' => 42, 'entity' => 'event', 'title' => 'Fete fictive']],
        'pagination' => ['perPage' => 30, 'currentPage' => 1, 'total' => ['items' => 53, 'pages' => 2]]];
    $f = login(new FakeTransport())->queue('GET', '/bookings?page=1&limit=30', 200, $body);
    ensure(client($f)->bookings() === $body, 'Donnees ou pagination bookings transformees');
    ensure(count($f->calls) === 2 && $f->remaining() === 0, 'Appels bookings incorrects');
});

test('P6.1 : recherche bookings et archives, sans filtre implicite nouveau', function (): void {
    $body = ['data' => [['id' => 42, 'entity' => 'event']], 'pagination' => ['currentPage' => 2]];
    $f = login(new FakeTransport())
        ->queue('GET', '/bookings?page=2&limit=15&search=Caf%C3%A9%20test&archived=1', 200, $body)
        ->queue('GET', '/bookings?page=1&limit=30', 200, ['data' => [], 'pagination' => []]);
    $c = client($f);
    ensure($c->bookings(2, 15, 'Café test', true) === $body, 'Recherche bookings incorrecte');
    ensure($c->bookings()['data'] === [], 'Page vide bookings incorrecte');
    ensure($f->remaining() === 0, 'Requetes non consommees');
});

test('P6.1 : details evenement et booking conservent le materiel affecte et ses quantites', function (): void {
    $materials = [
        ['id' => 103, 'name' => 'Projecteur fictif', 'reference' => 'TEST-103', 'quantity' => 6,
            'material' => ['id' => 103, 'name' => 'Projecteur fictif', 'extra' => true]],
        ['id' => 201, 'name' => 'Cable fictif', 'quantity' => 12,
            'material' => ['id' => 201, 'name' => 'Cable fictif']],
    ];
    $event = ['id' => 42, 'title' => 'Evenement fictif', 'materials' => $materials];
    $booking = ['id' => 42, 'entity' => 'event', 'materials' => $materials];
    $f = login(new FakeTransport())
        ->queue('GET', '/events/42', 200, $event)
        ->queue('GET', '/bookings/event/42', 200, $booking)
        ->queue('GET', '/events/43', 200, ['id' => 43, 'materials' => []]);
    $c = client($f);
    $detail = $c->event(42);
    ensure($detail === $event, 'Contenu du materiel affecte transforme');
    ensure($detail['materials'][0]['quantity'] === 6, 'Quantite de materiel modifiee');
    ensure($detail['materials'][0]['material']['extra'] === true, 'Proprietes materiel supprimees');
    ensure($c->eventBooking(42) === $booking, 'Vue booking ou materiel affecte transforme');
    ensure($c->event(43)['materials'] === [], 'Liste de materiel vide modifiee');
    ensure($f->remaining() === 0, 'Requetes de details non consommees');
});

test('P6.1 : recherche courte est limitee et count ne vaut pas la taille de data', function (): void {
    $body = ['count' => 79, 'data' => [['id' => 42, 'title' => 'Selection fictive']]];
    $f = login(new FakeTransport())
        ->queue('GET', '/events', 200, $body)
        ->queue('GET', '/events?search=Gala%20%C3%A9t%C3%A9', 200, ['count' => 0, 'data' => []]);
    $c = client($f);
    ensure($c->eventSuggestions() === $body, 'Count de suggestions modifie');
    ensure($c->eventSuggestions('Gala été') === ['count' => 0, 'data' => []],
        'Recherche accentuee incorrecte');
    ensure($f->remaining() === 0, 'Requetes suggestions non consommees');
});

test('P6.1 : schemas invalides des suggestions refuses', function (): void {
    foreach ([
        ['data' => []],
        ['count' => '1', 'data' => []],
        ['count' => -1, 'data' => []],
        ['count' => 0, 'data' => ['id' => 42]],
        ['count' => 1, 'data' => [42]],
        ['count' => 1, 'data' => [['bad-list']]],
    ] as $body) {
        $f = login(new FakeTransport())->queue('GET', '/events', 200, $body);
        try {
            client($f)->eventSuggestions();
        } catch (ProtocolException) {
            ensure(count($f->calls) === 2 && $f->remaining() === 0, 'Requete supplementaire apres erreur');
            continue;
        }
        throw new RuntimeException('Schema de suggestions incorrect accepte');
    }
});

test('P6.1 : bookings rejette les formats pagines invalides', function (): void {
    foreach ([
        ['data' => [], 'pagination' => 'bad'],
        ['data' => [['id' => 1]]],
        ['data' => ['id' => 1], 'pagination' => []],
    ] as $body) {
        $f = login(new FakeTransport())->queue('GET', '/bookings?page=1&limit=30', 200, $body);
        try {
            client($f)->bookings();
        } catch (ProtocolException) {
            ensure(count($f->calls) === 2, 'Bookings rejoues apres erreur');
            continue;
        }
        throw new RuntimeException('Schema bookings incorrect accepte');
    }
});

test('P6.1 : identifiants et parametres refuses avant acces HTTP', function (): void {
    $f = new FakeTransport();
    $c = client($f);
    foreach ([
        fn () => $c->bookings(0),
        fn () => $c->bookings(1, 0),
        fn () => $c->bookings(1, 31),
        fn () => $c->bookings(1, 30, 'a'),
        fn () => $c->bookings(1, 30, "\xFF"),
        fn () => $c->eventSuggestions('x'),
        fn () => $c->event(0),
        fn () => $c->eventBooking(-3),
    ] as $invalid) {
        try {
            $invalid();
        } catch (InvalidArgumentException) {
            continue;
        }
        throw new RuntimeException('Parametre P6 incorrect accepte');
    }
    ensure($f->calls === [], 'Acces HTTP avant validation');
});

test('P6.1 : 401 GET reconnecte une seule fois sans ecriture metier', function (): void {
    $f = login(new FakeTransport())
        ->queue('GET', '/bookings?page=1&limit=30', 401, ['error' => 'expired'])
        ->queue('POST', '/session', 200, ['token' => 'JWT2'])
        ->queue('GET', '/bookings?page=1&limit=30', 200, ['data' => [], 'pagination' => []]);
    ensure(client($f)->bookings()['data'] === [], 'Reconnexion bookings incorrecte');
    foreach ($f->calls as $call) {
        ensure($call['method'] === 'GET'
            || ($call['method'] === 'POST' && $call['path'] === '/session'),
            'Ecriture de ressource inattendue');
    }
    ensure(count($f->calls) === 4 && $f->remaining() === 0, 'Appels de reconnexion incorrects');
});

echo $testCount." tests isoles reussis (aucune requete reseau).\n";
