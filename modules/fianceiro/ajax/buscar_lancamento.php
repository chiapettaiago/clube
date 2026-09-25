<?php
require_once '../../../config.php';
require_once '../../includes/auth.php';

verificarPermissao('financeiro') or die('Acesso negado');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Lancamento nao informado.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT l.id, l.valor, l.descricao,
           s.nome AS socio_nome,
           COALESCE(sp.nome, s.nome) AS socio_financeiro_nome,
           COALESCE(NULLIF(s.socio_principal_id, 0), s.id) AS socio_financeiro_id
    FROM lancamentos l
    JOIN socios s ON s.id = l.socio_id
    LEFT JOIN socios sp ON sp.id = s.socio_principal_id
    WHERE l.id = ?
");
$stmt->execute([$id]);
$lancamento = $stmt->fetch();

if (!$lancamento) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Lancamento nao encontrado.']);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'id' => (int)$lancamento['id'],
    'valor' => (float)$lancamento['valor'],
    'descricao' => $lancamento['descricao'],
    'socio_nome' => $lancamento['socio_financeiro_nome'] ?: $lancamento['socio_nome'],
    'socio_original_nome' => $lancamento['socio_nome'],
    'socio_financeiro_id' => (int)$lancamento['socio_financeiro_id'],
]);
