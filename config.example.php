<?php
declare(strict_types=1);

/**
 * Copier en config.local.php HORS du repertoire web (chmod 600).
 * Ne pas publier ce fichier une fois des secrets inscrits dedans.
 * Le connecteur est independant de la configuration du portail.
 */
return [
    'base_url' => 'https://votre-instance.loxya.app',
    'allowed_host' => 'votre-instance.loxya.app',
    'username' => 'REMPLACER_IDENTIFIANT',
    'password' => 'REMPLACER_MOT_DE_PASSE',
    'connect_timeout' => 10,
    'timeout' => 30,
];
