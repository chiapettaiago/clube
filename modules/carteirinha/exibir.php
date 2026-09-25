<?php
require_once '../../config.php';

$stmt = $pdo->prepare("
    SELECT s.*, ts.nome AS tipo_socio, ts.cor_carteirinha
    FROM socios s
    LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.id = ? OR s.numero_titulo = ?
    LIMIT 1
");
$id = $_GET['id'] ?? '';
$stmt->execute([$id, $id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    http_response_code(404);
    echo 'Sócio não encontrado.';
    exit;
}

$cor = trim((string)($dados['cor_carteirinha'] ?? '')) ?: '#0d6efd';
$watermark = '../../assets/watermarks/escudo.png';
?>
<div style="background-color: <?= htmlspecialchars($cor) ?>; padding: 20px; border-radius: 15px; width: 350px; position: relative;">
    <?php if (file_exists($watermark)) echo '<img src="'.$watermark.'" style="opacity: 0.2; position: absolute; width: 100%; top: 0;">'; ?>
    <img src="../../assets/uploads/<?= htmlspecialchars($dados['foto'] ?? '') ?>" width="80">
    <h3><?= htmlspecialchars($dados['nome']) ?></h3>
    <p>Nº Título: <?= htmlspecialchars($dados['numero_titulo'] ?? '') ?></p>
    <p>Tipo: <?= htmlspecialchars($dados['tipo_socio'] ?? '') ?></p>
    <?php if (($_GET['tipo'] ?? '') == 'qrcode') echo '<img src="'.htmlspecialchars($dados['qrcode'] ?? '').'">'; ?>
    <?php if (($_GET['tipo'] ?? '') == 'barras') echo '<img src="'.htmlspecialchars($dados['codigo_barras'] ?? '').'">'; ?>
</div>
