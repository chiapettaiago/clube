<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Processar atualização de assinatura
if($_POST && isset($_POST['salvar_assinatura'])) {
    $socio_id = $_POST['socio_id'];
    $tipos = ['aniversario', 'vencimento', 'evento', 'reserva', 'comunicado'];
    
    foreach($tipos as $tipo) {
        $canal = $_POST[$tipo] ?? 'nenhum';
        
        if($canal != 'nenhum') {
            $stmt = $pdo->prepare("
                INSERT INTO assinaturas_notificacoes (socio_id, tipo_notificacao, canal, ativo) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE canal = ?, ativo = 1
            ");
            $stmt->execute([$socio_id, $tipo, $canal, $canal]);
        } else {
            $stmt = $pdo->prepare("UPDATE assinaturas_notificacoes SET ativo = 0 WHERE socio_id = ? AND tipo_notificacao = ?");
            $stmt->execute([$socio_id, $tipo]);
        }
    }
    $msg = "Assinaturas atualizadas com sucesso!";
}

// Buscar sócios
$socios = $pdo->query("SELECT id, nome, email_socio, telefone FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Buscar assinaturas existentes
$assinaturas = [];
$stmt = $pdo->query("SELECT * FROM assinaturas_notificacoes WHERE ativo = 1");
foreach($stmt as $a) {
    $assinaturas[$a['socio_id']][$a['tipo_notificacao']] = $a['canal'];
}

// Sócio selecionado para edição
$socioSelecionado = null;
if(isset($_GET['socio'])) {
    $id = $_GET['socio'];
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE id = ?");
    $stmt->execute([$id]);
    $socioSelecionado = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas - Notificações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-envelope-open-text"></i> Assinaturas de Notificações</h5>
                    </div>
                    <div class="card-body">
                        
                        <?php if(isset($msg)): ?>
                            <div class="alert alert-success"><?= $msg ?></div>
                        <?php endif; ?>
                        
                        <!-- Lista de Sócios -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sócio</th>
                                        <th>E-mail</th>
                                        <th>Telefone</th>
                                        <th>Aniversário</th>
                                        <th>Vencimento</th>
                                        <th>Eventos</th>
                                        <th>Reservas</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($socios as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['nome']) ?> (<?= $s['id'] ?>)</td>
                                        <td><?= $s['email_socio'] ?: '-' ?></td>
                                        <td><?= $s['telefone'] ?: '-' ?></td>
                                        <td>
                                            <?php 
                                            $canal = $assinaturas[$s['id']]['aniversario'] ?? 'nenhum';
                                            $badgeClass = match($canal) {
                                                'email' => 'bg-info',
                                                'sms' => 'bg-success',
                                                'ambos' => 'bg-primary',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($canal) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $canal = $assinaturas[$s['id']]['vencimento'] ?? 'nenhum';
                                            $badgeClass = match($canal) {
                                                'email' => 'bg-info',
                                                'sms' => 'bg-success',
                                                'ambos' => 'bg-primary',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($canal) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $canal = $assinaturas[$s['id']]['evento'] ?? 'nenhum';
                                            $badgeClass = match($canal) {
                                                'email' => 'bg-info',
                                                'sms' => 'bg-success',
                                                'ambos' => 'bg-primary',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($canal) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $canal = $assinaturas[$s['id']]['reserva'] ?? 'nenhum';
                                            $badgeClass = match($canal) {
                                                'email' => 'bg-info',
                                                'sms' => 'bg-success',
                                                'ambos' => 'bg-primary',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($canal) ?></span>
                                        </td>
                                        <td>
                                            <a href="?socio=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Editar
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
        </div>
        
        <!-- Modal de Edição de Assinaturas -->
        <?php if($socioSelecionado): ?>
        <div class="row mt-4">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h5><i class="fas fa-edit"></i> Editar Assinaturas - <?= htmlspecialchars($socioSelecionado['nome']) ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="socio_id" value="<?= $socioSelecionado['id'] ?>">
                            <input type="hidden" name="salvar_assinatura" value="1">
                            
                            <div class="mb-3">
                                <label>Notificações de Aniversário</label>
                                <select name="aniversario" class="form-select">
                                    <option value="nenhum">Não receber</option>
                                    <option value="email" <?= ($assinaturas[$socioSelecionado['id']]['aniversario'] ?? '') == 'email' ? 'selected' : '' ?>>Receber por E-mail</option>
                                    <option value="sms" <?= ($assinaturas[$socioSelecionado['id']]['aniversario'] ?? '') == 'sms' ? 'selected' : '' ?>>Receber por SMS</option>
                                    <option value="ambos" <?= ($assinaturas[$socioSelecionado['id']]['aniversario'] ?? '') == 'ambos' ? 'selected' : '' ?>>Receber por ambos</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Vencimento de Mensalidades</label>
                                <select name="vencimento" class="form-select">
                                    <option value="nenhum">Não receber</option>
                                    <option value="email" <?= ($assinaturas[$socioSelecionado['id']]['vencimento'] ?? '') == 'email' ? 'selected' : '' ?>>Receber por E-mail</option>
                                    <option value="sms" <?= ($assinaturas[$socioSelecionado['id']]['vencimento'] ?? '') == 'sms' ? 'selected' : '' ?>>Receber por SMS</option>
                                    <option value="ambos" <?= ($assinaturas[$socioSelecionado['id']]['vencimento'] ?? '') == 'ambos' ? 'selected' : '' ?>>Receber por ambos</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Novos Eventos</label>
                                <select name="evento" class="form-select">
                                    <option value="nenhum">Não receber</option>
                                    <option value="email" <?= ($assinaturas[$socioSelecionado['id']]['evento'] ?? '') == 'email' ? 'selected' : '' ?>>Receber por E-mail</option>
                                    <option value="sms" <?= ($assinaturas[$socioSelecionado['id']]['evento'] ?? '') == 'sms' ? 'selected' : '' ?>>Receber por SMS</option>
                                    <option value="ambos" <?= ($assinaturas[$socioSelecionado['id']]['evento'] ?? '') == 'ambos' ? 'selected' : '' ?>>Receber por ambos</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Confirmação de Reservas</label>
                                <select name="reserva" class="form-select">
                                    <option value="nenhum">Não receber</option>
                                    <option value="email" <?= ($assinaturas[$socioSelecionado['id']]['reserva'] ?? '') == 'email' ? 'selected' : '' ?>>Receber por E-mail</option>
                                    <option value="sms" <?= ($assinaturas[$socioSelecionado['id']]['reserva'] ?? '') == 'sms' ? 'selected' : '' ?>>Receber por SMS</option>
                                    <option value="ambos" <?= ($assinaturas[$socioSelecionado['id']]['reserva'] ?? '') == 'ambos' ? 'selected' : '' ?>>Receber por ambos</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label>Comunicados Gerais</label>
                                <select name="comunicado" class="form-select">
                                    <option value="nenhum">Não receber</option>
                                    <option value="email" <?= ($assinaturas[$socioSelecionado['id']]['comunicado'] ?? '') == 'email' ? 'selected' : '' ?>>Receber por E-mail</option>
                                    <option value="sms" <?= ($assinaturas[$socioSelecionado['id']]['comunicado'] ?? '') == 'sms' ? 'selected' : '' ?>>Receber por SMS</option>
                                    <option value="ambos" <?= ($assinaturas[$socioSelecionado['id']]['comunicado'] ?? '') == 'ambos' ? 'selected' : '' ?>>Receber por ambos</option>
                                </select>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Preferências
                                </button>
                                <a href="assinaturas.php" class="btn btn-secondary">Voltar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>