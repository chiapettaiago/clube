<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

// Data atual
$hoje = date('Y-m-d');
$primeiroDia = date('Y-m-01');
$ultimoDia = date('Y-m-t');

// ============================================
// ESTATÍSTICAS DO CAIXA
// ============================================

// Saldo atual do caixa
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END), 0) as total_entradas,
        COALESCE(SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END), 0) as total_saidas
    FROM caixa 
    WHERE data_movimento <= ? AND status = 'confirmado'
");
$stmt->execute([$hoje]);
$caixa = $stmt->fetch();
$saldoCaixa = $caixa['total_entradas'] - $caixa['total_saidas'];

// Entradas do dia
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM caixa 
    WHERE data_movimento = ? AND tipo = 'entrada' AND status = 'confirmado'
");
$stmt->execute([$hoje]);
$entradasHoje = $stmt->fetchColumn();

// Saídas do dia
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM caixa 
    WHERE data_movimento = ? AND tipo = 'saida' AND status = 'confirmado'
");
$stmt->execute([$hoje]);
$saidasHoje = $stmt->fetchColumn();

// Saldo do dia
$saldoDia = $entradasHoje - $saidasHoje;

// ============================================
// CONTAS BANCÁRIAS
// ============================================
$contas = $pdo->query("SELECT * FROM contas_bancarias WHERE ativo = 1")->fetchAll();

