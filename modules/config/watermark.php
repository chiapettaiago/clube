<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

$mensagem = '';
$watermarkPath = '../../assets/watermarks/escudo.png';

if($_FILES && isset($_FILES['watermark'])) {
    $ext = strtolower(pathinfo($_FILES['watermark']['name'], PATHINFO_EXTENSION));
    if(in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
        move_uploaded_file($_FILES['watermark']['tmp_name'], $watermarkPath);
        $mensagem = '<div class="alert alert-success">Marca d\'água atualizada com sucesso!</div>';
    } else {
        $mensagem = '<div class="alert alert-danger">Formato não suportado! Use PNG, JPG ou GIF.</div>';
    }
}

$hasWatermark = file_exists($watermarkPath);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marca d'Água</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-watermark"></i> Marca d'Água do Clube</h4>
                    </div>
                    <div class="card-body">
                        <?= $mensagem ?>
                        
                        <?php if($hasWatermark): ?>
                            <div class="text-center mb-4">
                                <h6>Marca d'água atual:</h6>
                                <img src="../../assets/watermarks/escudo.png" style="max-width: 200px; opacity: 0.5;" class="border p-2">
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label>Enviar nova marca d'água (escudo do clube)</label>
                                <input type="file" name="watermark" class="form-control" accept="image/*" required>
                                <small class="text-muted">Formatos: PNG, JPG, GIF. Recomendado: PNG com fundo transparente</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Enviar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>