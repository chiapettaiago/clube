<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$tipo_relatorio = $_GET['tipo'] ?? 'fluxo';

// Dados do período
$data_inicio = "$ano-$mes-01";
$data_fim = date('Y-m-t', strtotime($data_inicio));

// Buscar movimentos
$stmt = $pdo->prepare("
    SELECT 
        DATE(data_movimento) as data,
        SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas,
        SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas
    FROM caixa
    WHERE data_movimento BETWEEN ? AND ? AND status = 'confirmado'
    GROUP BY DATE(data_movimento)
    ORDER BY data
");
$stmt->execute([$data_inicio, $data_fim]);
$movimentosDiarios = $stmt->fetchAll();

// Totais
$totalEntradas = array_sum(array_column($movimentosDiarios, 'entradas'));
$totalSaidas = array_sum(array_column($movimentosDiarios, 'saidas'));
$saldo = $totalEntradas - $totalSaidas;

// Receitas por categoria
$receitasCategoria = $pdo->prepare("
    SELECT categoria, COALESCE(SUM(valor), 0) as total
    FROM caixa
    WHERE tipo = 'entrada' AND data_movimento BETWEEN ? AND ? AND status = 'confirmado'
    GROUP BY categoria
    ORDER BY total DESC
");
$receitasCategoria->execute([$data_inicio, $data_fim]);
$receitasCategoria = $receitasCategoria->fetchAll();

// Despesas por categoria
$despesasCategoria = $pdo->prepare("
    SELECT categoria, COALESCE(SUM(valor), 0) as total
    FROM caixa
    WHERE tipo = 'saida' AND data_movimento BETWEEN ? AND ? AND status = 'confirmado'
    GROUP BY categoria
    ORDER BY total DESC
");
$despesasCategoria->execute([$data_inicio, $data_fim]);
$despesasCategoria = $despesasCategoria->fetchAll();

// Formas de pagamento mais usadas
$formasPagamento = $pdo->prepare("
    SELECT forma_pagamento, COUNT(*) as quantidade, COALESCE(SUM(valor), 0) as total
    FROM caixa
    WHERE data_movimento BETWEEN ? AND ? AND status = 'confirmado'
    GROUP BY forma_pagamento
");
$formasPagamento->execute([$data_inicio, $data_fim]);
$formasPagamento = $formasPagamento->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Tesouraria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h4><i class="fas fa-chart-line"></i> Relatórios da Tesouraria</h4>
                <div class="float-end">
                    <form method="GET" class="row g-2">
                        <div class="col-auto">
                            <select name="mes" class="form-select">
                                <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="ano" class="form-select">
                                <?php for($a=date('Y')-2; $a<=date('Y'); $a++): ?>
                                <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                        <div class="col-auto">
                            <a href="exportar_relatorio.php?ano=<?= $ano ?>&mes=<?= $mes ?>" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Exportar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body">
                
                <!-- Resumo do Período -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h6>Total de Receitas</h6>
                            <h3>R$ <?= number_format($totalEntradas, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-danger">
                            <h6>Total de Despesas</h6>
                            <h3>R$ <?= number_format($totalSaidas, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h6>Saldo do Período</h6>
                            <h3 class="<?= $saldo >= 0 ? 'text-success' : 'text-danger' ?>">
                                R$ <?= number_format($saldo, 2, ',', '.') ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de Fluxo -->
                <div class="row mb-4">
                    <div class="col-12">
                        <canvas id="graficoFluxo" height="100"></canvas>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Receitas por Categoria -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-chart-pie"></i> Receitas por Categoria
                            </div>
                            <div class="card-body">
                                <canvas id="graficoReceitas" height="250"></canvas>
                                <table class="table table-sm mt-3">
                                    <?php foreach($receitasCategoria as $cat): ?>
                                    <tr>
                                        <td><?= $cat['categoria'] ?: 'Outras' ?></td>
                                        <td class="text-end">R$ <?= number_format($cat['total'], 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Despesas por Categoria -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-chart-pie"></i> Despesas por Categoria
                            </div>
                            <div class="card-body">
                                <canvas id="graficoDespesas" height="250"></canvas>
                                <table class="table table-sm mt-3">
                                    <?php foreach($despesasCategoria as $cat): ?>
                                    <tr>
                                        <td><?= $cat['categoria'] ?: 'Outras' ?></td>
                                        <td class="text-end">R$ <?= number_format($cat['total'], 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formas de Pagamento -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-credit-card"></i> Formas de Pagamento
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr><th>Forma de Pagamento</th><th>Quantidade</th><th>Valor Total</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($formasPagamento as $fp): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $icones = [
                                                        'dinheiro' => '💰 Dinheiro',
                                                        'cartao' => '💳 Cartão',
                                                        'pix' => '📱 PIX',
                                                        'transferencia' => '🏦 Transferência',
                                                        'boleto' => '📄 Boleto'
                                                    ];
                                                    echo $icones[$fp['forma_pagamento']] ?? $fp['forma_pagamento'];
                                                    ?>
                                                </td>
                                                <td><?= $fp['quantidade'] ?></td>
                                                <td class="text-success">R$ <?= number_format($fp['total'], 2, ',', '.') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de Fluxo Diário
        const dias = <?= json_encode(array_column($movimentosDiarios, 'data')) ?>;
        const entradas = <?= json_encode(array_column($movimentosDiarios, 'entradas')) ?>;
        const saidas = <?= json_encode(array_column($movimentosDiarios, 'saidas')) ?>;
        
        new Chart(document.getElementById('graficoFluxo'), {
            type: 'line',
            data: {
                labels: dias.map(d => d.split('-').reverse().join('/')),
                datasets: [
                    { label: 'Entradas', data: entradas, borderColor: '#4caf50', backgroundColor: 'rgba(76, 175, 80, 0.1)', fill: true },
                    { label: 'Saídas', data: saidas, borderColor: '#f44336', backgroundColor: 'rgba(244, 67, 54, 0.1)', fill: true }
                ]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { tooltip: { callbacks: { label: (ctx) => `R$ ${ctx.raw.toFixed(2).replace('.', ',')}` } } } }
        });
        
        // Gráfico Receitas
        const catReceitas = <?= json_encode(array_column($receitasCategoria, 'categoria')) ?>;
        const valReceitas = <?= json_encode(array_column($receitasCategoria, 'total')) ?>;
        new Chart(document.getElementById('graficoReceitas'), {
            type: 'pie',
            data: { labels: catReceitas.map(c => c || 'Outras'), datasets: [{ data: valReceitas, backgroundColor: ['#28a745', '#20c997', '#17a2b8', '#ffc107', '#fd7e14'] }] },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.label}: R$ ${ctx.raw.toFixed(2).replace('.', ',')}` } } } }
        });
        
        // Gráfico Despesas
        const catDespesas = <?= json_encode(array_column($despesasCategoria, 'categoria')) ?>;
        const valDespesas = <?= json_encode(array_column($despesasCategoria, 'total')) ?>;
        new Chart(document.getElementById('graficoDespesas'), {
            type: 'pie',
            data: { labels: catDespesas.map(c => c || 'Outras'), datasets: [{ data: valDespesas, backgroundColor: ['#dc3545', '#e83e8c', '#6f42c1', '#fd7e14', '#ffc107'] }] },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.label}: R$ ${ctx.raw.toFixed(2).replace('.', ',')}` } } } }
        });
    </script>
</body>
</html>
