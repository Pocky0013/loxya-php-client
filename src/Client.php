<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Client des contacts Loxya. Les POST et PUT ne sont jamais rejoues automatiquement. */
final class Client
{
    private ?string $token = null;

    public function __construct(
        private readonly Transport $transport,
        private readonly string $username,
        private readonly string $password,
    ) {
        if (trim($username) === '' || $password === '') {
            throw new \InvalidArgumentException('Compte de service Loxya non configure.');
        }
    }

    /** Lit la session sans exposer le jeton. */
    public function session(): array
    {
        $session = $this->request('GET', '/session');
        // La reponse de session ne doit jamais exposer le JWT a l'application.
        unset($session['token']);
        return $session;
    }

    /** Liste paginee ; la recherche est facultative. */
    public function beneficiaries(int $page = 1, int $limit = 30, ?string $search = null): array
    {
        return $this->getListing('/beneficiaries', $page, $limit, $search);
    }

    public function beneficiary(int $id): array
    {
        return $this->request('GET', '/beneficiaries/'.self::id($id));
    }

    /** Cree une fiche et la relit par son identifiant. */
    public function createBeneficiary(array $fields): array
    {
        self::requireFields($fields, ['first_name', 'last_name', 'country'], self::BENEFICIARY_FIELDS);
        $created = $this->request('POST', '/beneficiaries', $fields);
        return $this->beneficiary(self::responseId($created));
    }

    /** Mise a jour partielle, suivie d'une relecture. */
    public function updateBeneficiary(int $id, array $fields): array
    {
        self::requirePatch($fields, self::BENEFICIARY_FIELDS);
        $path = '/beneficiaries/'.self::id($id);
        $this->request('PUT', $path, $fields);
        return $this->beneficiary($id);
    }

    public function companies(int $page = 1, int $limit = 30, ?string $search = null): array
    {
        return $this->getListing('/companies', $page, $limit, $search);
    }

    public function company(int $id): array
    {
        return $this->request('GET', '/companies/'.self::id($id));
    }

    public function createCompany(array $fields): array
    {
        self::requireFields($fields, ['legal_name', 'country'], self::COMPANY_FIELDS);
        $created = $this->request('POST', '/companies', $fields);
        return $this->company(self::responseId($created));
    }

    public function updateCompany(int $id, array $fields): array
    {
        self::requirePatch($fields, self::COMPANY_FIELDS);
        $path = '/companies/'.self::id($id);
        $this->request('PUT', $path, $fields);
        return $this->company($id);
    }

    /**
     * Change la societe d'un beneficiaire si la relation n'a pas evolue.
     * La verification n'est pas atomique : Loxya ne fournit pas d'ETag connu.
     */
    public function setBeneficiaryCompany(int $beneficiaryId, ?int $newCompanyId, ?int $expectedCompanyId): array
    {
        self::id($beneficiaryId);
        if ($newCompanyId !== null) {
            self::id($newCompanyId);
            $this->company($newCompanyId);
        }
        if ($expectedCompanyId !== null) {
            self::id($expectedCompanyId);
        }
        $current = $this->beneficiary($beneficiaryId);
        if (!array_key_exists('company_id', $current)) {
            throw new ProtocolException('Champ company_id absent dans la reponse beneficiaire.');
        }
        if ($current['company_id'] !== $expectedCompanyId) {
            throw new \LogicException('Relation modifiee depuis la derniere lecture : operation refusee.');
        }
        if ($current['company_id'] === $newCompanyId) {
            return $current;
        }
        $after = $this->updateBeneficiary($beneficiaryId, ['company_id' => $newCompanyId]);
        if (!array_key_exists('company_id', $after) || $after['company_id'] !== $newCompanyId) {
            throw new ProtocolException('Modification envoyee, mais relation non confirmee par relecture.');
        }
        return $after;
    }

