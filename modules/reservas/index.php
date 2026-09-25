<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Buscar espaços
$espacos = $pdo->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM reservas WHERE espaco_id = e.id AND status IN ('confirmada', 'pendente')) as reservas_ativas
    FROM espacos e
    WHERE e.ativo = 1
    ORDER BY e.nome
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espaços - Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4><i class="fas fa-building"></i> Espaços para Reserva</h4>
                <a href="configurar.php" class="float-end btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Novo Espaço
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($espacos as $espaco): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($espaco['nome']) ?>
                                    <span class="badge bg-secondary float-end"><?= ucfirst($espaco['tipo']) ?></span>
                                </h5>
                                <p class="card-text text-muted small">
                                    <i class="fas fa-users"></i> Capacidade: <?= $espaco['capacidade'] ?> pessoas
                                    <br>
                                    <i class="fas fa-clock"></i> Horário: <?= substr($espaco['horario_inicio'], 0, 5) ?> - <?= substr($espaco['horario_fim'], 0, 5) ?>
                                    <br>
                                    <i class="fas fa-dollar-sign"></i> Valor: R$ <?= number_format($espaco['valor_hora'], 2, ',', '.') ?>/hora
                                    <br>
                                    <i class="fas fa-calendar-check"></i> Reservas ativas: <?= $espaco['reservas_ativas'] ?>
                                </p>
                                <p class="card-text"><?= nl2br(htmlspecialchars(substr((string)($espaco['descricao'] ?? ''), 0, 100))) ?></p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="nova.php?espaco=<?= $espaco['id'] ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-calendar-plus"></i> Reservar
                                </a>
                                <a href="configurar.php?editar_espaco=<?= $espaco['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Lista de Reservas Recentes -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-list"></i> Reservas Recentes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Espaço</th>
                                <th>Sócio</th>
                                <th>Horário</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $reservas = $pdo->query("
                                SELECT r.*, e.nome as espaco_nome, s.nome as socio_nome
                                FROM reservas r
                                JOIN espacos e ON r.espaco_id = e.id
                                JOIN socios s ON r.socio_id = s.id
                                ORDER BY r.data_reserva DESC
                                LIMIT 20
                            ")->fetchAll();
                            ?>
                            <?php foreach($reservas as $reserva): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($reserva['data_reserva'])) ?></td>
                                <td><?= htmlspecialchars($reserva['espaco_nome']) ?></td>
                                <td><?= htmlspecialchars($reserva['socio_nome']) ?></td>
                                <td><?= substr($reserva['hora_inicio'], 0, 5) ?> - <?= substr($reserva['hora_fim'], 0, 5) ?></td>
                                <td>R$ <?= number_format($reserva['valor_total'], 2, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($reserva['status']) {
                                        'confirmada' => 'bg-success',
                                        'pendente' => 'bg-warning',
                                        'cancelada' => 'bg-danger',
                                        'finalizada' => 'bg-secondary',
                                        default => 'bg-info'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($reserva['status']) ?></span>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?= $reserva['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="cancelar.php?id=<?= $reserva['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancelar esta reserva?')">
                                        <i class="fas fa-times"></i>
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
</body>
</html>
