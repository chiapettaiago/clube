<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Processar publicação de comunicado
$msg_sucesso = '';
$msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $titulo = trim($_POST['titulo']);
    $conteudo = trim($_POST['conteudo']);
    $tipo = $_POST['tipo'];
    $data_validade = !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
    
    // Validação
    if (empty($titulo)) {
        $msg_erro = "❌ O título é obrigatório!";
    } elseif (empty($conteudo)) {
        $msg_erro = "❌ O conteúdo é obrigatório!";
    } else {
        try {
            // Verificar quais colunas existem na tabela
            $columns = $pdo->query("SHOW COLUMNS FROM comunicados")->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($columns)) $columns = [];
            
            // Construir query dinâmica baseada nas colunas existentes
            if (in_array('tipo', $columns) && in_array('data_validade', $columns) && in_array('publicado_por', $columns)) {
                $stmt = $pdo->prepare("
                    INSERT INTO comunicados (titulo, conteudo, tipo, data_validade, publicado_por, ativo)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$titulo, $conteudo, $tipo, $data_validade, $_SESSION['usuario_id']]);
            } else {
                // Versão simplificada sem os campos extras
                $stmt = $pdo->prepare("
                    INSERT INTO comunicados (titulo, conteudo, publicado_por, ativo)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$titulo, $conteudo, $_SESSION['usuario_id']]);
            }
            $msg_sucesso = "✅ Comunicado publicado com sucesso!";
            
        } catch(PDOException $e) {
            $msg_erro = "❌ Erro ao publicar: " . $e->getMessage();
        }
    }
}

// Processar exclusão
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    try {
        $stmt = $pdo->prepare("UPDATE comunicados SET ativo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $msg_sucesso = "✅ Comunicado removido com sucesso!";
    } catch(PDOException $e) {
        $msg_erro = "❌ Erro ao remover: " . $e->getMessage();
    }
}

// Buscar comunicados ativos
$resultComunicados = $pdo->query("
    SELECT c.*, u.nome as autor_nome
    FROM comunicados c
    LEFT JOIN usuarios u ON c.publicado_por = u.id
    WHERE c.ativo = 1
    ORDER BY c.data_publicacao DESC
");
$comunicados = $resultComunicados instanceof PDOStatement ? $resultComunicados->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicados - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-modern { border: none; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header-modern { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; border-radius: 20px 20px 0 0 !important; padding: 15px 20px; }
        .btn-secretaria { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); border: none; color: white; }
        .btn-secretaria:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(44,62,80,0.3); color: white; }
        .comunicado-item {
            transition: all 0.2s;
            border-left: 4px solid transparent;
            margin-bottom: 10px;
        }
        .comunicado-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .comunicado-urgente { border-left-color: #e74c3c; }
        .comunicado-aviso { border-left-color: #f39c12; }
        .comunicado-informativo { border-left-color: #3498db; }
        .comunicado-evento { border-left-color: #2ecc71; }
        .required-field:after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Formulário -->
            <div class="col-md-5">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Novo Comunicado</h5>
                    </div>
                    <div class="card-body">
                        <?php if($msg_sucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle"></i> <?= $msg_sucesso ?>
                            </div>
                        <?php endif; ?>
                        <?php if($msg_erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle"></i> <?= $msg_erro ?>
                            </div>
                        <?php endif; ?>
                        <!-- restante do arquivo permanece igual -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
