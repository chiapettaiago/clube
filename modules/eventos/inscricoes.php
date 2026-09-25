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

// Processar inscrição manual
if($_POST && isset($_POST['inscrever'])) {
    $socio_id = $_POST['socio_id'];
    
    // Verificar se já está inscrito
    $check = $pdo->prepare("SELECT id FROM inscricoes_eventos WHERE evento_id = ? AND socio_id = ?");
    $check->execute([$evento_id, $socio_id]);
    
    if($check->rowCount() == 0) {
        $codigo = uniqid('INS-');
        $stmt = $pdo->prepare("
            INSERT INTO inscricoes_eventos (evento_id, socio_id, codigo_inscricao, status) 
            VALUES (?, ?, ?, 'confirmada')
        ");
        $stmt->execute([$evento_id, $socio_id, $codigo]);
        
        // Atualizar vagas
        $pdo->prepare("UPDATE eventos SET vagas_disponiveis = vagas_disponiveis - 1 WHERE id = ?")->execute([$evento_id]);
    }
}

// Processar cancelamento
if(isset($_GET['cancelar'])) {
    $inscricao_id = $_GET['cancelar'];
    $stmt = $pdo->prepare("UPDATE inscricoes_eventos SET status = 'cancelada' WHERE id = ?");
    $stmt->execute([$inscricao_id]);
    
    // Atualizar vagas
    $pdo->prepare("UPDATE eventos SET vagas_disponiveis = vagas_disponiveis + 1 WHERE id = ?")->execute([$evento_id]);
    
    header("Location: inscricoes.php?id=$evento_id");
    exit;
}

// Buscar inscrições
$inscricoes = $pdo->prepare("
    SELECT i.*, s.nome as socio_nome, s.numero_titulo, s.foto
    FROM inscricoes_eventos i
    JOIN socios s ON i.socio_id = s.id
    WHERE i.evento_id = ?
    ORDER BY i.data_inscricao DESC
");
$inscricoes->execute([$evento_id]);
$inscricoes = $inscricoes->fetchAll();

// Buscar sócios não inscritos
$naoInscritos = $pdo->prepare("
    SELECT s.id, s.nome, s.numero_titulo
    FROM socios s
    WHERE s.ativo = 1 
    AND s.id NOT IN (SELECT socio_id FROM inscricoes_eventos WHERE evento_id = ? AND status = 'confirmada')
    ORDER BY s.nome
");
$naoInscritos->execute([$evento_id]);
$naoInscritos = $naoInscritos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrições - <?= htmlspecialchars($evento['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-list"></i> Inscrições - <?= htmlspecialchars($evento['titulo']) ?></h4>
                <div class="float-end">
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalInscricao">
                        <i class="fas fa-plus"></i> Nova Inscrição
                    </button>
                    <a href="presenca.php?id=<?= $evento_id ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-check"></i> Lista de Presença
                    </a>
                </div>
            </div>
            <div class="card-body">
                
                <!-- Resumo -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-info">
                            <h6>Total de Inscritos</h6>
                            <h3><?= count($inscricoes) ?> / <?= $evento['capacidade'] ?: '∞' ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-success">
                            <h6>Confirmados</h6>
                            <h3><?= count(array_filter($inscricoes, fn($i) => $i['status'] == 'confirmada')) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning">
                            <h6>Presentes</h6>
                            <h3><?= count(array_filter($inscricoes, fn($i) => $i['presenca_confirmada'] == 1)) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger">
                            <h6>Cancelados</h6>
                            <h3><?= count(array_filter($inscricoes, fn($i) => $i['status'] == 'cancelada')) ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de Inscrições -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Sócio</th>
                                <th>Nº Título</th>
                                <th>Data Inscrição</th>
                                <th>Status</th>
                                <th>Presença</th>
                                <th>Certificado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inscricoes as $insc): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if($insc['foto'] && file_exists('../../assets/uploads/' . $insc['foto'])): ?>
                                            <img src="../../assets/uploads/<?= $insc['foto'] ?>" width="35" height="35" class="rounded-circle me-2">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x text-secondary me-2"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($insc['socio_nome']) ?>
                                    </div>
                                </td>
                                <td><?= $insc['numero_titulo'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($insc['data_inscricao'])) ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($insc['status']) {
                                        'confirmada' => 'bg-success',
                                        'cancelada' => 'bg-danger',
                                        'pendente' => 'bg-warning',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($insc['status']) ?></span>
                                </td>
                                <td>
                                    <?php if($insc['presenca_confirmada']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Presente</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ausente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($insc['certificado_emitido']): ?>
                                        <span class="badge bg-info"><i class="fas fa-certificate"></i> Emitido</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não emitido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($insc['status'] == 'confirmada'): ?>
                                        <a href="?cancelar=<?= $insc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancelar esta inscrição?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($insc['presenca_confirmada'] && !$insc['certificado_emitido'] && $evento['carga_horaria'] > 0): ?>
                                        <button onclick="emitirCertificado(<?= $insc['id'] ?>, <?= $evento_id ?>)" class="btn btn-sm btn-info">
                                            <i class="fas fa-certificate"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($inscricoes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                                    Nenhuma inscrição ainda
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Nova Inscrição -->
    <div class="modal fade" id="modalInscricao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nova Inscrição</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="inscrever" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Sócio</label>
                            <select name="socio_id" class="form-select select2" required>
                                <option value="">-- Selecione o sócio --</option>
                                <?php foreach($naoInscritos as $socio): ?>
                                    <option value="<?= $socio['id'] ?>"><?= htmlspecialchars($socio['nome']) ?> (<?= $socio['numero_titulo'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if($evento['valor'] > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            Este evento tem custo de R$ <?= number_format($evento['valor'], 2, ',', '.') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar Inscrição</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('#modalInscricao')
        });
        
        function emitirCertificado(inscricaoId, eventoId) {
            window.open(`ajax/gerar_certificado.php?inscricao=${inscricaoId}&evento=${eventoId}`, '_blank');
        }
    </script>
</body>
</html>