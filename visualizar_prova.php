<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

// Flash message
$flash = getFlashMessage() ?? null;

$professor_id = $_SESSION['user_id'];

// Validar id da prova
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Prova inválida.', 'danger');
    header("Location: area_professor.php");
    exit();
}

$prova_id = intval($_GET['id']);

// Buscar prova
$stmt = $pdo->prepare("SELECT * FROM provas WHERE id = ? AND professor_id = ?");
$stmt->execute([$prova_id, $professor_id]);
$prova = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prova) {
    setFlashMessage('Prova não encontrada.', 'danger');
    header("Location: area_professor.php");
    exit();
}

// Buscar questões da prova
$stmt = $pdo->prepare("SELECT * FROM prova_questoes WHERE prova_id = ? ORDER BY ordem ASC");
$stmt->execute([$prova_id]);
$questoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Prova - <?php echo htmlspecialchars($prova['titulo']); ?></title>
    <style>
        /* ===== Reset básico ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background: #0ef;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .header .logo img {
            height: 40px;
        }
        .navbar a {
            margin-left: 20px;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            transition: opacity 0.3s;
        }
        .navbar a:hover {
            opacity: 0.8;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 25px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1, h2 {
            margin-bottom: 20px;
            font-weight: bold;
            color: #222;
        }
        p {
            margin-bottom: 10px;
        }
        ol li {
            margin-bottom: 20px;
        }
        ol li ul {
            margin-top: 8px;
            margin-left: 20px;
        }
        .flash-message {
            margin: 15px 0;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        .flash-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .flash-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .flash-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        @media (max-width: 768px) {
            .container { padding: 15px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="static/img/logo.png" alt="Logo">
        </div>
        <nav class="navbar">
            <a href="area_professor.php">Voltar</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if (!empty($flash)): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($prova['titulo'] ?? '-'); ?></h1>
        <p><strong>Matéria:</strong> <?php echo htmlspecialchars($prova['materia'] ?? '-'); ?></p>
        <p><strong>Tipo:</strong> <?php echo ucfirst($prova['tipo'] ?? '-'); ?></p>
        <p><strong>Tempo limite:</strong> <?php echo isset($prova['tempo_limite']) ? ($prova['tempo_limite'] > 0 ? $prova['tempo_limite'].' min' : 'Sem limite') : '-'; ?></p>
        <p><strong>Descrição:</strong> <?php echo nl2br(htmlspecialchars($prova['descricao'] ?? '-')); ?></p>

        <h2>Questões</h2>
        <?php if (empty($questoes)): ?>
            <p>Nenhuma questão adicionada ainda.</p>
        <?php else: ?>
            <ol>
                <?php foreach ($questoes as $q): ?>
                    <li>
                        <p><?php echo htmlspecialchars($q['questao']); ?></p>
                        <ul>
                            <li>A) <?php echo htmlspecialchars($q['opcao_a']); ?></li>
                            <li>B) <?php echo htmlspecialchars($q['opcao_b']); ?></li>
                            <li>C) <?php echo htmlspecialchars($q['opcao_c']); ?></li>
                            <li>D) <?php echo htmlspecialchars($q['opcao_d']); ?></li>
                        </ul>
                        <p><strong>Resposta correta:</strong> <?php echo strtoupper($q['resposta_correta']); ?></p>
                        <p><strong>Pontuação:</strong> <?php echo $q['pontuacao']; ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</body>
</html>
