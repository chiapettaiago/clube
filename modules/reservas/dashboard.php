<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$hoje = date('Y-m-d');
$amanha = date('Y-m-d', strtotime('+1 day'));

// Estatísticas
$totalEspacos = $pdo->query("SELECT COUNT(*) FROM espacos WHERE ativo = 1")->fetchColumn();

// Reservas hoje
$reservasHoje = $pdo->prepare("
    SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor_total
    FROM reservas 
    WHERE data_reserva = ? AND status IN ('confirmada', 'pendente')
");
$reservasHoje->execute([$hoje]);
$reservasHoje = $reservasHoje->fetch();

// Reservas pendentes
$reservasPendentes = $pdo->query("
    SELECT COUNT(*) as total 
    FROM reservas 
    WHERE status = 'pendente'
")->fetchColumn();

// Reservas confirmadas
$reservasConfirmadas = $pdo->query("
    SELECT COUNT(*) as total 
    FROM reservas 
    WHERE status = 'confirmada'
")->fetchColumn();

// Próximas reservas
$proximasReservas = $pdo->prepare("
    SELECT r.*, e.nome as espaco_nome, e.tipo, s.nome as socio_nome, s.numero_titulo
    FROM reservas r
    JOIN espacos e ON r.espaco_id = e.id
    JOIN socios s ON r.socio_id = s.id
    WHERE r.data_reserva >= ? AND r.status IN ('confirmada', 'pendente')
    ORDER BY r.data_reserva ASC, r.hora_inicio ASC
    LIMIT 10
");
$proximasReservas->execute([$hoje]);
$proximasReservas = $proximasReservas->fetchAll();

// Espaços mais reservados
$espacosTop = $pdo->query("
    SELECT e.nome, e.tipo, COUNT(r.id) as total_reservas
    FROM espacos e
    LEFT JOIN reservas r ON e.id = r.espaco_id
    WHERE r.status IN ('confirmada', 'finalizada')
    GROUP BY e.id
    ORDER BY total_reservas DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border: none;
            border-radius: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .reserva-card {
            transition: all 0.3s;
            border-left: 4px solid;
        }
        .reserva-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-pendente { border-left-color: #f39c12; }
        .status-confirmada { border-left-color: #2ecc71; }
        .status-cancelada { border-left-color: #e74c3c; }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-calendar-check text-success"></i> Módulo de Reservas</h2>
                    <a href="nova.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nova Reserva
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Espaços Disponíveis</h6>
                                <h2><?= $totalEspacos ?></h2>
                                <small>Para reserva</small>
                            </div>
                            <i class="fas fa-building fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Reservas Hoje</h6>
                                <h2><?= $reservasHoje['total'] ?></h2>
                                <small>R$ <?= number_format($reservasHoje['valor_total'], 2, ',', '.') ?></small>
                            </div>
                            <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Pendentes</h6>
                                <h2><?= $reservasPendentes ?></h2>
                                <small>Aguardando confirmação</small>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Confirmadas</h6>
                                <h2><?= $reservasConfirmadas ?></h2>
                                <small>Total ativas</small>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Próximas Reservas -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-clock"></i> Próximas Reservas</h5>
                        <a href="index.php" class="float-end text-white">Ver reservas →</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($proximasReservas)): ?>
                            <p class="text-center text-muted py-4">
                                <i class="fas fa-calendar-week fa-3x mb-3 d-block"></i>
                                Nenhuma reserva programada
                            </p>
                        <?php else: ?>
                            <?php foreach($proximasReservas as $reserva): ?>
                            <div class="card mb-2 reserva-card status-<?= $reserva['status'] ?>">
                                <div class="card-body py-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <strong><?= htmlspecialchars($reserva['espaco_nome']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= ucfirst($reserva['tipo']) ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($reserva['data_reserva'])) ?>
                                            <br>
                                            <i class="fas fa-clock"></i> <?= substr($reserva['hora_inicio'], 0, 5) ?> - <?= substr($reserva['hora_fim'], 0, 5) ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong><?= htmlspecialchars($reserva['socio_nome']) ?></strong>
                                            <br>
                                            <small>Título: <?= $reserva['numero_titulo'] ?></small>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <span class="badge bg-<?= $reserva['status'] == 'confirmada' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($reserva['status']) ?>
                                            </span>
                                            <br>
                                            <small>R$ <?= number_format($reserva['valor_total'], 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Espaços Mais Reservados -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-trophy"></i> Espaços Mais Reservados</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($espacosTop as $espaco): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <i class="fas <?= $espaco['tipo'] == 'salao' ? 'fa-champagne-glasses' : ($espaco['tipo'] == 'churrasqueira' ? 'fa-utensils' : ($espaco['tipo'] == 'quadra' ? 'fa-futbol' : 'fa-building')) ?> me-2"></i>
                                <?= htmlspecialchars($espaco['nome']) ?>
                            </div>
                            <div>
                                <span class="badge bg-primary rounded-pill"><?= $espaco['total_reservas'] ?> reservas</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5><i class="fas fa-bolt"></i> Ações Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="nova.php" class="btn btn-outline-success w-100 py-2">
                                    <i class="fas fa-plus-circle"></i> Nova Reserva
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="index.php" class="btn btn-outline-primary w-100 py-2">
                                    <i class="fas fa-calendar-alt"></i> Calendário
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="index.php" class="btn btn-outline-info w-100 py-2">
                                    <i class="fas fa-list"></i> Todas Reservas
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="configurar.php" class="btn btn-outline-secondary w-100 py-2">
                                    <i class="fas fa-cog"></i> Configurar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
