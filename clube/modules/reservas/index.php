<?php
// Compatibilidade com o endereço legado /clube/modules/reservas/index.php.
// O módulo usa includes relativos, portanto preservamos o diretório de
// execução que ele teria quando acessado diretamente.
$moduloReservas = realpath(__DIR__ . '/../../../modules/reservas');
if ($moduloReservas === false) {
    http_response_code(500);
    exit('Módulo de reservas não encontrado.');
}
chdir($moduloReservas);
require_once 'index.php';
