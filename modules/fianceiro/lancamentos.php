<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

// Processar formulário
if($_POST) {
    $socio_id = $_POST['socio_id'] ?? null;
    $descricao = $_POST['descricao'];
    $valor = str_replace(',', '.', $_POST['valor']);
    $tipo = $_POST['tipo'];
    $categoria_id = $_POST['categoria_id'];
    $data_vencimento = $_POST['data_vencimento'];
    $observacao = $_POST['observacao'] ?? '';
    
    $sql = "INSERT INTO lancamentos (socio_id, descricao, valor, tipo, categoria_id, data_lancamento, data_vencimento, status, lancado_por) 
            VALUES (?, ?, ?, ?, ?, CURRENT_DATE(), ?, 'pendente', ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$socio_id, $descricao, $valor, $tipo, $categoria_id, $data_vencimento, $_SESSION['usuario_id']]);
    
    header('Location: lancamentos.php?msg=sucesso');
    exit;
}

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias_financeiras WHERE ativo = 1 ORDER BY tipo, nome")->fetchAll();

// Buscar sócios
$socios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Buscar lançamentos
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sql = "SELECT l.*, s.nome as socio_nome, c.nome as categoria_nome 
        FROM lancamentos l
        LEFT JOIN socios s ON l.socio_id = s.id
        LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
        WHERE 1=1";
if($search) $sql .= " AND (l.descricao LIKE '%$search%' OR s.nome LIKE '%$search%')";
if($status) $sql .= " AND l.status = '$status'";
$sql .= " ORDER BY l.data_vencimento DESC";
$lancamentos = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamentos - Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h4 class="mb-0"><i class="fas fa-list"></i> Lançamentos Financeiros</h4>
                        <button type="button" class="btn btn-light float-end" data-bs-toggle="modal" data-bs-target="#modalNovo">
                            <i class="fas fa-plus"></i> Novo Lançamento
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_GET['msg'])): ?>
                            <div class="alert alert-success">✅ Lançamento cadastrado com sucesso!</div>
                        <?php endif; ?>
                        
                        <!-- Filtros -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por descrição ou sócio...">
                            </div>
                            <div class="col-md-3">
                                <select id="statusFilter" class="form-select">
                                    <option value="">Todos os status</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="pago">Pago</option>
                                    <option value="vencido">Vencido</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" onclick="filtrar()">Filtrar</button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Vencimento</th>
                                        <th>Descrição</th>
                                        <th>Sócio</th>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lancamentos as $lanc): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($lanc['data_lancamento'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($lanc['data_vencimento'])) ?></td>
                                        <td><?= htmlspecialchars($lanc['descricao']) ?></td>
                                        <td><?= $lanc['socio_nome'] ?? '-' ?></td>
                                        <td><?= $lanc['categoria_nome'] ?></td>
                                        <td class="<?= $lanc['tipo'] == 'receita' ? 'text-success' : 'text-danger' ?>">
                                            <strong><?= $lanc['tipo'] == 'receita' ? '+' : '-' ?> R$ <?= number_format($lanc['valor'], 2, ',', '.') ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = '';
                                            switch($lanc['status']) {
                                                case 'pago': $badgeClass = 'bg-success'; break;
                                                case 'pendente': $badgeClass = 'bg-warning'; break;
                                                case 'vencido': $badgeClass = 'bg-danger'; break;
                                                default: $badgeClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($lanc['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($lanc['status'] == 'pendente'): ?>
                                            <button class="btn btn-sm btn-success" onclick="baixar(<?= $lanc['id'] ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger" onclick="excluir(<?= $lanc['id'] ?>)">
                                                <i class="fas fa-trash"></i>
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
    </div>
    
    <!-- Modal Novo Lançamento -->
    <div class="modal fade" id="modalNovo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Novo Lançamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="receita">Receita (Entrada)</option>
                                <option value="despesa">Despesa (Saída)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Categoria</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= $cat['nome'] ?> (<?= $cat['tipo'] == 'receita' ? 'Receita' : 'Despesa' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Sócio (opcional)</label>
                            <select name="socio_id" class="form-select select2">
                                <option value="">-- Selecione um sócio --</option>
                                <?php foreach($socios as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?> (<?= $s['numero_titulo'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Valor (R$)</label>
                            <input type="text" name="valor" class="form-control money" required>
                        </div>
                        <div class="mb-3">
                            <label>Data de Vencimento</label>
                            <input type="date" name="data_vencimento" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Observação</label>
                            <textarea name="observacao" class="form-control" rows="2"></textarea>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        function filtrar() {
            var search = document.getElementById('searchInput').value;
            var status = document.getElementById('statusFilter').value;
            window.location.href = 'lancamentos.php?search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
        }
        
        function baixar(id) {
            if(confirm('Confirmar recebimento deste pagamento?')) {
                window.location.href = 'ajax/baixar_mensalidade.php?id=' + id;
            }
        }
        
        function excluir(id) {
            if(confirm('Tem certeza que deseja excluir este lançamento?')) {
                window.location.href = 'ajax/excluir_lancamento.php?id=' + id;
            }
        }
    </script>
</body>
</html>