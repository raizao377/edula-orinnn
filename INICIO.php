<?php
require_once 'includes/session.php';

$flash = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    <link rel="stylesheet" href="static/css/style inicio.css">
    <title>Seja bem vindo(a) ao Edula!</title>
</head>

<body>

    <nav>
        <div class="nav-logo">
            <img src="static/img/logo.png">
        </div>
    
        <ul class="nav-links">
            <li class="link"><a href="INICIO.php">Inicio</a></li>
            <li id="link1" class="link"><a href="area_user.php">Área do aluno</a></li>
            <li id="link2" class="link"><a href="area_professor.php">Área do professor</a></li>
            <li id="btlog" class="link"><a href="login.php">Login</a></li>   
            <div class="menu-container">
                <button class="menu-button" id="closer" onclick="toggleMenu()">Menu</button>
                <div class="menu-content" id="menuContent">
                    <a href="login.php">Login Aluno</a>
                    <a href="login.php">Login Professor</a>
                    <a href="cadastro_user.php">Cadastrar Aluno</a>
                    <a href="cadastro_professor.php">Cadastrar Professor</a>
                </div>
            </div>
        </ul>
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </nav>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <header class="container">
        <div class="content">
            <span class="blur"></span>
            <span class="blur"></span>
            <h4>CRIE AQUI SEU PROJETO</h4>
            <H1>Olá, <span>Bem vindo</span> ao Edula!</H1>
            <p>
                Vivemos em uma era em que a tecnologia desempenha um papel fundamental na educação. 
                O EDULA combina inovação tecnológica e excelência educacional, personalizando o 
                aprendizado para cada aluno.
            </p>
            <button class="btn" onclick="window.location.href='login.php'">Começar Agora</button>
        </div>
        <div class="image">
            <img src="static/img/web.svg">
        </div>
    </header>

    <section class="container">
        <h2 class="header">NOSSOS RECURSOS</h2>
        <div class="features">
            <div class="card">
                <span><i class="ri-money-dollar-box-line"></i></span>
                <h4>Avaliações Diagnósticas</h4>
                <p>
                    Teste seus conhecimentos com avaliações personalizadas criadas pelos seus professores 
                    e acompanhe seu progresso de aprendizagem.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="card">
                <span><i class="ri-shield-check-line"></i></span>
                <h4>Material de Apoio</h4>
                <p>
                    Acesse materiais de estudo e recursos educacionais para complementar seu aprendizado 
                    e melhorar seu desempenho escolar.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="card">
                <span><i class="ri-phone-line"></i></span>
                <h4>Consultar a IA</h4>
                <p>
                    Tire suas dúvidas com nossa inteligência artificial especializada em educação, 
                    disponível 24 horas para ajudar em seus estudos.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="card">
                <span><i class="ri-pie-chart-line"></i></span>
                <h4>Relatórios de Desempenho</h4>
                <p>
                    Professores podem acompanhar o progresso dos alunos através de relatórios detalhados 
                    e análises de desempenho.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="card">
                <span><i class="ri-shield-star-line"></i></span>
                <h4>Aulas Interativas</h4>
                <p>
                    Explore conteúdos interativos e dinâmicos que tornam o aprendizado mais envolvente 
                    e eficaz para todos os estudantes.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="card">
                <span><i class="ri-bug-line"></i></span>
                <h4>Suporte Técnico</h4>
                <p>
                    Nossa equipe está sempre disponível para ajudar com questões técnicas e 
                    garantir a melhor experiência na plataforma.
                </p>
                <a href="login.php">Acessar <i class="ri-arrow-right-line"></i></a>
            </div>
        </div>
    </section>

    <section class="container">
        <h2 class="header">O QUE NOSSOS USUÁRIOS DIZEM</h2>
        <div class="pricing">
            <div class="card">
                <div class="content">
                    <h4>Estudante</h4>
                    <h3>João Silva</h3>
                    <p>
                        <i class="ri-checkbox-circle-line"></i>
                        "O Edula transformou minha forma de estudar. As avaliações diagnósticas me ajudaram 
                        a identificar minhas dificuldades e focar nos pontos que precisava melhorar."
                    </p>
                </div>
                <button class="btn">Depoimento</button>
            </div>
            <div class="card">
                <div class="content">
                    <h4>Professora</h4>
                    <h3>Maria Santos</h3>
                    <p>
                        <i class="ri-checkbox-circle-line"></i>
                        "Como professora, o Edula me permite acompanhar o progresso de cada aluno 
                        individualmente e criar avaliações personalizadas para suas necessidades."
                    </p>
                </div>
                <button class="btn">Depoimento</button>
            </div>
            <div class="card">
                <div class="content">
                    <h4>Coordenador</h4>
                    <h3>Carlos Oliveira</h3>
                    <p>
                        <i class="ri-checkbox-circle-line"></i>
                        "A plataforma revolucionou nossa escola. Os relatórios detalhados nos ajudam 
                        a tomar decisões pedagógicas mais assertivas e melhorar nossos resultados."
                    </p>
                </div>
                <button class="btn">Depoimento</button>
            </div>
        </div>
    </section>

    <footer class="container">
        <span class="blur"></span>
        <span class="blur"></span>
        <div class="column">
            <div class="logo">
                <img src="static/img/logo.png">
            </div>
            <p>
                O Edula é uma plataforma educacional inovadora que combina tecnologia e pedagogia 
                para oferecer uma experiência de aprendizado personalizada e eficaz.
            </p>
            <div class="socials">
                <a href="#"><i class="ri-youtube-line"></i></a>
                <a href="#"><i class="ri-instagram-line"></i></a>
                <a href="#"><i class="ri-twitter-line"></i></a>
            </div>
        </div>
        <div class="column">
            <h4>Empresa</h4>
            <a href="#">Sobre Nós</a>
            <a href="#">Nossa Equipe</a>
            <a href="#">Carreiras</a>
            <a href="#">Contato</a>
        </div>
        <div class="column">
            <h4>Recursos</h4>
            <a href="#">Avaliações</a>
            <a href="#">Material de Apoio</a>
            <a href="#">IA Educacional</a>
            <a href="#">Relatórios</a>
        </div>
        <div class="column">
            <h4>Suporte</h4>
            <a href="#">Central de Ajuda</a>
            <a href="#">Tutoriais</a>
            <a href="#">FAQ</a>
            <a href="#">Contato</a>
        </div>
    </footer>

    <div class="copyright">
        Copyright © 2024 Edula. Todos os direitos reservados.
    </div>

    <script src="static/js/app.js"></script>
    
    <style>
        .flash-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 15px 20px;
            border-radius: 5px;
            text-align: center;
            min-width: 300px;
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
        
        .menu-container {
            position: relative;
            display: inline-block;
        }
        
        .menu-button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .menu-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
        }
        
        .menu-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .menu-content a:hover {
            background-color: #f1f1f1;
        }
        
        .menu-content.show {
            display: block;
        }
    </style>
    
    <script>
        function toggleMenu() {
            document.getElementById("menuContent").classList.toggle("show");
        }
        
        // Fechar menu se clicar fora dele
        window.onclick = function(event) {
            if (!event.target.matches('.menu-button')) {
                var dropdowns = document.getElementsByClassName("menu-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>

