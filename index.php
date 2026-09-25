<?php
require_once 'config.php';

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        
        // Carrega permissões
        $stmt = $pdo->prepare("
            SELECT p.nome FROM usuario_permissoes up 
            JOIN permissoes p ON up.permissao_id = p.id 
            WHERE up.usuario_id = ?
        ");
        $stmt->execute([$usuario['id']]);
        $permissoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $_SESSION['permissoes'] = $permissoes;
        
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = 'E-mail ou senha inválidos!';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo i {
            font-size: 60px;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <i class="fas fa-users"></i>
            <h3 class="mt-2">Sistema de Clube</h3>
        </div>
        
        <?php if($erro): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
        
        <div class="text-center mt-3">
            <small class="text-muted">Usuário padrão: admin@admin.com / 123456</small>
        </div>
    </div>
</body>
</html>