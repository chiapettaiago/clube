<?php
require_once '../../../config.php';

// Permitir requisições AJAX
header('Content-Type: application/json');

$espaco_id = $_POST['espaco_id'] ?? $_GET['espaco_id'] ?? 0;
$data = $_POST['data'] ?? $_GET['data'] ?? date('Y-m-d');

if(!$espaco_id) {
    echo json_encode(['error' => 'Espaço não informado', 'horarios' => []]);
    exit;
}

// Buscar horário de funcionamento do espaço
$stmt = $pdo->prepare("SELECT horario_inicio, horario_fim, intervalo_minimo FROM espacos WHERE id = ? AND ativo = 1");
$stmt->execute([$espaco_id]);
$espaco = $stmt->fetch();

if(!$espaco) {
    echo json_encode(['error' => 'Espaço não encontrado', 'horarios' => []]);
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

// Marcar horários reservados
$reservados = [];
foreach($reservas as $r) {
    $inicio = strtotime($r['hora_inicio']);
    $fim = strtotime($r['hora_fim']);
    for($h = $inicio; $h < $fim; $h += 3600) {
        $reservados[date('H:i', $h)] = true;
    }
}

// Gerar lista de horários disponíveis
$horarios = [];
$inicio = strtotime($espaco['horario_inicio']);
$fim = strtotime($espaco['horario_fim']);
$intervalo = max(1, $espaco['intervalo_minimo']);

for($h = $inicio; $h < $fim; $h += ($intervalo * 3600)) {
    $hora = date('H:i', $h);
    $horarios[] = [
        'hora' => $hora,
        'reservado' => isset($reservados[$hora]),
        'label' => date('H:i', $h)
    ];
}

echo json_encode([
    'success' => true,
    'espaco' => $espaco['horario_inicio'] . ' - ' . $espaco['horario_fim'],
    'horarios' => $horarios,
    'total' => count($horarios)
]);
?>