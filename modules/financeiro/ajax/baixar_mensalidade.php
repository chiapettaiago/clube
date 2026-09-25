<?php
require_once '../../../config.php';
require_once '../../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$lancamentoId = (int)($_POST['lancamento_id'] ?? 0);
if ($lancamentoId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Lançamento inválido.']);
}

if (isset($_POST['estornar_lancamento'])) {
    try {
        $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pendente', data_pagamento = NULL, forma_pagamento = NULL WHERE id = ?");
        $stmt->execute([$lancamentoId]);
        jsonResponse(['success' => true, 'message' => 'Pagamento estornado com sucesso.']);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'message' => 'Não foi possível estornar o pagamento.']);
    }
}

try {
    $valorPago = str_replace(',', '.', trim($_POST['valor_pago'] ?? ''));
    $valorPago = $valorPago !== '' ? (float)$valorPago : null;
    $observacao = trim($_POST['observacao'] ?? '');
    $dataPagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $formaPagamento = $_POST['forma_pagamento'] ?? 'dinheiro';

    if ($valorPago !== null) {
        $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pago', valor = ?, data_pagamento = ?, forma_pagamento = ?, observacao = CONCAT(IFNULL(observacao, ''), ' ', ?) WHERE id = ?");
        $stmt->execute([$valorPago, $dataPagamento, $formaPagamento, $observacao, $lancamentoId]);
    } else {
        $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pago', data_pagamento = ?, forma_pagamento = ?, observacao = CONCAT(IFNULL(observacao, ''), ' ', ?) WHERE id = ?");
        $stmt->execute([$dataPagamento, $formaPagamento, $observacao, $lancamentoId]);
    }

    jsonResponse(['success' => true, 'message' => 'Mensalidade baixada com sucesso.']);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Erro ao processar a baixa.']);
}
