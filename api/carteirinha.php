<?php
require_once 'config.php';

$tokenData = validarToken();
$codigo = $_GET['codigo'] ?? '';
$registrar_uso = $_GET['registrar_uso'] ?? false;

if (empty($codigo)) {
    erroJSON('Codigo nao informado', 400);
}

$stmt = $pdo->prepare("
    SELECT s.*, ts.nome as tipo_socio, ts.cor_carteirinha,
           s.familia_id,
           (SELECT COUNT(*) FROM socios WHERE familia_id = s.familia_id AND ativo = 1) as total_membros
    FROM socios s
    LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE (s.numero_titulo = ? OR s.id = ?) AND s.ativo = 1
");
$stmt->execute([$codigo, $codigo]);
$socio = $stmt->fetch();

if (!$socio) {
    erroJSON('Socio nao encontrado ou inativo', 404);
}

$mesAtual = date('Y-m-01');
$stmtConv = $pdo->prepare("
    SELECT total_convites, convites_utilizados
    FROM convites_familia
    WHERE familia_id = ? AND mes_referencia = ?
");
$stmtConv->execute([$socio['familia_id'], $mesAtual]);
$convites = $stmtConv->fetch();

$totalConvites = $convites['total_convites'] ?? $socio['convites_por_mes'];
$convitesUsados = $convites['convites_utilizados'] ?? 0;
$extrasAtivos = calcularConvitesExtrasAtivos($pdo, date('Y-m-d'));
$convitesDisponiveis = ($totalConvites - $convitesUsados) + (int)$extrasAtivos['total'];

garantirTabelaSuspensoesSocios($pdo);

if (socioEstaSuspenso($socio)) {
    $payload = [
        'status' => 'negado',
        'nome' => $socio['nome'],
        'tipo_socio' => $socio['tipo_socio'],
        'numero_titulo' => $socio['numero_titulo'],
        'foto' => $socio['foto'] ? $base_url . 'assets/uploads/' . $socio['foto'] : null,
        'convites_disponiveis' => $convitesDisponiveis,
        'convites_totais_familia' => $totalConvites,
        'membros_familia' => $socio['total_membros'],
        'mensagem' => 'Acesso suspenso',
        'status_text' => 'SUSPENSO',
        'motivo_suspensao' => $socio['motivo_suspensao'] ?? null,
        'suspenso_ate' => $socio['suspenso_ate'] ?? null,
    ];
    respostaJSON($payload);
}

$configFinanceira = obterConfiguracaoFinanceira($pdo);
$configDiaVencimento = (int)($configFinanceira['financeiro_dia_vencimento'] ?? 12);
$configQtdAlerta = (int)($configFinanceira['financeiro_qtd_parcelas_alerta'] ?? 2);
$configMensagemAlerta = trim($configFinanceira['financeiro_mensagem_alerta'] ?? '');

$alvoFinanceiro = (int)($socio['socio_principal_id'] ?: $socio['id']);
$atrasoFinanceiro = calcularMensalidadesEmAtraso($pdo, $alvoFinanceiro, $configDiaVencimento);
$qtdParcelasAtrasadas = (int)$atrasoFinanceiro['qtd_parcelas'];
$temDebito = $qtdParcelasAtrasadas > 0;
$bloqueiaAcesso = $configQtdAlerta > 0 && $qtdParcelasAtrasadas >= $configQtdAlerta;

$validadeOk = true;
if ($socio['data_validade'] && $socio['data_validade'] < date('Y-m-d')) {
    $validadeOk = false;
}

if ($temDebito) {
    $status = $bloqueiaAcesso ? 'negado' : 'alerta';
    $mensagem = $configMensagemAlerta ?: 'O responsavel deve verificar as mensalidades em atraso.';
} elseif (!$validadeOk) {
    $status = 'negado';
    $mensagem = 'Carteirinha vencida';
} elseif ($convitesDisponiveis <= 0) {
    $status = 'negado';
    $mensagem = 'Sem convites disponiveis';
} else {
    $status = 'liberado';
    $mensagem = 'Acesso liberado';

    if ($registrar_uso == 'true') {
        $stmtUpdate = $pdo->prepare("
            UPDATE convites_familia
            SET convites_utilizados = convites_utilizados + 1
            WHERE familia_id = ? AND mes_referencia = ?
        ");
        $stmtUpdate->execute([$socio['familia_id'], $mesAtual]);

        $stmtReg = $pdo->prepare("
            INSERT INTO convites_uso (socio_id, familia_id, convite_usado_para, tipo_uso)
            VALUES (?, ?, ?, 'app')
        ");
        $stmtReg->execute([$socio['id'], $socio['familia_id'], 'Entrada via app']);

        $convitesDisponiveis--;
        $mensagem = "Acesso liberado. Convites restantes: $convitesDisponiveis";
    }
}

$payload = [
    'status' => $status,
    'nome' => $socio['nome'],
    'tipo_socio' => $socio['tipo_socio'],
    'numero_titulo' => $socio['numero_titulo'],
    'foto' => $socio['foto'] ? $base_url . 'assets/uploads/' . $socio['foto'] : null,
    'convites_disponiveis' => $convitesDisponiveis,
    'convites_totais_familia' => $totalConvites,
    'convites_extras_ativos' => (int)$extrasAtivos['total'],
    'membros_familia' => $socio['total_membros'],
    'mensagem' => $mensagem,
    'tipo_registro' => $socio['socio_principal_id'] ? 'dependente' : 'socio',
];

if ($temDebito) {
    $payload['parcelas_em_atraso'] = $qtdParcelasAtrasadas;
    $payload['dia_vencimento_configurado'] = $configDiaVencimento;
    $payload['data_limite_financeira'] = $atrasoFinanceiro['data_limite'];
    $payload['status_text'] = $bloqueiaAcesso ? 'EM DEBITO' : 'ALERTA FINANCEIRO';
}

respostaJSON($payload);
