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

// Processar confirmação de presença
if($_POST && isset($_POST['confirmar_presenca'])) {
    $inscricao_id = $_POST['inscricao_id'];
    $stmt = $pdo->prepare("
        UPDATE inscricoes_eventos 
        SET presenca_confirmada = 1, data_presenca = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$inscricao_id]);
}

// Processar remover presença
if(isset($_GET['remover'])) {
    $inscricao_id = $_GET['remover'];
    $stmt = $pdo->prepare("
        UPDATE inscricoes_eventos 
        SET presenca_confirmada = 0, data_presenca = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$inscricao_id]);
}

// Confirmar presença em lote
if($_POST && isset($_POST['confirmar_lote'])) {
    $ids = $_POST['ids'] ?? [];
    if(!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            UPDATE inscricoes_eventos 
            SET presenca_confirmada = 1, data_presenca = NOW() 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
    }
}

// Buscar inscrições confirmadas
$inscricoes = $pdo->prepare("
    SELECT i.*, s.nome as socio_nome, s.numero_titulo, s.foto, s.telefone
    FROM inscricoes_eventos i
    JOIN socios s ON i.socio_id = s.id
    WHERE i.evento_id = ? AND i.status = 'confirmada'
    ORDER BY s.nome
");
$inscricoes->execute([$evento_id]);
$inscricoes = $inscricoes->fetchAll();

$presentes = array_filter($inscricoes, fn($i) => $i['presenca_confirmada'] == 1);
$ausentes = array_filter($inscricoes, fn($i) => $i['presenca_confirmada'] == 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Presença - <?= htmlspecialchars($evento['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .presente-row {
            background-color: #d4edda !important;
        }
        .ausente-row {
            background-color: #f8d7da !important;
        }
        .card-presenca {
            cursor: pointer;
            transition: all 0.3s;
        }
        .card-presenca:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .foto-presenca {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4><i class="fas fa-check-circle"></i> Lista de Presença</h4>
                <div class="float-end">
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-users"></i> Total: <?= count($inscricoes) ?>
                    </span>
                    <span class="badge bg-success">
                        <i class="fas fa-check"></i> Presentes: <?= count($presentes) ?>
                    </span>
                    <span class="badge bg-danger">
                        <i class="fas fa-times"></i> Ausentes: <?= count($ausentes) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                
                <!-- Informações do Evento -->
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-calendar"></i> Evento:</strong> <?= htmlspecialchars($evento['titulo']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-clock"></i> Data:</strong> <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-map-marker-alt"></i> Local:</strong> <?= htmlspecialchars($evento['local'] ?: 'Não informado') ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="mb-3">
                    <button class="btn btn-success" onclick="confirmarTodos()">
                        <i class="fas fa-check-double"></i> Confirmar Todos
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Lista
                    </button>
                    <button class="btn btn-info" onclick="exportarExcel()">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
                
                <!-- Cards de Presença -->
                <div class="row">
                    <?php foreach($inscricoes as $inscricao): ?>
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card card-presenca <?= $inscricao['presenca_confirmada'] ? 'presente-row' : 'ausente-row' ?>" 
                             onclick="togglePresenca(<?= $inscricao['id'] ?>, <?= $evento_id ?>)">
                            <div class="card-body text-center">
                                <?php if($inscricao['foto'] && file_exists('../../assets/uploads/' . $inscricao['foto'])): ?>
                                    <img src="../../assets/uploads/<?= $inscricao['foto'] ?>" class="foto-presenca mb-2">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-4x text-secondary mb-2"></i>
                                <?php endif; ?>
                                <h6 class="mb-1"><?= htmlspecialchars($inscricao['socio_nome']) ?></h6>
                                <small class="text-muted">Título: <?= $inscricao['numero_titulo'] ?></small>
                                <div class="mt-2">
                                    <?php if($inscricao['presenca_confirmada']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Presente
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-clock"></i> Pendente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(empty($inscricoes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users-slash fa-4x text-muted mb-3 d-block"></i>
                    <h5>Nenhuma inscrição confirmada</h5>
                    <a href="inscricoes.php?id=<?= $evento_id ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Adicionar Inscrições
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Formulário oculto para confirmar presença -->
    <form id="formPresenca" method="POST" style="display: none;">
        <input type="hidden" name="confirmar_presenca" value="1">
        <input type="hidden" name="inscricao_id" id="inscricao_id">
    </form>
    
    <script>
        function togglePresenca(inscricaoId, eventoId) {
            document.getElementById('inscricao_id').value = inscricaoId;
            document.getElementById('formPresenca').submit();
        }
        
        function confirmarTodos() {
            if(confirm('Confirmar presença de todos os inscritos?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'confirmar_todos';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportarExcel() {
            window.location.href = 'exportar_presenca.php?id=<?= $evento_id ?>';
        }
    </script>
</body>
</html>