<?php
require_once 'config.php';
require_once 'includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Estatísticas
$hoje = date('Y-m-d H:i:s');

// Eventos ativos
$eventosAtivos = $pdo->query("
    SELECT COUNT(*) as total 
    FROM eventos 
    WHERE status = 'ativo' AND data_inicio >= NOW()
")->fetchColumn();

// Eventos realizados
$eventosRealizados = $pdo->query("
    SELECT COUNT(*) as total 
    FROM eventos 
    WHERE data_fim < NOW()
")->fetchColumn();

// Total de inscrições
$totalInscricoes = $pdo->query("
    SELECT COUNT(*) as total 
    FROM inscricoes_eventos 
    WHERE status = 'confirmada'
")->fetchColumn();

// Próximos eventos
$proximosEventos = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id AND status = 'confirmada') as inscritos
    FROM eventos e
    WHERE e.status = 'ativo' AND e.data_inicio >= NOW()
    ORDER BY e.data_inicio ASC
    LIMIT 5
");
$proximosEventos->execute();
$proximosEventos = $proximosEventos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Eventos</title>
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
        .evento-card {
            transition: all 0.3s;
            border-left: 4px solid #3498db;
        }
        .evento-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-calendar-alt text-primary"></i> Módulo de Eventos</h2>
                    <a href="cadastrar.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Evento
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Eventos Ativos</h6>
                                <h2><?= $eventosAtivos ?></h2>
                                <small>Próximos eventos</small>
                            </div>
                            <i class="fas fa-calendar-check fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Eventos Realizados</h6>
                                <h2><?= $eventosRealizados ?></h2>
                                <small>Total de eventos</small>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Inscrições Totais</h6>
                                <h2><?= $totalInscricoes ?></h2>
                                <small>Participantes</small>
                            </div>
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Próximos Eventos -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-clock"></i> Próximos Eventos</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($proximosEventos)): ?>
                            <p class="text-center text-muted py-4">
                                <i class="fas fa-calendar-day fa-3x mb-3 d-block"></i>
                                Nenhum evento programado
                            </p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($proximosEventos as $evento): 
                                    $vagasRestantes = $evento['capacidade'] - $evento['inscritos'];
                                    $percentual = $evento['capacidade'] > 0 ? ($evento['inscritos'] / $evento['capacidade']) * 100 : 0;
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card evento-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h5 class="card-title"><?= htmlspecialchars($evento['titulo']) ?></h5>
                                                <span class="badge bg-primary"><?= ucfirst($evento['tipo']) ?></span>
                                            </div>
                                            <p class="card-text text-muted small">
                                                <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?>
                                                <br>
                                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evento['local'] ?: 'Não informado') ?>
                                            </p>
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar" style="width: <?= $percentual ?>%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between small">
                                                <span><i class="fas fa-users"></i> <?= $evento['inscritos'] ?> inscritos</span>
                                                <span><i class="fas fa-ticket-alt"></i> <?= $vagasRestantes ?> vagas</span>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="inscricoes.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-list"></i> Inscrições
                                            </a>
                                            <a href="presenca.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i> Presença
                                            </a>
                                            <a href="certificados.php?id=<?= $evento['id'] ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-certificate"></i> Certificados
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ações Rápidas -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5><i class="fas fa-bolt"></i> Ações Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3 col-6">
                                <a href="cadastrar.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                    Novo Evento
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="index.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-list fa-2x d-block mb-2"></i>
                                    Todos Eventos
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="meus_eventos.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-user-check fa-2x d-block mb-2"></i>
                                    Meus Eventos
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="relatorios.php" class="btn btn-outline-warning w-100 py-3">
                                    <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
                                    Relatórios
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