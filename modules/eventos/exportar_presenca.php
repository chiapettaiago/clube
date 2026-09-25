<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$evento_id = $_GET['id'] ?? 0;

// Buscar evento
$evento = $pdo->prepare("SELECT titulo FROM eventos WHERE id = ?");
$evento->execute([$evento_id]);
$evento = $evento->fetch();

if(!$evento) {
    die('Evento não encontrado');
}

// Buscar inscrições
$inscricoes = $pdo->prepare("
    SELECT i.*, s.nome as socio_nome, s.numero_titulo, s.cpf, s.telefone
    FROM inscricoes_eventos i
    JOIN socios s ON i.socio_id = s.id
    WHERE i.evento_id = ? AND i.status = 'confirmada'
    ORDER BY s.nome
");
$inscricoes->execute([$evento_id]);
$inscricoes = $inscricoes->fetchAll();

// Gerar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="lista_presenca_' . $evento_id . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8

fputcsv($output, ['Nome', 'Nº Título', 'CPF', 'Telefone', 'Status Presença', 'Data/Hora Presença']);

foreach($inscricoes as $insc) {
    fputcsv($output, [
        $insc['socio_nome'],
        $insc['numero_titulo'],
        $insc['cpf'],
        $insc['telefone'],
        $insc['presenca_confirmada'] ? 'Presente' : 'Ausente',
        $insc['data_presenca'] ? date('d/m/Y H:i', strtotime($insc['data_presenca'])) : '-'
    ]);
}

fclose($output);
exit;
?>