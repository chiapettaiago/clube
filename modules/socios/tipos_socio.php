<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$tipos = $pdo->query("SELECT * FROM tipos_socio ORDER BY nome")->fetchAll();

if($_POST && isset($_POST['acao'])) {
    if($_POST['acao'] == 'novo') {
        $nome = $_POST['nome'];
        $cor = $_POST['cor'];
        $stmt = $pdo->prepare("INSERT INTO tipos_socio (nome, cor_carteirinha) VALUES (?, ?)");
        $stmt->execute([$nome, $cor]);
        header('Location: tipos_socio.php?msg=Criado com sucesso!');
    } elseif($_POST['acao'] == 'editar') {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $cor = $_POST['cor'];
        $stmt = $pdo->prepare("UPDATE tipos_socio SET nome = ?, cor_carteirinha = ? WHERE id = ?");
        $stmt->execute([$nome, $cor, $id]);
        header('Location: tipos_socio.php?msg=Atualizado com sucesso!');
    } elseif($_POST['acao'] == 'excluir') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM tipos_socio WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: tipos_socio.php?msg=Excluído com sucesso!');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Sócio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-success"><?= $_GET['msg'] ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-tags"></i> Tipos de Sócio</h4>
                        <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#modalNovo">
                            <i class="fas fa-plus"></i> Novo Tipo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome do Tipo</th>
                                        <th>Cor da Carteirinha</th>
                                        <th>Preview</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($tipos as $tipo): ?>
                                    <tr>
                                        <td><?= $tipo['id'] ?></td>
                                        <td><?= htmlspecialchars($tipo['nome']) ?></td>
                                        <td>
                                            <input type="color" value="<?= $tipo['cor_carteirinha'] ?>" disabled style="width: 50px;">
                                            <?= $tipo['cor_carteirinha'] ?>
                                        </td>
                                        <td>
                                            <div style="background-color: <?= $tipo['cor_carteirinha'] ?>; width: 50px; height: 30px; border-radius: 5px;"></div>
                                        </td>
                                        <td>
                                            <button onclick="editarTipo(<?= $tipo['id'] ?>, '<?= htmlspecialchars($tipo['nome']) ?>', '<?= $tipo['cor_carteirinha'] ?>')" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="excluirTipo(<?= $tipo['id'] ?>)" class="btn btn-sm btn-danger">
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
    
    <!-- Modal Novo Tipo -->
    <div class="modal fade" id="modalNovo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Tipo de Sócio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="acao" value="novo">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nome do Tipo</label>
                            <input type="text" name="nome" class="form-control" required>
                            <small>Ex: Sócio Premium, Sócio Vitalício, etc.</small>
                        </div>
                        <div class="mb-3">
                            <label>Cor da Carteirinha</label>
                            <input type="color" name="cor" class="form-control" value="#FFFFFF" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Tipo -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Tipo de Sócio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nome do Tipo</label>
                            <input type="text" name="nome" id="edit_nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Cor da Carteirinha</label>
                            <input type="color" name="cor" id="edit_cor" class="form-control" required>
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
    
    <!-- Form para excluir -->
    <form id="formExcluir" method="POST" style="display: none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="excluir_id">
    </form>
    
    <script>
    function editarTipo(id, nome, cor) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome').value = nome;
        document.getElementById('edit_cor').value = cor;
        new bootstrap.Modal(document.getElementById('modalEditar')).show();
    }
    
    function excluirTipo(id) {
        if(confirm('Tem certeza que deseja excluir este tipo de sócio?')) {
            document.getElementById('excluir_id').value = id;
            document.getElementById('formExcluir').submit();
        }
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>