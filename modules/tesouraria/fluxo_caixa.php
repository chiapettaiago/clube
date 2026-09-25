<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar movimentos do período
$stmt = $pdo->prepare("
    SELECT c.*, s.nome as socio_nome
    FROM caixa c
    LEFT JOIN socios s ON c.socio_id = s.id
    WHERE c.data_movimento BETWEEN ? AND ?
    ORDER BY c.data_movimento ASC, c.id ASC
");
$stmt->execute([$dataInicio, $dataFim]);
$movimentos = $stmt->fetchAll();

// Agrupar por dia
$movimentosPorDia = [];
$totalEntradas = 0;
$totalSaidas = 0;

foreach($movimentos as $mov) {
    $dia = $mov['data_movimento'];
    if(!isset($movimentosPorDia[$dia])) {
        $movimentosPorDia[$dia] = ['entradas' => 0, 'saidas' => 0, 'movimentos' => []];
    }
    if($mov['tipo'] == 'entrada') {
        $movimentosPorDia[$dia]['entradas'] += $mov['valor'];
        $totalEntradas += $mov['valor'];
    } else {
        $movimentosPorDia[$dia]['saidas'] += $mov['valor'];
        $totalSaidas += $mov['valor'];
    }
    $movimentosPorDia[$dia]['movimentos'][] = $mov;
}

$saldoFinal = $totalEntradas - $totalSaidas;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa - Tesouraria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h4><i class="fas fa-chart-line"></i> Fluxo de Caixa</h4>
                <div class="float-end">
                    <form method="GET" class="row g-2">
                        <div class="col-auto">
                            <input type="date" name="data_inicio" class="form-control" value="<?= $dataInicio ?>">
                        </div>
                        <div class="col-auto">
                            <input type="date" name="data_fim" class="form-control" value="<?= $dataFim ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumo -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h6>Total de Entradas</h6>
                            <h3>R$ <?= number_format($totalEntradas, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-danger">
                            <h6>Total de Saídas</h6>
                            <h3>R$ <?= number_format($totalSaidas, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h6>Saldo do Período</h6>
                            <h3 class="<?= $saldoFinal >= 0 ? 'text-success' : 'text-danger' ?>">
                                R$ <?= number_format($saldoFinal, 2, ',', '.') ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <!-- Fluxo por dia -->
                <div class="accordion" id="accordionFluxo">
                    <?php foreach($movimentosPorDia as $dia => $dados): 
                        $saldoDia = $dados['entradas'] - $dados['saidas'];
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= str_replace('-', '', $dia) ?>">
                                <div class="d-flex justify-content-between w-100 me-3">
                                    <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($dia)) ?></span>
                                    <span class="text-success">Entradas: R$ <?= number_format($dados['entradas'], 2, ',', '.') ?></span>
                                    <span class="text-danger">Saídas: R$ <?= number_format($dados['saidas'], 2, ',', '.') ?></span>
                                    <span class="<?= $saldoDia >= 0 ? 'text-success' : 'text-danger' ?>">
                                        Saldo: R$ <?= number_format($saldoDia, 2, ',', '.') ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= str_replace('-', '', $dia) ?>" class="accordion-collapse collapse" data-bs-parent="#accordionFluxo">
                            <div class="accordion-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr><th>Hora</th><th>Descrição</th><th>Sócio</th><th>Forma</th><th>Valor</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($dados['movimentos'] as $mov): ?>
                                        <tr>
                                            <td><?= date('H:i', strtotime($mov['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($mov['descricao']) ?></td>
                                            <td><?= $mov['socio_nome'] ?? '-' ?></td>
                                            <td><?= ucfirst($mov['forma_pagamento']) ?></td>
                                            <td class="<?= $mov['tipo'] == 'entrada' ? 'text-success' : 'text-danger' ?>">
                                                <?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(empty($movimentosPorDia)): ?>
                    <p class="text-center text-muted py-5">Nenhum movimento no período selecionado</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>