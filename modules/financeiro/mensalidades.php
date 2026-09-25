<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$config = obterConfiguracaoFinanceira($pdo);
$referencia = $_GET['referencia'] ?? date('Y-m-01');
$buscaNome = trim((string)($_GET['busca_nome'] ?? ''));
$resultado = null;
$msgBaixa = null;

if (isset($_POST['gerar_agora'])) {
    $resultado = gerarMensalidadesAutomaticas($pdo, $referencia);
}

if (isset($_POST['baixar_lancamento'])) {
    $lancamentoId = (int)($_POST['lancamento_id'] ?? 0);
    if ($lancamentoId > 0) {
        $valorPago = str_replace(',', '.', trim($_POST['valor_pago'] ?? ''));
        $valorPago = $valorPago !== '' ? (float)$valorPago : null;
        $observacaoBaixa = trim($_POST['observacao'] ?? '');
        $ajusteTexto = '';
        if ($valorPago !== null) {
            $stmtValorAtual = $pdo->prepare("SELECT valor FROM lancamentos WHERE id = ?");
            $stmtValorAtual->execute([$lancamentoId]);
            $valorAtual = (float)$stmtValorAtual->fetchColumn();
            $dif = $valorPago - $valorAtual;
            if (abs($dif) >= 0.01) {
                $ajusteTexto = $dif > 0
                    ? ' | Acréscimo/Juros de R$ ' . number_format($dif, 2, ',', '.')
                    : ' | Desconto de R$ ' . number_format(abs($dif), 2, ',', '.');
            }
        }

        $novoValor = $valorPago !== null ? $valorPago : null;
        $sql = "UPDATE lancamentos SET status = 'pago', data_pagamento = ?, forma_pagamento = ?, observacao = CONCAT(IFNULL(observacao, ''), ' ', ?, ?) WHERE id = ?";
        if ($novoValor !== null) {
            $sql = "UPDATE lancamentos SET status = 'pago', valor = ?, data_pagamento = ?, forma_pagamento = ?, observacao = CONCAT(IFNULL(observacao, ''), ' ', ?, ?) WHERE id = ?";
        }
        $stmt = $pdo->prepare($sql);
        if ($novoValor !== null) {
            $stmt->execute([
                $novoValor,
                $_POST['data_pagamento'] ?? date('Y-m-d'),
                $_POST['forma_pagamento'] ?? 'dinheiro',
                $observacaoBaixa,
                $ajusteTexto,
                $lancamentoId
            ]);
        } else {
            $stmt->execute([
                $_POST['data_pagamento'] ?? date('Y-m-d'),
                $_POST['forma_pagamento'] ?? 'dinheiro',
                $observacaoBaixa,
                $ajusteTexto,
                $lancamentoId
            ]);
        }
        $msgBaixa = 'Mensalidade baixada com sucesso.';
    }
}

$mesRef = date('m/Y', strtotime($referencia));
$dia = max(1, min(31, (int)($config['financeiro_dia_vencimento'] ?? 12)));
$valorMensalidadePadrao = (float)str_replace(',', '.', $config['financeiro_valor_mensalidade'] ?? '0');
$filtroTipo = $_GET['filtro_tipo'] ?? 'todos';
$base = new DateTimeImmutable($referencia);
$vencimentoPrevisto = $base->setDate((int)$base->format('Y'), (int)$base->format('m'), min($dia, (int)$base->format('t')))->format('d/m/Y');

