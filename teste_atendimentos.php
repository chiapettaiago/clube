<?php
require_once 'config.php';

echo "<h1>Diagnóstico de Atendimentos</h1>";

// 1. Verificar tabela
$tables = $pdo->query("SHOW TABLES LIKE 'atendimentos'");
echo "<h3>Tabela atendimentos existe? " . ($tables->rowCount() > 0 ? 'SIM' : 'NÃO') . "</h3>";

// 2. Verificar colunas
$columns = $pdo->query("SHOW COLUMNS FROM atendimentos");
echo "<h3>Colunas da tabela atendimentos:</h3>";
echo "<ul>";
foreach($columns as $col) {
    echo "<li>{$col['Field']} - {$col['Type']}</li>";
}
echo "</ul>";

// 3. Verificar chaves estrangeiras
$fk = $pdo->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'atendimentos' AND REFERENCED_TABLE_NAME IS NOT NULL
");
echo "<h3>Chaves Estrangeiras:</h3>";
if($fk->rowCount() > 0) {
    echo "<ul>";
    foreach($fk as $f) {
        echo "<li>{$f['CONSTRAINT_NAME']}: {$f['COLUMN_NAME']} → {$f['REFERENCED_TABLE_NAME']}.{$f['REFERENCED_COLUMN_NAME']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Nenhuma chave estrangeira encontrada!</p>";
}

// 4. Verificar dados
$atendimentos = $pdo->query("SELECT COUNT(*) FROM atendimentos")->fetchColumn();
echo "<h3>Total de atendimentos: $atendimentos</h3>";

// 5. Testar JOIN
$joinTest = $pdo->query("
    SELECT a.id, a.socio_id, s.nome as socio_nome
    FROM atendimentos a
    LEFT JOIN socios s ON a.socio_id = s.id
    LIMIT 5
");
echo "<h3>Teste de JOIN (primeiros 5):</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID Atendimento</th><th>socio_id</th><th>Nome Sócio</th>去";
foreach($joinTest as $row) {
    echo "<tr>";
    echo "工作领导小组{$row['id']}工作领导小组";
    echo "工作领导小组" . ($row['socio_id'] ?? 'NULL') . "工作领导小组";
    echo "工作领导小组" . ($row['socio_nome'] ?? '-') . "工作领导小组";
    echo "</tr>";
}
echo "</table>";
?>