    private function getListing(string $path, int $page, int $limit, ?string $search): array
    {
        if ($page < 1 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Pagination invalide : page >= 1 et limit 1..100.');
        }
        $params = ['page' => $page, 'limit' => $limit];
        if ($search !== null && trim($search) !== '') {
            $length = preg_match_all('/./us', $search);
            if ($length === false || $length < 2 || $length > 180) {
                throw new \InvalidArgumentException('Recherche : 2 a 180 caracteres.');
            }
            $params['search'] = $search;
        }
        $result = $this->request('GET', $path.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        if (!isset($result['data'], $result['pagination'])
            || !is_array($result['data']) || !array_is_list($result['data'])
            || !is_array($result['pagination'])) {
            throw new ProtocolException('Format de pagination Loxya inattendu.');
        }
        foreach ($result['data'] as $contact) {
            if (!is_array($contact) || array_is_list($contact)) {
                throw new ProtocolException('Entree de liste de contacts Loxya inattendue.');
            }
        }
        return $result;
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if ($this->token === null) {
            $this->authenticate();
        }
        $response = $this->exchange($method, $path, $payload, $this->token);
        if ($response->status === 401 && $method === 'GET') {
            // Ne jamais retenter une ecriture dont l'etat est incertain.
            $this->token = null;
            $this->authenticate();
            $response = $this->exchange($method, $path, $payload, $this->token);
        }
        return $this->decodeResponse($response, $method, $path);
    }

    private function authenticate(): void
    {
        $response = $this->exchange('POST', '/session', [
            'identifier' => $this->username,
            'password' => $this->password,
        ], null);
        $data = $this->decodeResponse($response, 'POST', '/session');
        if (!isset($data['token']) || !is_string($data['token']) || $data['token'] === '') {
            throw new ProtocolException('Authentification sans jeton JWT dans la reponse.');
        }
        $this->token = $data['token'];
    }

    private function exchange(string $method, string $path, ?array $payload, ?string $token): HttpResponse
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer '.$token;
        }
        return $this->transport->send($method, $path, $headers, $payload);
    }

    private function decodeResponse(HttpResponse $response, string $method, string $path): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            // Les parametres de recherche et le corps HTTP restent hors des erreurs.
            throw new HttpException($response->status, $method, explode('?', $path, 2)[0]);
        }
        if (trim($response->body) === '') {
            throw new ProtocolException('Reponse HTTP sans contenu JSON.');
        }
        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProtocolException('Reponse HTTP non JSON.');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new ProtocolException('Format de reponse JSON inattendu.');
        }
        return $data;
    }

    private static function requireFields(array $fields, array $mandatory, array $allowed): void
    {
        foreach ($mandatory as $name) {
            if (!isset($fields[$name]) || !is_string($fields[$name]) || trim($fields[$name]) === '') {
                throw new \InvalidArgumentException('Champ obligatoire manquant : '.$name.'.');
            }
        }
        self::requirePatch($fields, $allowed);
    }

    private static function requirePatch(array $fields, array $allowed): void
    {
        if ($fields === [] || array_is_list($fields)) {
            throw new \InvalidArgumentException('Payload vide ou non associatif.');
        }
        // Seuls les champs de contact connus peuvent etre envoyes.
        foreach (array_keys($fields) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new \InvalidArgumentException('Champ de contact non autorise : '.(string)$field);
            }
        }
    }

    private const BENEFICIARY_FIELDS = [
        'first_name', 'last_name', 'email', 'reference', 'phone', 'company_id',
        'street', 'additional_street', 'postal_code', 'administrative_area',
        'locality', 'country', 'note',
    ];

    private const COMPANY_FIELDS = [
        'legal_name', 'is_public_entity', 'registration_id', 'vat_number',
        'invoice_identifier', 'service_code', 'phone', 'street',
        'additional_street', 'postal_code', 'administrative_area',
        'locality', 'country', 'note',
    ];

    private static function id(int $id): int
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Identifiant Loxya positif requis.');
        }
        return $id;
    }

    private static function responseId(array $response): int
    {
        $id = $response['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new ProtocolException('Ecriture envoyee, mais aucun ID valide retourne : relecture manuelle requise.');
        }
        return $id;
    }
}
