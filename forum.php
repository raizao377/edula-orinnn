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

// Processar criação de novo tópico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (!empty($title) && !empty($content)) {
        try {
            $pdo->beginTransaction();
            
            // Criar o tópico
            $stmt = $pdo->prepare("INSERT INTO forum_topics (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, $title]);
            $topic_id = $pdo->lastInsertId();
            
            // Criar o primeiro post do tópico
            $stmt = $pdo->prepare("INSERT INTO forum_posts (topic_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$topic_id, $user_id, $content]);
            
            $pdo->commit();
            setFlashMessage('Tópico criado com sucesso!', 'success');
            header("Location: forum.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlashMessage('Erro ao criar tópico. Tente novamente.', 'danger');
        }
    } else {
        setFlashMessage('Por favor, preencha todos os campos.', 'warning');
    }
}

// Buscar todos os tópicos
try {
    $stmt = $pdo->prepare("
        SELECT ft.id, ft.title, ft.created_at, u.nome as author_name,
               COUNT(fp.id) as post_count,
               MAX(fp.created_at) as last_post_date
        FROM forum_topics ft
        JOIN users u ON ft.user_id = u.id
        LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
        GROUP BY ft.id, ft.title, ft.created_at, u.nome
        ORDER BY last_post_date DESC, ft.created_at DESC
    ");
    $stmt->execute();
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topics = [];
    setFlashMessage('Erro ao carregar tópicos.', 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/forum.css">
    <title>Fórum - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Fórum</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Participe das discussões!</p>
        <ul>
            <li><a href="area_user.php">Voltar à Área do Usuário</a></li>
            <li><a href="INICIO.php">Início</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="forum-container">
        <div class="forum-header">
            <h1>Fórum de Discussão</h1>
            <p>Compartilhe conhecimento e tire suas dúvidas com outros estudantes!</p>
        </div>

        <!-- Formulário para criar novo tópico -->
        <div class="create-topic-section">
            <h2>Criar Novo Tópico</h2>
            <form method="POST" class="topic-form">
                <div class="form-group">
                    <label for="title">Título do Tópico:</label>
                    <input type="text" id="title" name="title" required maxlength="255" placeholder="Digite o título do seu tópico">
                </div>
                <div class="form-group">
                    <label for="content">Conteúdo:</label>
                    <textarea id="content" name="content" required rows="5" placeholder="Descreva sua dúvida ou compartilhe seu conhecimento..."></textarea>
                </div>
                <button type="submit" name="create_topic" class="btn-create">
                    <i class='bx bx-plus'></i> Criar Tópico
                </button>
            </form>
        </div>

        <!-- Lista de tópicos -->
        <div class="topics-section">
            <h2>Tópicos Recentes</h2>
            <?php if (empty($topics)): ?>
                <div class="no-topics">
                    <i class='bx bx-chat'></i>
                    <p>Ainda não há tópicos no fórum. Seja o primeiro a criar um!</p>
                </div>
            <?php else: ?>
                <div class="topics-list">
                    <?php foreach ($topics as $topic): ?>
                        <div class="topic-item">
                            <div class="topic-info">
                                <h3><a href="topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h3>
                                <div class="topic-meta">
                                    <span class="author">Por: <?php echo htmlspecialchars($topic['author_name']); ?></span>
                                    <span class="date">Criado em: <?php echo date('d/m/Y H:i', strtotime($topic['created_at'])); ?></span>
                                    <?php if ($topic['last_post_date']): ?>
                                        <span class="last-post">Última resposta: <?php echo date('d/m/Y H:i', strtotime($topic['last_post_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="topic-stats">
                                <div class="stat">
                                    <i class='bx bx-message-dots'></i>
                                    <span><?php echo $topic['post_count']; ?> respostas</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

        .forum-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .forum-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .forum-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .create-topic-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .topic-form .form-group {
            margin-bottom: 15px;
        }

        .topic-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .topic-form input, .topic-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-create {
            background: #007bff;
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

        .btn-create:hover {
            background: #0056b3;
        }

        .topics-section h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .no-topics {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-topics i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .topic-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topic-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .topic-info h3 {
            margin: 0 0 10px 0;
        }

        .topic-info h3 a {
            color: #007bff;
            text-decoration: none;
        }

        .topic-info h3 a:hover {
            text-decoration: underline;
        }

        .topic-meta {
            font-size: 12px;
            color: #666;
        }

        .topic-meta span {
            margin-right: 15px;
        }

        .topic-stats {
            text-align: center;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
        }
    </style>
</body>
</html>

