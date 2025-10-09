<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'models/User.php';

$flash = getFlashMessage();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (!empty($nome) && !empty($cpf) && !empty($senha)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $user = new User($db);
        
        // Verificar se CPF já existe
        if ($user->matriculaExists($cpf)) {
            setFlashMessage('Este CPF já está cadastrado!', 'danger');
        } else {
            // Criar novo professor
            $user->nome = $nome;
            $user->matricula = $cpf; // Usando CPF como matrícula para professores
            $user->password = $senha;
            $user->role = 'professor';
            
            if ($user->create()) {
                setFlashMessage('Professor cadastrado com sucesso! Faça login.', 'success');
                header("Location: login.php");
                exit();
            } else {
                setFlashMessage('Erro ao cadastrar professor. Tente novamente.', 'danger');
            }
        }
    } else {
        setFlashMessage('Por favor, preencha todos os campos!', 'warning');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Professor</title>
    <link rel="stylesheet" href="static/css/cadastro.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="logo">
                <img src="static/img/logo.png" alt="Edula">
                <h4>EDULA</h4>
            </div>
            
            <h2>Cadastro de Professor</h2>
            
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="cadastro-form">
                <div class="input-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" required class="input-field">
                </div>

                <div class="input-group">
                    <label for="cpf">CPF:</label>
                    <input type="text" id="cpf" name="cpf" required class="input-field" placeholder="000.000.000-00">
                </div>

                <div class="input-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required class="input-field">
                </div>

                <button type="submit" class="btn-submit">Cadastrar Professor</button>
            </form>
            
            <div class="links">
                <a href="login.php">Já tem conta? Faça login</a>
                <a href="cadastro_user.php">Cadastrar como Usuário</a>
            </div>
        </div>
    </div>

    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            font-family: 'Arial', sans-serif;
        }
        
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
        }
        
        .logo h4 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        .input-field {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #764ba2;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            display: block;
            margin: 10px 0;
            color: #764ba2;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .flash-message {
            margin-bottom: 20px;
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

