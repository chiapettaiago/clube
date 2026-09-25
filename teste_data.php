<?php
require_once 'config.php';

echo "<h1>Diagnóstico de Aniversariantes</h1>";

// Verificar todos os sócios com data de nascimento
$socios = $pdo->query("
    SELECT id, nome, data_nascimento, 
           MONTH(data_nascimento) as mes,
           DAY(data_nascimento) as dia,
           ativo
    FROM socios 
    WHERE data_nascimento IS NOT NULL 
    AND data_nascimento != '0000-00-00'
");

echo "<h3>Lista de sócios com data de nascimento:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Nome</th><th>Data Nascimento</th><th>Mês</th><th>Dia</th><th>Ativo</th></tr>";

$temAniversariante = false;
$mesAtual = date('m');

foreach($socios as $s) {
    $mes = date('m', strtotime($s['data_nascimento']));
    $ehAniversariante = ($mes == $mesAtual);
    
    echo "<tr>";
    echo "<td>{$s['id']}</td>";
    echo "<td>{$s['nome']}</td>";
    echo "<td>{$s['data_nascimento']}</td>";
    echo "<td>{$mes} (hoje é mês {$mesAtual})</td>";
    echo "<td>" . date('d', strtotime($s['data_nascimento'])) . "</td>";
    echo "<td>" . ($s['ativo'] ? 'Sim' : 'Não') . "</td>";
    echo "<td>" . ($ehAniversariante ? '<span style="color:green">✅ ANIVERSARIANTE!</span>' : '-') . "</td>";
    echo "</tr>";
    
    if($ehAniversariante) $temAniversariante = true;
}

echo "</table>";

if(!$temAniversariante) {
    echo "<p style='color:red'>Nenhum sócio faz aniversário no mês atual (mês " . $mesAtual . ")</p>";
    echo "<p>Para testar, execute: UPDATE socios SET data_nascimento = CONCAT(YEAR(data_nascimento), '-', MONTH(CURRENT_DATE()), '-15') WHERE id = 1;</p>";
}

// Contar total
$total = $pdo->query("SELECT COUNT(*) FROM socios WHERE data_nascimento IS NOT NULL AND data_nascimento != '0000-00-00'")->fetchColumn();
echo "<p><strong>Total de sócios com data de nascimento: $total</strong></p>";
?>