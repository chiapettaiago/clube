<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

$socio_id = $_SESSION['socio_id'] ?? null;

// Se o usuário não tem sócio vinculado, buscar pelo CPF
if(!$socio_id) {
    $stmt = $pdo->prepare("SELECT id FROM socios WHERE email = ? OR cpf = ?");
    $stmt->execute([$_SESSION['usuario_email'] ?? '', '']);
    $socio = $stmt->fetch();
    if($socio) {
        $socio_id = $socio['id'];
        $_SESSION['socio_id'] = $socio_id;
    }
}

// Buscar inscrições do sócio
$inscricoes = $pdo->prepare("
    SELECT i.*, e.titulo, e.data_inicio, e.data_fim, e.local, e.imagem, e.carga_horaria
    FROM inscricoes_eventos i
    JOIN eventos e ON i.evento_id = e.id
    WHERE i.socio_id = ?
    ORDER BY e.data_inicio DESC
");
$inscricoes->execute([$socio_id]);
$inscricoes = $inscricoes->fetchAll();

// Buscar eventos disponíveis (não inscritos)
$eventosDisponiveis = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id) as inscritos
    FROM eventos e
    WHERE e.status = 'ativo' 
    AND e.data_inicio > NOW()
    AND e.id NOT IN (SELECT evento_id FROM inscricoes_eventos WHERE socio_id = ? AND status = 'confirmada')
    ORDER BY e.data_inicio ASC
    LIMIT 10
");
$eventosDisponiveis->execute([$socio_id]);
$eventosDisponiveis = $eventosDisponiveis->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .evento-card {
            transition: all 0.3s;
            border-radius: 15px;
            overflow: hidden;
        }
        .evento-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .evento-imagem {
            height: 150px;
            object-fit: cover;
            width: 100%;
        }
        .badge-presenca {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            left: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <!-- Cabeçalho -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-calendar-check text-primary"></i> Meus Eventos</h2>
                    <span class="badge bg-primary fs-6"><?= count($inscricoes) ?> eventos</span>
                </div>
                <hr>
            </div>
        </div>
        
        <!-- Meus Eventos (Inscrições) -->
        <div class="row mb-5">
            <div class="col-12">
                <h4><i class="fas fa-ticket-alt"></i> Minhas Inscrições</h4>
            </div>
            
            <?php if(empty($inscricoes)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-calendar-day fa-4x mb-3 d-block"></i>
                    <h5>Você ainda não se inscreveu em nenhum evento</h5>
                    <p>Confira os eventos disponíveis abaixo e faça sua inscrição!</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($inscricoes as $insc): 
                    $eventoPassado = strtotime($insc['data_fim']) < time();
                    $status = '';
                    if($insc['presenca_confirmada']) {
                        $status = '<span class="badge bg-success"><i class="fas fa-check"></i> Presente</span>';
                    } elseif($eventoPassado) {
                        $status = '<span class="badge bg-danger"><i class="fas fa-times"></i> Ausente</span>';
                    } else {
                        $status = '<span class="badge bg-warning"><i class="fas fa-clock"></i> Aguardando</span>';
                    }
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card evento-card h-100 position-relative">
                        <?php if($insc['imagem'] && file_exists('../../assets/uploads/eventos/' . $insc['imagem'])): ?>
                            <img src="../../assets/uploads/eventos/<?= $insc['imagem'] ?>" class="evento-imagem">
                        <?php else: ?>
                            <div class="evento-imagem bg-secondary d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar-alt fa-4x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="status-badge"><?= $status ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($insc['titulo']) ?></h5>
                            <p class="card-text text-muted small">
                                <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($insc['data_inicio'])) ?>
                                <br>
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($insc['local'] ?: 'Não informado') ?>
                                <?php if($insc['carga_horaria'] > 0): ?>
                                <br>
                                <i class="fas fa-hourglass-half"></i> <?= $insc['carga_horaria'] ?> horas
                                <?php endif; ?>
                            </p>
                            <?php if($insc['certificado_emitido']): ?>
                                <a href="ajax/visualizar_certificado.php?inscricao=<?= $insc['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                    <i class="fas fa-certificate"></i> Ver Certificado
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <small class="text-muted">
                                Inscrito em: <?= date('d/m/Y', strtotime($insc['data_inscricao'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Eventos Disponíveis -->
        <div class="row">
            <div class="col-12">
                <h4><i class="fas fa-calendar-plus"></i> Eventos Disponíveis</h4>
            </div>
            
            <?php if(empty($eventosDisponiveis)): ?>
            <div class="col-12">
                <div class="alert alert-secondary text-center py-5">
                    <i class="fas fa-check-circle fa-4x mb-3 d-block"></i>
                    <h5>Nenhum evento disponível no momento</h5>
                    <p>Em breve teremos novidades! Fique atento.</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($eventosDisponiveis as $evento): 
                    $vagasRestantes = $evento['capacidade'] - $evento['inscritos'];
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card evento-card h-100">
                        <?php if($evento['imagem'] && file_exists('../../assets/uploads/eventos/' . $evento['imagem'])): ?>
                            <img src="../../assets/uploads/eventos/<?= $evento['imagem'] ?>" class="evento-imagem">
                        <?php else: ?>
                            <div class="evento-imagem bg-secondary d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar-alt fa-4x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($evento['titulo']) ?></h5>
                            <p class="card-text text-muted small">
                                <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?>
                                <br>
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evento['local'] ?: 'Não informado') ?>
                                <br>
                                <i class="fas fa-users"></i> <?= $vagasRestantes ?> vagas restantes
                            </p>
                            <p class="card-text"><?= nl2br(htmlspecialchars(substr($evento['descricao'], 0, 100))) ?>...</p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <button onclick="inscrever(<?= $evento['id'] ?>)" class="btn btn-primary w-100">
                                <i class="fas fa-check-circle"></i> Inscrever-se
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function inscrever(eventoId) {
            if(confirm('Confirmar inscrição neste evento?')) {
                fetch('ajax/inscrever.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'evento_id=' + eventoId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('✅ Inscrição realizada com sucesso!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>