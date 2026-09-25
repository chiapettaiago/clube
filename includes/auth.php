<?php
if (!isset($_SESSION['usuario_id'])) {
    // O sistema é publicado na raiz do subdomínio; /clube/ era o caminho da
    // instalação antiga e causa 404 neste domínio.
    header('Location: /index.php');
    exit;
}
?>
