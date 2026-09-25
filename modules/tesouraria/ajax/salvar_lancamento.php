<?php
require_once '../../../config.php';
//require_once '../../includes/auth.php';

$data_movimento = $_POST['data_movimento'];
$tipo = $_POST['tipo'];
$descricao = $_POST['descricao'];
$socio_id = !empty($_POST['socio_id']) ? $_POST['socio_id'] : null;
$valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
$forma_pagamento = $_POST['forma_pagamento'];
$documento = $_POST['documento'] ?? '';
$observacao = $_POST['observacao'] ?? '';

$stmt = $pdo->prepare("
    INSERT INTO caixa (data_movimento, tipo, descricao, socio_id, valor, forma_pagamento, documento, lancado_por, observacao, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado')
");
$stmt->execute([$data_movimento, $tipo, $descricao, $socio_id, $valor, $forma_pagamento, $documento, $_SESSION['usuario_id'], $observacao]);

header('Location: ../index.php?msg=sucesso');
?>