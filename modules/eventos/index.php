<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$status = $_GET['status'] ?? 'todos';
$search = $_GET['search'] ?? '';

$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id AND status = 'confirmada') as inscritos
        FROM eventos e
        WHERE 1=1";

if($status != 'todos') {
    $sql .= " AND e.status = '$status'";
}
if($search) {
    $sql .= " AND (e.titulo LIKE '%$search%' OR e.local LIKE '%$search%')";
}
$sql .= " ORDER BY e.data_inicio DESC";

$eventos = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-calendar-alt"></i> Todos os Eventos</h4>
                <div class="float-end">
                    <a href="cadastrar.php" class="btn btn-light btn-sm">
                        <i class="fas fa-plus"></i> Novo Evento
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar evento..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="todos" <?= $status == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="ativo" <?= $status == 'ativo' ? 'selected' : '' ?>>Ativos</option>
                            <option value="finalizado" <?= $status == 'finalizado' ? 'selected' : '' ?>>Finalizados</option>
                            <option value="cancelado" <?= $status == 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="filtrar()">Filtrar</button>
                    </div>
                </div>
                
                <!-- Tabela de Eventos -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Evento</th>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Inscritos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($eventos as $evento): ?>
                            <tr>
                                <td><?= $evento['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($evento['titulo']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= ucfirst($evento['tipo']) ?></small>
                                </td>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?>
                                    <br>
                                    <small>até <?= date('d/m/Y H:i', strtotime($evento['data_fim'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($evento['local'] ?: '-') ?></td>
                                <td class="text-center"><?= $evento['inscritos'] ?> / <?= $evento['capacidade'] ?: '∞' ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($evento['status']) {
                                        'ativo' => 'bg-success',
                                        'finalizado' => 'bg-secondary',
                                        'cancelado' => 'bg-danger',
                                        default => 'bg-warning'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($evento['status']) ?></span>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="inscricoes.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <a href="presenca.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <button onclick="excluir(<?= $evento['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($eventos)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    Nenhum evento encontrado
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function filtrar() {
            var search = document.getElementById('searchInput').value;
            var status = document.getElementById('statusFilter').value;
            window.location.href = 'index.php?search=' + encodeURIComponent(search) + '&status=' + status;
        }
        
        function excluir(id) {
            if(confirm('Tem certeza que deseja excluir este evento?')) {
                window.location.href = 'excluir.php?id=' + id;
            }
        }
    </script>
</body>
</html>