<?php

declare(strict_types=1);

/*
 * Carrega as fixtures do contrato de segurança do roteamento (docs/ROTEAMENTO.md).
 * O namespace App\ não está no autoload: as fixtures são carregadas aqui.
 */

foreach ([
    'BaseController',
    'ExportActions',
    'UserController',
    'UseradminController',
    'Report2024Controller',
    'AuditController',
    'SharedController',
    'StatusController',
    'PrivateController',
    'Mailer',
    'ServiceController',
    'ExternalChildController',
] as $fixture) {
    require_once __DIR__ . '/' . $fixture . '.php';
}
