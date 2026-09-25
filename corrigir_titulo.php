<?php
require_once 'config.php';

echo "<h1>Corrigir Título Duplicado '061'</h1>";

// Verificar se existe o título
$stmt = $pdo->prepare("SELECT * FROM socios WHERE numero_titulo = '061'");
$stmt->execute();
$socios = $stmt->fetchAll();

if(count($socios) == 0) {
    echo "<p style='color:green'>✅ Nenhum sócio com título '061' encontrado. Pode cadastrar normalmente!</p>";
} elseif(count($socios) == 1) {
    echo "<p style='color:green'>✅ Apenas um sócio com título '061'. Tudo ok!</p>";
    echo "<pre>";
    print_r($socios[0]);
    echo "</pre>";
} else {
    echo "<p style='color:red'>⚠️ Encontrados " . count($socios) . " sócios com título '061'</p>";
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nome</th><th>CPF</th><th>Tipo</th></tr>";
    foreach($socios as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>{$s['nome']}</td>";
        echo "<td>{$s['cpf']}</td>";
        echo "<td>{$s['tipo_socio_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Resolvendo...</h3>";
    
    // Manter o primeiro, renomear os outros
    $primeiroId = $socios[0]['id'];
    array_shift($socios); // Remove o primeiro
    
    foreach($socios as $s) {
        $novoTitulo = $s['numero_titulo'] . '_antigo_' . $s['id'];
        $update = $pdo->prepare("UPDATE socios SET numero_titulo = ? WHERE id = ?");
        $update->execute([$novoTitulo, $s['id']]);
        echo "<p>✅ Sócio ID {$s['id']} renomeado para: $novoTitulo</p>";
    }
    
    echo "<p style='color:green'>✅ Correção concluída! Agora pode cadastrar o título '061' normalmente.</p>";
}

echo "<hr>";
echo "<a href='modules/socios/cadastrar.php' class='btn btn-primary'>Voltar para Cadastro</a>";
echo " <a href='modules/socios/index.php' class='btn btn-secondary'>Ver Sócios</a>";
?>