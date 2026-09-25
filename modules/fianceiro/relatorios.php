<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// Resumo do período
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END), 0) as total_receitas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END), 0) as total_despesas,
        COALESCE(SUM(CASE WHEN tipo = 'receita' AND status = 'pendente' THEN valor ELSE 0 END), 0) as total_pendente,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as qtd_pendente,
        COUNT(CASE WHEN status = 'vencido' THEN 1 END) as qtd_vencido
    FROM lancamentos
    WHERE YEAR(data_vencimento) = ? AND MONTH(data_vencimento) = ?
");
$stmt->execute([$ano, $mes]);
$resumo = $stmt->fetch();

// Receitas por categoria
$receitasCategoria = $pdo->prepare("
    SELECT c.nome, COALESCE(SUM(l.valor), 0) as total
    FROM categorias_financeiras c
    LEFT JOIN lancamentos l ON l.categoria_id = c.id AND l.tipo = 'receita' AND l.status = 'pago'
        AND YEAR(l.data_pagamento) = ? AND MONTH(l.data_pagamento) = ?
    WHERE c.tipo = 'receita' AND c.ativo = 1
    GROUP BY c.id
");
$receitasCategoria->execute([$ano, $mes]);
$receitasCategoria = $receitasCategoria->fetchAll();

// Despesas por categoria
$despesasCategoria = $pdo->prepare("
    SELECT c.nome, COALESCE(SUM(l.valor), 0) as total
    FROM categorias_financeiras c
    LEFT JOIN lancamentos l ON l.categoria_id = c.id AND l.tipo = 'despesa' AND l.status = 'pago'
        AND YEAR(l.data_pagamento) = ? AND MONTH(l.data_pagamento) = ?
    WHERE c.tipo = 'despesa' AND c.ativo = 1
    GROUP BY c.id
");
$despesasCategoria->execute([$ano, $mes]);
$despesasCategoria = $despesasCategoria->fetchAll();

$saldo = $resumo['total_receitas'] - $resumo['total_despesas'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Financeiros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h4><i class="fas fa-chart-bar"></i> Relatórios Financeiros</h4>
                <div class="float-end">
                    <select id="mesSelect" onchange="atualizar()">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="anoSelect" onchange="atualizar()">
                        <?php for($a=date('Y')-2; $a<=date('Y'); $a++): ?>
                            <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumo -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-success">
                            <h6>Total Receitas</h6>
                            <h3>R$ <?= number_format($resumo['total_receitas'], 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger">
                            <h6>Total Despesas</h6>
                            <h3>R$ <?= number_format($resumo['total_despesas'], 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-info">
                            <h6>Saldo do Período</h6>
                            <h3>R$ <?= number_format($saldo, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning">
                            <h6>Pendentes</h6>
                            <h3>R$ <?= number_format($resumo['total_pendente'], 2, ',', '.') ?></h3>
                            <small><?= $resumo['qtd_pendente'] ?> lançamentos</small>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="graficoReceitas" height="250"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="graficoDespesas" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Botão Exportar -->
                <div class="text-center mt-4">
                    <a href="exportar.php?ano=<?= $ano ?>&mes=<?= $mes ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Exportar para Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Gráfico Receitas
    const categoriasReceitas = <?= json_encode(array_column($receitasCategoria, 'nome')) ?>;
    const valoresReceitas = <?= json_encode(array_column($receitasCategoria, 'total')) ?>;
    
    new Chart(document.getElementById('graficoReceitas'), {
        type: 'pie',
        data: { labels: categoriasReceitas, datasets: [{ data: valoresReceitas, backgroundColor: ['#28a745', '#20c997', '#17a2b8', '#ffc107', '#fd7e14'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (ctx) => `${ctx.label}: R$ ${ctx.raw.toFixed(2).replace('.', ',')}` } } } }
    });
    
    // Gráfico Despesas
    const categoriasDespesas = <?= json_encode(array_column($despesasCategoria, 'nome')) ?>;
    const valoresDespesas = <?= json_encode(array_column($despesasCategoria, 'total')) ?>;
    
    new Chart(document.getElementById('graficoDespesas'), {
        type: 'pie',
        data: { labels: categoriasDespesas, datasets: [{ data: valoresDespesas, backgroundColor: ['#dc3545', '#e83e8c', '#6f42c1', '#fd7e14', '#ffc107'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (ctx) => `${ctx.label}: R$ ${ctx.raw.toFixed(2).replace('.', ',')}` } } } }
    });
    
    function atualizar() {
        var mes = document.getElementById('mesSelect').value;
        var ano = document.getElementById('anoSelect').value;
        window.location.href = 'relatorios.php?ano=' + ano + '&mes=' + mes;
    }
    </script>
</body>
</html>
