<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Buscar configurações
$config = [];
$stmt = $pdo->query("SELECT chave, valor FROM config_notificacoes");
foreach($stmt as $c) {
    $config[$c['chave']] = $c['valor'];
}

// Processar formulário
if($_POST) {
    $chaves = [
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure',
        'email_remetente', 'nome_remetente', 'sms_api_key', 'sms_api_secret', 'sms_remetente',
        'lembrete_aniversario', 'lembrete_vencimento', 'lembrete_evento'
    ];
    
    foreach($chaves as $chave) {
        $valor = $_POST[$chave] ?? '';
        $stmt = $pdo->prepare("UPDATE config_notificacoes SET valor = ? WHERE chave = ?");
        $stmt->execute([$valor, $chave]);
    }
    
    // Testar SMTP
    if(isset($_POST['testar_smtp'])) {
        $teste = testarSMTP($config['smtp_host'], $config['smtp_port'], $config['smtp_user'], $config['smtp_pass'], $config['smtp_secure']);
        $msgTeste = $teste ? "✅ Conexão SMTP bem-sucedida!" : "❌ Falha na conexão SMTP. Verifique as configurações.";
    }
    
    $msg = "Configurações salvas com sucesso!";
}

function testarSMTP($host, $port, $user, $pass, $secure) {
    try {
        $transport = new \Swift_SmtpTransport($host, $port, $secure);
        $transport->setUsername($user);
        $transport->setPassword($pass);
        $mailer = new \Swift_Mailer($transport);
        $mailer->getTransport()->start();
        return true;
    } catch(Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Notificações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-envelope"></i> Configurações de E-mail (SMTP)</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($msg)): ?>
                            <div class="alert alert-success"><?= $msg ?></div>
                        <?php endif; ?>
                        <?php if(isset($msgTeste)): ?>
                            <div class="alert alert-info"><?= $msgTeste ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label>Servidor SMTP</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= $config['smtp_host'] ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="mb-3">
                                <label>Porta SMTP</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= $config['smtp_port'] ?>" placeholder="587">
                            </div>
                            <div class="mb-3">
                                <label>Tipo de Segurança</label>
                                <select name="smtp_secure" class="form-select">
                                    <option value="tls" <?= $config['smtp_secure'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= $config['smtp_secure'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="" <?= $config['smtp_secure'] == '' ? 'selected' : '' ?>>Nenhum</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Usuário (E-mail)</label>
                                <input type="email" name="smtp_user" class="form-control" value="<?= $config['smtp_user'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Senha</label>
                                <input type="password" name="smtp_pass" class="form-control" value="<?= $config['smtp_pass'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>E-mail Remetente</label>
                                <input type="email" name="email_remetente" class="form-control" value="<?= $config['email_remetente'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Nome do Remetente</label>
                                <input type="text" name="nome_remetente" class="form-control" value="<?= $config['nome_remetente'] ?>">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="salvar" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Configurações
                                </button>
                                <button type="submit" name="testar_smtp" class="btn btn-info">
                                    <i class="fas fa-vial"></i> Testar Conexão SMTP
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-phone"></i> Configurações de SMS</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>API Key (Twilio/Zenvia)</label>
                                <input type="text" name="sms_api_key" class="form-control" value="<?= $config['sms_api_key'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>API Secret</label>
                                <input type="text" name="sms_api_secret" class="form-control" value="<?= $config['sms_api_secret'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Remetente (Nome ou Número)</label>
                                <input type="text" name="sms_remetente" class="form-control" value="<?= $config['sms_remetente'] ?>">
                            </div>
                            <button type="submit" name="salvar" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Salvar Configurações
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header bg-warning text-white">
                        <h5><i class="fas fa-clock"></i> Lembretes Automáticos</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Lembrete de Aniversário (dias antes)</label>
                                <input type="number" name="lembrete_aniversario" class="form-control" value="<?= $config['lembrete_aniversario'] ?>">
                                <small>0 = não enviar</small>
                            </div>
                            <div class="mb-3">
                                <label>Lembrete de Vencimento (dias antes)</label>
                                <input type="number" name="lembrete_vencimento" class="form-control" value="<?= $config['lembrete_vencimento'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Lembrete de Evento (dias antes)</label>
                                <input type="number" name="lembrete_evento" class="form-control" value="<?= $config['lembrete_evento'] ?>">
                            </div>
                            <button type="submit" name="salvar" class="btn btn-warning w-100">
                                <i class="fas fa-save"></i> Salvar Lembretes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>