<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Agendamento da geração de mensalidades</h4>
        </div>
        <div class="card-body">
            <p>Use este arquivo no Agendador de Tarefas do Windows para gerar as mensalidades automaticamente todo mês.</p>

            <div class="alert alert-primary">
                <strong>Arquivo executável:</strong><br>
                <code>C:\xampp\htdocs\clube\scripts\AgendarMensalidades.bat</code>
            </div>

            <h5 class="mt-4">Passo a passo</h5>
            <ol>
                <li>Abra o <strong>Agendador de Tarefas</strong> do Windows.</li>
                <li>Crie uma nova tarefa básica.</li>
                <li>Na ação, escolha <strong>Iniciar um programa</strong>.</li>
                <li>No campo programa/script, informe o caminho do BAT acima.</li>
                <li>Agende para executar no dia 1 de cada mês ou conforme sua rotina.</li>
            </ol>

            <div class="alert alert-warning">
                <strong>Dica:</strong> você também pode testar manualmente abrindo o arquivo BAT duas vezes para conferir se o PHP do XAMPP está gerando as mensalidades corretamente.
            </div>

            <div class="d-flex gap-2">
                <a href="mensalidades.php" class="btn btn-success">Voltar para mensalidades</a>
                <a href="../../scripts/AgendarMensalidades.bat" class="btn btn-outline-dark">Abrir BAT</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
