<?php
require_once 'config.php';

// Criar usuário admin
$nome = 'Administrador';
$email = 'admin@admin.com';
$senha = '123456';
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try {
    // Verificar se já existe
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->execute([$email]);
    
    if($check->rowCount() > 0) {
        // Atualizar senha
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->execute([$senhaHash, $email]);
        echo "Usuário atualizado com sucesso!<br>";
    } else {
        // Inserir novo
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, ativo) VALUES (?, ?, ?, 1)");
        $stmt->execute([$nome, $email, $senhaHash]);
        echo "Usuário criado com sucesso!<br>";
    }
    
    // Pegar o ID do usuário
    $userId = $pdo->lastInsertId();
    if(!$userId) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();
    }
    
    // Dar todas as permissões
    $permissoes = $pdo->query("SELECT id FROM permissoes")->fetchAll();
    foreach($permissoes as $permissao) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)");
        $stmt->execute([$userId, $permissao['id']]);
    }
    
    echo "Permissões atribuídas com sucesso!<br>";
    echo "<hr>";
    echo "<strong>Dados para login:</strong><br>";
    echo "E-mail: admin@admin.com<br>";
    echo "Senha: 123456<br>";
    echo "<hr>";
    echo "<a href='index.php'>Ir para o login</a>";
    
} catch(PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>