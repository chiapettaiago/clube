<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$espaco_id = $_GET['espaco'] ?? 0;
$status = $_GET['status'] ?? 'todos';

// Construir query
$sql = "
    SELECT 
        r.data_reserva,
        e.nome as espaco_nome,
        e.tipo as espaco_tipo,
        s.nome as socio_nome,
        s.numero_titulo,
        r.hora_inicio,
        r.hora_fim,
        r.valor_total,
        r.status,
        r.codigo_reserva,
        r.data_solicitacao
    FROM reservas r
    JOIN espacos e ON r.espaco_id = e.id
    JOIN socios s ON r.socio_id = s.id
    WHERE DATE(r.data_reserva) BETWEEN ? AND ?
";

$params = [$data_inicio, $data_fim];

if($espaco_id > 0) {
    $sql .= " AND r.espaco_id = ?";
    $params[] = $espaco_id;
}

if($status != 'todos') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY r.data_reserva DESC, r.hora_inicio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

// Gerar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_reservas_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8

// Cabeçalho
fputcsv($output, [
    'Data', 'Espaço', 'Tipo', 'Sócio', 'Nº Título', 
    'Hora Início', 'Hora Fim', 'Horas', 'Valor Total', 
    'Status', 'Código Reserva', 'Data Solicitação'
]);

// Dados
foreach($reservas as $r) {
    $inicio = strtotime($r['hora_inicio']);
    $fim = strtotime($r['hora_fim']);
    $horas = ($fim - $inicio) / 3600;
    
    fputcsv($output, [
        date('d/m/Y', strtotime($r['data_reserva'])),
        $r['espaco_nome'],
        ucfirst($r['espaco_tipo']),
        $r['socio_nome'],
        $r['numero_titulo'],
        substr($r['hora_inicio'], 0, 5),
        substr($r['hora_fim'], 0, 5),
        number_format($horas, 1) . 'h',
        'R$ ' . number_format($r['valor_total'], 2, ',', '.'),
        ucfirst($r['status']),
        $r['codigo_reserva'],
        date('d/m/Y H:i', strtotime($r['data_solicitacao']))
    ]);
}

fclose($output);
exit;
?>