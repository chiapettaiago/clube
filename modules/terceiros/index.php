<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM terceiros WHERE ativo = 1";
if($search) {
    $sql .= " AND nome_servico LIKE '%$search%'";
}
$sql .= " ORDER BY nome_servico";
$terceiros = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Terceiros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-handshake"></i> Serviços Terceirizados</h4>
                        <div class="float-end">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovo">
                                <i class="fas fa-plus"></i> Novo Serviço
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Buscar serviço..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </form>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome do Serviço</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($terceiros as $terceiro): ?>
                                    <tr>
                                        <td><?= $terceiro['id'] ?></td>
                                        <td><?= htmlspecialchars($terceiro['nome_servico']) ?></td>
                                        <td>
                                            <span class="badge bg-success">Ativo</span>
                                        </td>
                                        <td>
                                            <button onclick="editarTerceiro(<?= $terceiro['id'] ?>, '<?= htmlspecialchars($terceiro['nome_servico']) ?>')" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="excluir.php?id=<?= $terceiro['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
    
    <!-- Modal Novo Serviço -->
    <div class="modal fade" id="modalNovo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Serviço Terceirizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="cadastrar.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nome do Serviço</label>
                            <input type="text" name="nome_servico" class="form-control" required>
                            <small class="text-muted">Ex: Academia, Capoeira, Natação, Judô, etc.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Cadastrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Serviço -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Serviço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="editar.php" method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nome do Serviço</label>
                            <input type="text" name="nome_servico" id="edit_nome" class="form-control" required>
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
    
    <script>
    function editarTerceiro(id, nome) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome').value = nome;
        new bootstrap.Modal(document.getElementById('modalEditar')).show();
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>