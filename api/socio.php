<?php
require_once 'config.php';

$tokenData = validarToken();
$socio_id = $tokenData['socio_id'];

// Buscar dados do sócio
$stmt = $pdo->prepare("
    SELECT s.id, s.nome, s.numero_titulo, s.cpf, s.data_nascimento, 
           s.telefone, s.email_socio, s.endereco, s.foto, ts.nome as tipo_socio,
           (SELECT COUNT(*) FROM convites_familia cf 
            JOIN socios s2 ON cf.familia_id = s2.familia_id 
            WHERE s2.id = s.id AND cf.mes_referencia = DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
           ) as convites_totais,
           (SELECT convites_utilizados FROM convites_familia cf 
            JOIN socios s2 ON cf.familia_id = s2.familia_id 
            WHERE s2.id = s.id AND cf.mes_referencia = DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
           ) as convites_usados
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.id = ?
");
$stmt->execute([$socio_id]);
$socio = $stmt->fetch();

if(!$socio) {
    erroJSON('Sócio não encontrado', 404);
}

// Buscar dependentes
$stmt = $pdo->prepare("
    SELECT id, nome, parentesco, foto
    FROM socios 
    WHERE socio_principal_id = ? AND ativo = 1
");
$stmt->execute([$socio_id]);
$dependentes = $stmt->fetchAll();

$socio['dependentes'] = $dependentes;
$socio['foto'] = $socio['foto'] ? 'http://localhost/clube/assets/uploads/' . $socio['foto'] : null;
$socio['convites_disponiveis'] = $socio['convites_totais'] - $socio['convites_usados'];

logAPI('socio.php', 'GET', $socio_id, 200);

respostaJSON($socio);
?>