<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = $_GET['id'] ?? 0;

// Verificar se existem reservas para este espaço
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE espaco_id = ?");
$stmt->execute([$id]);
$totalReservas = $stmt->fetchColumn();

if($totalReservas > 0) {
    // Se tem reservas, apenas desativar
    $stmt = $pdo->prepare("UPDATE espacos SET ativo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "Espaço desativado pois possui reservas vinculadas.";
} else {
    // Se não tem reservas, excluir completamente
    $stmt = $pdo->prepare("DELETE FROM espacos WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "Espaço excluído com sucesso!";
}

header('Location: configurar.php?msg=' . urlencode($msg));
exit;
?>