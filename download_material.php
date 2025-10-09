<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Verificar se está logado e é usuário
requireLogin();
if (!isUser()) {
    setFlashMessage('Acesso negado. Área restrita para alunos.', 'danger');
    header("Location: login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$material_id) {
    setFlashMessage('Material não encontrado.', 'danger');
    header("Location: materiais_aluno.php");
    exit();
}

try {
    // Verificar se o material existe e está disponível para o aluno
    $stmt = $pdo->prepare("
        SELECT 
            md.*,
            ma.id as acesso_id
        FROM materiais_didaticos md
        LEFT JOIN material_acesso ma ON md.id = ma.material_id
        WHERE md.id = ? 
        AND md.ativo = 1 
        AND md.publico = 1
        AND (ma.aluno_id IS NULL OR ma.aluno_id = ?)
        AND (ma.data_inicio IS NULL OR ma.data_inicio <= NOW())
        AND (ma.data_fim IS NULL OR ma.data_fim >= NOW())
    ");
    $stmt->execute([$material_id, $aluno_id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        setFlashMessage('Material não encontrado ou não disponível.', 'danger');
        header("Location: materiais_aluno.php");
        exit();
    }
    
    // Verificar se é um link externo
    if ($material['tipo_arquivo'] === 'link') {
        // Registrar visualização e redirecionar
        $stmt = $pdo->prepare("UPDATE materiais_didaticos SET visualizacoes = visualizacoes + 1 WHERE id = ?");
        $stmt->execute([$material_id]);
        
        $stmt = $pdo->prepare("INSERT INTO material_historico (material_id, aluno_id, acao, ip_address, user_agent) VALUES (?, ?, 'visualizacao', ?, ?)");
        $stmt->execute([$material_id, $aluno_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
        
        header("Location: " . $material['url_externa']);
        exit();
    }
    
    // Verificar se o arquivo existe
    $caminho_arquivo = $material['caminho_arquivo'];
    if (!file_exists($caminho_arquivo)) {
        setFlashMessage('Arquivo não encontrado no servidor.', 'danger');
        header("Location: materiais_aluno.php");
        exit();
    }
    
    // Incrementar contador de downloads
    $stmt = $pdo->prepare("UPDATE materiais_didaticos SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$material_id]);
    
    // Registrar no histórico
    $stmt = $pdo->prepare("INSERT INTO material_historico (material_id, aluno_id, acao, ip_address, user_agent) VALUES (?, ?, 'download', ?, ?)");
    $stmt->execute([$material_id, $aluno_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    
    // Preparar download
    $nome_arquivo = $material['nome_arquivo'];
    $tamanho_arquivo = filesize($caminho_arquivo);
    $tipo_mime = mime_content_type($caminho_arquivo);
    
    // Headers para download
    header('Content-Type: ' . $tipo_mime);
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Content-Length: ' . $tamanho_arquivo);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Limpar buffer de saída
    ob_clean();
    flush();
    
    // Enviar arquivo
    readfile($caminho_arquivo);
    exit();
    
} catch (PDOException $e) {
    setFlashMessage('Erro ao processar download: ' . $e->getMessage(), 'danger');
    header("Location: materiais_aluno.php");
    exit();
}
?>

