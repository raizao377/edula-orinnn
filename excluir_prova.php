<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Verifica se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

// Verifica se o ID foi enviado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('ID de prova inválido.', 'danger');
    header("Location: criar_prova.php");
    exit();
}

$prova_id = intval($_GET['id']);
$professor_id = $_SESSION['user_id'];

try {
    // Verifica se a prova pertence a este professor
    $stmt = $pdo->prepare("SELECT * FROM provas WHERE id = ? AND professor_id = ?");
    $stmt->execute([$prova_id, $professor_id]);
    $prova = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prova) {
        setFlashMessage('Prova não encontrada ou não pertence a você.', 'danger');
        header("Location: criar_prova.php");
        exit();
    }

    // Excluir registros dependentes (por exemplo, disponibilidade e questões)
    $pdo->prepare("DELETE FROM prova_disponibilidade WHERE prova_id = ?")->execute([$prova_id]);
    $pdo->prepare("DELETE FROM questoes WHERE prova_id = ?")->execute([$prova_id]);

    // Excluir a prova
    $stmt = $pdo->prepare("DELETE FROM provas WHERE id = ?");
    $stmt->execute([$prova_id]);

    setFlashMessage('Prova excluída com sucesso!', 'success');
} catch (PDOException $e) {
    setFlashMessage('Erro ao excluir prova: ' . $e->getMessage(), 'danger');
}

// Volta para a página de provas
header("Location: criar_prova.php");
exit();
?>
