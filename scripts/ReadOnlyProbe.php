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

    private const BOOKING_KEYS = [
        'id', 'entity', 'title', 'has_materials', 'operation_period', 'mobilization_period',
    ];
    private const EVENT_KEYS = ['id', 'title', 'materials'];
    private const EVENT_LINE_KEYS = [
        'id', 'reference', 'name', 'quantity', 'unit_price', 'material',
    ];
    private const MATERIAL_KEYS = [
        'id', 'reference', 'name', 'rental_price', 'replacement_price',
    ];

    /**
     * Diagnostic P6 explicite : sept jours civils au maximum, une fiche evenement
     * et une fiche catalogue au maximum. Aucun nom, ID ou montant dans le rapport.
     * Methode de diagnostic hors contrat public du SDK.
     *
     * @return array<string,mixed>
     */
    public static function inspectP6(Client $client, string $startDate, string $endDate): array
    {
        self::assertShortPeriod($startDate, $endDate);

        $client->session();
        $bookings = $client->bookingsInPeriod($startDate, $endDate);
        $first = $bookings[0] ?? null;
        $eventId = null;
        $eventCount = 0;
        foreach ($bookings as $booking) {
            if (($booking['entity'] ?? null) !== 'event') {
                continue;
            }
            $eventCount++;
            $id = $booking['id'] ?? null;
            if ($eventId === null && is_int($id) && $id > 0) {
                $eventId = $id;
            }
        }

        $report = [
            'session' => 'ok',
            'bookings' => [
                'sample_count' => count($bookings),
                'events_count' => $eventCount,
                'field_types' => $first === null ? [] : self::fieldTypes($first, self::BOOKING_KEYS),
            ],
            'event' => [
                'detail' => $eventCount === 0 ? 'skipped_no_event' : 'skipped_invalid_id',
                'field_types' => [],
                'materials_count' => null,
                'line' => 'skipped_unavailable',
                'line_types' => [],
                'nested_material_types' => [],
            ],
            'catalogue' => [
                'detail' => 'skipped_no_material',
                'field_types' => [],
                'id_matches_line' => 'not_checked',
            ],
        ];

        if ($eventId === null) {
            return $report;
        }

        $event = $client->event($eventId);
        $report['event']['detail'] = 'ok';
        $report['event']['field_types'] = self::fieldTypes($event, self::EVENT_KEYS);

        $lines = $event['materials'] ?? null;
        if (!is_array($lines) || !array_is_list($lines)) {
            return $report;
        }
        $report['event']['materials_count'] = count($lines);
        if ($lines === []) {
            $report['event']['line'] = 'skipped_empty';
            return $report;
        }

        $line = $lines[0];
        if (!is_array($line) || array_is_list($line)) {
            $report['event']['line'] = 'skipped_invalid_shape';
            return $report;
        }

        $report['event']['line'] = 'ok';
        $report['event']['line_types'] = self::fieldTypes($line, self::EVENT_LINE_KEYS);
        $nested = $line['material'] ?? null;
        if (is_array($nested) && !array_is_list($nested)) {
            $report['event']['nested_material_types'] = self::fieldTypes($nested, self::MATERIAL_KEYS);
        }

        $materialId = $line['id'] ?? null;
        if (!is_int($materialId) || $materialId < 1) {
            $report['catalogue']['detail'] = 'skipped_invalid_id';
            return $report;
        }

        $material = $client->material($materialId);
        $report['catalogue']['detail'] = 'ok';
        $report['catalogue']['field_types'] = self::fieldTypes($material, self::MATERIAL_KEYS);
        $catalogueId = $material['id'] ?? null;
        if (is_int($catalogueId) && $catalogueId > 0) {
            $report['catalogue']['id_matches_line'] = $catalogueId === $materialId ? 'yes' : 'no';
        } else {
            $report['catalogue']['id_matches_line'] = 'not_comparable';
        }
        return $report;
    }

    private static function assertShortPeriod(string $startDate, string $endDate): void
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if ($start === false || $end === false
            || $start->format('Y-m-d') !== $startDate
            || $end->format('Y-m-d') !== $endDate
            || $start > $end
            || $start->diff($end)->days > 6) {
            throw new \InvalidArgumentException(
                'Diagnostic P6 : periode YYYY-MM-DD valide, au maximum 7 jours civils.',
            );
        }
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
