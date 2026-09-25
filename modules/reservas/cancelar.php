<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = $_GET['id'] ?? 0;

// Buscar reserva
$stmt = $pdo->prepare("SELECT * FROM reservas WHERE id = ?");
$stmt->execute([$id]);
$reserva = $stmt->fetch();

if(!$reserva) {
    header('Location: index.php');
    exit;
}

// Cancelar reserva
$stmt = $pdo->prepare("UPDATE reservas SET status = 'cancelada' WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php?msg=cancelado');
exit;
?>