<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = $_GET['id'] ?? 0;

// Soft delete (apenas desativa)
$stmt = $pdo->prepare("UPDATE terceiros SET ativo = 0 WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php?msg=Excluído com sucesso!');
exit;
?>