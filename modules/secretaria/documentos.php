<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Criar pasta de uploads se não existir
$uploadDir = __DIR__ . '/uploads/documentos/';
if(!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$msg_sucesso = '';
$msg_erro = '';

// Processar upload
if($_POST && isset($_POST['upload'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $categoria = $_POST['categoria'];
    
    if(empty($titulo)) {
        $msg_erro = "❌ O título é obrigatório!";
    } elseif(isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png', 'txt'];
        
        if(!in_array($ext, $extensoesPermitidas)) {
            $msg_erro = "❌ Formato de arquivo não permitido! Use: PDF, DOC, XLS, JPG, PNG ou TXT";
        } else {
            $nomeArquivo = uniqid() . '.' . $ext;
            $caminho = $uploadDir . $nomeArquivo;
            
            if(move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminho)) {
                try {
                    // Verificar quais colunas existem na tabela
                    $columns = $pdo->query("SHOW COLUMNS FROM documentos")->fetchAll(PDO::FETCH_COLUMN);
                    if(!is_array($columns)) $columns = [];
                    
                    if(in_array('ativo', $columns)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO documentos (titulo, descricao, arquivo, categoria, upload_por, ativo)
                            VALUES (?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$titulo, $descricao, $nomeArquivo, $categoria, $_SESSION['usuario_id']]);
                    } else {
                        // Versão sem a coluna ativo
                        $stmt = $pdo->prepare("
                            INSERT INTO documentos (titulo, descricao, arquivo, categoria, upload_por)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$titulo, $descricao, $nomeArquivo, $categoria, $_SESSION['usuario_id']]);
                    }
                    $msg_sucesso = "✅ Documento enviado com sucesso!";
                } catch(PDOException $e) {
                    $msg_erro = "❌ Erro ao salvar: " . $e->getMessage();
                }
            } else {
                $msg_erro = "❌ Erro ao fazer upload do arquivo!";
            }
        }
    } else {
        $msg_erro = "❌ Selecione um arquivo para upload!";
    }
}

// Processar exclusão
if(isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    try {
        // Buscar o arquivo para deletar
        $stmt = $pdo->prepare("SELECT arquivo FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if($doc && file_exists($uploadDir . $doc['arquivo'])) {
            unlink($uploadDir . $doc['arquivo']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        $msg_sucesso = "✅ Documento excluído com sucesso!";
    } catch(PDOException $e) {
        $msg_erro = "❌ Erro ao excluir: " . $e->getMessage();
    }
}

// Processar download (contabilizar)
if(isset($_GET['download'])) {
    $id = intval($_GET['download']);
    
    // Verificar se a coluna downloads existe
    $columns = $pdo->query("SHOW COLUMNS FROM documentos")->fetchAll(PDO::FETCH_COLUMN);
    if(!is_array($columns)) $columns = [];
    if(in_array('downloads', $columns)) {
        $stmt = $pdo->prepare("UPDATE documentos SET downloads = downloads + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    // Buscar o arquivo
    $stmt = $pdo->prepare("SELECT arquivo FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if($doc && file_exists($uploadDir . $doc['arquivo'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($doc['arquivo']) . '"');
        readfile($uploadDir . $doc['arquivo']);
        exit;
    }
}

// Buscar documentos - CORRIGIDO
$documentos = [];
try {
    // Verificar se a coluna ativo existe
    $columns = $pdo->query("SHOW COLUMNS FROM documentos")->fetchAll(PDO::FETCH_COLUMN);
    if(!is_array($columns)) $columns = [];
    
    if(in_array('ativo', $columns)) {
        $sql = "SELECT d.*, u.nome as autor_nome 
                FROM documentos d 
                LEFT JOIN usuarios u ON d.upload_por = u.id 
                WHERE d.ativo = 1 
                ORDER BY d.data_upload DESC";
    } else {
        $sql = "SELECT d.*, u.nome as autor_nome 
                FROM documentos d 
                LEFT JOIN usuarios u ON d.upload_por = u.id 
                ORDER BY d.data_upload DESC";
    }
    $result = $pdo->query($sql);
    $documentos = $result instanceof PDOStatement ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
} catch(PDOException $e) {
    $documentos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-modern { border: none; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header-modern { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; border-radius: 20px 20px 0 0 !important; padding: 15px 20px; }
        .btn-secretaria { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); border: none; color: white; }
        .btn-secretaria:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(44,62,80,0.3); color: white; }
        .required-field:after { content: " *"; color: #e74c3c; font-weight: bold; }
        .documento-item { transition: all 0.2s; }
        .documento-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Formulário de Upload -->
            <div class="col-md-4">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-upload"></i> Upload de Documento</h5>
                    </div>
                    <div class="card-body">
                        <?php if($msg_sucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle"></i> <?= $msg_sucesso ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($msg_erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle"></i> <?= $msg_erro ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="fw-bold required-field">Título</label>
                                <input type="text" name="titulo" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Categoria</label>
                                <select name="categoria" class="form-select">
                                    <option value="estatuto">📜 Estatuto</option>
                                    <option value="regimento">📋 Regimento Interno</option>
                                    <option value="formulario">📝 Formulário</option>
                                    <option value="comunicado">📢 Comunicado</option>
                                    <option value="outro">📁 Outro</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold required-field">Arquivo</label>
                                <input type="file" name="arquivo" class="form-control" required>
                                <small class="text-muted">Formatos: PDF, DOC, XLS, JPG, PNG, TXT (Max 10MB)</small>
                            </div>
                            <button type="submit" name="upload" class="btn btn-secretaria w-100 py-2">
                                <i class="fas fa-cloud-upload-alt"></i> Enviar Documento
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Documentos -->
            <div class="col-md-8">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-folder-open"></i> Documentos Disponíveis</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($documentos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                                <p>Nenhum documento disponível</p>
                                <small class="text-muted">Use o formulário ao lado para fazer upload de documentos</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Título</th>
                                            <th>Categoria</th>
                                            <th>Data</th>
                                            <th>Downloads</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($documentos as $doc): ?>
                                        <tr class="documento-item">
                                            <td>
                                                <strong><?= htmlspecialchars($doc['titulo']) ?></strong>
                                                <?php if(!empty($doc['descricao'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($doc['descricao'], 0, 50)) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $iconeCategoria = '';
                                                switch($doc['categoria']) {
                                                    case 'estatuto': $iconeCategoria = '📜'; break;
                                                    case 'regimento': $iconeCategoria = '📋'; break;
                                                    case 'formulario': $iconeCategoria = '📝'; break;
                                                    case 'comunicado': $iconeCategoria = '📢'; break;
                                                    default: $iconeCategoria = '📁';
                                                }
                                                ?>
                                                <span class="badge bg-secondary"><?= $iconeCategoria ?> <?= ucfirst($doc['categoria']) ?></span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($doc['data_upload'])) ?> </td>
                                            <td><?= $doc['downloads'] ?? 0 ?> </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?download=<?= $doc['id'] ?>" class="btn btn-primary" title="Baixar">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="?excluir=<?= $doc['id'] ?>" class="btn btn-danger" onclick="return confirm('Excluir este documento?')" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                          </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
