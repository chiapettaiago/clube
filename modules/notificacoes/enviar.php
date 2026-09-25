<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Buscar sócios para o select
$socios = $pdo->query("SELECT id, nome, email_socio, telefone FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Buscar templates
$templates = $pdo->query("SELECT * FROM templates_notificacao WHERE ativo = 1 ORDER BY nome")->fetchAll();

if($_POST) {
    $tipo = $_POST['tipo'];
    $destinatario_tipo = $_POST['destinatario_tipo'];
    $assunto = $_POST['assunto'];
    $mensagem = $_POST['mensagem'];
    
    if($destinatario_tipo == 'todos') {
        // Enviar para todos os sócios ativos
        $stmt = $pdo->query("SELECT id, nome, email_socio, telefone FROM socios WHERE ativo = 1");
        $destinatarios = $stmt->fetchAll();
        
        foreach($destinatarios as $dest) {
            $email = $dest['email_socio'];
            $telefone = $dest['telefone'];
            $mensagemPersonalizada = str_replace('{NOME}', $dest['nome'], $mensagem);
            
            if($tipo == 'email' && $email) {
                // Adicionar à fila de e-mail
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', ?, ?)");
                $stmt->execute([$email, $assunto, $mensagemPersonalizada]);
            } elseif($tipo == 'sms' && $telefone) {
                // Adicionar à fila de SMS
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', ?, ?)");
                $stmt->execute([$telefone, $assunto, $mensagemPersonalizada]);
            }
        }
        $msg = "Notificação adicionada à fila para todos os sócios!";
        
    } elseif($destinatario_tipo == 'grupo') {
        $grupo = $_POST['grupo'];
        // Implementar grupos específicos
        $msg = "Funcionalidade em desenvolvimento";
        
    } else {
        // Enviar para um sócio específico
        $socio_id = $_POST['socio_id'];
        $stmt = $pdo->prepare("SELECT nome, email_socio, telefone FROM socios WHERE id = ?");
        $stmt->execute([$socio_id]);
        $socio = $stmt->fetch();
        
        $mensagemPersonalizada = str_replace('{NOME}', $socio['nome'], $mensagem);
        
        if($tipo == 'email' && $socio['email_socio']) {
            $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', ?, ?)");
            $stmt->execute([$socio['email_socio'], $assunto, $mensagemPersonalizada]);
        } elseif($tipo == 'sms' && $socio['telefone']) {
            $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', ?, ?)");
            $stmt->execute([$socio['telefone'], $assunto, $mensagemPersonalizada]);
        }
        $msg = "Notificação adicionada à fila!";
    }
    
    // Registrar no histórico
    $stmt = $pdo->prepare("INSERT INTO historico_notificacoes (socio_id, tipo, assunto, mensagem, status) VALUES (?, ?, ?, ?, 'enviado')");
    $stmt->execute([$socio_id ?? null, $tipo, $assunto, $mensagem]);
    
    $sucesso = "✅ Notificação adicionada à fila de envio!";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Notificação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-paper-plane"></i> Enviar Notificação</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($sucesso)): ?>
                            <div class="alert alert-success"><?= $sucesso ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="formNotificacao">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="required">Tipo de Notificação</label>
                                    <select name="tipo" id="tipo" class="form-select" required>
                                        <option value="email">E-mail</option>
                                        <option value="sms">SMS</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="required">Destinatário</label>
                                    <select name="destinatario_tipo" id="destinatario_tipo" class="form-select" required>
                                        <option value="todos">Todos os sócios</option>
                                        <option value="unico">Sócio específico</option>
                                        <option value="grupo">Grupo específico</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3" id="div_socio" style="display: none;">
                                    <label>Sócio</label>
                                    <select name="socio_id" class="form-select select2">
                                        <option value="">Selecione o sócio...</option>
                                        <?php foreach($socios as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?> (<?= $s['email_socio'] ?: $s['telefone'] ?: 'Sem contato' ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Template</label>
                                    <select name="template_id" id="template_id" class="form-select">
                                        <option value="">Selecionar template...</option>
                                        <?php foreach($templates as $t): ?>
                                            <option value="<?= $t['id'] ?>" data-assunto="<?= htmlspecialchars($t['assunto']) ?>" data-mensagem="<?= htmlspecialchars($t['mensagem']) ?>">
                                                <?= htmlspecialchars($t['nome']) ?> (<?= $t['tipo'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Assunto</label>
                                    <input type="text" name="assunto" id="assunto" class="form-control" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Mensagem</label>
                                    <textarea name="mensagem" id="mensagem" class="form-control" rows="8" required></textarea>
                                    <small class="text-muted">
                                        Variáveis disponíveis: {NOME} - Nome do sócio
                                    </small>
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-paper-plane"></i> Enviar Notificação
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary px-5">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            
            $('#destinatario_tipo').change(function() {
                if($(this).val() == 'unico') {
                    $('#div_socio').show();
                } else {
                    $('#div_socio').hide();
                }
            });
            
            $('#template_id').change(function() {
                var option = $(this).find('option:selected');
                var assunto = option.data('assunto');
                var mensagem = option.data('mensagem');
                if(assunto) $('#assunto').val(assunto);
                if(mensagem) $('#mensagem').val(mensagem);
            });
        });
    </script>
</body>
</html>