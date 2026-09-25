<?php
require_once 'config.php';

$tokenData = validarToken();
$socio_id = $tokenData['socio_id'];

// Buscar dados do sócio e família
$stmt = $pdo->prepare("
    SELECT s.familia_id, s.convites_por_mes,
           (SELECT COUNT(*) FROM socios WHERE familia_id = s.familia_id AND ativo = 1) as total_membros
    FROM socios s
    WHERE s.id = ?
");
$stmt->execute([$socio_id]);
$socio = $stmt->fetch();

// Buscar convites do mês
$mesAtual = date('Y-m-01');
$extrasAtivos = calcularConvitesExtrasDisponiveis($pdo, date('Y-m-d'));
$stmt = $pdo->prepare("
    SELECT total_convites, convites_utilizados 
    FROM convites_familia 
    WHERE familia_id = ? AND mes_referencia = ?
");
$stmt->execute([$socio['familia_id'], $mesAtual]);
$convites = $stmt->fetch();

$total = $convites['total_convites'] ?? $socio['convites_por_mes'];
$usados = $convites['convites_utilizados'] ?? 0;
$disponiveis = $total - $usados;
$disponiveisComExtras = $disponiveis + (int)$extrasAtivos['total'];

// Buscar histórico de uso
$stmt = $pdo->prepare("
    SELECT cu.*, s.nome as socio_nome
    FROM convites_uso cu
    JOIN socios s ON cu.socio_id = s.id
    WHERE cu.familia_id = ?
    ORDER BY cu.data_uso DESC
    LIMIT 20
");
$stmt->execute([$socio['familia_id']]);
$historico = $stmt->fetchAll();

logAPI('convites.php', 'GET', $socio_id, 200);

respostaJSON([
    'mes_referencia' => date('m/Y'),
    'total_convites' => $total,
    'convites_utilizados' => $usados,
    'convites_disponiveis' => $disponiveis,
    'convites_extras_ativos' => $extrasAtivos['disponiveis'],
    'convites_disponiveis_com_extras' => $disponiveisComExtras,
    'regras_atuais' => array_map(static function (array $regra): array {
        return [
            'nome' => $regra['nome'],
            'tipo_convite' => $regra['tipo_convite'],
            'quantidade' => (int)$regra['quantidade'],
            'data_inicio' => $regra['data_inicio'],
            'data_fim' => $regra['data_fim'],
            'observacao' => $regra['observacao'],
        ];
    }, $extrasAtivos['regras']),
    'total_membros' => $socio['total_membros'],
    'historico' => $historico
]);
?>
