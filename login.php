<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'models/User.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirectByRole();
}

$flash = getFlashMessage();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $matricula_cpf = $_POST['matricula_cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (!empty($tipo) && !empty($matricula_cpf) && !empty($senha)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        
        if ($user->login($matricula_cpf, $senha)) {
            // Verificar se o tipo selecionado corresponde ao role do usuário
            if ($user->role === $tipo) {
                loginUser($user->id, $user->nome, $user->matricula, $user->role);
                setFlashMessage("Bem-vindo, " . $user->nome . "!", 'success');
                redirectByRole();
            } else {
                setFlashMessage('Tipo de usuário incorreto!', 'danger');
            }
        } else {
            setFlashMessage('Matrícula/CPF ou senha incorretos!', 'danger');
        }
    } else {
        setFlashMessage('Por favor, preencha todos os campos!', 'warning');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login</title>
    <link rel="stylesheet" href="static/css/style.css" />
  </head>
  <body>
    <main>
      <div class="box">
        <div class="inner-box">
          <div class="forms-wrap">
            <!-- Login Form -->
            <form method="POST" class="sign-in-form">
              <div class="logo">
                <img src="static/img/logo.png" alt="easyclass" />
                <h4>EDULA</h4>
              </div>

              <div class="heading">
                <h2>Login</h2>
              </div>

              <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                  <?php echo htmlspecialchars($flash['message']); ?>
                </div>
              <?php endif; ?>

              <div class="actual-form">
                <div class="input-wrap">
                  <label for="tipo">Tipo de usuário:</label><br>
                  <select name="tipo" id="tipo">
                    <option value="user">Usuário</option>
                    <option value="professor">Professor</option>
                  </select><br><br>
                </div>

                <div class="input-wrap">
                  <label for="matricula_cpf">Matrícula ou CPF:</label><br>
                  <input
                    type="text"
                    id="matricula_cpf"
                    name="matricula_cpf"
                    required
                    class="input-field"
                  /><br><br>
                </div>

                <div class="input-wrap">
                  <label for="senha">Senha:</label><br>
                  <input
                    type="password"
                    id="senha"
                    name="senha"
                    required
                    class="input-field"
                  /><br><br>
                </div>
                <a href="cadastro_user.php">Cadastrar Usuário</a><br>
                <a href="cadastro_professor.php">Cadastrar Professor</a>

                <input type="submit" value="Entrar" class="sign-btn" />
              </div>
            </form>

            <!-- Registration Form -->
            <form action="index.html" autocomplete="off" class="sign-up-form">
              <div class="logo">
                <img src="static/img/logo.png" alt="easyclass" />
                <h4>TecAlchemy</h4>
              </div>

              <div class="heading">
                <h2>Faça seu Cadastro</h2>
                <h6>Fez seu Cadastro?</h6>
                <a href="#" class="toggle">Login</a>
              </div>

              <div class="actual-form">
                <div class="input-wrap">
                  <input
                    type="text"
                    minlength="4"
                    class="input-field"
                    autocomplete="off"
                    required
                  />
                  <label>Nome</label>
                </div>

                <div class="input-wrap">
                  <input
                    type="email"
                    class="input-field"
                    autocomplete="off"
                    required
                  />
                  <label>Email</label>
                </div>

                <div class="input-wrap">
                  <input
                    type="password"
                    minlength="4"
                    class="input-field"
                    autocomplete="off"
                    required
                  />
                  <label>Senha</label>
                </div>

                <input type="submit" value="Cadastro" class="sign-btn" />

                <p class="text">
                  Aceite os termos de serviço
                  <a href="#">Termos de serviço</a> e
                  <a href="#">Politica Privada</a>
                </p>
              </div>
            </form>
          </div>

          <div class="carousel">
            <div class="images-wrapper">
              <img src="static/img/image1.png" class="image img-1 show" alt="" />
              <img src="static/img/image2.png" class="image img-2" alt="" />
              <img src="static/img/image3.png" class="image img-3" alt="" />
            </div>

            <div class="text-slider">
              <div class="text-wrap">
                <div class="text-group">
                  <h2>Crie seu projetos</h2>
                  <h2>E colocaremos ele aqui!</h2>
                  <h2>Site Criado para ajuda-lo!</h2>
                </div>
              </div>

              <div class="bullets">
                <span class="active" data-value="1"></span>
                <span data-value="2"></span>
                <span data-value="3"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Javascript file -->
    <script src="static/js/app.js"></script>
    
    <style>
      .flash-message {
        margin-bottom: 15px;
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

