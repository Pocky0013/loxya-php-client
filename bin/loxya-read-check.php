#!/usr/bin/env php
<?php
declare(strict_types=1);

$usage = "Usage : php bin/loxya-read-check.php --check | --check-p6 YYYY-MM-DD YYYY-MM-DD | --help\n"
    ."--help     : affiche ce message sans acces reseau ni configuration.\n"
    ."--check    : diagnostic Contacts existant (societes et beneficiaires).\n"
    ."--check-p6 : diagnostic de lecture evenements et materiel, periode de 1 a 7 jours civils.\n";

if ($argc === 2 && $argv[1] === '--help') {
    fwrite(STDOUT, $usage);
    exit(0);
}
$checkContacts = $argc === 2 && $argv[1] === '--check';
$checkP6 = $argc === 4 && $argv[1] === '--check-p6';
if (!$checkContacts && !$checkP6) {
    fwrite(STDERR, $usage);
    exit(2);
}

$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Dependances Composer absentes. Executer composer install.\n");
    exit(1);
}
require $autoload;
require dirname(__DIR__).'/scripts/ReadOnlyProbe.php';

use Electraudio\Loxya\Factory;
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\ProtocolException;
use Electraudio\Loxya\TransportException;

try {
    if ($checkP6) {
        $report = ReadOnlyProbe::inspectP6(Factory::fromEnvironment(), $argv[2], $argv[3]);
        fwrite(STDOUT, "Session Loxya : OK\n");
        fwrite(STDOUT, 'Bookings : '.$report['bookings']['sample_count']
            .', evenements : '.$report['bookings']['events_count']."\n");
        foreach ($report['bookings']['field_types'] as $key => $type) {
            fwrite(STDOUT, '  booking.'.$key.' : '.$type."\n");
        }

        $event = $report['event'];
        fwrite(STDOUT, 'Evenement : '.$event['detail']."\n");
        foreach ($event['field_types'] as $key => $type) {
            fwrite(STDOUT, '  evenement.'.$key.' : '.$type."\n");
        }
        if ($event['materials_count'] !== null) {
            fwrite(STDOUT, '  lignes de materiel : '.$event['materials_count']."\n");
        }
        fwrite(STDOUT, '  ligne : '.$event['line']."\n");
        foreach ($event['line_types'] as $key => $type) {
            fwrite(STDOUT, '  ligne.'.$key.' : '.$type."\n");
        }
        foreach ($event['nested_material_types'] as $key => $type) {
            fwrite(STDOUT, '  ligne.material.'.$key.' : '.$type."\n");
        }

        $catalogue = $report['catalogue'];
        fwrite(STDOUT, 'Catalogue : '.$catalogue['detail']."\n");
        foreach ($catalogue['field_types'] as $key => $type) {
            fwrite(STDOUT, '  catalogue.'.$key.' : '.$type."\n");
        }
        fwrite(STDOUT, '  concordance des identifiants : '.$catalogue['id_matches_line']."\n");
    } else {
        $report = ReadOnlyProbe::inspect(Factory::fromEnvironment());
        fwrite(STDOUT, "Session Loxya : OK\n");
        foreach (['companies' => 'Societes', 'beneficiaries' => 'Beneficiaires'] as $kind => $label) {
            $entry = $report[$kind];
            fwrite(STDOUT, $label.' : liste OK, detail '.$entry['detail']."\n");
            fwrite(STDOUT, '  Enregistrements dans la page : '.$entry['sample_count']."\n");
            foreach ($entry['pagination_types'] as $key => $type) {
                fwrite(STDOUT, '  pagination.'.$key.' : '.$type."\n");
            }
            foreach ($entry['field_types'] as $key => $type) {
                fwrite(STDOUT, '  '.$key.' : '.$type."\n");
            }
        }
    }
    fwrite(STDOUT, "Diagnostic en lecture seule termine. Aucune valeur ou identifiant source affiche.\n");
} catch (\Throwable $error) {
    // Ne jamais transmettre les messages distants, donnees sources ou secrets dans les logs.
    $reason = match (true) {
        $error instanceof HttpException => 'HTTP '.$error->status,
        $error instanceof ProtocolException => 'protocole',
        $error instanceof TransportException => 'transport',
        $error instanceof \InvalidArgumentException => 'configuration',
        default => 'erreur interne',
    };
    fwrite(STDERR, "Diagnostic interrompu (".$reason."). Aucun detail distant divulgue.\n");
    exit(1);
}
