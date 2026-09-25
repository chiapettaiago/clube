<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

function normalizePhoneForWhatsApp($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (strpos($digits, '55') === 0) return $digits;
    return '55' . ltrim($digits, '0');
}

function ensureMemberAssets(array $dados) {
    $id = (int)$dados['id'];
    $baseDir = __DIR__ . '/../../assets/uploads/';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }

    $qrRel = 'assets/uploads/carteirinha_qr_' . $id . '.png';
    $barRel = 'assets/uploads/carteirinha_bar_' . $id . '.png';
    $qrAbs = __DIR__ . '/../../' . $qrRel;
    $barAbs = __DIR__ . '/../../' . $barRel;

    $link = $GLOBALS['base_url'] . 'modules/carteirinha/validar.php?id=' . $id;
    gerarQRCode($link, $qrAbs);

    $img = imagecreate(420, 110);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 18, 18, 18);
    imagefilledrectangle($img, 0, 0, 419, 109, $bg);
    imagestring($img, 5, 150, 76, 'ID ' . $id, $black);
    imagepng($img, $barAbs);
    imagedestroy($img);

    return [$qrRel, $barRel, $link];
}

function hexToRgb(string $hex): ?array {
    $hex = trim($hex);
    if ($hex === '') return null;
    if ($hex[0] === '#') {
        $hex = substr($hex, 1);
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return null;
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function shadeHexColor(string $hex, float $factor): string {
    $rgb = hexToRgb($hex);
    if ($rgb === null) {
        return $hex;
    }
    $rgb = array_map(static function ($channel) use ($factor) {
        return max(0, min(255, (int)round($channel * $factor)));
    }, $rgb);
    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
}

function socioBadgeLabel(string $tipo): string {
    return stripos($tipo, 'Depend') !== false ? 'Dependente' : 'Proprietário';
}

function buildPrintableCard(array $dados, string $qrUrl, string $fotoUrl, string $cardMain, string $cardDark, string $memberLabel): string {
    $escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $nome = $escape($dados['nome'] ?? '');
    $titulo = $escape($dados['numero_titulo'] ?? '-');
    $tipo = $escape($dados['tipo_socio'] ?? 'Sócio');
    $sangue = $escape($dados['tipo_sanguineo'] ?? '-');
    $label = $escape($memberLabel);
    $qr = $escape($qrUrl);
    $foto = $escape($fotoUrl);
    $corPrincipal = $escape($cardMain);
    $corEscura = $escape($cardDark);

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carteirinha - {$nome}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #eef2ff; font-family: Arial, sans-serif; color: #fff; }
        .sheet { width: 100%; max-width: 900px; padding: 28px; }
        .card { overflow: hidden; border-radius: 28px; box-shadow: 0 20px 55px rgba(15, 23, 42, .22); background: linear-gradient(135deg, {$corPrincipal}, {$corEscura}); }
        .top { padding: 30px; display: flex; justify-content: space-between; gap: 26px; align-items: flex-start; }
        .identity { display: flex; align-items: center; gap: 20px; }
        .avatar { width: 122px; height: 122px; border-radius: 24px; border: 4px solid rgba(255,255,255,.72); object-fit: cover; background: #fff; }
        .eyebrow { margin: 0 0 7px; font-size: 12px; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; opacity: .78; }
        h1 { margin: 0 0 12px; font-size: 30px; line-height: 1.1; }
        .badges span { display: inline-block; padding: 7px 11px; margin: 0 6px 6px 0; border-radius: 999px; background: #fff; color: #172554; font-size: 13px; font-weight: bold; }
        .qr-wrap { min-width: 190px; text-align: center; }
        .qr-wrap small { display: block; margin-bottom: 8px; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; opacity: .8; }
        .qr { width: 175px; height: 175px; padding: 10px; object-fit: contain; border-radius: 18px; background: #fff; }
        .details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 0 30px 30px; }
        .detail { padding: 13px; border: 1px solid rgba(255,255,255,.18); border-radius: 14px; background: rgba(255,255,255,.09); }
        .detail small { display: block; margin-bottom: 5px; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; opacity: .72; }
        .detail strong { font-size: 17px; }
        .actions { padding: 16px; text-align: center; background: #fff; }
        button { border: 0; border-radius: 999px; padding: 11px 18px; background: #172554; color: #fff; font-weight: bold; cursor: pointer; }
        @media (max-width: 640px) { .top { flex-direction: column; } .details { grid-template-columns: 1fr; } .qr-wrap { align-self: center; } h1 { font-size: 24px; } }
        @media print { body { background: #fff; display: block; } .sheet { max-width: none; padding: 0; } .card { box-shadow: none; border-radius: 0; min-height: 100vh; } .actions { display: none; } }
    </style>
</head>
<body>
    <main class="sheet">
        <section class="card">
            <div class="top">
                <div class="identity">
                    <img class="avatar" src="{$foto}" alt="Foto do associado">
                    <div>
                        <p class="eyebrow">Carteirinha de associado</p>
                        <h1>{$nome}</h1>
                        <div class="badges"><span>{$label}</span><span>Título {$titulo}</span></div>
                    </div>
                </div>
                <div class="qr-wrap"><small>Validação digital</small><img class="qr" src="{$qr}" alt="QR Code"></div>
            </div>
            <div class="details">
                <div class="detail"><small>Título</small><strong>{$titulo}</strong></div>
                <div class="detail"><small>Categoria</small><strong>{$tipo}</strong></div>
                <div class="detail"><small>Tipo sanguíneo</small><strong>{$sangue}</strong></div>
            </div>
        </section>
        <div class="actions"><button onclick="window.print()">Imprimir / Salvar como PDF</button></div>
    </main>
</body>
</html>
HTML;
}

$id = (int)($_GET['id'] ?? $_GET['codigo'] ?? 0);
$download = ($_GET['download'] ?? '') === 'pdf';

// A geração exige um sócio específico. Evita o 404 ao abrir o item do menu
// diretamente, encaminhando o usuário para a lista onde pode selecioná-lo.
if ($id <= 0) {
    header('Location: /modules/socios/index.php?mensagem=selecione_um_socio_para_gerar_a_carteirinha');
    exit;
}

$stmt = $pdo->prepare("\n    SELECT s.*, ts.nome as tipo_socio, ts.cor_carteirinha\n    FROM socios s\n    LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id\n    WHERE s.id = ? AND s.ativo = 1\n    LIMIT 1\n");
$stmt->execute([$id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    header('Location: /modules/socios/index.php?mensagem=socio_nao_encontrado_ou_inativo');
    exit;
}

[$qrRel, $barRel, $linkCarteirinha] = ensureMemberAssets($dados);
$qrFile = __DIR__ . '/../../' . $qrRel;
$fotoFile = !empty($dados['foto']) ? __DIR__ . '/../../assets/uploads/' . $dados['foto'] : null;
$qrUrl = $GLOBALS['base_url'] . $qrRel . '?v=' . (@filemtime($qrFile) ?: time());
$fotoUrl = ($fotoFile && file_exists($fotoFile))
    ? $GLOBALS['base_url'] . 'assets/uploads/' . $dados['foto'] . '?v=' . (@filemtime($fotoFile) ?: time())
    : $GLOBALS['base_url'] . 'assets/img/avatar.png';
$whats = normalizePhoneForWhatsApp($dados['telefone'] ?? '');
$bloodTheme = [$dados['cor_carteirinha'] ?? '', '', ''];
if (trim((string)$bloodTheme[0]) === '') {
    $bloodTheme = ['#0f766e', '#115e59', '#ccfbf1'];
}
$cardMain = hexToRgb((string)$bloodTheme[0]) ? $bloodTheme[0] : '#0d6efd';
$cardDark = shadeHexColor($cardMain, 0.78);
$memberLabel = socioBadgeLabel($dados['tipo_socio'] ?? '');

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    echo buildPrintableCard($dados, $qrUrl, $fotoUrl, $cardMain, $cardDark, $memberLabel);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Carteirinha do Sócio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%); min-height: 100vh; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 18px; }
        .cardx { background: rgba(255,255,255,.92); border-radius: 28px; box-shadow: 0 20px 60px rgba(15,23,42,.12); overflow: hidden; }
        .hero { background: linear-gradient(135deg, <?= htmlspecialchars($cardMain) ?> 0%, <?= htmlspecialchars($cardDark) ?> 100%); color: #fff; padding: 22px; }
        .avatar { width: 136px; height: 136px; object-fit: cover; border-radius: 26px; border: 4px solid rgba(255,255,255,.7); background:#fff; }
        .qr { width: 180px; height: 180px; background: #fff; border-radius: 18px; padding: 10px; object-fit: contain; }
        .detail-item { background: rgba(255,255,255,.08); border-radius: 14px; padding: 10px 12px; }
        .detail-item small { display:block; opacity:.72; text-transform:uppercase; letter-spacing:.08em; margin-bottom:4px; }
        .status-note { margin-top: 14px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); border-radius: 18px; padding: 12px 14px; }
    </style>
</head>
<body>
<div class="wrap py-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <div class="text-uppercase small text-muted">Carteirinha do Sócio</div>
            <h1 class="h3 mb-0">Identificação moderna e pronta para impressão</h1>
        </div>
        <a href="/dashboard.php" class="btn btn-outline-secondary rounded-pill">Voltar</a>
    </div>
    <div class="cardx">
        <div class="hero">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <img src="<?= htmlspecialchars($fotoUrl) ?>" class="avatar" alt="Foto">
                    <div>
                        <div class="small text-uppercase opacity-75">Carteirinha de Associado</div>
                        <h2 class="mb-2"><?= htmlspecialchars($dados['nome']) ?></h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge text-bg-light text-dark"><?= htmlspecialchars($memberLabel) ?></span>
                            <span class="badge text-bg-light text-dark">Título <?= htmlspecialchars($dados['numero_titulo']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="small text-uppercase opacity-75 mb-2">QR Code</div>
                    <img src="<?= htmlspecialchars($qrUrl) ?>" class="qr" alt="QR Code">
                </div>
            </div>
            <div class="row g-3 mt-3">
                <div class="col-md-4"><div class="detail-item"><small>Título</small><strong><?= htmlspecialchars($dados['numero_titulo']) ?></strong></div></div>
                <div class="col-md-4"><div class="detail-item"><small>Tipo</small><strong><?= htmlspecialchars($dados['tipo_socio'] ?: 'Sócio') ?></strong></div></div>
                <div class="col-md-4"><div class="detail-item"><small>Tipo sanguíneo</small><strong><?= htmlspecialchars($dados['tipo_sanguineo'] ?: '-') ?></strong></div></div>
            </div>
            <div class="status-note mt-3">
                <div class="fw-semibold"><?= htmlspecialchars($dados['tipo_socio'] ?: 'Sócio') ?> • <?= htmlspecialchars($dados['tipo_sanguineo'] ?: 'Sem tipo sanguíneo informado') ?></div>
                <div class="small opacity-75 mt-1">QR Code gerado por pessoa, compatível com leitura na portaria e impressão.</div>
            </div>
        </div>
        <div class="p-3 p-md-4 d-flex gap-2 flex-wrap">
            <a class="btn btn-primary rounded-pill" href="?id=<?= $id ?>&download=pdf" target="_blank">Imprimir / Salvar PDF</a>
            <button class="btn btn-outline-primary rounded-pill" onclick="shareMember()">Compartilhar</button>
            <?php if ($whats): ?>
                <a class="btn btn-success rounded-pill" href="https://wa.me/<?= htmlspecialchars($whats) ?>?text=<?= urlencode('Sua carteirinha: ' . $linkCarteirinha) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
async function shareMember() {
    const url = <?= json_encode($linkCarteirinha) ?>;
    const text = 'Carteirinha: ' + url;
    if (navigator.share) {
        try { await navigator.share({ title: 'Carteirinha', text, url }); return; } catch (e) {}
    }
    try { await navigator.clipboard.writeText(url); alert('Link copiado.'); } catch (e) { prompt('Copie o link:', url); }
}
</script>
</body>
</html>