// ============================================
// BOLETOS A VENCER
// ============================================
$boletosVencer = $pdo->prepare("
    SELECT b.*, s.nome as socio_nome, s.numero_titulo
    FROM boletos b
    JOIN socios s ON b.socio_id = s.id
    WHERE b.status = 'pendente' 
    AND b.data_vencimento >= CURRENT_DATE()
    AND b.data_vencimento <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ORDER BY b.data_vencimento ASC
    LIMIT 10
");
$boletosVencer->execute();
$boletosVencer = $boletosVencer->fetchAll();

// ============================================
// BOLETOS VENCIDOS
// ============================================
$boletosVencidos = $pdo->prepare("
    SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor_total
    FROM boletos 
    WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE()
");
$boletosVencidos->execute();
$boletosVencidos = $boletosVencidos->fetch();

// ============================================
// ÚLTIMOS MOVIMENTOS
// ============================================
$ultimosMovimentos = $pdo->query("
    SELECT c.*, s.nome as socio_nome
    FROM caixa c
    LEFT JOIN socios s ON c.socio_id = s.id
    ORDER BY c.data_movimento DESC, c.id DESC
    LIMIT 15
")->fetchAll();

// ============================================
// RESUMO DO MÊS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END), 0) as total_entradas_mes,
        COALESCE(SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END), 0) as total_saidas_mes
    FROM caixa 
    WHERE data_movimento BETWEEN ? AND ? AND status = 'confirmado'
");
$stmt->execute([$primeiroDia, $ultimoDia]);
$resumoMes = $stmt->fetch();
$saldoMes = $resumoMes['total_entradas_mes'] - $resumoMes['total_saidas_mes'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tesouraria - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --tesouraria: #00695c;
            --tesouraria-light: #00897b;
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
            background: linear-gradient(135deg, var(--tesouraria) 0%, var(--tesouraria-light) 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 15px 20px;
        }
        
        .btn-tesouraria {
            background: linear-gradient(135deg, var(--tesouraria) 0%, var(--tesouraria-light) 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-tesouraria:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 105, 92, 0.3);
            color: white;
        }
        
        .saldo-positivo { color: #4caf50; font-weight: bold; }
        .saldo-negativo { color: #f44336; font-weight: bold; }
        
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
                <div class="card-modern text-white p-4" style="background: linear-gradient(135deg, var(--tesouraria) 0%, var(--tesouraria-light) 100%); border-radius: 20px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-coins me-2"></i> Módulo de Tesouraria</h2>
                            <p class="mb-0">Controle de caixa, conciliação bancária e boletos</p>
                        </div>
                        <div class="text-end">
                            <h4><?= date('d/m/Y') ?></h4>
                            <small>Saldo do dia: <strong>R$ <?= number_format($saldoDia, 2, ',', '.') ?></strong></small>
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
                        <h6 class="mb-0">Saldo em Caixa</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($saldoCaixa, 2, ',', '.') ?></h2>
                        <small>Disponível</small>
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Entradas Hoje</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($entradasHoje, 2, ',', '.') ?></h2>
                        <small>Recebimentos do dia</small>
                        <i class="fas fa-arrow-up stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Saídas Hoje</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($saidasHoje, 2, ',', '.') ?></h2>
                        <small>Pagamentos do dia</small>
                        <i class="fas fa-arrow-down stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Boletos Vencidos</h6>
                        <h2 class="mt-2 mb-0">R$ <?= number_format($boletosVencidos['valor_total'], 2, ',', '.') ?></h2>
                        <small><?= $boletosVencidos['total'] ?> boletos</small>
                        <i class="fas fa-exclamation-circle stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-3">
            <!-- Contas Bancárias -->
            <div class="col-xl-4 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-university me-2"></i> Contas Bancárias
                        <button class="btn btn-sm btn-light float-end" data-bs-toggle="modal" data-bs-target="#modalNovaConta">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php foreach($contas as $conta): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                            <div>
                                <strong><?= htmlspecialchars($conta['banco']) ?></strong>
                                <br>
                                <small>Ag: <?= $conta['agencia'] ?> | C/C: <?= $conta['conta'] ?></small>
                            </div>
                            <div class="text-end">
                                <strong class="text-success">R$ <?= number_format($conta['saldo_atual'], 2, ',', '.') ?></strong>
                                <br>
                                <button class="btn btn-sm btn-outline-primary" onclick="conciliar(<?= $conta['id'] ?>)">
                                    <i class="fas fa-check-double"></i> Conciliar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(empty($contas)): ?>
                            <p class="text-muted text-center">Nenhuma conta cadastrada</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Boletos a Vencer -->
            <div class="col-xl-8 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-barcode me-2"></i> Boletos a Vencer (Próximos 7 dias)
                        <a href="boletos.php" class="float-end text-white opacity-75">Ver todos →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sócio</th>
                                        <th>Nº Boleto</th>
                                        <th>Vencimento</th>
                                        <th>Valor</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($boletosVencer)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                                            Nenhum boleto a vencer nos próximos dias
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach($boletosVencer as $boleto): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($boleto['socio_nome']) ?></strong>
                                                <br>
                                                <small><?= $boleto['numero_titulo'] ?></small>
                                            </td>
                                            <td><?= $boleto['numero_boleto'] ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?>
                                                <?php if(strtotime($boleto['data_vencimento']) == strtotime($hoje)): ?>
                                                    <span class="badge bg-warning">Hoje!</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-success">R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="baixarBoleto(<?= $boleto['id'] ?>)">
                                                    <i class="fas fa-check"></i> Baixar
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="visualizarBoleto(<?= $boleto['id'] ?>)">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimos Movimentos -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-history me-2"></i> Últimos Movimentos do Caixa
                        <a href="fluxo_caixa.php" class="float-end text-white opacity-75">Ver todos →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrição</th>
                                        <th>Sócio</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Forma</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ultimosMovimentos as $mov): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($mov['data_movimento'])) ?></td>
                                        <td><?= htmlspecialchars($mov['descricao']) ?></td>
                                        <td><?= $mov['socio_nome'] ?? '-' ?></td>
                                        <td>
                                            <?php if($mov['tipo'] == 'entrada'): ?>
                                                <span class="badge bg-success">Entrada</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Saída</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $mov['tipo'] == 'entrada' ? 'text-success' : 'text-danger' ?>">
                                            <strong><?= $mov['tipo'] == 'entrada' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $formas = [
                                                'dinheiro' => '💰 Dinheiro',
                                                'cartao' => '💳 Cartão',
                                                'pix' => '📱 PIX',
                                                'transferencia' => '🏦 Transferência',
                                                'boleto' => '📄 Boleto'
                                            ];
                                            echo $formas[$mov['forma_pagamento']] ?? $mov['forma_pagamento'];
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $mov['status'] == 'confirmado' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($mov['status']) ?>
                                            </span>
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
        
        <!-- Ações Rápidas -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-bolt me-2"></i> Ações Rápidas - Tesouraria
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-success w-100 py-3" data-bs-toggle="modal" data-bs-target="#modalLancamento" onclick="setTipo('entrada')">
                                    <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                    Registrar Entrada
                                </button>
                            </div>
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-danger w-100 py-3" data-bs-toggle="modal" data-bs-target="#modalLancamento" onclick="setTipo('saida')">
                                    <i class="fas fa-minus-circle fa-2x d-block mb-2"></i>
                                    Registrar Saída
                                </button>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="boletos.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-barcode fa-2x d-block mb-2"></i>
                                    Emitir Boleto
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="relatorios.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-chart-line fa-2x d-block mb-2"></i>
                                    Relatórios
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Lançamento -->
    <div class="modal fade" id="modalLancamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="modalLancamentoHeader">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> <span id="modalTitulo">Registrar Entrada</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="ajax/salvar_lancamento.php" method="POST">
                    <input type="hidden" name="tipo" id="lancamentoTipo" value="entrada">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Data do Movimento</label>
                            <input type="date" name="data_movimento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Sócio (opcional)</label>
                            <select name="socio_id" class="form-select select2">
                                <option value="">-- Selecione --</option>
                                <?php
                                $socios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1 ORDER BY nome");
                                foreach($socios as $soc):
                                ?>
                                <option value="<?= $soc['id'] ?>"><?= htmlspecialchars($soc['nome']) ?> (<?= $soc['numero_titulo'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Valor (R$)</label>
                            <input type="text" name="valor" class="form-control money" required>
                        </div>
                        <div class="mb-3">
                            <label>Forma de Pagamento</label>
                            <select name="forma_pagamento" class="form-select" required>
                                <option value="dinheiro">Dinheiro</option>
                                <option value="cartao">Cartão de Crédito/Débito</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência Bancária</option>
                                <option value="boleto">Boleto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Documento/Número</label>
                            <input type="text" name="documento" class="form-control" placeholder="Nota fiscal, recibo, etc">
                        </div>
                        <div class="mb-3">
                            <label>Observação</label>
                            <textarea name="observacao" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar Lançamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Nova Conta Bancária -->
    <div class="modal fade" id="modalNovaConta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-university"></i> Nova Conta Bancária</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="ajax/salvar_conta.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Banco</label>
                            <input type="text" name="banco" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Agência</label>
                            <input type="text" name="agencia" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Conta</label>
                            <input type="text" name="conta" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Tipo de Conta</label>
                            <select name="tipo_conta" class="form-select">
                                <option value="corrente">Corrente</option>
                                <option value="poupanca">Poupança</option>
                                <option value="salario">Salário</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Saldo Inicial</label>
                            <input type="text" name="saldo_inicial" class="form-control money" value="0,00">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#modalLancamento') });
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        function setTipo(tipo) {
            $('#lancamentoTipo').val(tipo);
            if(tipo == 'entrada') {
                $('#modalLancamentoHeader').removeClass('bg-danger').addClass('bg-success');
                $('#modalTitulo').html('Registrar Entrada');
            } else {
                $('#modalLancamentoHeader').removeClass('bg-success').addClass('bg-danger');
                $('#modalTitulo').html('Registrar Saída');
            }
        }
        
        function baixarBoleto(id) {
            if(confirm('Confirmar o recebimento deste boleto?')) {
                window.location.href = 'ajax/baixar_boleto.php?id=' + id;
            }
        }
        
        function visualizarBoleto(id) {
            window.open('ajax/visualizar_boleto.php?id=' + id, '_blank');
        }
        
        function conciliar(id) {
            window.location.href = 'conciliacao.php?conta=' + id;
        }
    </script>
</body>
</html>