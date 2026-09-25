<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = intval($_GET['id'] ?? 0);

// Buscar atendimento
$stmt = $pdo->prepare("
    SELECT a.*, s.nome as socio_nome, s.numero_titulo
    FROM atendimentos a
    LEFT JOIN socios s ON a.socio_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$atendimento = $stmt->fetch();

if(!$atendimento) {
    header('Location: atendimentos.php');
    exit;
}

$msg_erro = '';
$msg_sucesso = '';

if($_POST) {
    $tipo = $_POST['tipo'];
    $assunto = $_POST['assunto'];
    $descricao = $_POST['descricao'];
    $prioridade = $_POST['prioridade'];
    $status = $_POST['status'];
    $observacoes = $_POST['observacoes'];
    
    if(empty($assunto) || empty($descricao)) {
        $msg_erro = "❌ Assunto e descrição são obrigatórios!";
    } else {
        $stmt = $pdo->prepare("
            UPDATE atendimentos 
            SET tipo = ?, assunto = ?, descricao = ?, prioridade = ?, status = ?, observacoes = ?
            WHERE id = ?
        ");
        $stmt->execute([$tipo, $assunto, $descricao, $prioridade, $status, $observacoes, $id]);
        $msg_sucesso = "✅ Atendimento atualizado com sucesso!";
        
        if(!isset($_POST['save_and_continue'])) {
            echo "<script>setTimeout(function() { window.location.href = 'atendimentos.php'; }, 1500);</script>";
        } else {
            // Recarregar dados
            $stmt = $pdo->prepare("
                SELECT a.*, s.nome as socio_nome, s.numero_titulo
                FROM atendimentos a
                LEFT JOIN socios s ON a.socio_id = s.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $atendimento = $stmt->fetch();
        }
    }
}

// Buscar sócios para o select
$resultSocios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1 ORDER BY nome");
$socios = $resultSocios instanceof PDOStatement ? $resultSocios->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Atendimento - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h4 class="mb-0"><i class="fas fa-edit"></i> Editar Atendimento #<?= $id ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if($msg_erro): ?>
                            <div class="alert alert-danger"><?= $msg_erro ?></div>
                        <?php endif; ?>
                        <?php if($msg_sucesso): ?>
                            <div class="alert alert-success"><?= $msg_sucesso ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <strong>Cliente:</strong> 
                            <?php if($atendimento['socio_id']): ?>
                                <?= htmlspecialchars($atendimento['socio_nome']) ?> (Título: <?= $atendimento['numero_titulo'] ?>)
                            <?php else: ?>
                                <?= htmlspecialchars($atendimento['nome_visitante']) ?> (Visitante)
                            <?php endif; ?>
                            <br>
                            <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($atendimento['data_atendimento'])) ?>
                        </div>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="informacao" <?= $atendimento['tipo'] == 'informacao' ? 'selected' : '' ?>>ℹ️ Informação</option>
                                        <option value="reclamacao" <?= $atendimento['tipo'] == 'reclamacao' ? 'selected' : '' ?>>⚠️ Reclamação</option>
                                        <option value="sugestao" <?= $atendimento['tipo'] == 'sugestao' ? 'selected' : '' ?>>💡 Sugestão</option>
                                        <option value="solicitacao" <?= $atendimento['tipo'] == 'solicitacao' ? 'selected' : '' ?>>📋 Solicitação</option>
                                        <option value="documento" <?= $atendimento['tipo'] == 'documento' ? 'selected' : '' ?>>📄 Documento</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Prioridade</label>
                                    <select name="prioridade" class="form-select">
                                        <option value="baixa" <?= $atendimento['prioridade'] == 'baixa' ? 'selected' : '' ?>>🟢 Baixa</option>
                                        <option value="media" <?= $atendimento['prioridade'] == 'media' ? 'selected' : '' ?>>🟡 Média</option>
                                        <option value="alta" <?= $atendimento['prioridade'] == 'alta' ? 'selected' : '' ?>>🟠 Alta</option>
                                        <option value="urgente" <?= $atendimento['prioridade'] == 'urgente' ? 'selected' : '' ?>>🔴 Urgente</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="pendente" <?= $atendimento['status'] == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="em_andamento" <?= $atendimento['status'] == 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                                        <option value="concluido" <?= $atendimento['status'] == 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                        <option value="cancelado" <?= $atendimento['status'] == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="fw-bold">Assunto</label>
                                    <input type="text" name="assunto" class="form-control" value="<?= htmlspecialchars($atendimento['assunto']) ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="fw-bold">Descrição</label>
                                    <textarea name="descricao" class="form-control" rows="5" required><?= htmlspecialchars($atendimento['descricao']) ?></textarea>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="fw-bold">Observações</label>
                                    <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($atendimento['observacoes']) ?></textarea>
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" name="save" class="btn btn-primary px-5">
                                    <i class="fas fa-save"></i> Salvar
                                </button>
                                <button type="submit" name="save_and_continue" class="btn btn-info px-4">
                                    <i class="fas fa-sync-alt"></i> Salvar e Continuar
                                </button>
                                <a href="atendimentos.php" class="btn btn-secondary px-4">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
