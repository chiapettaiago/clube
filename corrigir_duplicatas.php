<?php
require_once 'config.php';

echo "<h1>Corrigir Duplicatas de Título</h1>";

// Buscar títulos duplicados
$stmt = $pdo->query("
    SELECT numero_titulo, COUNT(*) as total 
    FROM socios 
    GROUP BY numero_titulo 
    HAVING total > 1
");

$duplicados = $stmt->fetchAll();

if(empty($duplicados)) {
    echo "<p style='color:green'>✅ Nenhum título duplicado encontrado!</p>";
} else {
    echo "<p style='color:red'>⚠️ Títulos duplicados encontrados:</p>";
    echo "<ul>";
    foreach($duplicados as $dup) {
        echo "<li>Título: {$dup['numero_titulo']} - {$dup['total']} ocorrências</li>";
        
        // Buscar os registros duplicados
        $stmt2 = $pdo->prepare("SELECT id, nome FROM socios WHERE numero_titulo = ?");
        $stmt2->execute([$dup['numero_titulo']]);
        $registros = $stmt2->fetchAll();
        
        echo "<ul>";
        foreach($registros as $reg) {
            echo "<li>ID: {$reg['id']} - Nome: {$reg['nome']}</li>";
        }
        echo "</ul>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3>Solução:</h3>";
    echo "<p>Execute os comandos abaixo no phpMyAdmin para corrigir:</p>";
    echo "<pre style='background:#f4f4f4; padding:10px;'>";
    foreach($duplicados as $dup) {
        $stmt2 = $pdo->prepare("SELECT id FROM socios WHERE numero_titulo = ? ORDER BY id");
        $stmt2->execute([$dup['numero_titulo']]);
        $ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        array_shift($ids); // Remove o primeiro (mantém o original)
        foreach($ids as $id) {
            echo "UPDATE socios SET numero_titulo = CONCAT(numero_titulo, '_OLD_', id) WHERE id = $id;\n";
        }
    }
    echo "</pre>";
}
?>