<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

if (isset($_GET['status'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    $data_conclusao = ($status === 'concluido') ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE atendimentos SET status = ?, data_conclusao = ? WHERE id = ?");
    $stmt->execute([$status, $data_conclusao, $id]);
    header('Location: atendimentos.php?msg=status');
    exit;
}

if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: atendimentos.php?msg=excluido');
    exit;
}

$status_filter = $_GET['status'] ?? 'todos';
$tipo_filter = $_GET['tipo'] ?? 'todos';

$columns = $pdo->query("SHOW COLUMNS FROM atendimentos")->fetchAll(PDO::FETCH_COLUMN) ?: [];

$sql = "
    SELECT a.*, s.nome as socio_nome, s.numero_titulo, u.nome as atendente_nome
    FROM atendimentos a
    LEFT JOIN socios s ON a.socio_id = s.id
    LEFT JOIN usuarios u ON a.atendido_por = u.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== 'todos') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}
if ($tipo_filter !== 'todos') {
    $sql .= " AND a.tipo = ?";
    $params[] = $tipo_filter;
}
$sql .= " ORDER BY a.data_atendimento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['pendentes' => 0, 'em_andamento' => 0, 'concluidos' => 0, 'urgentes' => 0];
if (in_array('status', $columns, true)) {
    $stats['pendentes'] = (int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE status = 'pendente'")->fetchColumn();
    $stats['em_andamento'] = (int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE status = 'em_andamento'")->fetchColumn();
    $stats['concluidos'] = (int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE status = 'concluido'")->fetchColumn();
}
if (in_array('prioridade', $columns, true)) {
    $stats['urgentes'] = (int)$pdo->query("SELECT COUNT(*) FROM atendimentos WHERE prioridade = 'urgente'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atendimentos - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f0f2f5; }
        .panel { border:none; border-radius:20px; box-shadow:0 5px 15px rgba(0,0,0,.08); margin-bottom:25px; }
        .panel-header { background:linear-gradient(135deg,#2c3e50 0%,#3498db 100%); color:#fff; border-radius:20px 20px 0 0 !important; padding:15px 20px; }
        .status-pendente { background-color:#fff3cd; border-left:4px solid #ffc107; }
        .status-em_andamento { background-color:#cfe2ff; border-left:4px solid #0d6efd; }
        .status-concluido { background-color:#d1e7dd; border-left:4px solid #198754; }
        .status-cancelado { background-color:#f8d7da; border-left:4px solid #dc3545; }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid py-4">
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card text-dark bg-warning"><div class="card-body d-flex justify-content-between"><div><h6>Pendentes</h6><h2><?= $stats['pendentes'] ?></h2></div><i class="fas fa-clock fa-3x opacity-50"></i></div></div></div>
        <div class="col-md-3"><div class="card text-white bg-info"><div class="card-body d-flex justify-content-between"><div><h6>Em Andamento</h6><h2><?= $stats['em_andamento'] ?></h2></div><i class="fas fa-spinner fa-3x opacity-50"></i></div></div></div>
        <div class="col-md-3"><div class="card text-white bg-success"><div class="card-body d-flex justify-content-between"><div><h6>Concluídos</h6><h2><?= $stats['concluidos'] ?></h2></div><i class="fas fa-check-circle fa-3x opacity-50"></i></div></div></div>
        <div class="col-md-3"><div class="card text-white bg-danger"><div class="card-body d-flex justify-content-between"><div><h6>Urgentes</h6><h2><?= $stats['urgentes'] ?></h2></div><i class="fas fa-exclamation-triangle fa-3x opacity-50"></i></div></div></div>
    </div>

    <div class="card panel">
        <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Atendimentos</h5>
            <a href="novo_atendimento.php" class="btn btn-light btn-sm"><i class="fas fa-plus"></i> Novo Atendimento</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?= $status_filter === 'todos' ? 'selected' : '' ?>>Todos os status</option>
                        <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="em_andamento" <?= $status_filter === 'em_andamento' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="concluido" <?= $status_filter === 'concluido' ? 'selected' : '' ?>>Concluídos</option>
                        <option value="cancelado" <?= $status_filter === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?= $tipo_filter === 'todos' ? 'selected' : '' ?>>Todos os tipos</option>
                        <option value="informacao" <?= $tipo_filter === 'informacao' ? 'selected' : '' ?>>Informação</option>
                        <option value="reclamacao" <?= $tipo_filter === 'reclamacao' ? 'selected' : '' ?>>Reclamação</option>
                        <option value="sugestao" <?= $tipo_filter === 'sugestao' ? 'selected' : '' ?>>Sugestão</option>
                        <option value="solicitacao" <?= $tipo_filter === 'solicitacao' ? 'selected' : '' ?>>Solicitação</option>
                        <option value="documento" <?= $tipo_filter === 'documento' ? 'selected' : '' ?>>Documento</option>
                    </select>
                </div>
            </form>

            <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    Operação realizada com sucesso!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($atendimentos)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                    <p>Nenhum atendimento registrado</p>
                    <a href="novo_atendimento.php" class="btn btn-primary">Registrar primeiro atendimento</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Assunto</th>
                            <?php if (in_array('prioridade', $columns, true)): ?><th>Prioridade</th><?php endif; ?>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($atendimentos as $atend): ?>
                            <tr class="status-<?= htmlspecialchars($atend['status']) ?>">
                                <td><?= (int)$atend['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($atend['data_atendimento'])) ?></td>
                                <td>
                                    <?php if (!empty($atend['socio_id'])): ?>
                                        <strong><?= htmlspecialchars($atend['socio_nome']) ?></strong><br>
                                        <small class="text-muted">Título: <?= htmlspecialchars($atend['numero_titulo'] ?? '-') ?></small>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($atend['nome_visitante'] ?? '-') ?></strong><br>
                                        <small class="text-muted">Visitante</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $tipoIcone = [
                                        'informacao' => 'fa-info-circle',
                                        'reclamacao' => 'fa-exclamation-triangle',
                                        'sugestao' => 'fa-lightbulb',
                                        'solicitacao' => 'fa-file-alt',
                                        'documento' => 'fa-folder'
                                    ];
                                    $icone = $tipoIcone[$atend['tipo']] ?? 'fa-tag';
                                    ?>
                                    <i class="fas <?= $icone ?>"></i> <?= htmlspecialchars(ucfirst($atend['tipo'])) ?>
                                </td>
                                <td><?= htmlspecialchars(substr((string)$atend['assunto'], 0, 50)) ?></td>
                                <?php if (in_array('prioridade', $columns, true)): ?>
                                    <td>
                                        <span class="badge bg-<?= $atend['prioridade'] === 'urgente' ? 'danger' : ($atend['prioridade'] === 'alta' ? 'warning' : 'secondary') ?>">
                                            <?= htmlspecialchars(ucfirst($atend['prioridade'])) ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $statusBadge = [
                                        'pendente' => 'warning',
                                        'em_andamento' => 'info',
                                        'concluido' => 'success',
                                        'cancelado' => 'secondary'
                                    ];
                                    $badge = $statusBadge[$atend['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $atend['status']))) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="editar_atendimento.php?id=<?= (int)$atend['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                        <?php if ($atend['status'] === 'pendente'): ?>
                                            <a href="?status=em_andamento&id=<?= (int)$atend['id'] ?>" class="btn btn-info"><i class="fas fa-play"></i></a>
                                        <?php endif; ?>
                                        <?php if ($atend['status'] === 'em_andamento'): ?>
                                            <a href="?status=concluido&id=<?= (int)$atend['id'] ?>" class="btn btn-success"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <a href="?excluir=<?= (int)$atend['id'] ?>" class="btn btn-danger" onclick="return confirm('Excluir este atendimento?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
