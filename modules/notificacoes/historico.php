<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';

$sql = "
    SELECT h.*, s.nome as socio_nome, s.numero_titulo
    FROM historico_notificacoes h
    LEFT JOIN socios s ON h.socio_id = s.id
    WHERE 1=1
";

if($filtro_tipo) {
    $sql .= " AND h.tipo = '$filtro_tipo'";
}
if($filtro_status) {
    $sql .= " AND h.status = '$filtro_status'";
}
if($busca) {
    $sql .= " AND (s.nome LIKE '%$busca%' OR h.assunto LIKE '%$busca%' OR h.mensagem LIKE '%$busca%')";
}

$sql .= " ORDER BY h.data_envio DESC LIMIT 100";

$historico = $pdo->query($sql)->fetchAll();

// Estatísticas
$totalEnviados = $pdo->query("SELECT COUNT(*) FROM historico_notificacoes WHERE status = 'enviado'")->fetchColumn();
$totalFalhas = $pdo->query("SELECT COUNT(*) FROM historico_notificacoes WHERE status = 'falhou'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Notificações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-history"></i> Histórico de Notificações</h5>
            </div>
            <div class="card-body">
                
                <!-- Filtros -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" name="busca" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="tipo" class="form-select">
                            <option value="">Todos os tipos</option>
                            <option value="email" <?= $filtro_tipo == 'email' ? 'selected' : '' ?>>E-mail</option>
                            <option value="sms" <?= $filtro_tipo == 'sms' ? 'selected' : '' ?>>SMS</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">Todos os status</option>
                            <option value="enviado" <?= $filtro_status == 'enviado' ? 'selected' : '' ?>>Enviados</option>
                            <option value="falhou" <?= $filtro_status == 'falhou' ? 'selected' : '' ?>>Falhas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="badge bg-success me-2">Enviados: <?= $totalEnviados ?></span>
                        <span class="badge bg-danger">Falhas: <?= $totalFalhas ?></span>
                    </div>
                </form>
                
                <!-- Tabela -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Destinatário</th>
                                <th>Tipo</th>
                                <th>Assunto</th>
                                <th>Mensagem</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($historico as $h): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($h['data_envio'])) ?></td>
                                <td>
                                    <?= $h['socio_nome'] ?? 'Sistema' ?>
                                    <br>
                                    <small class="text-muted"><?= $h['socio_id'] ? "ID: {$h['socio_id']}" : '' ?></small>
                                </td>
                                <td>
                                    <?php if($h['tipo'] == 'email'): ?>
                                        <span class="badge bg-info"><i class="fas fa-envelope"></i> E-mail</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-phone"></i> SMS</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(substr($h['assunto'], 0, 40)) ?>...</td>
                                <td><?= htmlspecialchars(substr($h['mensagem'], 0, 60)) ?>...</td>
                                <td>
                                    <?php if($h['status'] == 'enviado'): ?>
                                        <span class="badge bg-success">Enviado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Falhou</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($historico)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    Nenhuma notificação encontrada
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>