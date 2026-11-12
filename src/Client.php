<?php
declare(strict_types=1);

namespace Electraudio\Loxya;

/** Client PHP des routes Loxya verifiees. Les POST et PUT ne sont jamais rejoues automatiquement. */
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

    /**
     * Liste paginee ; la recherche est facultative.
     * @return array{data:list<array<string,mixed>>,pagination:array<array-key,mixed>}
     */
    public function beneficiaries(int $page = 1, int $limit = 30, ?string $search = null): array
    {
        return $this->getListing('/beneficiaries', $page, $limit, $search);
    }

    /** @return array<string,mixed> Reponse source, non normalisee. */
    public function beneficiary(int $id): array
    {
        return $this->request('GET', '/beneficiaries/'.self::id($id));
    }

    /** Cree une fiche et confirme les champs recus par relecture (sans retry). */
    public function createBeneficiary(array $fields): array
    {
        self::requireFields($fields, ['first_name', 'last_name', 'country'], self::BENEFICIARY_FIELDS);
        $created = $this->request('POST', '/beneficiaries', $fields);
        return $this->confirmWrite('create', 'beneficiaries', self::responseId($created, 'beneficiaries'), $fields);
    }

    /** Mise a jour partielle, puis confirmation stricte des champs relus. */
    public function updateBeneficiary(int $id, array $fields): array
    {
        self::requirePatch($fields, self::BENEFICIARY_FIELDS);
        $path = '/beneficiaries/'.self::id($id);
        $this->request('PUT', $path, $fields);
        return $this->confirmWrite('update', 'beneficiaries', $id, $fields);
    }

    /** @return array{data:list<array<string,mixed>>,pagination:array<array-key,mixed>} */
    public function companies(int $page = 1, int $limit = 30, ?string $search = null): array
    {
        return $this->getListing('/companies', $page, $limit, $search);
    }

    /** @return array<string,mixed> Reponse source, non normalisee. */
    public function company(int $id): array
    {
        return $this->request('GET', '/companies/'.self::id($id));
    }


    /**
     * Recherche courte des evenements (dix resultats maximum dans le code etudie).
     * Le nombre total "count" peut etre superieur a la longueur de "data".
     * @return array{count:int,data:list<array<string,mixed>>}
     */
    public function eventSuggestions(?string $search = null): array
    {
        $params = self::searchParams($search);
        $path = '/events'.($params === [] ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $result = $this->request('GET', $path);
        if (!isset($result['count'], $result['data'])
            || !is_int($result['count']) || $result['count'] < 0
            || !is_array($result['data']) || !array_is_list($result['data'])) {
            throw new ProtocolException('Format de recherche des evenements inattendu.');
        }
        foreach ($result['data'] as $event) {
            if (!is_array($event) || array_is_list($event)) {
                throw new ProtocolException('Entree de recherche des evenements inattendue.');
            }
        }
        return $result;
    }

    /** @return array<string,mixed> Fiche evenement brute. */
    public function event(int $id): array
    {
        return $this->request('GET', '/events/'.self::id($id));
    }

    /**
     * Liste paginee des bookings, limitee a 30 par page dans le code etudie.
     * Par defaut Loxya exclut les bookings archives.
     * @return array{data:list<array<string,mixed>>,pagination:array<array-key,mixed>}
     */
    public function bookings(
        int $page = 1,
        int $limit = 30,
        ?string $search = null,
        bool $archived = false,
    ): array {
        if ($limit > 30) {
            throw new \InvalidArgumentException('Bookings : maximum 30 entrees par page.');
        }
        return $this->getListing(
            '/bookings', $page, $limit, $search, $archived ? ['archived' => '1'] : [],
        );
    }

    /** @return array<string,mixed> Vue booking d'un evenement, distincte de event(). */
    public function eventBooking(int $id): array
    {
        return $this->request('GET', '/bookings/event/'.self::id($id));
    }

    public function createCompany(array $fields): array
    {
        self::requireFields($fields, ['legal_name', 'country'], self::COMPANY_FIELDS);
        $created = $this->request('POST', '/companies', $fields);
        return $this->confirmWrite('create', 'companies', self::responseId($created, 'companies'), $fields);
    }

    public function updateCompany(int $id, array $fields): array
    {
        self::requirePatch($fields, self::COMPANY_FIELDS);
        $path = '/companies/'.self::id($id);
        $this->request('PUT', $path, $fields);
        return $this->confirmWrite('update', 'companies', $id, $fields);
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


    /**
     * Confirme uniquement les champs transmis et l'ID source, sans normalisation.
     * Si Loxya modifie le format d'une valeur, le resultat reste "incertain",
     * jamais une preuve d'echec de l'ecriture : aucune tentative n'est relancee.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private function confirmWrite(string $operation, string $resource, int $id, array $fields): array
    {
        try {
            $after = $resource === 'companies' ? $this->company($id) : $this->beneficiary($id);
        } catch (LoxyaException $e) {
            throw new WriteNotConfirmedException($operation, $resource, $id, 'readback_failed', $e);
        }
        if (($after['id'] ?? null) !== $id) {
            throw new WriteNotConfirmedException($operation, $resource, $id, 'id_mismatch');
        }
        foreach ($fields as $field => $expected) {
            if (!array_key_exists($field, $after) || $after[$field] !== $expected) {
                throw new WriteNotConfirmedException($operation, $resource, $id, 'fields_mismatch');
            }
        }
        return $after;
    }

    /** @param array<string,string> $extraParams */
    private function getListing(
        string $path, int $page, int $limit, ?string $search, array $extraParams = [],
    ): array {
        if ($page < 1 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Pagination invalide : page >= 1 et limit 1..100.');
        }
        $params = array_merge(['page' => $page, 'limit' => $limit], self::searchParams($search), $extraParams);
        $result = $this->request('GET', $path.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        if (!isset($result['data'], $result['pagination'])
            || !is_array($result['data']) || !array_is_list($result['data'])
            || !is_array($result['pagination'])) {
            throw new ProtocolException('Format de pagination Loxya inattendu.');
        }
        foreach ($result['data'] as $contact) {
            if (!is_array($contact) || array_is_list($contact)) {
                throw new ProtocolException('Entree de liste Loxya inattendue.');
            }
        }
        return $result;
    }

    /** @return array<string,string> */
    private static function searchParams(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return [];
        }
        $length = preg_match_all('/./us', $search);
        if ($length === false || $length < 2 || $length > 180) {
            throw new \InvalidArgumentException('Recherche : 2 a 180 caracteres.');
        }
        return ['search' => $search];
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
        foreach ($fields as $field => $value) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new \InvalidArgumentException('Champ de contact non autorise : '.(string)$field);
            }
            // Le protocole actuel ne definit pas de structures imbriquees en ecriture.
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Valeur de contact non scalaire : '.$field.'.');
            }
            if ($field === 'company_id' && $value !== null && (!is_int($value) || $value < 1)) {
                throw new \InvalidArgumentException('company_id doit etre un entier positif ou null.');
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

    private static function responseId(array $response, string $resource): int
    {
        $id = $response['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new WriteNotConfirmedException('create', $resource, null, 'invalid_response_id');
        }
        return $id;
    }
}
