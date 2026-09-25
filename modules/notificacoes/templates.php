<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Processar formulário
if($_POST && isset($_POST['salvar_template'])) {
    $nome = $_POST['nome'];
    $assunto = $_POST['assunto'];
    $mensagem = $_POST['mensagem'];
    $tipo = $_POST['tipo'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if(isset($_POST['id']) && $_POST['id'] > 0) {
        $stmt = $pdo->prepare("UPDATE templates_notificacao SET nome = ?, assunto = ?, mensagem = ?, tipo = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$nome, $assunto, $mensagem, $tipo, $ativo, $_POST['id']]);
        $msg = "Template atualizado com sucesso!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO templates_notificacao (nome, assunto, mensagem, tipo, ativo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $assunto, $mensagem, $tipo, $ativo]);
        $msg = "Template criado com sucesso!";
    }
}

// Processar exclusão
if(isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM templates_notificacao WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: templates.php?msg=excluido');
    exit;
}

// Buscar template para editar
$templateEdit = null;
if(isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM templates_notificacao WHERE id = ?");
    $stmt->execute([$id]);
    $templateEdit = $stmt->fetch();
}

// Buscar templates
$templates = $pdo->query("SELECT * FROM templates_notificacao ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates - Notificações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Formulário -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-file-alt"></i> <?= $templateEdit ? 'Editar Template' : 'Novo Template' ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($msg)): ?>
                            <div class="alert alert-success"><?= $msg ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <?php if($templateEdit): ?>
                                <input type="hidden" name="id" value="<?= $templateEdit['id'] ?>">
                            <?php endif; ?>
                            <input type="hidden" name="salvar_template" value="1">
                            
                            <div class="mb-3">
                                <label>Nome do Template</label>
                                <input type="text" name="nome" class="form-control" value="<?= $templateEdit['nome'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Assunto</label>
                                <input type="text" name="assunto" class="form-control" value="<?= htmlspecialchars($templateEdit['assunto'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Tipo</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="email" <?= ($templateEdit['tipo'] ?? '') == 'email' ? 'selected' : '' ?>>E-mail</option>
                                    <option value="sms" <?= ($templateEdit['tipo'] ?? '') == 'sms' ? 'selected' : '' ?>>SMS</option>
                                    <option value="ambos" <?= ($templateEdit['tipo'] ?? '') == 'ambos' ? 'selected' : '' ?>>Ambos</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Mensagem</label>
                                <textarea name="mensagem" class="form-control" rows="6" required><?= htmlspecialchars($templateEdit['mensagem'] ?? '') ?></textarea>
                                <small class="text-muted">
                                    Variáveis disponíveis: {NOME}, {EMAIL}, {TELEFONE}, {TITULO}, {DATA}, {VALOR}, {ESPACO}, {EVENTO}
                                </small>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="ativo" class="form-check-input" value="1" <?= (!isset($templateEdit) || $templateEdit['ativo']) ? 'checked' : '' ?>>
                                <label class="form-check-label">Template Ativo</label>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= $templateEdit ? 'Atualizar' : 'Salvar' ?>
                            </button>
                            <?php if($templateEdit): ?>
                                <a href="templates.php" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Templates -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-list"></i> Templates Cadastrados</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
                            <div class="alert alert-success">Template excluído com sucesso!</div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Assunto</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($templates as $t): ?>
                                    <tr>
                                        <td><?= $t['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($t['nome']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($t['assunto'], 0, 40)) ?>...</td>
                                        <td>
                                            <?php if($t['tipo'] == 'email'): ?>
                                                <span class="badge bg-info">E-mail</span>
                                            <?php elseif($t['tipo'] == 'sms'): ?>
                                                <span class="badge bg-success">SMS</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Ambos</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($t['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?editar=<?= $t['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?excluir=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este template?')">
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
</body>
</html>