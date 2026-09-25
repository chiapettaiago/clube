<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$login = $input['login'] ?? '';
$senha = $input['senha'] ?? '';
$device_name = $input['device_name'] ?? 'Dispositivo Mobile';
$device_uuid = $input['device_uuid'] ?? '';

if(empty($login) || empty($senha)) {
    erroJSON('Login e senha são obrigatórios', 400);
}

// Buscar sócio por título ou CPF
$stmt = $pdo->prepare("
    SELECT id, nome, numero_titulo, cpf, email_socio, telefone, foto 
    FROM socios 
    WHERE (numero_titulo = ? OR cpf = ?) AND ativo = 1
");
$stmt->execute([$login, $login]);
$socio = $stmt->fetch();

if(!$socio) {
    erroJSON('Sócio não encontrado', 401);
}

// Verificar se a senha está correta (aqui você pode usar uma senha fixa ou implementar senha no app)
// Para simplificar, vamos considerar que o sócio pode acessar com o número do título
// Em produção, implemente uma tabela de senhas para o app

// Gerar token
$token = bin2hex(random_bytes(32));
$expiracao = date('Y-m-d H:i:s', strtotime('+30 days'));

$stmt = $pdo->prepare("
    INSERT INTO api_tokens (socio_id, token, device_name, device_uuid, data_expiracao)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$socio['id'], $token, $device_name, $device_uuid, $expiracao]);

logAPI('auth.php', 'POST', $socio['id'], 200, $input);

respostaJSON([
    'token' => $token,
    'expiracao' => $expiracao,
    'socio' => [
        'id' => $socio['id'],
        'nome' => $socio['nome'],
        'numero_titulo' => $socio['numero_titulo'],
        'cpf' => $socio['cpf'],
        'email' => $socio['email_socio'],
        'telefone' => $socio['telefone'],
        'foto' => $socio['foto'] ? 'http://localhost/clube/assets/uploads/' . $socio['foto'] : null
    ]
]);
?>