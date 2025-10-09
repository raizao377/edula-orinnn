<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Conectar ao banco de dados
$database = new Database();
$pdo = $database->getConnection();

// Verificar se está logado e é usuário
requireLogin();
if (!isUser()) {
    setFlashMessage('Acesso negado. Área restrita para usuários.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$nome = $_SESSION['nome'];
$user_id = $_SESSION['user_id'];

// Verificar se o ID do tópico foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Tópico não encontrado.', 'danger');
    header("Location: forum.php");
    exit();
}

$topic_id = (int)$_GET['id'];

// Processar nova resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $content = trim($_POST['content']);
    
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$topic_id, $user_id, $content]);
            setFlashMessage('Resposta adicionada com sucesso!', 'success');
            header("Location: topic.php?id=" . $topic_id);
            exit();
        } catch (Exception $e) {
            setFlashMessage('Erro ao adicionar resposta. Tente novamente.', 'danger');
        }
    } else {
        setFlashMessage('Por favor, escreva uma resposta.', 'warning');
    }
}

// Buscar informações do tópico
try {
    $stmt = $pdo->prepare("
        SELECT ft.id, ft.title, ft.created_at, u.nome as author_name
        FROM forum_topics ft
        JOIN users u ON ft.user_id = u.id
        WHERE ft.id = ?
    ");
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        setFlashMessage('Tópico não encontrado.', 'danger');
        header("Location: forum.php");
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar tópico.', 'danger');
    header("Location: forum.php");
    exit();
}

// Buscar todas as respostas do tópico
try {
    $stmt = $pdo->prepare("
        SELECT fp.id, fp.content, fp.created_at, u.nome as author_name
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.topic_id = ?
        ORDER BY fp.created_at ASC
    ");
    $stmt->execute([$topic_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $posts = [];
    setFlashMessage('Erro ao carregar respostas.', 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/forum.css">
    <title><?php echo htmlspecialchars($topic['title']); ?> - Fórum Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Fórum</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Participando da discussão!</p>
        <ul>
            <li><a href="forum.php">Voltar ao Fórum</a></li>
            <li><a href="area_user.php">Área do Usuário</a></li>
            <li><a href="INICIO.php">Início</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="topic-container">
        <!-- Cabeçalho do tópico -->
        <div class="topic-header">
            <h1><?php echo htmlspecialchars($topic['title']); ?></h1>
            <div class="topic-meta">
                <span class="author">Criado por: <?php echo htmlspecialchars($topic['author_name']); ?></span>
                <span class="date">Em: <?php echo date('d/m/Y H:i', strtotime($topic['created_at'])); ?></span>
            </div>
        </div>

        <!-- Lista de posts -->
        <div class="posts-section">
            <?php if (empty($posts)): ?>
                <div class="no-posts">
                    <i class='bx bx-message'></i>
                    <p>Ainda não há respostas neste tópico. Seja o primeiro a responder!</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $index => $post): ?>
                    <div class="post-item <?php echo $index === 0 ? 'first-post' : ''; ?>">
                        <div class="post-header">
                            <div class="post-author">
                                <i class='bx bx-user-circle'></i>
                                <span><?php echo htmlspecialchars($post['author_name']); ?></span>
                            </div>
                            <div class="post-date">
                                <?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulário para nova resposta -->
        <div class="reply-section">
            <h3>Adicionar Resposta</h3>
            <form method="POST" class="reply-form">
                <div class="form-group">
                    <label for="content">Sua resposta:</label>
                    <textarea id="content" name="content" required rows="5" placeholder="Escreva sua resposta aqui..."></textarea>
                </div>
                <button type="submit" name="add_reply" class="btn-reply">
                    <i class='bx bx-send'></i> Enviar Resposta
                </button>
            </form>
        </div>
    </div>

    <style>
        .flash-message {
            margin: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .flash-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .flash-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .flash-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .topic-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .topic-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .topic-header h1 {
            color: #333;
            margin: 0 0 10px 0;
        }

        .topic-meta {
            font-size: 14px;
            color: #666;
        }

        .topic-meta span {
            margin-right: 20px;
        }

        .posts-section {
            margin-bottom: 30px;
        }

        .no-posts {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .no-posts i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .post-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .first-post {
            border-left: 4px solid #007bff;
        }

        .post-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            color: #333;
        }

        .post-author i {
            font-size: 20px;
            color: #007bff;
        }

        .post-date {
            font-size: 12px;
            color: #666;
        }

        .post-content {
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }

        .reply-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }

        .reply-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .reply-form .form-group {
            margin-bottom: 15px;
        }

        .reply-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .reply-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
        }

        .btn-reply {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reply:hover {
            background: #218838;
        }
    </style>
</body>
</html>

