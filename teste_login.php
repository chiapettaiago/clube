<?php
require_once 'config.php';

$email = 'admin@admin.com';
$senha = '123456';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

echo "<pre>";
echo "Usuário encontrado? " . ($usuario ? "Sim" : "Não") . "\n";
if($usuario) {
    echo "ID: " . $usuario['id'] . "\n";
    echo "Nome: " . $usuario['nome'] . "\n";
    echo "Email: " . $usuario['email'] . "\n";
    echo "Hash da senha: " . $usuario['senha'] . "\n";
    echo "Senha correta? " . (password_verify($senha, $usuario['senha']) ? "SIM" : "NÃO") . "\n";
    
    if(password_verify($senha, $usuario['senha'])) {
        echo "\n✅ Login funcionaria normalmente!\n";
    } else {
        echo "\n❌ Senha incorreta! Vamos corrigir...\n";
        $novaSenha = password_hash($senha, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $update->execute([$novaSenha, $usuario['id']]);
        echo "Senha atualizada! Tente novamente.\n";
    }
}
echo "</pre>";
?>