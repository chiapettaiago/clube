<?php
require_once 'config.php';

echo "<h1>Teste Simples de Aniversariantes</h1>";

$mes = date('m');
echo "<p>Mês atual: $mes</p>";

$sql = "
    SELECT s.id, s.nome, s.data_nascimento
    FROM socios s
    WHERE s.ativo = 1 
    AND s.data_nascimento IS NOT NULL
    AND s.data_nascimento != '0000-00-00'
    AND MONTH(s.data_nascimento) = $mes
";

echo "<p>SQL: $sql</p>";

$resultado = $pdo->query($sql);

echo "<p>Tipo do resultado: " . gettype($resultado) . "</p>";

if($resultado !== false) {
    echo "<h3>Resultados:</h3>";
    echo "<ul>";
    while($row = $resultado->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>{$row['nome']} - {$row['data_nascimento']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Erro na consulta!</p>";
}

// Testar se o arquivo aniversariantes.php está sendo incluído corretamente
echo "<hr>";
echo "<h3>Teste de inclusão:</h3>";
echo "<a href='modules/secretaria/aniversariantes.php' class='btn btn-primary'>Ir para Aniversariantes</a>";
?>