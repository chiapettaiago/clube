<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

// Estatísticas do mês atual
$mesAtual = date('Y-m-01');
$proximoMes = date('Y-m-01', strtotime('+1 month'));
$anoAtual = date('Y');

// Total a receber (mensalidades pendentes)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM lancamentos 
    WHERE tipo = 'receita' 
    AND status = 'pendente' 
    AND MONTH(data_vencimento) = MONTH(CURRENT_DATE())
");
$stmt->execute();
$totalPendente = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT COALESCE(NULLIF(s.socio_principal_id, 0), s.id)) as total
    FROM lancamentos l
    JOIN socios s ON l.socio_id = s.id
    WHERE l.tipo = 'receita'
    AND l.status = 'pendente'
    AND MONTH(l.data_vencimento) = MONTH(CURRENT_DATE())
");
$stmt->execute();
$titularesPendentes = $stmt->fetchColumn();

// Total recebido no mês
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM lancamentos 
    WHERE tipo = 'receita' 
    AND status = 'pago' 
    AND MONTH(data_pagamento) = MONTH(CURRENT_DATE())
");
$stmt->execute();
$totalRecebido = $stmt->fetchColumn();

// Total de despesas no mês
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM lancamentos 
    WHERE tipo = 'despesa' 
    AND status = 'pago' 
    AND MONTH(data_pagamento) = MONTH(CURRENT_DATE())
");
$stmt->execute();
$totalDespesas = $stmt->fetchColumn();

// Saldo do mês
$saldoMes = $totalRecebido - $totalDespesas;

