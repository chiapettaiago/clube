<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$tipos = $pdo->query("SELECT * FROM tipos_evento")->fetchAll();

if($_POST) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $local = $_POST['local'];
    $endereco = $_POST['endereco'];
    $capacidade = intval($_POST['capacidade']);
    $carga_horaria = intval($_POST['carga_horaria']);
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    
    // Upload da imagem
    $imagem = '';
    if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if(in_array($ext, $allowed)) {
            $imagem = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagem']['tmp_name'], '../../assets/uploads/eventos/' . $imagem);
        }
    }
    
    $sql = "INSERT INTO eventos (titulo, descricao, tipo, data_inicio, data_fim, local, endereco, capacidade, vagas_disponiveis, carga_horaria, valor, imagem, criado_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$titulo, $descricao, $tipo, $data_inicio, $data_fim, $local, $endereco, $capacidade, $capacidade, $carga_horaria, $valor, $imagem, $_SESSION['usuario_id']]);
    
    header('Location: index.php?msg=sucesso');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Evento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-plus-circle"></i> Cadastrar Evento</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="required">Título do Evento</label>
                                    <input type="text" name="titulo" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="required">Tipo</label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach($tipos as $tipo): ?>
                                            <option value="<?= $tipo['nome'] ?>">
                                                <i class="fas <?= $tipo['icone'] ?>"></i> <?= ucfirst($tipo['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="required">Data/Hora Início</label>
                                    <input type="datetime-local" name="data_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="required">Data/Hora Fim</label>
                                    <input type="datetime-local" name="data_fim" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Local</label>
                                    <input type="text" name="local" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Endereço</label>
                                    <input type="text" name="endereco" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Capacidade Máxima</label>
                                    <input type="number" name="capacidade" class="form-control" value="0">
                                    <small>0 = ilimitado</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Carga Horária (horas)</label>
                                    <input type="number" name="carga_horaria" class="form-control" value="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Valor (R$)</label>
                                    <input type="text" name="valor" class="form-control money" value="0,00">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Descrição</label>
                                    <textarea name="descricao" class="form-control" rows="4"></textarea>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Imagem do Evento</label>
                                    <input type="file" name="imagem" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-save"></i> Salvar Evento
                                </button>
                                <a href="index.php" class="btn btn-secondary px-5">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        flatpickr("input[type=datetime-local]", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true
        });
    </script>
</body>
</html>