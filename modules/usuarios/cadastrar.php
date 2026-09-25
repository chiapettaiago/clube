<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="mb-3">
    <label>Tipo de identificação na carteirinha:</label>
    <select name="tipo_id" class="form-select">
        <option value="qrcode">QR Code</option>
        <option value="barras">Código de Barras</option>
    </select>
</div>
    
</body>
</html>
<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $numero_titulo = $_POST['numero_titulo'];
    $cpf = $_POST['cpf'];
    $sexo = $_POST['sexo'];
    $tipo_sanguineo = $_POST['tipo_sanguineo'];
    $tipo_socio_id = $_POST['tipo_socio_id'];
    
    // Upload da foto
    $foto = '';
    if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], '../../assets/uploads/' . $foto);
    }
    
    // Gerar QR Code com ID do sócio
    include('../../vendor/phpqrcode/qrlib.php');
    $qrConteudo = "https://seusite.com/carteirinha/validar.php?id={$numero_titulo}";
    $qrPath = '../../assets/uploads/qrcode_' . $numero_titulo . '.png';
    QRcode::png($qrConteudo, $qrPath, QR_ECLEVEL_L, 10);
    
    // Gerar código de barras (exemplo simples)
    $barcodePath = '../../assets/uploads/barcode_' . $numero_titulo . '.png';
    // use um gerador como "php-barcode"
    
    $sql = "INSERT INTO socios (nome, numero_titulo, cpf, sexo, tipo_sanguineo, tipo_socio_id, foto, qrcode, codigo_barras)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $numero_titulo, $cpf, $sexo, $tipo_sanguineo, $tipo_socio_id, $foto, $qrPath, $barcodePath]);
}
?>