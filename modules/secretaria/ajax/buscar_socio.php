<?php
require_once '../../../config.php';

$termo = $_GET['termo'] ?? '';
$termo = trim($termo);

$response = [];

if(strlen($termo) >= 2) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nome, s.numero_titulo, s.cpf, s.telefone, s.email_socio,
               ts.nome as tipo_socio,
               (SELECT COUNT(*) FROM socios WHERE socio_principal_id = s.id) as num_dependentes
        FROM socios s
        JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
        WHERE s.ativo = 1 
        AND (s.nome LIKE ? 
             OR s.numero_titulo LIKE ? 
             OR s.cpf LIKE ?)
        ORDER BY s.nome
        LIMIT 20
    ");
    $stmt->execute(["%$termo%", "%$termo%", "%$termo%"]);
    $socios = $stmt->fetchAll();
    
    foreach($socios as $socio) {
        $response[] = [
            'id' => $socio['id'],
            'text' => $socio['nome'] . ' (Título: ' . $socio['numero_titulo'] . ')',
            'nome' => $socio['nome'],
            'numero_titulo' => $socio['numero_titulo'],
            'cpf' => $socio['cpf'],
            'telefone' => $socio['telefone'],
            'tipo_socio' => $socio['tipo_socio'],
            'num_dependentes' => $socio['num_dependentes']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>