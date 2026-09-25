<?php
require_once 'config.php';

echo "<h1>Diagnóstico - Aniversariantes</h1>";

// 1. Verificar tabela
$tables = $pdo->query("SHOW TABLES LIKE 'socios'");
echo "<h3>Tabela socios existe? " . ($tables->rowCount() > 0 ? 'SIM' : 'NÃO') . "</h3>";

// 2. Verificar coluna data_nascimento
$columns = $pdo->query("SHOW COLUMNS FROM socios");
echo "<h3>Colunas da tabela socios:</h3>";
echo "<ul>";
foreach($columns as $col) {
    echo "<li>" . $col['Field'] . " - " . $col['Type'] . "</li>";
}
echo "</ul>";

// 3. Buscar sócios com data de nascimento
$socios = $pdo->query("
    SELECT id, nome, data_nascimento, ativo 
    FROM socios 
    WHERE data_nascimento IS NOT NULL 
    AND data_nascimento != '0000-00-00'
");
echo "<h3>Sócios com data de nascimento: " . $socios->rowCount() . "</h3>";

// 4. Buscar aniversariantes do mês atual
$mesAtual = date('m');
$aniversariantes = $pdo->prepare("
    SELECT id, nome, data_nascimento 
    FROM socios 
    WHERE ativo = 1 
    AND data_nascimento IS NOT NULL
    AND MONTH(data_nascimento) = ?
");
$aniversariantes->execute([$mesAtual]);
echo "<h3>Aniversariantes do mês " . $mesAtual . ": " . $aniversariantes->rowCount() . "</h3>";

if($aniversariantes->rowCount() > 0) {
    echo "<ul>";
    foreach($aniversariantes as $a) {
        echo "<li>{$a['nome']} - {$a['data_nascimento']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Nenhum aniversariante encontrado!</p>";
    echo "<p>Para testar, execute: UPDATE socios SET data_nascimento = '1990-" . $mesAtual . "-15' WHERE id = 1;</p>";
}
?>