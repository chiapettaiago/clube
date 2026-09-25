<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$conta_id = $_GET['conta'] ?? 1;
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar dados da conta
$stmt = $pdo->prepare("SELECT * FROM contas_bancarias WHERE id = ?");
$stmt->execute([$conta_id]);
$conta = $stmt->fetch();

if(!$conta) {
    header('Location: index.php');
    exit;
}

// Buscar lançamentos do sistema no período
$stmt = $pdo->prepare("
    SELECT l.*, s.nome as socio_nome
    FROM lancamentos l
    LEFT JOIN socios s ON l.socio_id = s.id
    WHERE l.data_vencimento BETWEEN ? AND ?
    AND l.status = 'pago'
    ORDER BY l.data_pagamento
");
$stmt->execute([$data_inicio, $data_fim]);
$lancamentos = $stmt->fetchAll();

// Buscar extratos do banco
$stmt = $pdo->prepare("
    SELECT * FROM extrato_bancario 
    WHERE conta_id = ? AND data_lancamento BETWEEN ? AND ?
    ORDER BY data_lancamento
");
$stmt->execute([$conta_id, $data_inicio, $data_fim]);
$extratos = $stmt->fetchAll();

// Processar conciliação
if($_POST && isset($_POST['conciliar'])) {
    $extrato_id = $_POST['extrato_id'];
    $lancamento_id = $_POST['lancamento_id'];
    
    $stmt = $pdo->prepare("
        UPDATE extrato_bancario 
        SET conciliado = 1, data_conciliacao = CURRENT_DATE(), lancamento_id = ? 
        WHERE id = ?
    ");
    $stmt->execute([$lancamento_id, $extrato_id]);
    
    header("Location: conciliacao.php?conta=$conta_id&msg=sucesso");
    exit;
}

// Adicionar extrato manual
if($_POST && isset($_POST['adicionar_extrato'])) {
    $data_lancamento = $_POST['data_lancamento'];
    $descricao = $_POST['descricao'];
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $tipo = $_POST['tipo'];
    $documento = $_POST['documento'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO extrato_bancario (conta_id, data_lancamento, descricao, valor, tipo, documento)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$conta_id, $data_lancamento, $descricao, $valor, $tipo, $documento]);
    
    header("Location: conciliacao.php?conta=$conta_id&msg=adicionado");
    exit;
}

// Estatísticas de conciliação
$totalExtratos = count($extratos);
$conciliados = 0;
$naoConciliados = 0;
foreach($extratos as $ext) {
    if($ext['conciliado']) $conciliados++;
    else $naoConciliados++;
}
$percentualConciliado = $totalExtratos > 0 ? ($conciliados / $totalExtratos) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conciliação Bancária - Tesouraria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .conciliado { background-color: #d4edda !important; }
        .nao-conciliado { background-color: #f8d7da !important; }
        .table-hover tbody tr:hover { cursor: pointer; }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h4><i class="fas fa-check-double"></i> Conciliação Bancária</h4>
                <div class="float-end">
                    <small>Conta: <strong><?= htmlspecialchars($conta['banco']) ?></strong> - Ag: <?= $conta['agencia'] ?> / C/C: <?= $conta['conta'] ?></small>
                </div>
            </div>
            <div class="card-body">
                
                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-success">✅ Operação realizada com sucesso!</div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="conta" value="<?= $conta_id ?>">
                            <div class="col-auto">
                                <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                            </div>
                            <div class="col-auto">
                                <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalExtrato">
                            <i class="fas fa-plus"></i> Adicionar Extrato
                        </button>
                        <button class="btn btn-info" onclick="window.location.href='exportar_extrato.php?conta=<?= $conta_id ?>&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>'">
                            <i class="fas fa-file-excel"></i> Exportar
                        </button>
                    </div>
                </div>
                
                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h6>Total de Lançamentos</h6>
                            <h3><?= count($extratos) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h6>Conciliados</h6>
                            <h3><?= $conciliados ?> (<?= number_format($percentualConciliado, 1) ?>%)</h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <h6>Não Conciliados</h6>
                            <h3><?= $naoConciliados ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de Conciliação -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Documento</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($extratos as $extrato): ?>
                            <tr class="<?= $extrato['conciliado'] ? 'conciliado' : 'nao-conciliado' ?>">
                                <td><?= date('d/m/Y', strtotime($extrato['data_lancamento'])) ?></td>
                                <td><?= htmlspecialchars($extrato['descricao']) ?></td>
                                <td><?= $extrato['documento'] ?: '-' ?></td>
                                <td class="<?= $extrato['tipo'] == 'credito' ? 'text-success' : 'text-danger' ?>">
                                    <strong>R$ <?= number_format($extrato['valor'], 2, ',', '.') ?></strong>
                                </td>
                                <td>
                                    <?php if($extrato['tipo'] == 'credito'): ?>
                                        <span class="badge bg-success">Crédito</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Débito</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($extrato['conciliado']): ?>
                                        <span class="badge bg-success">Conciliado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!$extrato['conciliado']): ?>
                                    <button class="btn btn-sm btn-primary" onclick="conciliar(<?= $extrato['id'] ?>, <?= $extrato['valor'] ?>)">
                                        <i class="fas fa-check-double"></i> Conciliar
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($extratos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    Nenhum extrato encontrado no período
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Conciliação -->
    <div class="modal fade" id="modalConciliar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-check-double"></i> Conciliar Lançamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="conciliar" value="1">
                    <input type="hidden" name="extrato_id" id="extrato_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Selecione o Lançamento Correspondente</label>
                            <select name="lancamento_id" id="lancamento_id" class="form-select select2" style="width: 100%;" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach($lancamentos as $lanc): ?>
                                <option value="<?= $lanc['id'] ?>" data-valor="<?= $lanc['valor'] ?>">
                                    <?= date('d/m/Y', strtotime($lanc['data_pagamento'])) ?> - 
                                    <?= htmlspecialchars($lanc['descricao']) ?> - 
                                    R$ <?= number_format($lanc['valor'], 2, ',', '.') ?>
                                    <?= $lanc['socio_nome'] ? " - {$lanc['socio_nome']}" : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Certifique-se de que o valor do lançamento corresponde ao valor do extrato.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Conciliação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Adicionar Extrato -->
    <div class="modal fade" id="modalExtrato" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Adicionar Extrato Manual</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="adicionar_extrato" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Data do Lançamento</label>
                            <input type="date" name="data_lancamento" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Valor (R$)</label>
                            <input type="text" name="valor" class="form-control money" required>
                        </div>
                        <div class="mb-3">
                            <label>Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="credito">Crédito (Entrada)</option>
                                <option value="debito">Débito (Saída)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Documento</label>
                            <input type="text" name="documento" class="form-control" placeholder="Número do documento">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Adicionar</button>
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
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#modalConciliar') });
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        function conciliar(id, valor) {
            $('#extrato_id').val(id);
            $('#modalConciliar').modal('show');
            
            // Filtrar lançamentos pelo valor aproximado
            $('#lancamento_id option').each(function() {
                var valorOption = $(this).data('valor');
                if(Math.abs(valorOption - valor) < 0.01) {
                    $(this).prop('selected', true);
                }
            });
        }
    </script>
</body>
</html>