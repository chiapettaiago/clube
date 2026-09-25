<?php
require_once 'config.php';

$socios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

echo "<h1>Lista de Sócios</h1>";
echo "Total: " . count($socios) . "<br>";

if(count($socios) > 0) {
    echo "<select style='width:300px; padding:10px;'>";
    echo "<option value=''>-- Selecione --</option>";
    foreach($socios as $s) {
        echo "<option value='{$s['id']}'>{$s['nome']} ({$s['numero_titulo']})</option>";
    }
    echo "</select>";
} else {
    echo "<p style='color:red'>Nenhum sócio encontrado! Cadastre um sócio primeiro.</p>";
    echo "<a href='modules/socios/cadastrar.php'>Cadastrar Sócio</a>";
}
?>