$estimativaReajuste = [
    'ativo' => false,
    'total_extra' => 0.0,
    'titulares' => 0,
    'descricao' => ''
];
try {
    $configFinanceira = obterConfiguracaoFinanceira($pdo);
    $estimativaReajuste['ativo'] = (int)($configFinanceira['financeiro_reajuste_dependente_ativo'] ?? 0) === 1;
    if ($estimativaReajuste['ativo']) {
        $titulares = $pdo->query("
            SELECT id
            FROM socios
            WHERE ativo = 1
              AND (socio_principal_id IS NULL OR socio_principal_id = 0)
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($titulares as $titularId) {
            $calc = calcularReajusteMensalidadeDependentes($pdo, (int)$titularId, $mesAtual);
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
        'descricao' => ''
    ];
}

// Total de inadimplentes
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT COALESCE(NULLIF(s.socio_principal_id, 0), s.id)) as total
    FROM lancamentos l
    JOIN socios s ON l.socio_id = s.id
    WHERE l.tipo = 'receita'
    AND l.status IN ('pendente', 'vencido')
    AND l.data_vencimento < CURRENT_DATE()
");
$stmt->execute();
$totalInadimplentes = $stmt->fetchColumn();

// Previsão para próximos meses
$previsao = $pdo->prepare("
    SELECT 
        DATE_FORMAT(data_vencimento, '%Y-%m') as mes,
        COALESCE(SUM(valor), 0) as total
    FROM lancamentos 
    WHERE tipo = 'receita' 
    AND status = 'pendente'
    AND data_vencimento >= CURRENT_DATE()
    GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')
    ORDER BY mes ASC
    LIMIT 6
");
$previsao->execute();
$previsaoResult = $previsao->fetchAll();

// Últimos lançamentos
$ultimosLancamentos = $pdo->query("
    SELECT
        sp.id AS socio_financeiro_id,
        sp.nome AS socio_financeiro_nome,
        sp.numero_titulo AS socio_financeiro_titulo,
        COUNT(l.id) AS qtde_lancamentos,
        SUM(l.valor) AS valor_total,
        MAX(l.created_at) AS ultima_movimentacao,
        GROUP_CONCAT(DISTINCT l.descricao ORDER BY l.created_at DESC SEPARATOR ' | ') AS descricoes,
        GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS socios_originais,
        GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') AS categorias
    FROM lancamentos l
    LEFT JOIN socios s ON l.socio_id = s.id
    LEFT JOIN socios sp ON sp.id = COALESCE(NULLIF(s.socio_principal_id, 0), s.id)
    LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
    GROUP BY sp.id, sp.nome, sp.numero_titulo
    ORDER BY ultima_movimentacao DESC
    LIMIT 10
")->fetchAll();

// Mensalidades a vencer nos próximos dias
$proximosVencimentos = $pdo->prepare("
    SELECT
        sp.id AS socio_financeiro_id,
        sp.nome AS socio_financeiro_nome,
        sp.numero_titulo AS socio_financeiro_titulo,
        sp.telefone AS socio_financeiro_telefone,
        COUNT(l.id) AS qtde_lancamentos,
        SUM(l.valor) AS valor_total,
        MIN(l.data_vencimento) AS primeiro_vencimento,
        GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS socios_originais
    FROM lancamentos l
    JOIN socios s ON l.socio_id = s.id
    LEFT JOIN socios sp ON sp.id = COALESCE(NULLIF(s.socio_principal_id, 0), s.id)
    WHERE l.tipo = 'receita' 
    AND l.status = 'pendente'
    AND l.data_vencimento BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    GROUP BY sp.id, sp.nome, sp.numero_titulo, sp.telefone
    ORDER BY primeiro_vencimento ASC, sp.nome ASC
    LIMIT 10
");
$proximosVencimentos->execute();
$proximosVencimentos = $proximosVencimentos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --financeiro: #1b5e20;
            --financeiro-light: #2e7d32;
            --success: #4caf50;
            --danger: #f44336;
            --warning: #ff9800;
            --info: #2196f3;
        }
        
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 3rem;
            opacity: 0.2;
        }
        
        .card-modern {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--financeiro) 0%, var(--financeiro-light) 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 15px 20px;
        }
        
        .btn-financeiro {
            background: linear-gradient(135deg, var(--financeiro) 0%, var(--financeiro-light) 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-financeiro:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 94, 32, 0.3);
            color: white;
        }
        
        .badge-pendente { background: #ff9800; color: #fff; }
        .badge-pago { background: #4caf50; color: #fff; }
        .badge-vencido { background: #f44336; color: #fff; }
        
        @media (max-width: 768px) {
            .stat-icon { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card-modern bg-success text-white p-4" style="background: linear-gradient(135deg, var(--financeiro) 0%, var(--financeiro-light) 100%); border-radius: 20px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-dollar-sign me-2"></i> Módulo Financeiro</h2>
                            <p class="mb-0">Controle de mensalidades, lançamentos e inadimplência</p>
                        </div>
                        <div class="text-end">
                            <h4><?= date('F \d\e Y', strtotime($mesAtual)) ?></h4>
                            <small>Saldo do mês: <strong>R$ <?= number_format($saldoMes, 2, ',', '.') ?></strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Recebido no Mês</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($totalRecebido, 2, ',', '.') ?></h2>
                        <small>Receitas do período</small>
                        <i class="fas fa-arrow-up stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Despesas do Mês</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($totalDespesas, 2, ',', '.') ?></h2>
                        <small>Despesas pagas</small>
                        <i class="fas fa-arrow-down stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="mb-0">A Receber</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($totalPendente, 2, ',', '.') ?></h2>
                        <small><?= (int)$titularesPendentes ?> titular(es) com mensalidades pendentes</small>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Inadimplentes</h6>
                        <h2 class="mt-2 mb-0"><?= $totalInadimplentes ?></h2>
                        <small>Titulares em débito</small>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="alert <?= $estimativaReajuste['ativo'] ? 'alert-warning' : 'alert-secondary' ?> mb-0">
                    <?php if ($estimativaReajuste['ativo']): ?>
                        <strong>Estimativa futura ativa na base:</strong> adicional potencial de R$ <?= number_format((float)$estimativaReajuste['total_extra'], 2, ',', '.') ?> para <?= (int)$estimativaReajuste['titulares'] ?> titular(es), considerando dependentes que já passaram da idade mínima configurada.
                    <?php else: ?>
                        <strong>Estimativa futura preparada:</strong> a regra por dependente e faixa etária está pronta, mas ainda desativada. Quando ligar, o sistema poderá somar o adicional no titular sem mudar a cobrança atual.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row g-3">
            <!-- Próximos Vencimentos -->
            <div class="col-xl-5 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-calendar-alt me-2"></i> Próximos Vencimentos
                        <small class="float-end">Próximos 7 dias</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if(empty($proximosVencimentos)): ?>
                                <div class="list-group-item text-center py-4">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                    Nenhum vencimento nos próximos dias
                                </div>
                            <?php else: ?>
                                <?php foreach($proximosVencimentos as $venc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($venc['socio_financeiro_nome']) ?></strong>
                                        <?php if ((int)$venc['qtde_lancamentos'] > 1): ?>
                                            <br><small class="text-muted"><?= (int)$venc['qtde_lancamentos'] ?> mensalidades da mesma família nesta semana</small>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Primeiro vencimento: <?= date('d/m/Y', strtotime($venc['primeiro_vencimento'])) ?>
                                            | <i class="fas fa-id-card"></i> <?= htmlspecialchars($venc['socio_financeiro_titulo']) ?>
                                            | <i class="fas fa-users"></i> <?= htmlspecialchars($venc['socios_originais']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-success">R$ <?= number_format((float)$venc['valor_total'], 2, ',', '.') ?></strong>
                                        <br>
                                        <button class="btn btn-sm btn-success mt-1" onclick="baixarMensalidade(<?= (int)$venc['socio_financeiro_id'] ?>, '<?= addslashes($venc['socio_financeiro_nome']) ?>')">
                                            <i class="fas fa-check"></i> Receber
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Últimos Lançamentos -->
            <div class="col-xl-7 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-list me-2"></i> Últimos Lançamentos
                        <a href="lancamentos.php" class="float-end text-white opacity-75">Ver todos →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrição</th>
                                        <th>Sócio</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ultimosLancamentos as $lanc): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($lanc['ultima_movimentacao'])) ?></td>
                                        <td>
                                            <?= htmlspecialchars($lanc['descricoes']) ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($lanc['categorias'] ?: '-') ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($lanc['socio_financeiro_nome']) ?>
                                            <br><small class="text-muted">
                                                <?= (int)$lanc['qtde_lancamentos'] ?> movimento(s) recentes
                                                | <?= htmlspecialchars($lanc['socios_originais']) ?>
                                            </small>
                                        </td>
                                        <td class="text-success">
                                            <strong>+ R$ <?= number_format((float)$lanc['valor_total'], 2, ',', '.') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">Consolidado</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="verDetalhes(<?= $lanc['socio_financeiro_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Previsão de Receitas -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-chart-line me-2"></i> Previsão de Receitas - Próximos Meses
                    </div>
                    <div class="card-body">
                        <canvas id="graficoPrevisao" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ações Rápidas -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-bolt me-2"></i> Ações Rápidas
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3 col-6">
                                <a href="lancamentos.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                    Novo Lançamento
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="../financeiro/mensalidades.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-calendar-alt fa-2x d-block mb-2"></i>
                                    Gerar Mensalidades
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="../secretaria/inadimplentes.php" class="btn btn-outline-danger w-100 py-3">
                                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                                    Inadimplentes
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="relatorios.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
                                    Relatórios
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Baixar Mensalidade -->
    <div class="modal fade" id="modalBaixar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Receber Pagamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formBaixar">
                    <input type="hidden" name="lancamento_id" id="lancamento_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Sócio</label>
                            <input type="text" id="socio_nome" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label>Valor</label>
                            <input type="text" id="valor" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label>Forma de Pagamento</label>
                            <select name="forma_pagamento" class="form-select" required>
                                <option value="dinheiro">Dinheiro</option>
                                <option value="pix">PIX</option>
                                <option value="cartao">Cartão de Crédito/Débito</option>
                                <option value="transferencia">Transferência Bancária</option>
                                <option value="boleto">Boleto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Data do Pagamento</label>
                            <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Observação (opcional)</label>
                            <textarea name="observacao" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar Recebimento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Gráfico de Previsão
    const meses = <?php 
        $mesesArray = [];
        $valoresArray = [];
        foreach($previsaoResult as $p) {
            $mesesArray[] = date('m/Y', strtotime($p['mes'] . '-01'));
            $valoresArray[] = $p['total'];
        }
        echo json_encode($mesesArray);
    ?>;
    const valores = <?= json_encode($valoresArray) ?>;
    
    const ctx = document.getElementById('graficoPrevisao').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Previsão de Recebimento (R$)',
                data: valores,
                borderColor: '#2e7d32',
                backgroundColor: 'rgba(46, 125, 50, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { callbacks: { label: function(context) { return 'R$ ' + context.raw.toFixed(2).replace('.', ','); } } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return 'R$ ' + value.toFixed(2).replace('.', ','); } } } }
        }
    });
    
    function baixarMensalidade(id, nome) {
        fetch('ajax/buscar_lancamento.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('lancamento_id').value = data.id;
                document.getElementById('socio_nome').value = data.socio_nome;
                document.getElementById('valor').value = 'R$ ' + data.valor.toFixed(2).replace('.', ',');
                new bootstrap.Modal(document.getElementById('modalBaixar')).show();
            });
    }
    
    document.getElementById('formBaixar').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax/baixar_mensalidade.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('✅ Pagamento registrado com sucesso!');
                    location.reload();
                } else {
                    alert('❌ Erro: ' + data.message);
                }
            });
    });
    
    function verDetalhes(id) {
        window.location.href = 'lancamentos.php?detalhe=' + id;
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
