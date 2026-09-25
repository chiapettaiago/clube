<?php
require_once 'config.php';

echo "<h1>Diagnóstico do Sistema de Carteirinhas</h1>";

// 1. Verificar se existem sócios
$socios = $pdo->query("SELECT id, nome, numero_titulo, qrcode, codigo_barras, ativo FROM socios")->fetchAll();

echo "<h2>1. Sócios cadastrados:</h2>";
if(empty($socios)) {
    echo "<p style='color:red'>❌ NENHUM SÓCIO CADASTRADO! <a href='modules/socios/cadastrar.php'>Cadastre um sócio</a></p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Nº Título</th><th>Ativo</th><th>QR Code existe?</th><th>Ações</th></tr>";
    foreach($socios as $socio) {
        $qrExiste = file_exists($socio['qrcode']) ? 'Sim' : 'Não';
        echo "<tr>";
        echo "<td>{$socio['id']}</td>";
        echo "<td>{$socio['nome']}</td>";
        echo "<td><strong>'{$socio['numero_titulo']}'</strong></td>";
        echo "<td>" . ($socio['ativo'] ? '✅ Ativo' : '❌ Inativo') . "</td>";
        echo "<td>$qrExiste</td>";
        echo "<td>
                <a href='modules/carteirinha/validar.php?codigo={$socio['numero_titulo']}' target='_blank'>Testar com Nº Título</a> | 
                <a href='modules/carteirinha/validar.php?codigo={$socio['id']}' target='_blank'>Testar com ID</a>
              </td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. Testar busca direta
echo "<h2>2. Teste de busca direta no banco:</h2>";
if(!empty($socios)) {
    $testeCodigo = $socios[0]['numero_titulo'];
    echo "<p>Testando busca pelo código: <strong>'$testeCodigo'</strong></p>";
    
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE numero_titulo = ? AND ativo = 1");
    $stmt->execute([$testeCodigo]);
    $resultado = $stmt->fetch();
    
    if($resultado) {
        echo "<p style='color:green'>✅ BUSCA FUNCIONA! Sócio encontrado: {$resultado['nome']}</p>";
    } else {
        echo "<p style='color:red'>❌ BUSCA FALHOU! Verifique se o número do título está correto.</p>";
        echo "<p>Valor no banco: '" . $socios[0]['numero_titulo'] . "'</p>";
        echo "<p>Tipo do campo: " . gettype($socios[0]['numero_titulo']) . "</p>";
    }
}

// 3. Verificar URL base
echo "<h2>3. Configuração da URL:</h2>";
$base_url = 'http://localhost/clube/';
echo "<p>URL Base: $base_url</p>";

// 4. Verificar permissões de pasta
echo "<h2>4. Permissões de diretório:</h2>";
$uploadsDir = __DIR__ . '/assets/uploads/';
echo "<p>Diretório uploads: " . (is_writable($uploadsDir) ? '✅ Gravável' : '❌ Não gravável') . "</p>";

// 5. Mostrar um exemplo de QR Code gerado
if(!empty($socios) && $socios[0]['qrcode']) {
    echo "<h2>5. QR Code gerado:</h2>";
    echo "<p>Caminho: {$socios[0]['qrcode']}</p>";
    if(file_exists($socios[0]['qrcode'])) {
        echo "<img src='{$socios[0]['qrcode']}' width='200'><br>";
        // Ler o conteúdo do QR Code (se for imagem PNG)
        echo "<p><strong>Conteúdo que deveria estar no QR Code:</strong><br>";
        echo "<code>$base_url modules/carteirinha/validar.php?codigo={$socios[0]['numero_titulo']}</code></p>";
    } else {
        echo "<p style='color:red'>❌ Arquivo do QR Code não encontrado!</p>";
    }
}

// 6. Sugestões
echo "<h2>6. Sugestões de correção:</h2>";
echo "<ul>";
echo "<li>Se não há sócios: <a href='modules/socios/cadastrar.php'>CADASTRE UM SÓCIO</a></li>";
echo "<li>Se o QR Code não existe: Regenere a carteirinha</li>";
echo "<li>Se a busca falha: Verifique se o campo 'numero_titulo' está preenchido</li>";
echo "<li>Se o sócio está inativo: Atualize o campo 'ativo' para 1</li>";
echo "</ul>";
?>