$estimativaReajuste = [
    'ativo' => false,
    'total_extra' => 0.0,
    'titulares' => 0,
];
try {
    $estimativaReajuste['ativo'] = (int)($config['financeiro_reajuste_dependente_ativo'] ?? 0) === 1;
    if ($estimativaReajuste['ativo']) {
        $titulares = $pdo->query("
            SELECT id
            FROM socios
            WHERE ativo = 1
              AND (socio_principal_id IS NULL OR socio_principal_id = 0)
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($titulares as $titularId) {
            $calc = calcularReajusteMensalidadeDependentes($pdo, (int)$titularId, $referencia);
            if (!empty($calc['itens'])) {
                $estimativaReajuste['total_extra'] += (float)$calc['total_extra'];
                $estimativaReajuste['titulares']++;
            }
        }
    }
} catch (Throwable $e) {
    $estimativaReajuste = [
        'ativo' => false,
        'total_extra' => 0.0,
        'titulares' => 0,
    ];
}

$pendentes = $pdo->prepare("
    SELECT
        MIN(l.id) AS lancamento_id,
        COALESCE(NULLIF(s.socio_principal_id, 0), s.id) AS socio_financeiro_id,
        sp.nome AS socio_financeiro_nome,
        sp.numero_titulo AS socio_financeiro_titulo,
        COUNT(DISTINCT s.id) AS qtde_socios,
        COUNT(l.id) AS qtde_lancamentos,
        SUM(l.valor) AS valor_total,
        MIN(l.data_vencimento) AS primeiro_vencimento,
        GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS socios_originais,
        GROUP_CONCAT(DISTINCT l.descricao ORDER BY l.data_vencimento ASC SEPARATOR ' | ') AS descricoes
    FROM lancamentos l
    JOIN socios s ON s.id = l.socio_id
    LEFT JOIN socios sp ON sp.id = COALESCE(NULLIF(s.socio_principal_id, 0), s.id)
    WHERE l.tipo = 'receita'
      AND l.status = 'pendente'
      AND DATE_FORMAT(l.data_vencimento, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
    GROUP BY socio_financeiro_id, sp.nome, sp.numero_titulo
    ORDER BY primeiro_vencimento ASC, sp.nome ASC
");
$pendentes->execute([$referencia]);
$pendentes = $pendentes->fetchAll();
foreach ($pendentes as &$pendente) {
    $partes = array_values(array_filter(array_map('trim', explode(' | ', (string)($pendente['descricoes'] ?? '')))));
    $pendente['descricao_resumo'] = $partes[0] ?? '';
    $pendente['descricao_extra'] = count($partes) > 1 ? implode(' | ', array_slice($partes, 1)) : '';
}
unset($pendente);

$pendentesFiltrados = array_values(array_filter($pendentes, function (array $p) use ($filtroTipo): bool {
    if ($filtroTipo === 'titular') {
        return (int)$p['qtde_socios'] <= 1;
    }
    if ($filtroTipo === 'dependente') {
        return (int)$p['qtde_socios'] > 1;
    }
    return true;
}));

if ($buscaNome !== '') {
    $termoBusca = mb_strtolower($buscaNome);
    $pendentesFiltrados = array_values(array_filter($pendentesFiltrados, function (array $p) use ($termoBusca): bool {
        $texto = mb_strtolower((string)($p['socio_financeiro_nome'] ?? '') . ' ' . (string)($p['socios_originais'] ?? '') . ' ' . (string)($p['descricoes'] ?? ''));
        return str_contains($texto, $termoBusca);
    }));
}

$totalFiltrados = count($pendentesFiltrados);
$totalTitularesFiltrados = 0;
$totalDependentesFiltrados = 0;
foreach ($pendentesFiltrados as $p) {
    if ((int)$p['qtde_socios'] > 1) {
        $totalDependentesFiltrados++;
    } else {
        $totalTitularesFiltrados++;
    }
}

$historicoFinanceiro = $pdo->prepare("
    SELECT hf.*, s.nome as socio_nome, u.nome as usuario_nome
    FROM historico_financeiro hf
    JOIN socios s ON s.id = hf.socio_id
    LEFT JOIN usuarios u ON u.id = hf.usuario_id
    ORDER BY hf.data_evento DESC
    LIMIT 12
");
$historicoFinanceiro->execute();
$historicoFinanceiro = $historicoFinanceiro->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensalidades - Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Mensalidades</h4>
                <small>Geracao automatica e conferencia do mes <?= htmlspecialchars($mesRef) ?></small>
            </div>
            <a href="configurar.php" class="btn btn-light btn-sm">Configurar</a>
        </div>
        <div class="card-body">
            <?php if ($resultado): ?>
                <div class="alert alert-success">
                    <?= (int)$resultado['criados'] ?> mensalidade(s) gerada(s), <?= (int)$resultado['ignorados'] ?> ja existia(m).
                </div>
            <?php endif; ?>
            <?php if ($msgBaixa): ?>
                <div class="alert alert-info"><?= htmlspecialchars($msgBaixa) ?></div>
            <?php endif; ?>
            <div class="alert alert-secondary">
                <strong>Base preparada:</strong> o reajuste por dependente e faixa etária já pode ser configurado em Financeiro, mas ainda não é aplicado automaticamente na cobrança.
            </div>
            <div class="alert <?= $estimativaReajuste['ativo'] ? 'alert-warning' : 'alert-secondary' ?>">
                <?php if ($estimativaReajuste['ativo']): ?>
                    <strong>Estimativa futura:</strong> R$ <?= number_format((float)$estimativaReajuste['total_extra'], 2, ',', '.') ?> em adicional potencial para <?= (int)$estimativaReajuste['titulares'] ?> titular(es) com dependentes acima da idade mínima configurada.
                <?php else: ?>
                    <strong>Estimativa futura preparada:</strong> a regra por dependente e faixa etária está pronta, mas continua desativada.
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded">
                        <small class="text-muted">Vencimento previsto</small>
                        <h5 class="mb-0"><?= htmlspecialchars($vencimentoPrevisto) ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded">
                        <small class="text-muted">Valor padrao</small>
                        <h5 class="mb-0">R$ <?= number_format($valorMensalidadePadrao, 2, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded">
                        <small class="text-muted">Parcelas em aberto neste mes</small>
                        <h5 class="mb-0"><?= count($pendentes) ?></h5>
                    </div>
                </div>
            </div>

            <form method="POST" class="mb-4">
                <input type="hidden" name="gerar_agora" value="1">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <label class="form-label mb-0">Referencia</label>
                    <input type="month" name="referencia" class="form-control" value="<?= htmlspecialchars(date('Y-m', strtotime($referencia))) ?>" style="max-width: 180px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bolt me-1"></i> Gerar mensalidades
                    </button>
                    <a href="mensalidades.php?referencia=<?= htmlspecialchars(date('Y-m-01')) ?>" class="btn btn-outline-secondary">Mes atual</a>
                </div>
            </form>

            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Automacao Windows</strong>
                    <div class="small">Use o agendamento para executar a geracao todo dia 1 no seu XAMPP.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="agendamento.php" class="btn btn-outline-primary btn-sm">Ver instrucoes</a>
                    <a href="../../scripts/AgendarMensalidades.bat" class="btn btn-outline-dark btn-sm">Ver arquivo de agendamento</a>
                </div>
            </div>

            <form method="GET" class="mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Referencia</label>
                        <input type="month" name="referencia" class="form-control" value="<?= htmlspecialchars(date('Y-m', strtotime($referencia))) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filtro</label>
                        <select name="filtro_tipo" class="form-select">
                            <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="titular" <?= $filtroTipo === 'titular' ? 'selected' : '' ?>>Titular</option>
                            <option value="dependente" <?= $filtroTipo === 'dependente' ? 'selected' : '' ?>>Dependentes</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Buscar nome</label>
                        <input type="text" name="busca_nome" class="form-control" placeholder="Digite um nome" value="<?= htmlspecialchars($buscaNome) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">Aplicar filtro</button>
                    </div>
                    <div class="col-md-12">
                        <a href="mensalidades.php?referencia=<?= htmlspecialchars(date('Y-m', strtotime($referencia))) ?>" class="btn btn-outline-secondary">Limpar filtro</a>
                    </div>
                </div>
            </form>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white">
                        <small class="text-muted">Total filtrado</small>
                        <h5 class="mb-0"><?= (int)$totalFiltrados ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white">
                        <small class="text-muted">Titulares</small>
                        <h5 class="mb-0"><?= (int)$totalTitularesFiltrados ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white">
                        <small class="text-muted">Famílias com dependentes</small>
                        <h5 class="mb-0"><?= (int)$totalDependentesFiltrados ?></h5>
                    </div>
                </div>
            </div>

            <?php if (!empty($pendentesFiltrados)): ?>
                <div class="row g-3">
                    <?php foreach ($pendentesFiltrados as $p): ?>
                        <div class="col-12 col-lg-6">
                            <div class="card h-100 shadow-sm border-0 <?= (int)$p['qtde_socios'] > 1 ? 'border-start border-4 border-warning' : 'border-start border-4 border-primary' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($p['socio_financeiro_nome']) ?></h5>
                                            <div class="text-muted small">Título <?= htmlspecialchars($p['socio_financeiro_titulo']) ?></div>
                                        </div>
                                        <span class="badge <?= (int)$p['qtde_socios'] > 1 ? 'bg-warning text-dark' : 'bg-primary' ?>">Pendente</span>
                                    </div>
                                    <div class="mt-3 small">
                                        <div><strong>Socio(s):</strong> <?= htmlspecialchars($p['socios_originais']) ?></div>
                                        <div><strong>Descrição:</strong> <?= htmlspecialchars($p['descricao_resumo'] !== '' ? $p['descricao_resumo'] : '-') ?></div>
                                        <?php if (!empty($p['descricao_extra'])): ?>
                                            <div class="text-muted">+ <?= htmlspecialchars($p['descricao_extra']) ?></div>
                                        <?php endif; ?>
                                        <div><strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($p['primeiro_vencimento'])) ?></div>
                                        <div><strong>Valor:</strong> R$ <?= number_format((float)$p['valor_total'], 2, ',', '.') ?></div>
                                        <?php if ((int)$p['qtde_lancamentos'] > 1): ?>
                                            <div class="text-muted mt-1"><?= (int)$p['qtde_lancamentos'] ?> lançamentos agrupados em <?= (int)$p['qtde_socios'] ?> sócio(s)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalBaixar" data-id="<?= (int)$p['lancamento_id'] ?>" data-nome="<?= htmlspecialchars($p['socio_financeiro_nome']) ?>" data-valor-base="<?= number_format($valorMensalidadePadrao, 2, '.', '') ?>" data-valor-original="<?= number_format((float)$p['valor_total'], 2, '.', '') ?>">
                                        Baixar mensalidade
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center py-4">Nenhuma mensalidade encontrada para o filtro selecionado.</div>
            <?php endif; ?><div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Histórico financeiro auditado</strong>
                    <span class="badge bg-info text-dark">Baixas e estornos</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Socio</th>
                                    <th>Descricao</th>
                                    <th>Data</th>
                                    <th>Acao</th>
                                    <th>Operador</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historicoFinanceiro as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['socio_nome']) ?></td>
                                        <td><?= htmlspecialchars($h['descricao'] ?? '-') ?></td>
                                        <td><?= !empty($h['data_evento']) ? date('d/m/Y H:i', strtotime($h['data_evento'])) : '-' ?></td>
                                        <td>
                                            <?php if ($h['acao'] === 'baixa'): ?>
                                                <span class="badge bg-success">Baixa</span>
                                            <?php elseif ($h['acao'] === 'estorno'): ?>
                                                <span class="badge bg-warning text-dark">Estorno</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($h['acao']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($h['usuario_nome'] ?? 'Sistema') ?></td>
                                        <td>R$ <?= number_format((float)$h['valor'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if ($h['acao'] === 'baixa'): ?>
                                                <span class="badge bg-success">Pago</span>
                                            <?php elseif ($h['acao'] === 'estorno'): ?>
                                                <span class="badge bg-warning text-dark">Estornado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Registrado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($h['acao'] === 'baixa'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-estornar" data-id="<?= (int)$h['lancamento_id'] ?>" data-nome="<?= htmlspecialchars($h['socio_nome']) ?>">
                                                    Estornar
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($historicoFinanceiro)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted">Nenhum evento financeiro registrado ainda.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBaixar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formBaixar">
                <input type="hidden" name="baixar_lancamento" value="1">
                <input type="hidden" name="lancamento_id" id="modalLancamentoId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Baixar mensalidade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Socio</label>
                        <input type="text" id="modalLancamentoNome" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <input type="text" id="modalLancamentoValor" name="valor_pago" class="form-control" inputmode="decimal" placeholder="0,00">
                    <small class="text-muted">Você pode manter o valor original ou ajustar com juros/desconto na hora da baixa.</small>
                        <div class="small mt-2 text-muted">
                            <div><strong>Valor original do lançamento:</strong> <span id="modalLancamentoValorOriginal">-</span></div>
                            <div><strong>Valor base configurado:</strong> <span id="modalLancamentoValorBase">-</span></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">Percentual</small>
                                <select class="form-select form-select-sm" id="percentualAjuste" style="width: 110px;">
                                    <option value="2">2%</option>
                                    <option value="5" selected>5%</option>
                                    <option value="10">10%</option>
                                    <option value="15">15%</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm" id="btnJurosRapido">Aplicar juros</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btnDescontoRapido">Aplicar desconto</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnValorOriginal">Voltar ao valor original</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de pagamento</label>
                        <select name="forma_pagamento" class="form-select" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartao</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="boleto">Boleto</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data do pagamento</label>
                        <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observacao</label>
                        <textarea name="observacao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnConfirmarBaixa">Confirmar baixa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="toastMensagem" class="toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header" id="toastHeader">
            <i class="fas fa-circle-check me-2 text-success" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitulo">Sucesso</strong>
            <small class="text-muted">agora</small>
            <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastTexto">Mensagem</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('modalBaixar').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('modalLancamentoId').value = button.getAttribute('data-id');
    document.getElementById('modalLancamentoNome').value = button.getAttribute('data-nome');
    const base = parseFloat(button.getAttribute('data-valor-base') || '0') || 0;
    const original = parseFloat(button.getAttribute('data-valor-original') || '0') || 0;
    valorOriginalModal = base;
    document.getElementById('modalLancamentoValor').value = base.toFixed(2).replace('.', ',');
    document.getElementById('modalLancamentoValorOriginal').textContent = 'R$ ' + original.toFixed(2).replace('.', ',');
    document.getElementById('modalLancamentoValorBase').textContent = 'R$ ' + base.toFixed(2).replace('.', ',');
});

const valorInput = document.getElementById('modalLancamentoValor');
let valorOriginalModal = 0;
valorInput.addEventListener('input', function () {
    let value = this.value.replace(/\D/g, '');
    if (value === '') {
        this.value = '';
        return;
    }
    value = (parseInt(value, 10) / 100).toFixed(2);
    this.value = value.replace('.', ',');
});

function parseValorBR(valor) {
    const normalizado = String(valor || '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const num = parseFloat(normalizado);
    return Number.isFinite(num) ? num : 0;
}

function setValorBR(valor) {
    valorInput.value = Number(valor).toFixed(2).replace('.', ',');
}

document.getElementById('btnJurosRapido').addEventListener('click', function () {
    const atual = parseValorBR(valorInput.value || valorOriginalModal);
    const pct = parseFloat(document.getElementById('percentualAjuste').value) || 5;
    setValorBR(atual * (1 + (pct / 100)));
});

document.getElementById('btnDescontoRapido').addEventListener('click', function () {
    const atual = parseValorBR(valorInput.value || valorOriginalModal);
    const pct = parseFloat(document.getElementById('percentualAjuste').value) || 5;
    setValorBR(atual * (1 - (pct / 100)));
});

document.getElementById('btnValorOriginal').addEventListener('click', function () {
    setValorBR(valorOriginalModal);
});

function mostrarToast(mensagem, tipo) {
    const toastEl = document.getElementById('toastMensagem');
    const toastHeader = document.getElementById('toastHeader');
    const toastIcon = document.getElementById('toastIcon');
    const toastTitulo = document.getElementById('toastTitulo');
    const toastTexto = document.getElementById('toastTexto');
    toastTexto.textContent = mensagem;
    const mapa = {
        success: { cls: 'bg-success text-white', icon: 'fa-circle-check', title: 'Sucesso' },
        danger: { cls: 'bg-danger text-white', icon: 'fa-triangle-exclamation', title: 'Erro' },
        warning: { cls: 'bg-warning', icon: 'fa-circle-exclamation', title: 'Atencao' },
        info: { cls: 'bg-info text-white', icon: 'fa-circle-info', title: 'Informacao' }
    };
    const cfg = mapa[tipo || 'success'] || mapa.success;
    toastHeader.className = 'toast-header ' + cfg.cls;
    toastIcon.className = 'fas ' + cfg.icon + ' me-2';
    toastTitulo.textContent = cfg.title;
    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2800 });
    toast.show();
}

async function enviarAcaoFinanceira(formData) {
    const response = await fetch('../financeiro/ajax/baixar_mensalidade.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return response.json();
}

const formBaixar = document.getElementById('formBaixar');
formBaixar.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnConfirmarBaixa');
    const dados = new FormData(formBaixar);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
    try {
        const result = await enviarAcaoFinanceira(dados);
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalBaixar')).hide();
            mostrarToast('Mensalidade baixada com sucesso.', 'success');
            setTimeout(() => window.location.reload(), 700);
        } else {
            mostrarToast(result.message || 'Nao foi possivel baixar a mensalidade.', 'danger');
        }
    } catch (error) {
        mostrarToast('Erro ao processar a baixa.', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirmar baixa';
    }
});

document.querySelectorAll('.btn-estornar').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const nome = btn.getAttribute('data-nome');
        const id = btn.getAttribute('data-id');
        if (!confirm(`Estornar a mensalidade de ${nome}?`)) return;

        try {
            const form = new FormData();
            form.append('estornar_lancamento', '1');
            form.append('lancamento_id', id);
            const result = await enviarAcaoFinanceira(form);
            if (result.success) {
                mostrarToast(result.message || 'Pagamento estornado.', 'warning');
                setTimeout(() => window.location.reload(), 700);
            } else {
                mostrarToast(result.message || 'Nao foi possivel estornar.', 'danger');
            }
        } catch (error) {
            mostrarToast('Erro ao estornar pagamento.', 'danger');
        }
    });
});

document.querySelectorAll('tbody tr').forEach(function (row) {
    const cells = row.querySelectorAll('td');
    if (cells.length < 3) return;
    const resumoCell = cells[2];
    const familiaBadge = row.querySelector('small.text-muted');
    if (!resumoCell || !familiaBadge) return;
    if (resumoCell.textContent && resumoCell.textContent.includes('Cobran')) {
        resumoCell.textContent = 'Cobrança consolidada da família';
    }
});
</script>
</body>
</html>

