<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

verificarPermissao('cadastros') or die('Acesso negado');

echo "<h1>Recriando identificação das carteirinhas...</h1>";

$socios = $pdo->query("SELECT id, numero_titulo, nome, socio_principal_id FROM socios WHERE ativo = 1 ORDER BY nome")->fetchAll();

if (empty($socios)) {
    echo "<p style='color:red'>Nenhum sócio encontrado! <a href='../../modules/socios/cadastrar.php'>Cadastre um sócio</a></p>";
    exit;
}

$baseUrl = $base_url . "modules/carteirinha/validar.php?id=";

foreach ($socios as $socio) {
    $id = (int)$socio['id'];
    $tipoPessoa = !empty($socio['socio_principal_id']) ? 'dependente' : 'sócio';

    echo "Processando: " . htmlspecialchars($socio['nome']) . " ({$tipoPessoa}, ID: {$id}, Título: " . htmlspecialchars($socio['numero_titulo']) . ")... ";

    $qrRel = 'assets/uploads/carteirinha_qr_' . $id . '.png';
    $barRel = 'assets/uploads/carteirinha_bar_' . $id . '.png';
    $qrAbs = __DIR__ . '/../../' . $qrRel;
    $barAbs = __DIR__ . '/../../' . $barRel;

    $qrConteudo = $baseUrl . $id;
    gerarQRCode($qrConteudo, $qrAbs);
    gerarCodigoBarras($id, $barAbs);

    $stmt = $pdo->prepare("UPDATE socios SET qrcode = ?, codigo_barras = ? WHERE id = ?");
    $stmt->execute([$qrRel, $barRel, $id]);

    if (file_exists($qrAbs) && file_exists($barAbs)) {
        echo "OK<br>";
    } else {
        echo "Falha ao gerar alguns arquivos<br>";
    }
}

echo "<h2>Concluído!</h2>";
echo "<p>Todos os registros ativos foram atualizados para identificadores únicos por ID.</p>";
echo "<p>As carteirinhas antigas podem ser recriadas no novo padrão por pessoa.</p>";
echo "<p><a href='../../modules/socios/index.php'>Voltar para lista de sócios</a></p>";
echo "<p><a href='../../dashboard.php'>Ir para o dashboard</a></p>";
?>
