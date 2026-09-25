<?php
require_once '../../../config.php';

$espaco_id = $_POST['espaco_id'] ?? 0;
$data = $_POST['data'] ?? date('Y-m-d');
$hora_inicio = $_POST['hora_inicio'] ?? '';

$stmt = $pdo->prepare("SELECT horario_fim FROM espacos WHERE id = ?");
$stmt->execute([$espaco_id]);
$espaco = $stmt->fetch();

if(!$espaco) {
    echo json_encode(['horarios' => []]);
    exit;
}

// Buscar reservas existentes
$stmt = $pdo->prepare("
    SELECT hora_inicio, hora_fim 
    FROM reservas 
    WHERE espaco_id = ? AND data_reserva = ? 
    AND status IN ('confirmada', 'pendente')
");
$stmt->execute([$espaco_id, $data]);
$reservas = $stmt->fetchAll();

$reservados = [];
foreach($reservas as $r) {
    $inicio = strtotime($r['hora_inicio']);
    $fim = strtotime($r['hora_fim']);
    for($h = $inicio; $h < $fim; $h += 3600) {
        $reservados[date('H:i', $h)] = true;
    }
}

// Gerar horários disponíveis APÓS a hora de início
$horarios = [];
$fim = strtotime($espaco['horario_fim']);
$horaInicioTs = strtotime($hora_inicio);

for($h = $horaInicioTs + 3600; $h <= $fim; $h += 3600) {
    $hora = date('H:i', $h);
    if(!isset($reservados[$hora])) {
        $horarios[] = $hora;
    }
}

echo json_encode(['horarios' => $horarios]);
?>