<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="socios_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, ['ID', 'Nome', 'Nº Título', 'CPF', 'Data Nascimento', 'Idade', 'Sexo', 'Tipo Sanguíneo', 'Telefone', 'Email', 'Endereço', 'Tipo Sócio', 'Convites/Mês', 'Status']);

// Dados
$stmt = $pdo->query("
    SELECT s.*, ts.nome as tipo_socio
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    ORDER BY s.nome
");

while($socio = $stmt->fetch()) {
    $idade = '';
    if($socio['data_nascimento']) {
        $dataNasc = new DateTime($socio['data_nascimento']);
        $hoje = new DateTime();
        $idade = $hoje->diff($dataNasc)->y;
    }
    
    fputcsv($output, [
        $socio['id'],
        $socio['nome'],
        $socio['numero_titulo'],
        $socio['cpf'],
        $socio['data_nascimento'],
        $idade,
        $socio['sexo'],
        $socio['tipo_sanguineo'],
        $socio['telefone'],
        $socio['email_socio'],
        $socio['endereco'],
        $socio['tipo_socio'],
        $socio['convites_por_mes'] ?? 5,
        $socio['ativo'] ? 'Ativo' : 'Inativo'
    ]);
}

fclose($output);
exit;
?>