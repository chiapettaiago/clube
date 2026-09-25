<?php
require_once '../../../config.php';

$inscricao_id = $_GET['inscricao'] ?? 0;
$evento_id = $_GET['evento'] ?? 0;

// Buscar dados
$stmt = $pdo->prepare("
    SELECT i.*, s.nome as socio_nome, s.numero_titulo, s.cpf,
           e.titulo as evento_titulo, e.data_inicio, e.carga_horaria, e.local
    FROM inscricoes_eventos i
    JOIN socios s ON i.socio_id = s.id
    JOIN eventos e ON i.evento_id = e.id
    WHERE i.id = ?
");
$stmt->execute([$inscricao_id]);
$dados = $stmt->fetch();

if(!$dados) {
    die('Dados não encontrados');
}

// Marcar como emitido
$pdo->prepare("UPDATE inscricoes_eventos SET certificado_emitido = 1 WHERE id = ?")->execute([$inscricao_id]);

// Criar imagem do certificado (simplificado)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Certificado - <?= htmlspecialchars($dados['socio_nome']) ?></title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Times New Roman', serif;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .certificado {
            width: 800px;
            height: 600px;
            background: white;
            border: 10px solid #daa520;
            padding: 40px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .certificado h1 {
            font-size: 48px;
            color: #daa520;
            margin-top: 50px;
        }
        .certificado h3 {
            font-size: 24px;
            margin-top: 20px;
        }
        .certificado .nome {
            font-size: 32px;
            font-weight: bold;
            margin: 30px 0;
            border-bottom: 2px solid #daa520;
            display: inline-block;
            padding: 0 20px;
        }
        .certificado .evento {
            font-size: 28px;
            font-weight: bold;
            margin: 20px 0;
        }
        .certificado .texto {
            font-size: 16px;
            line-height: 1.8;
            margin: 30px 0;
        }
        .certificado .assinatura {
            margin-top: 50px;
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 10px;
            width: 250px;
        }
        .selo {
            position: absolute;
            bottom: 40px;
            right: 40px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #daa520;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="certificado">
        <h1>CERTIFICADO</h1>
        <h3>O Clube certifica que</h3>
        <div class="nome"><?= strtoupper(htmlspecialchars($dados['socio_nome'])) ?></div>
        <div class="texto">
            participou do evento <strong class="evento"><?= htmlspecialchars($dados['evento_titulo']) ?></strong><br>
            realizado no dia <?= date('d/m/Y', strtotime($dados['data_inicio'])) ?>, 
            com carga horária de <strong><?= $dados['carga_horaria'] ?> horas</strong>.
        </div>
        <div class="assinatura">Diretoria do Clube</div>
        <div class="selo">
            <span>CLUBE<br>MANAGER</span>
        </div>
    </div>
    
    <div class="no-print" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Certificado</button>
        <button onclick="window.close()" class="btn btn-secondary">Fechar</button>
    </div>
</body>
</html>