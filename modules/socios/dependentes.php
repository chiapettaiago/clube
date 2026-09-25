<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$socio_id = $_GET['id'] ?? 0;

// Buscar sócio principal
$stmt = $pdo->prepare("SELECT * FROM socios WHERE id = ?");
$stmt->execute([$socio_id]);
$principal = $stmt->fetch();

if(!$principal) {
    header('Location: index.php');
    exit;
}

// Buscar dependentes
$dependentes = $pdo->prepare("
    SELECT s.*, p.nome as parentesco_nome
    FROM socios s
    LEFT JOIN parentescos p ON s.parentesco = p.nome
    WHERE s.socio_principal_id = ?
    ORDER BY s.nome
");
$dependentes->execute([$socio_id]);
$dependentes = $dependentes->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Dependentes - <?= htmlspecialchars($principal['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-users"></i> Dependentes de: <?= htmlspecialchars($principal['nome']) ?></h4>
                <small>Título: <?= $principal['numero_titulo'] ?></small>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Dependentes compartilham o mesmo número de título e têm acesso às dependências do clube.
                </div>
                
                <a href="cadastrar.php?socio_principal_id=<?= $socio_id ?>" class="btn btn-success mb-3">
                    <i class="fas fa-plus"></i> Adicionar Dependente
                </a>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Parentesco</th>
                                <th>Data Nasc.</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dependentes as $dep): ?>
                            <tr>
                                <td>
                                    <?php if($dep['foto'] && file_exists('../../assets/uploads/' . $dep['foto'])): ?>
                                        <img src="../../assets/uploads/<?= $dep['foto'] ?>" width="40" height="40" class="rounded-circle">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($dep['nome']) ?></td>
                                <td><?= $dep['cpf'] ?></td>
                                <td><?= $dep['parentesco'] ?: '-' ?></td>
                                <td><?= $dep['data_nascimento'] ? date('d/m/Y', strtotime($dep['data_nascimento'])) : '-' ?></td>
                                <td>
                                    <a href="editar.php?id=<?= $dep['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../carteirinha/gerar.php?id=<?= $dep['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-id-card"></i>
                                    </a>
                                    <button onclick="excluir(<?= $dep['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($dependentes)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum dependente cadastrado</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <a href="index.php" class="btn btn-secondary mt-3">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>
    
    <script>
    function excluir(id) {
        if(confirm('Tem certeza que deseja excluir este dependente?')) {
            window.location.href = 'excluir.php?id=' + id;
        }
    }
    </script>
</body>
</html>
