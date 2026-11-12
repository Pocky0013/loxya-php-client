#!/usr/bin/env php
<?php
declare(strict_types=1);

$usage = "Usage : php bin/loxya-read-check.php --check | --help\n"
    ."--help  : affiche ce message sans acces reseau ni configuration.\n"
    ."--check : verifie les routes de lecture sur l'instance Loxya configuree.\n";

if ($argc === 2 && $argv[1] === '--help') {
    fwrite(STDOUT, $usage);
    exit(0);
}
if ($argc !== 2 || $argv[1] !== '--check') {
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
