<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Electraudio\Loxya\Client;
use Electraudio\Loxya\CurlTransport;
use Electraudio\Loxya\Factory;
use Electraudio\Loxya\HttpException;
use Electraudio\Loxya\HttpResponse;
use Electraudio\Loxya\LoxyaException;
use Electraudio\Loxya\ProtocolException;
use Electraudio\Loxya\Transport;
use Electraudio\Loxya\TransportException;
use Electraudio\Loxya\WriteNotConfirmedException;

/**
 * Controle hors reseau du contrat public candidat v0.1.0.
 * @param array<string,string> $parameters
 * @param array<string,mixed> $defaults
 */
function checkPublicMethod(
    string $class,
    string $method,
    bool $static,
    ?string $returnType,
    array $parameters,
    array $defaults = [],
): void {
    $reflection = new ReflectionMethod($class, $method);
    if (!$reflection->isPublic() || $reflection->isStatic() !== $static) {
        throw new RuntimeException('Visibilite ou methode statique modifiee : '.$class.'::'.$method);
    }

    $type = $reflection->getReturnType();
    if (($type === null ? null : (string)$type) !== $returnType) {
        throw new RuntimeException('Type de retour modifie : '.$class.'::'.$method);
    }

    $actualParameters = $reflection->getParameters();
    if (array_keys($parameters) !== array_map(
        static fn (ReflectionParameter $parameter): string => $parameter->getName(),
        $actualParameters,
    )) {
        throw new RuntimeException('Noms ou ordre des parametres modifies : '.$class.'::'.$method);
    }

    foreach ($actualParameters as $parameter) {
        $name = $parameter->getName();
        $actualType = $parameter->getType();
        if (($actualType === null ? null : (string)$actualType) !== $parameters[$name]) {
            throw new RuntimeException('Type de parametre modifie : '.$class.'::'.$method.' '.$name);
        }
        $hasDefault = array_key_exists($name, $defaults);
        if ($parameter->isDefaultValueAvailable() !== $hasDefault) {
            throw new RuntimeException('Parametre optionnel ou obligatoire modifie : '.$class.'::'.$method.' '.$name);
        }
        if ($hasDefault && $parameter->getDefaultValue() !== $defaults[$name]) {
            throw new RuntimeException('Valeur par defaut modifiee : '.$class.'::'.$method.' '.$name);
        }
    }
}

$methods = [
    [Client::class, '__construct', false, null,
        ['transport' => Transport::class, 'username' => 'string', 'password' => 'string']],
    [Client::class, 'session', false, 'array', []],
    [Client::class, 'companies', false, 'array',
        ['page' => 'int', 'limit' => 'int', 'search' => '?string'],
        ['page' => 1, 'limit' => 30, 'search' => null]],
    [Client::class, 'company', false, 'array', ['id' => 'int']],
    [Client::class, 'beneficiaries', false, 'array',
        ['page' => 'int', 'limit' => 'int', 'search' => '?string'],
        ['page' => 1, 'limit' => 30, 'search' => null]],
    [Client::class, 'beneficiary', false, 'array', ['id' => 'int']],
    [Client::class, 'createCompany', false, 'array', ['fields' => 'array']],
    [Client::class, 'updateCompany', false, 'array', ['id' => 'int', 'fields' => 'array']],
    [Client::class, 'createBeneficiary', false, 'array', ['fields' => 'array']],
    [Client::class, 'updateBeneficiary', false, 'array', ['id' => 'int', 'fields' => 'array']],
    [Client::class, 'setBeneficiaryCompany', false, 'array', [
        'beneficiaryId' => 'int', 'newCompanyId' => '?int', 'expectedCompanyId' => '?int',
    ]],
    [Factory::class, 'fromEnvironment', true, Client::class, []],
    [Factory::class, 'fromArray', true, Client::class, ['config' => 'array']],
    [Transport::class, 'send', false, HttpResponse::class, [
        'method' => 'string', 'path' => 'string', 'headers' => 'array', 'payload' => '?array',
    ]],
    [CurlTransport::class, '__construct', false, null, [
        'baseUrl' => 'string', 'allowedHost' => 'string',
        'connectTimeout' => 'int', 'timeout' => 'int',
    ], ['connectTimeout' => 10, 'timeout' => 30]],
    [HttpResponse::class, '__construct', false, null, ['status' => 'int', 'body' => 'string']],
    [HttpException::class, '__construct', false, null, [
        'status' => 'int', 'method' => 'string', 'path' => 'string',
    ]],
    [WriteNotConfirmedException::class, '__construct', false, null, [
        'operation' => 'string', 'resource' => 'string',
        'resourceId' => '?int', 'reason' => 'string', 'previous' => '?Throwable',
    ], ['previous' => null]],
];

foreach ($methods as $descriptor) {
    [$class, $method, $static, $return, $params] = $descriptor;
    checkPublicMethod($class, $method, $static, $return, $params, $descriptor[5] ?? []);
}

foreach ([HttpException::class, ProtocolException::class, TransportException::class] as $exception) {
    if (!is_subclass_of($exception, LoxyaException::class)) {
        throw new RuntimeException('Hierarchie des exceptions modifiee : '.$exception);
    }
}
if (!is_subclass_of(WriteNotConfirmedException::class, ProtocolException::class)) {
    throw new RuntimeException('Heritage WriteNotConfirmedException/ProtocolException modifie.');
}

foreach ([
    [HttpException::class, ['status' => 'int', 'method' => 'string', 'path' => 'string']],
    [WriteNotConfirmedException::class, [
        'operation' => 'string', 'resource' => 'string',
        'resourceId' => '?int', 'reason' => 'string',
    ]],
    [HttpResponse::class, ['status' => 'int', 'body' => 'string']],
] as [$class, $properties]) {
    foreach ($properties as $name => $type) {
        $property = new ReflectionProperty($class, $name);
        if (!$property->isPublic() || !$property->isReadOnly() || (string)$property->getType() !== $type) {
            throw new RuntimeException('Propriete publique modifiee : '.$class.'::$'.$name);
        }
    }
}

echo count($methods)." signatures publiques verifiees (sans acces reseau).\n";
