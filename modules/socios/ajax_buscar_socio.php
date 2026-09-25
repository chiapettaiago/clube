<?php
require_once '../../config.php';

$id = $_POST['id'] ?? 0;

$stmt = $pdo->prepare("SELECT numero_titulo, nome FROM socios WHERE id = ?");
$stmt->execute([$id]);
$socio = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode($socio);
?>