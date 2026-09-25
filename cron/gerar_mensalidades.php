<?php
require_once '../config.php';

$referencia = $argv[1] ?? date('Y-m-01');
$resultado = gerarMensalidadesAutomaticas($pdo, $referencia);

echo "Mensalidades geradas: {$resultado['criados']}\n";
echo "Ja existentes: {$resultado['ignorados']}\n";
echo "Referencia: {$resultado['referencia']}\n";
echo "Vencimento: {$resultado['vencimento']}\n";
