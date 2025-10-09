<?php
require_once 'includes/session.php';

// Verificar se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$nome = $_SESSION['nome'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <title>Bem vindo professor!</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    
    <header class="header">
        <i class='bx bx-menu' id="menu-icon"></i>
    <nav class="nav-logo">
        <div class="logo">
            <img src="static/img/logo.png">
        </div>
    </nav>
        <nav class="navbar">
            <a href="INICIO.php" class="active">Inicio</a>
            <a href="criar_prova.php">Avaliações</a>
            <a href="gerenciar_materiais.php">Materiais Didáticos</a>
            <a href="admin_study_tracks.php">Trilhas de Estudo</a>
            <a href="teacher_reports.php">Relatórios do aluno</a>
            <a href="teacher_progress.php">Progresso dos Alunos</a>
            <a href="#interativas">Aulas interativas</a>
            <a href="usuarios_alunos.php">Usuários</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <section class="home" id="home">
        <div class="home-content">
                <h3>Oláá, Bem vindo professor!
                    <h1>Bem-vindo, <?php echo htmlspecialchars($nome); ?></h1></h3>
               
                <h3>Tenha novas experiencias com o <span>Edula!</span></h3>
                <p>Explore mais ferramentas que o Edula pode oferecer para melhorar o ensino-aprendizagem do professores e alunos!</p>
                <div class="social-media">
            </div>
        </div>
        <div class="home-img">
            <img src="static/img/antes2.svg">
        </div>
    </section>
    <section class="about" id="about">
        <div class="about-img">
            <img src="static/img/depois2.svg">
        </div>
        <div class="about-content">
            <h2 class="heading">Crie aqui suas <span>Avaliações</span></h2>
            <h3>Avaliações diagnósticas</h3>
            <p>Aqui você pode ver o nível de conhecimento dos seus alunos atravez de avaliações criadas por você mesmo caro professor, crie avaliações de determinadas matérias e envie diretamente para seus alunos!</p>
                <a href="criar_prova.php" class="btn">Por aqui</a>
        </div>
    </section>
    <section class="services" id="services">
        <h2 class="heading">Relatórios do <span>Aluno</span></h2>
        <div class="services-container">
            <div class="services-box">
                <i class='bx bx-code-alt'></i>
                <h3>Relatórios de português</h3>
                <p>Veja agora como seu aluno se saiu na avaliação diagnóstica de português feita pelo senhor professor. E o ajude a melhorar as notas   </p>
                <a href="#" class="btn">Por aqui</a>
            </div>
            <div class="services-box">
                <i class='bx bx-pencil'></i>
                <h3>Relatórios de matemática</h3>
                <p>Veja aqui como seu aluno foi por meio de um pequeno relatório disponibilizado pela IA do Edula, e busque melhorar o conhecimento do seu aluno.</p>
                <a href="#" class="btn">Por aqui</a>
            </div>
            <div class="services-box">
                <i class='bx bx-conversation'></i>
                <h3>Relatório de ciencias</h3>  
                <p>Veja por aqui atravez de um relatório como foi o desempenho do seu aluno em ciencias, o Edula gera relatórios por meio de uma IA.</p>
                <a href="#" class="btn">Por aqui</a>
            </div>
        </div>
    </section>
    <section class="portfolio" id="portfolio">
        <h2 class="heading">Aulas <span>Interativas</span></h2>
        <div class="portfolio-container">
            <div class="portfolio-box">
                <img src="static/img/aulas divertidas2.jpg" alt="">
                <div class="portfolio-layer">
                 
                    <h4>Aulas divertidas</h4>
                    <p>Com aulas divertidas podemos elencar a atenção, a memorização e imaginação que são de fundamental importância para o ensino de qualidade.</p>
                    <a href="https://www.youtube.com/watch?v=ctWwF_9CeCk"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
            <div class="portfolio-box">
                <img src="static/img/aulas des.jpg" alt="">
                <div class="portfolio-layer">
                    <h4>Aulas descomplicadas</h4>
                    <p>Com aulas descomplicadas os assuntos ensinados são bem mais captados e aproveitados.</p>
                    <a href="https://www.youtube.com/watch?v=6Th-RGQShjc&pp=ygUuZXhlbXBsb3MgZGUgYXVsYXMgZGVzY29tcGxpY2FkYXMgZW5zaW5vIG3DqWRpbw%3D%3D"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
            <div class="portfolio-box">
                <img src="static/img/aulas dinamicaas.jpg" alt="">
                <div class="portfolio-layer">
                    <h4>Aulas dinâmicas</h4>
                    <p>Além de atrair, engajar e prender a atenção do aluno, as aulas dinâmicas proporcionam uma maior participação do estudante no ambiente virtual e potencializam o aprendizado.</p>
                    <a href="https://www.youtube.com/watch?v=R02Ulz9MPDQ&pp=ygUuZXhlbXBsb3MgZGUgYXVsYXMgZGVzY29tcGxpY2FkYXMgZW5zaW5vIG3DqWRpbw%3D%3D"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
            <div class="portfolio-box">
                <img src="static/img/aulas práticas1.jpeg" alt="">
                <div class="portfolio-layer">
                    <h4>Aulas práticas</h4>
                    <p>Com aulas práticas, conseguirão ensinar conceitos mais abstratos, como a fisiologia de uma planta, as forças gravitacionais e as reações químicas.</p>
                    <a href="https://www.youtube.com/watch?v=Tlucee8TCK8&pp=ygUoZXhlbXBsb3MgZGUgYXVsYXMgcHJhdGljYXMgZW5zaW5vIG3DqWRpbw%3D%3D"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
            <div class="portfolio-box">
                <img src="static/img/aulas com apresentações.jpg" alt="">
                <div class="portfolio-layer">
                    <h4>Aula com apresentações</h4>
                    <p>a Aula expositiva pode ser um bom recurso de aprendizagem para momentos de: Apresentar informações ou conteúdo novo. Sistematizar conteúdos já trabalhados..</p>
                    <a href="https://www.youtube.com/watch?v=Ded0NZyK_TE&pp=ygUyZXhlbXBsb3MgZGUgYXVsYXMgY29tIGFwcmVzZW50YcOnb2VzIGVuc2lubyBtw6lkaW8%3D"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
            <div class="portfolio-box">
                <img src="static/img/aulas expositivas.jpg" alt="">
                <div class="portfolio-layer">
                    <h4>Aulas expositivas</h4>
                    <p>O propósito fundamental da aula expositiva é apresentar fatos, conceitos e generalizações por meio da ordenação verbal do professor. É mais adequada a alunos que tenham um repertório de conceitos e princípios básicos em uma área de conteúdo.</p>
                    <a href="https://www.youtube.com/watch?v=ZJYIIvEyHdU&pp=ygUrZXhlbXBsb3MgZGUgYXVsYXMgZXhwb3NpdGl2YXMgZW5zaW5vIG3DqWRpbw%3D%3D"><i class='bx bx-link-external'></i></a>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="footer-text">
            <p>Copyright &copy; 2024 Edula Ensino-Aprendizagem.</p>
        </div>

        <div class="footer-iconTop">
            <a href="#home"><i class='bx bx-up-arrow-alt'></i></a>
        </div>
    </footer>

    <script src="static/css/script.js"></script>

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

