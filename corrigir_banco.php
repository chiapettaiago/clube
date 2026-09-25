<?php
require_once 'config.php';

echo "<h1>🔧 Corrigindo Banco de Dados</h1>";

try {
    // 1. Remover a constraint UNIQUE do campo numero_titulo
    echo "<p>📌 Verificando constraint UNIQUE...</p>";
    
    $check = $pdo->query("SHOW INDEX FROM socios WHERE Key_name = 'numero_titulo' AND Non_unique = 0");
    if($check->fetch()) {
        $pdo->exec("ALTER TABLE socios DROP INDEX numero_titulo");
        echo "<p style='color:green'>✅ Constraint UNIQUE removida com sucesso!</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ Constraint UNIQUE já não existe.</p>";
    }
    
    // 2. Adicionar índice normal (opcional, para performance)
    $pdo->exec("ALTER TABLE socios ADD INDEX idx_numero_titulo (numero_titulo)");
    echo "<p style='color:green'>✅ Índice adicionado!</p>";
    
    // 3. Verificar estrutura da tabela
    echo "<h2>📊 Estrutura atual da tabela socios:</h2>";
    $columns = $pdo->query("DESCRIBE socios");
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Extra</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Mostrar sócios cadastrados
    echo "<h2>📋 Sócios cadastrados:</h2>";
    $socios = $pdo->query("
        SELECT s.id, s.nome, s.numero_titulo, ts.nome as tipo 
        FROM socios s 
        JOIN tipos_socio ts ON s.tipo_socio_id = ts.id 
        ORDER BY s.id
    ");
    
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Título</th><th>Tipo</th></tr>";
    foreach($socios as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>{$s['nome']}</td>";
        echo "<td><strong>{$s['numero_titulo']}</strong></td>";
        echo "<td>{$s['tipo']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 10px;'>";
    echo "<h3 style='color: green;'>✅ Banco corrigido com sucesso!</h3>";
    echo "<p>Agora você pode:</p>";
    echo "<ul>";
    echo "<li>Cadastrar PROPRIETÁRIOS normalmente</li>";
    echo "<li>Cadastrar DEPENDENTES com o mesmo título do proprietário</li>";
    echo "<li>Compartilhar convites entre familiares</li>";
    echo "</ul>";
    echo "<a href='modules/socios/cadastrar.php' class='btn btn-primary'>➡️ Ir para Cadastro de Sócios</a>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>