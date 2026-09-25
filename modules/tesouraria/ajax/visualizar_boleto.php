<?php
require_once '../../../config.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT b.*, s.nome as socio_nome, s.cpf, s.endereco
    FROM boletos b
    JOIN socios s ON b.socio_id = s.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$boleto = $stmt->fetch();

if(!$boleto) {
    die('Boleto não encontrado');
}

// Buscar configurações
$config = [];
$cfg = $pdo->query("SELECT chave, valor FROM config_financeiro");
foreach($cfg as $c) {
    $config[$c['chave']] = $c['valor'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleto - <?= $boleto['numero_boleto'] ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
        }
        .boleto {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            background: #00695c;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .beneficiario, .pagador, .detalhes {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .linha-digitavel {
            background: #f5f5f5;
            padding: 15px;
            text-align: center;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 2px;
        }
        .codigo-barras {
            text-align: center;
            padding: 20px;
            background: #fff;
            font-family: monospace;
            font-size: 24px;
            letter-spacing: 5px;
        }
        .valor {
            font-size: 24px;
            color: #00695c;
            font-weight: bold;
        }
        table { width: 100%; }
        td { padding: 5px; }
        .label { font-weight: bold; width: 120px; }
    </style>
</head>
<body>
    <div class="boleto">
        <div class="header">
            <h2><?= $config['boleto_beneficiario'] ?? 'Clube Esportivo' ?></h2>
            <p>CNPJ: <?= $config['boleto_cnpj'] ?? '00.000.000/0001-00' ?></p>
        </div>
        
        <div class="beneficiario">
            <h4>Beneficiário</h4>
            <table>
                <tr><td class="label">Nome:</td><td><?= $config['boleto_beneficiario'] ?? 'Clube Esportivo' ?></td></tr>
                <tr><td class="label">CNPJ:</td><td><?= $config['boleto_cnpj'] ?? '00.000.000/0001-00' ?></td></tr>
                <tr><td class="label">Banco:</td><td><?= $config['boleto_banco'] ?? '001' ?> - Banco do Brasil</td></tr>
            </table>
        </div>
        
        <div class="pagador">
            <h4>Pagador</h4>
            <table>
                <tr><td class="label">Nome:</td><td><?= htmlspecialchars($boleto['socio_nome']) ?></td></tr>
                <tr><td class="label">CPF:</td><td><?= $boleto['cpf'] ?></td></tr>
                <tr><td class="label">Endereço:</td><td><?= htmlspecialchars($boleto['endereco'] ?? '-') ?></td></tr>
            </table>
        </div>
        
        <div class="detalhes">
            <h4>Detalhes do Documento</h4>
            <table>
                <tr><td class="label">Número do Boleto:</td><td><?= $boleto['numero_boleto'] ?></td></tr>
                <tr><td class="label">Nosso Número:</td><td><?= $boleto['nosso_numero'] ?></td></tr>
                <tr><td class="label">Data de Emissão:</td><td><?= date('d/m/Y', strtotime($boleto['data_emissao'])) ?></td></tr>
                <tr><td class="label">Data de Vencimento:</td><td><?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?></td></tr>
                <tr><td class="label">Valor do Documento:</td><td class="valor">R$ <?= number_format($boleto['valor'], 2, ',', '.') ?></td></tr>
            </table>
        </div>
        
        <div class="linha-digitavel">
            <strong>Linha Digitável</strong><br>
            <?= chunk_split($boleto['linha_digitavel'], 5, ' ') ?>
        </div>
        
        <div class="codigo-barras">
            <?= $boleto['codigo_barras'] ?>
        </div>
        
        <div style="padding: 15px; font-size: 12px; color: #666; background: #f9f9f9;">
            <strong>Instruções:</strong> <?= $config['boleto_instrucoes'] ?? 'Após o vencimento, cobrar multa de 2% e juros de 0,33% ao dia.' ?>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Boleto</button>
        <button onclick="window.close()" class="btn btn-secondary">Fechar</button>
    </div>
</body>
</html>