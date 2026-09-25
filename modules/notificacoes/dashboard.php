<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Estatísticas
$totalEnviados = $pdo->query("SELECT COUNT(*) FROM historico_notificacoes")->fetchColumn();
$totalPendentes = $pdo->query("SELECT COUNT(*) FROM fila_notificacoes WHERE status = 'pendente'")->fetchColumn();
$totalFalhas = $pdo->query("SELECT COUNT(*) FROM historico_notificacoes WHERE status = 'falhou'")->fetchColumn();

// Últimos envios
$ultimosEnvios = $pdo->query("
    SELECT h.*, s.nome as socio_nome
    FROM historico_notificacoes h
    LEFT JOIN socios s ON h.socio_id = s.id
    ORDER BY h.data_envio DESC
    LIMIT 10
")->fetchAll();

// Assinaturas ativas
$totalAssinaturas = $pdo->query("SELECT COUNT(*) FROM assinaturas_notificacoes WHERE ativo = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Sistema de Clube</title>
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
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-bell text-primary"></i> Módulo de Notificações</h2>
                    <a href="enviar.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Nova Notificação
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Enviados</h6>
                                <h2><?= $totalEnviados ?></h2>
                                <small>Notificações</small>
                            </div>
                            <i class="fas fa-paper-plane fa-3x opacity-50"></i>
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
                                <h2><?= $totalPendentes ?></h2>
                                <small>Na fila</small>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Falhas</h6>
                                <h2><?= $totalFalhas ?></h2>
                                <small>Erros no envio</small>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Assinaturas</h6>
                                <h2><?= $totalAssinaturas ?></h2>
                                <small>Ativas</small>
                            </div>
                            <i class="fas fa-envelope-open-text fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Últimos Envios -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-history"></i> Últimas Notificações</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Destinatário</th>
                                        <th>Tipo</th>
                                        <th>Assunto</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ultimosEnvios as $envio): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($envio['data_envio'])) ?></td>
                                        <td><?= $envio['socio_nome'] ?? 'Sistema' ?></td>
                                        <td>
                                            <?php if($envio['tipo'] == 'email'): ?>
                                                <span class="badge bg-info"><i class="fas fa-envelope"></i> E-mail</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="fas fa-phone"></i> SMS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(substr($envio['assunto'], 0, 50)) ?>...</td>
                                        <td>
                                            <?php if($envio['status'] == 'enviado'): ?>
                                                <span class="badge bg-success">Enviado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Falhou</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5><i class="fas fa-bolt"></i> Ações Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="enviar.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-paper-plane fa-2x d-block mb-2"></i>
                                    Nova Notificação
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="templates.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-file-alt fa-2x d-block mb-2"></i>
                                    Templates
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="assinaturas.php" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-users fa-2x d-block mb-2"></i>
                                    Assinaturas
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="configuracoes.php" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="fas fa-cog fa-2x d-block mb-2"></i>
                                    Configurações
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lembretes Programados -->
                <div class="card mt-3">
                    <div class="card-header bg-warning text-white">
                        <h5><i class="fas fa-clock"></i> Lembretes Programados</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Aniversariantes
                                <span class="badge bg-primary rounded-pill">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Vencimento de Mensalidades
                                <span class="badge bg-primary rounded-pill">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Próximos Eventos
                                <span class="badge bg-primary rounded-pill">Ativo</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Confirmação de Reservas
                                <span class="badge bg-primary rounded-pill">Ativo</span>
                            </li>
                        </ul>
                        <div class="text-center mt-3">
                            <a href="lembretes.php" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-cog"></i> Gerenciar Lembretes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>