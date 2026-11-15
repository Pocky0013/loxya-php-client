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
     * Liste les techniciens avec leurs rôles (réponse paginée).
     * @return array{data:list<array<string,mixed>>,pagination:array<array-key,mixed>}
     */
    public function technicians(int $page = 1, int $limit = 30, ?string $search = null): array
    {
        $result = $this->getListing('/technicians', $page, $limit, $search);
        foreach ($result['data'] as $technician) {
            self::requireRoles($technician);
        }
        return $result;
    }

    /** Lit la fiche brute d'un technicien, y compris ses rôles déclarés. */
    public function technician(int $id): array
    {
        $technician = $this->request('GET', '/technicians/'.self::id($id));
        self::requireRoles($technician);
        return $technician;
    }

    /**
     * Liste toutes les assignations d'un technicien (sans filtre de date côté Loxya).
     * Chaque ligne porte son propre ID, sa période, son rôle et son événement.
     * @return list<array<string,mixed>>
     */
    public function technicianAssignments(int $id): array
    {
        $rows = $this->getObjectList('/technicians/'.self::id($id).'/events');
        self::requireAssignments($rows, 'event');
        return $rows;
    }

    /**
     * Liste les techniciens et leurs assignations pour les événements de la période.
     * La période porte sur les événements Loxya, pas strictement sur les horaires
     * d'assignation. Loxya limite la durée maximale de la requête (HTTP 416).
     * @return list<array<string,mixed>>
     */
    public function techniciansWithAssignmentsInPeriod(string $startDate, string $endDate): array
    {
        $query = self::periodQuery($startDate, $endDate, ['paginated' => '0']);
        $technicians = $this->getObjectList('/technicians/with-assignments?'.$query);
        foreach ($technicians as $technician) {
            self::requireRoles($technician);
            if (!isset($technician['events']) || !is_array($technician['events'])
                || !array_is_list($technician['events'])) {
                throw new ProtocolException('Liste des assignations du technicien absente ou invalide.');
            }
            self::requireAssignments($technician['events'], 'event');
        }
        return $technicians;
    }

    /**
     * Retourne les rôles disponibles. Dans les assignations, role contient
     * déjà l'objet rôle (ou null), pas seulement role_id.
     * @return list<array<string,mixed>>
     */
    public function roles(): array
    {
        return $this->getObjectList('/roles');
    }

    /**
     * Catégories et sous-catégories de matériel, avec leurs noms.
     * @return list<array<string,mixed>>
     */
    public function categories(): array
    {
        $categories = $this->getObjectList('/categories');
        foreach ($categories as $category) {
            if (!is_int($category['id'] ?? null) || !is_string($category['name'] ?? null)
                || !isset($category['sub_categories']) || !is_array($category['sub_categories'])
                || !array_is_list($category['sub_categories'])) {
                throw new ProtocolException('Categorie ou sous-categories Loxya incompletes.');
            }
            foreach ($category['sub_categories'] as $subCategory) {
                if (!is_array($subCategory) || array_is_list($subCategory)
                    || !is_int($subCategory['id'] ?? null)
                    || !is_string($subCategory['name'] ?? null)) {
                    throw new ProtocolException('Sous-categorie Loxya inattendue.');
                }
            }
        }
        return $categories;
    }

    /**
     * Barèmes dégressifs avec leurs paliers (from_day, is_rate, value).
     * Aucun calcul de coefficient ni remise n'est effectué par le SDK.
     * @return list<array<string,mixed>>
     */
    public function degressiveRates(): array
    {
        $rates = $this->getObjectList('/degressive-rates');
        foreach ($rates as $rate) {
            if (!is_int($rate['id'] ?? null) || !is_string($rate['name'] ?? null)
                || !isset($rate['tiers']) || !is_array($rate['tiers'])
                || !array_is_list($rate['tiers'])) {
                throw new ProtocolException('Bareme ou paliers degressifs Loxya incomplets.');
            }
            foreach ($rate['tiers'] as $tier) {
                if (!is_array($tier) || array_is_list($tier)
                    || !is_int($tier['from_day'] ?? null)
                    || !is_bool($tier['is_rate'] ?? null)
                    || !(is_string($tier['value'] ?? null) || is_int($tier['value'] ?? null)
                        || is_float($tier['value'] ?? null))) {
                    throw new ProtocolException('Palier degressif Loxya inattendu.');
                }
            }
        }
        return $rates;
    }

    /**
     * Lit une fiche materiel du catalogue Loxya, notamment son prix de location
     * catalogue (rental_price) lorsqu'il est disponible.
     * Les donnees sont conservees sans calcul tarifaire ni normalisation.
     *
     * @return array<string,mixed>
     */
    public function material(int $id): array
    {
        return $this->request('GET', '/materials/'.self::id($id));
    }

    /**
     * Retourne la fiche brute d'un evenement, avec ses lignes de materiel affecte
     * lorsque le SaaS expose le champ "materials".
     *
     * @return array<string,mixed>
     */
    public function event(int $id): array
    {
        return $this->request('GET', '/events/'.self::id($id));
    }

    /**
     * Liste les assignations de l'événement avec technicien, période et rôle.
     * Utilise la fiche event() existante : pas de route HTTP supplémentaire.
     * @return list<array<string,mixed>>
     */
    public function eventAssignments(int $id): array
    {
        $event = $this->event($id);
        if (!isset($event['technicians']) || !is_array($event['technicians'])
            || !array_is_list($event['technicians'])) {
            throw new ProtocolException('Assignations de l evenement absentes ou invalides.');
        }
        self::requireAssignments($event['technicians'], 'technician');
        return $event['technicians'];
    }

    /**
     * Liste les bookings d'une periode. Reponse JSON non paginee (tableau racine).
     * Les informations de materiel se trouvent dans la fiche event(), pas ici.
     *
     * @return list<array<string,mixed>>
     */
    public function bookingsInPeriod(string $startDate, string $endDate): array
    {
        $query = self::periodQuery($startDate, $endDate, ['paginated' => '0']);
        $entries = $this->request('GET', '/bookings?'.$query, null, true);
        foreach ($entries as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new ProtocolException('Entree de booking de periode inattendue.');
            }
        }
        return $entries;
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

    /**
     * Conserve les objets JSON bruts dans une liste racine sans pagination.
     * @return list<array<string,mixed>>
     */
    private function getObjectList(string $path): array
    {
        $rows = $this->request('GET', $path, null, true);
        foreach ($rows as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new ProtocolException('Entree de liste Loxya inattendue.');
            }
        }
        return $rows;
    }

    /** @param array<string,string> $extra */
    private static function periodQuery(string $startDate, string $endDate, array $extra = []): string
    {
        foreach ([$startDate, $endDate] as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Periode Loxya : date au format YYYY-MM-DD requise.');
            }
        }
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Periode Loxya : fin anterieure au debut.');
        }
        return http_build_query($extra + [
            'period' => ['start' => $startDate, 'end' => $endDate],
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $technician */
    private static function requireRoles(array $technician): void
    {
        if (!isset($technician['roles']) || !is_array($technician['roles'])
            || !array_is_list($technician['roles'])) {
            throw new ProtocolException('Roles du technicien absents ou invalides.');
        }
        foreach ($technician['roles'] as $role) {
            if (!is_array($role) || array_is_list($role)
                || !is_int($role['id'] ?? null) || !is_string($role['name'] ?? null)) {
                throw new ProtocolException('Role de technicien Loxya inattendu.');
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $assignments
     * @param 'event'|'technician' $related
     */
    private static function requireAssignments(array $assignments, string $related): void
    {
        foreach ($assignments as $assignment) {
            if (!is_array($assignment) || array_is_list($assignment)
                || !is_int($assignment['id'] ?? null)
                || !is_int($assignment['event_id'] ?? null)
                || !is_int($assignment['technician_id'] ?? null)
                || !is_array($assignment['period'] ?? null)
                || !is_string($assignment['period']['start'] ?? null)
                || !is_string($assignment['period']['end'] ?? null)
                || !array_key_exists('role', $assignment)
                || ($assignment['role'] !== null
                    && (!is_array($assignment['role']) || array_is_list($assignment['role'])
                        || !is_int($assignment['role']['id'] ?? null)
                        || !is_string($assignment['role']['name'] ?? null)))
                || !is_array($assignment[$related] ?? null)
                || array_is_list($assignment[$related])) {
                throw new ProtocolException('Assignation Loxya incomplete ou inattendue.');
            }
        }
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

    private function request(string $method, string $path, ?array $payload = null, bool $acceptList = false): array
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
        return $this->decodeResponse($response, $method, $path, $acceptList);
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

    private function decodeResponse(HttpResponse $response, string $method, string $path, bool $acceptList = false): array
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
        if (!is_array($data) || array_is_list($data) !== $acceptList
            || ($acceptList && !str_starts_with(ltrim($response->body), '['))) {
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
