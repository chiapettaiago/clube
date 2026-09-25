<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$evento_id = $_GET['id'] ?? 0;

// Buscar evento
$stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
$stmt->execute([$evento_id]);
$evento = $stmt->fetch();

if(!$evento) {
    header('Location: index.php');
    exit;
}

// Buscar inscrições com presença confirmada
$inscricoes = $pdo->prepare("
    SELECT i.*, s.nome as socio_nome, s.numero_titulo, s.cpf
    FROM inscricoes_eventos i
    JOIN socios s ON i.socio_id = s.id
    WHERE i.evento_id = ? AND i.status = 'confirmada' AND i.presenca_confirmada = 1
    ORDER BY s.nome
");
$inscricoes->execute([$evento_id]);
$inscricoes = $inscricoes->fetchAll();

$comCertificado = array_filter($inscricoes, fn($i) => $i['certificado_emitido'] == 1);
$semCertificado = array_filter($inscricoes, fn($i) => $i['certificado_emitido'] == 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificados - <?= htmlspecialchars($evento['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4><i class="fas fa-certificate"></i> Certificados - <?= htmlspecialchars($evento['titulo']) ?></h4>
            </div>
            <div class="card-body">
                
                <!-- Informações do Evento -->
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-calendar"></i> Evento:</strong> <?= htmlspecialchars($evento['titulo']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-clock"></i> Data:</strong> <?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-hourglass-half"></i> Carga Horária:</strong> <?= $evento['carga_horaria'] ?> horas
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h6>Participantes com Presença</h6>
                            <h3><?= count($inscricoes) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-primary">
                            <h6>Certificados Emitidos</h6>
                            <h3><?= count($comCertificado) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <h6>Pendentes</h6>
                            <h3><?= count($semCertificado) ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="mb-3">
                    <button class="btn btn-success" onclick="emitirTodos()">
                        <i class="fas fa-certificate"></i> Emitir Todos os Certificados
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Lista
                    </button>
                </div>
                
                <!-- Tabela de Certificados -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Sócio</th>
                                <th>Nº Título</th>
                                <th>Data Presença</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inscricoes as $insc): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle fa-2x text-secondary me-2"></i>
                                        <?= htmlspecialchars($insc['socio_nome']) ?>
                                    </div>
                                </td>
                                <td><?= $insc['numero_titulo'] ?></td>
                                <td><?= $insc['data_presenca'] ? date('d/m/Y H:i', strtotime($insc['data_presenca'])) : '-' ?></td>
                                <td>
                                    <?php if($insc['certificado_emitido']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Emitido
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock"></i> Pendente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!$insc['certificado_emitido']): ?>
                                        <button onclick="emitirCertificado(<?= $insc['id'] ?>, <?= $evento_id ?>)" class="btn btn-sm btn-info">
                                            <i class="fas fa-certificate"></i> Emitir
                                        </button>
                                    <?php else: ?>
                                        <a href="ajax/visualizar_certificado.php?inscricao=<?= $insc['id'] ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-eye"></i> Visualizar
                                        </a>
                                    <?php endif; ?>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                            <?php if(empty($inscricoes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                    Nenhum participante com presença confirmada
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
        function emitirCertificado(inscricaoId, eventoId) {
            window.open(`ajax/gerar_certificado.php?inscricao=${inscricaoId}&evento=${eventoId}`, '_blank');
            setTimeout(() => location.reload(), 1000);
        }
        
        function emitirTodos() {
            if(confirm('Emitir certificados para todos os participantes?')) {
                window.location.href = `ajax/emitir_todos_certificados.php?evento=<?= $evento_id ?>`;
            }
        }
    </script>
</body>
</html>