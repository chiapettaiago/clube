<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = $_GET['id'] ?? 0;

// Buscar reserva
$stmt = $pdo->prepare("
    SELECT r.*, e.nome as espaco_nome, e.valor_hora, s.nome as socio_nome
    FROM reservas r
    JOIN espacos e ON r.espaco_id = e.id
    JOIN socios s ON r.socio_id = s.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reserva = $stmt->fetch();

if(!$reserva) {
    header('Location: index.php');
    exit;
}

// Buscar espaços
$espacos = $pdo->query("SELECT id, nome, valor_hora FROM espacos WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Processar edição
if($_POST) {
    $espaco_id = $_POST['espaco_id'];
    $data_reserva = $_POST['data_reserva'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $quantidade_pessoas = $_POST['quantidade_pessoas'];
    $status = $_POST['status'];
    $observacao = $_POST['observacao'];
    
    // Recalcular valor
    $stmtEspaco = $pdo->prepare("SELECT valor_hora FROM espacos WHERE id = ?");
    $stmtEspaco->execute([$espaco_id]);
    $espaco = $stmtEspaco->fetch();
    
    $inicio = strtotime($hora_inicio);
    $fim = strtotime($hora_fim);
    $horas = ($fim - $inicio) / 3600;
    $valor_total = $horas * $espaco['valor_hora'];
    
    $stmt = $pdo->prepare("
        UPDATE reservas SET 
            espaco_id = ?, data_reserva = ?, hora_inicio = ?, hora_fim = ?,
            quantidade_pessoas = ?, valor_total = ?, status = ?, observacao = ?
        WHERE id = ?
    ");
    $stmt->execute([$espaco_id, $data_reserva, $hora_inicio, $hora_fim, 
                   $quantidade_pessoas, $valor_total, $status, $observacao, $id]);
    
    header('Location: index.php?msg=editado');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h4><i class="fas fa-edit"></i> Editar Reserva #<?= $reserva['codigo_reserva'] ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="required">Espaço</label>
                                    <select name="espaco_id" class="form-select" required>
                                        <?php foreach($espacos as $e): ?>
                                            <option value="<?= $e['id'] ?>" <?= $reserva['espaco_id'] == $e['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($e['nome']) ?> - R$ <?= number_format($e['valor_hora'], 2, ',', '.') ?>/hora
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="required">Sócio</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($reserva['socio_nome']) ?>" readonly disabled>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="required">Data</label>
                                    <input type="date" name="data_reserva" class="form-control" value="<?= $reserva['data_reserva'] ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="required">Hora Início</label>
                                    <input type="time" name="hora_inicio" class="form-control" value="<?= substr($reserva['hora_inicio'], 0, 5) ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="required">Hora Fim</label>
                                    <input type="time" name="hora_fim" class="form-control" value="<?= substr($reserva['hora_fim'], 0, 5) ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Quantidade de Pessoas</label>
                                    <input type="number" name="quantidade_pessoas" class="form-control" value="<?= $reserva['quantidade_pessoas'] ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Status</label>
                                    <select name="status" class="form-select">
                                        <option value="pendente" <?= $reserva['status'] == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="confirmada" <?= $reserva['status'] == 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
                                        <option value="cancelada" <?= $reserva['status'] == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                        <option value="finalizada" <?= $reserva['status'] == 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Valor Total</label>
                                    <input type="text" class="form-control" value="R$ <?= number_format($reserva['valor_total'], 2, ',', '.') ?>" readonly disabled>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Observação</label>
                                    <textarea name="observacao" class="form-control" rows="3"><?= htmlspecialchars($reserva['observacao']) ?></textarea>
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-warning px-5">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                                <a href="index.php" class="btn btn-secondary px-5">
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