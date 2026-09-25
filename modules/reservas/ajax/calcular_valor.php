<?php
require_once '../../../config.php';

header('Content-Type: application/json');

$espaco_id = $_POST['espaco_id'] ?? 0;
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fim = $_POST['hora_fim'] ?? '';

if(!$espaco_id || !$hora_inicio || !$hora_fim) {
    echo json_encode(['error' => 'Parâmetros incompletos', 'valor' => '0,00']);
    exit;
}

$stmt = $pdo->prepare("SELECT valor_hora FROM espacos WHERE id = ?");
$stmt->execute([$espaco_id]);
$espaco = $stmt->fetch();

if($espaco) {
    $inicio = strtotime($hora_inicio);
    $fim = strtotime($hora_fim);
    $horas = ($fim - $inicio) / 3600;
    $valor = $horas * $espaco['valor_hora'];
    echo json_encode(['valor' => number_format($valor, 2, ',', '.'), 'horas' => $horas]);
} else {
    echo json_encode(['error' => 'Espaço não encontrado', 'valor' => '0,00']);
}
?>
