<?php
require_once '../../config.php';

$mes = $_GET['mes'] ?? date('m');

$stmt = $pdo->prepare("
    SELECT id, nome, data_nascimento, foto, telefone, email_socio
    FROM socios 
    WHERE ativo = 1 
    AND data_nascimento IS NOT NULL
    AND MONTH(data_nascimento) = ?
    ORDER BY DAY(data_nascimento)
");
$stmt->execute([$mes]);
$aniversariantes = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($aniversariantes);
?>