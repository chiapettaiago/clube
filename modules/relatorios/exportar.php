<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

$tipo = $_GET['tipo'] ?? 'socios';
$tiposValidos = [
    'socios' => [
        'arquivo' => 'relatorio_socios',
        'cabecalho' => ['ID', 'Nome', 'Numero Titulo', 'CPF', 'Tipo Socio', 'Status'],
        'sql' => "
            SELECT s.id, s.nome, s.numero_titulo, s.cpf, ts.nome as tipo,
                   CASE WHEN s.ativo = 1 THEN 'Ativo' ELSE 'Inativo' END as status
            FROM socios s
            LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
            ORDER BY s.nome
        ",
    ],
    'financeiro' => [
        'arquivo' => 'relatorio_financeiro',
        'cabecalho' => ['ID', 'Socio', 'Descricao', 'Valor', 'Vencimento', 'Status'],
        'sql' => "
            SELECT f.id, s.nome AS socio, f.descricao, f.valor, f.data_vencimento AS vencimento, f.status
            FROM financeiro f
            JOIN socios s ON f.socio_id = s.id
            ORDER BY f.data_vencimento DESC
        ",
    ],
    'convites' => [
        'arquivo' => 'relatorio_convites',
        'cabecalho' => ['Socio', 'Mes/Ano', 'Total Convites', 'Utilizados', 'Disponiveis'],
        'sql' => "
            SELECT s.nome AS socio,
                   DATE_FORMAT(c.mes_referencia, '%m/%Y') AS mes_ano,
                   c.total_convites,
                   c.convites_utilizados AS utilizados,
                   (c.total_convites - c.convites_utilizados) AS disponiveis
            FROM convites c
            JOIN socios s ON c.socio_id = s.id
            ORDER BY c.mes_referencia DESC, s.nome
        ",
    ],
    'aniversariantes' => [
        'arquivo' => 'relatorio_aniversariantes',
        'cabecalho' => ['ID', 'Nome', 'CPF', 'Data Nascimento', 'Dia'],
        'sql' => "
            SELECT s.id, s.nome, s.cpf, s.data_nascimento, DAY(s.data_nascimento) AS dia
            FROM socios s
            WHERE s.ativo = 1 AND s.data_nascimento IS NOT NULL
            ORDER BY MONTH(s.data_nascimento), DAY(s.data_nascimento), s.nome
        ",
    ],
    'historico_financeiro' => [
        'arquivo' => 'relatorio_historico_financeiro',
        'cabecalho' => ['Data', 'Socio', 'Titulo', 'Acao', 'Operador', 'Valor', 'Observacao'],
        'sql' => "
            SELECT hf.data_evento, s.nome AS socio, s.numero_titulo AS titulo, hf.acao,
                   COALESCE(u.nome, 'Sistema') AS operador, hf.valor, hf.observacao
            FROM historico_financeiro hf
            JOIN socios s ON s.id = hf.socio_id
            LEFT JOIN usuarios u ON u.id = hf.usuario_id
            ORDER BY hf.data_evento DESC
        ",
    ],
];

if (!isset($tiposValidos[$tipo])) {
    $tipo = 'socios';
}

$def = $tiposValidos[$tipo];
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $def['arquivo'] . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, $def['cabecalho']);
$stmt = $pdo->query($def['sql']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, array_values($row));
}

fclose($output);
exit;
