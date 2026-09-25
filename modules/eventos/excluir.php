<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$id = $_GET['id'] ?? 0;

// Buscar imagem para deletar
$stmt = $pdo->prepare("SELECT imagem FROM eventos WHERE id = ?");
$stmt->execute([$id]);
$evento = $stmt->fetch();

if($evento && $evento['imagem'] && file_exists('../../assets/uploads/eventos/' . $evento['imagem'])) {
    unlink('../../assets/uploads/eventos/' . $evento['imagem']);
}

// Excluir inscrições primeiro (por causa da FK)
$pdo->prepare("DELETE FROM inscricoes_eventos WHERE evento_id = ?")->execute([$id]);

// Excluir evento
$pdo->prepare("DELETE FROM eventos WHERE id = ?")->execute([$id]);

header('Location: index.php?msg=excluido');
exit;
?>