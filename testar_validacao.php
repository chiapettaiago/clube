<?php
require_once 'config.php';

// Listar todos os sócios ativos
$socios = $pdo->query("SELECT id, nome, numero_titulo FROM socios WHERE ativo = 1")->fetchAll();

echo "<h2>Teste de Validação de Carteirinha</h2>";

if(empty($socios)) {
    echo "<div style='color: red;'>Nenhum sócio cadastrado! <a href='modules/socios/cadastrar.php'>Cadastre um sócio</a></div>";
} else {
    echo "<h3>Sócios disponíveis para teste:</h3>";
    echo "<ul>";
    foreach($socios as $socio) {
        echo "<li>";
        echo "<strong>{$socio['nome']}</strong> - Nº Título: {$socio['numero_titulo']}";
        echo " <a href='modules/carteirinha/validar.php?codigo={$socio['numero_titulo']}' target='_blank'>Testar Validação</a>";
        echo " <a href='modules/carteirinha/gerar.php?id={$socio['id']}' target='_blank'>Ver Carteirinha</a>";
        echo "</li>";
    }
    echo "</ul>";
}

// Teste manual
if(isset($_GET['testar'])) {
    $codigo = $_GET['testar'];
    echo "<hr>";
    echo "<h3>Testando código: $codigo</h3>";
    
    $ch = curl_init("http://localhost/clube/modules/carteirinha/validar.php?codigo=" . urlencode($codigo));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resposta = curl_exec($ch);
    curl_close($ch);
    
    echo "<pre>";
    print_r(json_decode($resposta, true));
    echo "</pre>";
}
?>