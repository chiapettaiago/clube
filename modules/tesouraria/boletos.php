<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

// Processar emissão de boleto
if($_POST && isset($_POST['emitir_boleto'])) {
    $socio_id = $_POST['socio_id'];
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $data_vencimento = $_POST['data_vencimento'];
    $descricao = $_POST['descricao'];
    
    // Gerar número do boleto
    $numero_boleto = date('Ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $nosso_numero = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);
    
    // Gerar linha digitável simulada
    $linha_digitavel = "00190.00009 01234.56789 01234.56789 0 000000" . str_pad($valor * 100, 10, '0', STR_PAD_LEFT);
    $codigo_barras = "0019000009012345678901234567890000000" . str_pad($valor * 100, 10, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO boletos (socio_id, numero_boleto, valor, data_vencimento, nosso_numero, linha_digitavel, codigo_barras, descricao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$socio_id, $numero_boleto, $valor, $data_vencimento, $nosso_numero, $linha_digitavel, $codigo_barras, $descricao]);
    
    header('Location: boletos.php?msg=emitido');
    exit;
}

// Processar baixa de boleto
if(isset($_GET['baixar'])) {
    $id = $_GET['baixar'];
    $stmt = $pdo->prepare("UPDATE boletos SET status = 'pago', data_pagamento = CURRENT_DATE() WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: boletos.php?msg=baixado');
    exit;
}

// Buscar boletos
$status = $_GET['status'] ?? 'todos';
$sql = "SELECT b.*, s.nome as socio_nome, s.numero_titulo, s.cpf
        FROM boletos b
        JOIN socios s ON b.socio_id = s.id
        WHERE 1=1";
if($status != 'todos') {
    $sql .= " AND b.status = '$status'";
}
$sql .= " ORDER BY b.data_vencimento DESC";
$boletos = $pdo->query($sql)->fetchAll();

// Buscar sócios para o select
$socios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Estatísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pendentes,
        COUNT(CASE WHEN status = 'pago' THEN 1 END) as pagos,
        COUNT(CASE WHEN status = 'vencido' THEN 1 END) as vencidos,
        COALESCE(SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END), 0) as valor_pendente,
        COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) as valor_pago
    FROM boletos
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletos - Tesouraria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h4><i class="fas fa-barcode"></i> Gestão de Boletos</h4>
                <button class="btn btn-light float-end" data-bs-toggle="modal" data-bs-target="#modalBoleto">
                    <i class="fas fa-plus"></i> Emitir Boleto
                </button>
            </div>
            <div class="card-body">
                
                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-success">
                        <?php if($_GET['msg'] == 'emitido'): ?>
                            ✅ Boleto emitido com sucesso!
                        <?php elseif($_GET['msg'] == 'baixado'): ?>
                            ✅ Boleto baixado com sucesso!
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-warning text-center">
                            <h6>Pendentes</h6>
                            <h3><?= $stats['pendentes'] ?></h3>
                            <small>R$ <?= number_format($stats['valor_pendente'], 2, ',', '.') ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-success text-center">
                            <h6>Pagos</h6>
                            <h3><?= $stats['pagos'] ?></h3>
                            <small>R$ <?= number_format($stats['valor_pago'], 2, ',', '.') ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger text-center">
                            <h6>Vencidos</h6>
                            <h3><?= $stats['vencidos'] ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-info text-center">
                            <h6>Total Geral</h6>
                            <h3><?= count($boletos) ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select id="statusFilter" class="form-select" onchange="filtrar()">
                            <option value="todos" <?= $status == 'todos' ? 'selected' : '' ?>>Todos os boletos</option>
                            <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                            <option value="pago" <?= $status == 'pago' ? 'selected' : '' ?>>Pagos</option>
                            <option value="vencido" <?= $status == 'vencido' ? 'selected' : '' ?>>Vencidos</option>
                        </select>
                    </div>
                </div>
                
                <!-- Tabela de Boletos -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nº Boleto</th>
                                <th>Sócio</th>
                                <th>Descrição</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($boletos as $boleto): ?>
                            <tr>
                                <td><?= $boleto['numero_boleto'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($boleto['socio_nome']) ?></strong>
                                    <br>
                                    <small><?= $boleto['numero_titulo'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($boleto['descricao']) ?></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?>
                                    <?php if(strtotime($boleto['data_vencimento']) < time() && $boleto['status'] == 'pendente'): ?>
                                        <span class="badge bg-danger">Vencido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-success">R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch($boleto['status']) {
                                        case 'pago': $badgeClass = 'bg-success'; break;
                                        case 'pendente': $badgeClass = 'bg-warning'; break;
                                        case 'vencido': $badgeClass = 'bg-danger'; break;
                                        default: $badgeClass = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($boleto['status']) ?></span>
                                    <?php if($boleto['data_pagamento']): ?>
                                        <br><small>Pago em: <?= date('d/m/Y', strtotime($boleto['data_pagamento'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($boleto['status'] == 'pendente'): ?>
                                    <a href="?baixar=<?= $boleto['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Confirmar pagamento deste boleto?')">
                                        <i class="fas fa-check"></i> Baixar
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-info" onclick="visualizarBoleto(<?= $boleto['id'] ?>)">
                                        <i class="fas fa-print"></i> Visualizar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($boletos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    Nenhum boleto encontrado
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Emitir Boleto -->
    <div class="modal fade" id="modalBoleto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-barcode"></i> Emitir Boleto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="emitir_boleto" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Sócio</label>
                            <select name="socio_id" class="form-select select2" style="width: 100%;" required>
                                <option value="">-- Selecione o sócio --</option>
                                <?php foreach($socios as $soc): ?>
                                <option value="<?= $soc['id'] ?>"><?= htmlspecialchars($soc['nome']) ?> (<?= $soc['numero_titulo'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Mensalidade de Abril/2026" required>
                        </div>
                        <div class="mb-3">
                            <label>Valor (R$)</label>
                            <input type="text" name="valor" class="form-control money" required>
                        </div>
                        <div class="mb-3">
                            <label>Data de Vencimento</label>
                            <input type="date" name="data_vencimento" class="form-control" required>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            O boleto será gerado com as configurações padrão do clube.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Emitir Boleto</button>
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
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#modalBoleto') });
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        function filtrar() {
            var status = document.getElementById('statusFilter').value;
            window.location.href = 'boletos.php?status=' + status;
        }
        
        function visualizarBoleto(id) {
            window.open('ajax/visualizar_boleto.php?id=' + id, '_blank', 'width=800,height=600');
        }
    </script>
</body>
</html>