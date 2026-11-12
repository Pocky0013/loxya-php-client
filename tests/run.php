<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Electraudio\Loxya\Client;
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\Factory;
use Electraudio\Loxya\HttpResponse;
use Electraudio\Loxya\Transport;

final class FakeTransport implements Transport
{
    /** @var list<array{method:string,path:string,status:int,body:string}> */
    private array $items = [];
    /** @var list<array{method:string,path:string,headers:array,payload:?array}> */
    public array $calls = [];

    public function queue(string $method, string $path, int $status, array $body): self
    {
        $this->items[] = compact('method', 'path', 'status') + [
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ];
        return $this;
    }

    public function send(string $method, string $path, array $headers, ?array $payload): HttpResponse
    {
        $item = array_shift($this->items);
        if ($item === null || $item['method'] !== $method || $item['path'] !== $path) {
            throw new LogicException('Appel HTTP non prevu: '.$method.' '.$path);
        }
        $this->calls[] = compact('method', 'path', 'headers', 'payload');
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

echo $testCount." tests isoles reussis (aucune requete reseau).\n";
