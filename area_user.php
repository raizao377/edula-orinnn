<?php
require_once 'includes/session.php';

// Verificar se está logado e é usuário
requireLogin();
if (!isUser()) {
    setFlashMessage('Acesso negado. Área restrita para usuários.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$nome = $_SESSION['nome'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <title>Área do Usuário - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span></h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Bem-vindo à sua área personalizada.</p>
        <ul>
            <li><a href="INICIO.php">Início</a></li>
            <li id="link1"><a href="provas_disponiveis.php">Avaliação Diagnóstica</a></li>
            <li id="link2"><a href="materiais_aluno.php">Material de Apoio</a></li>
            <li id="link3"><a href="chat.php">Consultar a IA</a></li>
            <li><a href="forum.php">Fórum</a></li>
            <li><a href="study_tracks.php">Trilha de Estudos</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="user-area">
        <p class="title">Aprenda com mais facilidade através do Edula.</p>
        <p class="subtitle">O Edula vai ajudar você a ter um melhor desempenho na escola.</p>
        <img src="static/img/logo.png">
    </div>

    <div class="guarantee">
        <div class="item" onclick="window.location.href='provas_disponiveis.php'">
            <div class="icon"><i class='bx bx-book-bookmark'></i></div>
            <div class="info"><p>Avaliações Diagnósticas</p></div>
            <i class='bx bx-chevron-right'></i>
        </div>
        <div class="item" onclick="window.location.href='materiais_aluno.php'">
            <div class="icon"><i class='bx bxs-package'></i></div>
            <div class="info"><p>Material de Apoio</p></div>
            <i class='bx bx-chevron-right'></i>
        </div>
        <div class="item" onclick="window.location.href='chat.php'">
            <div class="icon"><i class='bx bx-shape-triangle'></i></div>
            <div class="info"><p>Consultar a IA</p></div>
            <i class='bx bx-chevron-right'></i>
        </div>
        <div class="item" onclick="window.location.href='forum.php'">
            <div class="icon"><i class='bx bx-chat'></i></div>
            <div class="info"><p>Fórum de Discussão</p></div>
            <i class='bx bx-chevron-right'></i>
        </div>
        <div class="item" onclick="window.location.href='study_tracks.php'">
            <div class="icon"><i class='bx bx-map'></i></div>
            <div class="info"><p>Trilha de Estudos</p></div>
            <i class='bx bx-chevron-right'></i>
        </div>
    </div>

    <div class="about">
        <img src="static/img/webinar-animate Mulher.svg">
        <div class="info">
            <h3>Agradecemos sua preferência!</h3>
            <p>Vivemos em uma era em que a tecnologia desempenha um papel fundamental na educação. O EDULA combina inovação tecnológica e excelência educacional, personalizando o aprendizado para cada aluno.</p>
        </div>
    </div>

    <footer>
        <div class="start">
            <h3>Dica bônus</h3>
            <p>Estude mais com o Edula! Continuaremos aprimorando a plataforma para oferecer a melhor experiência de ensino.</p>
        </div>
        <div class="cols">
            <div class="about-col">
                <h3>EDULA.com</h3>
                <p>O site de ensino-aprendizagem</p>
            </div>
            <div class="news-col">
                <h4>Notificações</h4>
                <p>Coloque seu número de telefone para receber informações.</p>
                <form>
                    <input type="text" placeholder="Digite seu telefone">
                    <button><i class='bx bxl-telegram'></i></button>
                </form>
                <div class="copyright">
                    Copyright © 2024 Edula. Site de Ensino-Aprendizagem.
                </div>
            </div>
        </div>
    </footer>

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
    </style>
</body>
</html>

