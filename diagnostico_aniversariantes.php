<?php
require_once 'config.php';

echo "<h1>Diagnóstico de Aniversariantes</h1>";
echo "<h3>Data atual: " . date('d/m/Y') . "</h3>";
echo "<h3>Mês atual (número): " . date('m') . "</h3>";
echo "<h3>Mês atual (nome): " . date('F') . "</h3>";
echo "<hr>";

// 1. Verificar se a coluna existe
$checkColumn = $pdo->query("SHOW COLUMNS FROM socios LIKE 'data_nascimento'");
if($checkColumn->rowCount() == 0) {
    echo "<p style='color:red'>❌ A coluna 'data_nascimento' não existe na tabela socios!</p>";
    echo "<p>Execute: ALTER TABLE socios ADD COLUMN data_nascimento DATE NULL;</p>";
    exit;
} else {
    echo "<p style='color:green'>✅ Coluna 'data_nascimento' existe</p>";
}

// 2. Buscar todos os sócios com data de nascimento
$socios = $pdo->query("
    SELECT id, nome, data_nascimento, ativo
    FROM socios 
    WHERE data_nascimento IS NOT NULL 
    AND data_nascimento != '0000-00-00'
");

echo "<h3>Lista de sócios com data de nascimento:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Nome</th><th>Data Nascimento</th><th>Mês</th><th>Ativo</th><th>Aniversariante?</th></tr>";

$temAniversariante = false;
$mesAtual = date('m');

foreach($socios as $s) {
    $data = $s['data_nascimento'];
    $mes = date('m', strtotime($data));
    $ehAniversariante = ($mes == $mesAtual);
    
    echo "<tr>";
    echo "<td>{$s['id']}</td>";
    echo "<td>{$s['nome']}</td>";
    echo "<td>{$data}</td>";
    echo "<td>{$mes} (hoje é mês {$mesAtual})</td>";
    echo "<td>" . ($s['ativo'] ? 'Sim' : 'Não') . "</td>";
    echo "<td>" . ($ehAniversariante ? '✅ SIM' : '❌ Não') . "</td>";
    echo "</tr>";
    
    if($ehAniversariante) $temAniversariante = true;
}

echo "</table>";

if(!$temAniversariante) {
    echo "<p style='color:red; margin-top:20px'>❌ Nenhum sócio faz aniversário no mês atual!</p>";
    echo "<p><strong>Solução:</strong> Execute o SQL abaixo para criar um aniversariante de teste:</p>";
    echo "<pre style='background:#f4f4f4; padding:10px; border-radius:5px;'>
-- Para o sócio com ID 1 (ou outro ID existente)
UPDATE socios 
SET data_nascimento = CONCAT(YEAR(data_nascimento), '-', MONTH(CURRENT_DATE()), '-15') 
WHERE id = 1;
    </pre>";
} else {
    echo "<p style='color:green; margin-top:20px'>✅ Encontramos aniversariantes! O problema está na consulta do dashboard.</p>";
}

// 3. Testar a consulta usada no dashboard
echo "<h3>Teste da consulta do Dashboard:</h3>";
$mesAtualNum = date('m');
$testQuery = $pdo->prepare("
    SELECT id, nome, data_nascimento, 
           DAY(data_nascimento) as dia,
           DATE_FORMAT(data_nascimento, '%d/%m') as data_aniversario
    FROM socios
    WHERE ativo = 1 
    AND data_nascimento IS NOT NULL
    AND data_nascimento != '0000-00-00'
    AND MONTH(data_nascimento) = ?
    ORDER BY DAY(data_nascimento)
");
$testQuery->execute([$mesAtualNum]);
$resultados = $testQuery->fetchAll();

echo "<p>Resultados encontrados: " . count($resultados) . "</p>";
if(count($resultados) > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Dia</th><th>Data Aniversário</th></tr>";
    foreach($resultados as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['nome']}</td><td>{$r['dia']}</td><td>{$r['data_aniversario']}</td></tr>";
    }
    echo "</table>";
}
?>