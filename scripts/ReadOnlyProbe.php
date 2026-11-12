<?php
declare(strict_types=1);

use Electraudio\Loxya\Client;

/**
 * Diagnostic local en lecture seule. Hors surface publique du SDK :
 * ne retourne que des types et des compteurs, jamais les valeurs Loxya.
 */
final class ReadOnlyProbe
{
    private const COMPANY_KEYS = [
        'id', 'legal_name', 'is_public_entity', 'registration_id', 'vat_number',
        'invoice_identifier', 'service_code', 'phone', 'street', 'additional_street',
        'postal_code', 'administrative_area', 'locality', 'country', 'note',
    ];

    private const BENEFICIARY_KEYS = [
        'id', 'first_name', 'last_name', 'email', 'reference', 'phone', 'company_id',
        'street', 'additional_street', 'postal_code', 'administrative_area',
        'locality', 'country', 'note',
    ];

    /**
     * Demande la session puis une page (limit=1) de chaque ressource et,
     * uniquement si un ID entier positif existe, une fiche detaillee.
     *
     * @return array<string,mixed> Diagnostic sans identifiants ni valeurs source.
     */
    public static function inspect(Client $client): array
    {
        // Cette lecture authentifie le client et ne transmet aucun jeton au diagnostic.
        $client->session();

        return [
            'session' => 'ok',
            'companies' => self::inspectResource($client, 'companies'),
            'beneficiaries' => self::inspectResource($client, 'beneficiaries'),
        ];
    }

    /** @return array<string,mixed> */
    private static function inspectResource(Client $client, string $kind): array
    {
        $list = $kind === 'companies' ? $client->companies(1, 1) : $client->beneficiaries(1, 1);
        $first = $list['data'][0] ?? null;
        $pagination = $list['pagination'];
        $total = $pagination['total'] ?? null;

        $report = [
            'list' => 'ok',
            'sample_count' => count($list['data']),
            'pagination_types' => [
                'currentPage' => self::fieldType($pagination, 'currentPage'),
                'total' => self::fieldType($pagination, 'total'),
                'total.items' => is_array($total) ? self::fieldType($total, 'items') : 'not_applicable',
                'total.pages' => is_array($total) ? self::fieldType($total, 'pages') : 'not_applicable',
            ],
            'detail' => 'skipped_empty',
            'field_types' => [],
        ];

        if ($first === null) {
            return $report;
        }
        $keys = $kind === 'companies' ? self::COMPANY_KEYS : self::BENEFICIARY_KEYS;
        $id = $first['id'] ?? null;

        if (!is_int($id) || $id < 1) {
            $report['detail'] = 'skipped_invalid_id';
            $report['field_types'] = self::fieldTypes($first, $keys);
            return $report;
        }

        $detail = $kind === 'companies' ? $client->company($id) : $client->beneficiary($id);
        $report['detail'] = 'ok';
        $report['field_types'] = self::fieldTypes($detail, $keys);

        return $report;
    }

    /** @param list<string> $keys
     *  @return array<string,string>
     */
    private static function fieldTypes(array $data, array $keys): array
    {
        $types = [];
        foreach ($keys as $key) {
            $types[$key] = self::fieldType($data, $key);
        }
        return $types;
    }

    private static function fieldType(array $data, string $key): string
    {
        if (!array_key_exists($key, $data)) {
            return 'absent';
        }
        $value = $data[$key];

        if ($value === null) return 'null';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'number';
        if (is_bool($value)) return 'boolean';
        if (is_string($value)) return 'string';
        if (is_array($value)) return 'array';

        return 'other';
    }
}
