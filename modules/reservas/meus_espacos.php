<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

$socio_id = $_SESSION['socio_id'] ?? null;

// Se o usuário não tem sócio vinculado
if(!$socio_id) {
    $stmt = $pdo->prepare("SELECT id FROM socios WHERE email = ?");
    $stmt->execute([$_SESSION['usuario_email'] ?? '']);
    $socio = $stmt->fetch();
    if($socio) {
        $socio_id = $socio['id'];
        $_SESSION['socio_id'] = $socio_id;
    }
}

// Buscar reservas do sócio
$reservas = $pdo->prepare("
    SELECT r.*, e.nome as espaco_nome, e.tipo, e.imagem
    FROM reservas r
    JOIN espacos e ON r.espaco_id = e.id
    WHERE r.socio_id = ?
    ORDER BY r.data_reserva DESC
");
$reservas->execute([$socio_id]);
$reservas = $reservas->fetchAll();

// Buscar espaços disponíveis
$espacos = $pdo->query("SELECT * FROM espacos WHERE ativo = 1 ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Espaços</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <!-- Minhas Reservas -->
        <div class="row mb-5">
            <div class="col-12">
                <h3><i class="fas fa-calendar-check text-primary"></i> Minhas Reservas</h3>
                <hr>
            </div>
            
            <?php if(empty($reservas)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-calendar-day fa-4x mb-3 d-block"></i>
                    <h5>Você ainda não fez nenhuma reserva</h5>
                    <p>Escolha um espaço abaixo e faça sua reserva!</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($reservas as $r): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title"><?= htmlspecialchars($r['espaco_nome']) ?></h5>
                                <span class="badge bg-<?= $r['status'] == 'confirmada' ? 'success' : ($r['status'] == 'pendente' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </div>
                            <p class="card-text">
                                <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($r['data_reserva'])) ?>
                                <br>
                                <i class="fas fa-clock"></i> <?= substr($r['hora_inicio'], 0, 5) ?> - <?= substr($r['hora_fim'], 0, 5) ?>
                                <br>
                                <i class="fas fa-dollar-sign"></i> R$ <?= number_format($r['valor_total'], 2, ',', '.') ?>
                                <br>
                                <i class="fas fa-hashtag"></i> Código: <?= $r['codigo_reserva'] ?>
                            </p>
                            <?php if($r['status'] == 'confirmada' && strtotime($r['data_reserva']) > time()): ?>
                                <a href="cancelar.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancelar esta reserva?')">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Espaços Disponíveis -->
        <div class="row">
            <div class="col-12">
                <h3><i class="fas fa-building text-success"></i> Espaços Disponíveis</h3>
                <hr>
            </div>
            
            <?php foreach($espacos as $e): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($e['nome']) ?></h5>
                        <p class="card-text text-muted small">
                            <i class="fas fa-users"></i> Capacidade: <?= $e['capacidade'] ?: 'Ilimitada' ?> pessoas
                            <br>
                            <i class="fas fa-clock"></i> Horário: <?= substr($e['horario_inicio'], 0, 5) ?> - <?= substr($e['horario_fim'], 0, 5) ?>
                            <br>
                            <i class="fas fa-dollar-sign"></i> R$ <?= number_format($e['valor_hora'], 2, ',', '.') ?>/hora
                        </p>
                        <p class="card-text"><?= nl2br(htmlspecialchars(substr($e['descricao'], 0, 100))) ?></p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="nova.php?espaco=<?= $e['id'] ?>" class="btn btn-primary w-100">
                            <i class="fas fa-calendar-plus"></i> Reservar
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>