<?php
require_once 'config.php';

$endpoints = [
    [
        'path' => '/api/auth.php',
        'method' => 'POST',
        'description' => 'Autenticação do sócio',
        'params' => ['login', 'senha'],
        'example' => '{"login": "123456", "senha": "123456"}'
    ],
    [
        'path' => '/api/socio.php',
        'method' => 'GET',
        'description' => 'Dados do sócio logado',
        'auth' => true,
        'params' => []
    ],
    [
        'path' => '/api/carteirinha.php',
        'method' => 'GET',
        'description' => 'Validação de carteirinha (leitura QR Code)',
        'auth' => true,
        'params' => ['codigo']
    ],
    [
        'path' => '/api/convites.php',
        'method' => 'GET',
        'description' => 'Consulta de convites disponíveis',
        'auth' => true,
        'params' => []
    ],
    [
        'path' => '/api/eventos.php',
        'method' => 'GET',
        'description' => 'Lista de eventos disponíveis',
        'auth' => true,
        'params' => ['status', 'limite']
    ],
    [
        'path' => '/api/eventos.php/inscrever',
        'method' => 'POST',
        'description' => 'Inscrever-se em um evento',
        'auth' => true,
        'params' => ['evento_id']
    ],
    [
        'path' => '/api/reservas.php',
        'method' => 'GET',
        'description' => 'Lista de reservas do sócio',
        'auth' => true,
        'params' => ['status']
    ],
    [
        'path' => '/api/reservas.php/nova',
        'method' => 'POST',
        'description' => 'Criar nova reserva',
        'auth' => true,
        'params' => ['espaco_id', 'data', 'hora_inicio', 'hora_fim']
    ],
    [
        'path' => '/api/notificacoes.php',
        'method' => 'GET',
        'description' => 'Lista de notificações push',
        'auth' => true,
        'params' => ['limite', 'offset']
    ],
    [
        'path' => '/api/notificacoes.php/marcar_lida',
        'method' => 'PUT',
        'description' => 'Marcar notificação como lida',
        'auth' => true,
        'params' => ['id']
    ],
    [
        'path' => '/api/dispositivo.php',
        'method' => 'POST',
        'description' => 'Registrar dispositivo para push',
        'auth' => true,
        'params' => ['device_token', 'platform', 'device_model']
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Mobile - Clube Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: white; }
        .card { background: #16213e; border: none; border-radius: 15px; }
        code { background: #0f3460; color: #fff; padding: 2px 5px; border-radius: 5px; }
        pre { background: #0f3460; padding: 15px; border-radius: 10px; }
        .endpoint { border-left: 4px solid #f39c12; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-5">
            <i class="fas fa-mobile-alt fa-4x text-warning mb-3"></i>
            <h1>API Mobile - Clube Manager</h1>
            <p class="text-muted">Base URL: <code>http://localhost/clube/api/</code></p>
            <p class="text-muted">Versão: 1.0.0</p>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-info-circle"></i> Como usar a API</h5>
                    </div>
                    <div class="card-body">
                        <p>Todas as requisições devem incluir o header:</p>
                        <pre><code>Authorization: Bearer SEU_TOKEN_AQUI</code></pre>
                        <p>Ou via query string:</p>
                        <pre><code>?token=SEU_TOKEN_AQUI</code></pre>
                        <p>Resposta padrão:</p>
                        <pre>{
    "status": 200,
    "success": true,
    "message": "",
    "data": {...},
    "timestamp": "2024-01-01 00:00:00"
}</pre>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-list"></i> Endpoints Disponíveis</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($endpoints as $ep): ?>
                        <div class="endpoint p-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge bg-<?= $ep['method'] == 'GET' ? 'info' : ($ep['method'] == 'POST' ? 'success' : 'warning') ?>">
                                        <?= $ep['method'] ?>
                                    </span>
                                    <code class="ms-2"><?= $ep['path'] ?></code>
                                    <?php if($ep['auth'] ?? false): ?>
                                        <span class="badge bg-danger ms-2">Autenticado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mt-2 mb-2"><?= $ep['description'] ?></p>
                            <?php if(!empty($ep['params'])): ?>
                            <small><strong>Parâmetros:</strong> <?= implode(', ', $ep['params']) ?></small>
                            <?php endif; ?>
                            <?php if(isset($ep['example'])): ?>
                            <div class="mt-2">
                                <small><strong>Exemplo:</strong></small>
                                <pre class="mt-1 mb-0"><code><?= $ep['example'] ?></code></pre>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-key"></i> Como Obter um Token</h5>
                    </div>
                    <div class="card-body">
                        <p>Para obter um token de acesso, o sócio deve fazer login no aplicativo:</p>
                        <pre><code>POST /api/auth.php
{
    "login": "123456",
    "senha": "123456",
    "device_name": "iPhone 13"
}</code></pre>
                        <p>Resposta:</p>
                        <pre>{
    "status": 200,
    "success": true,
    "data": {
        "token": "abc123...",
        "socio": {...},
        "expiracao": "2024-02-01 00:00:00"
    }
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
