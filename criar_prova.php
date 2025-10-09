<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Verificar se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$professor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'criar_prova') {
        $titulo = trim($_POST['titulo']);
        $descricao = trim($_POST['descricao']);
        $materia = trim($_POST['materia']);
        $tipo = $_POST['tipo'];
        $tempo_limite = intval($_POST['tempo_limite']);
        $ativa = isset($_POST['ativa']) ? 1 : 0;
        $turma = !empty($_POST['turma']) ? trim($_POST['turma']) : NULL;
        $tentativas = intval($_POST['tentativas']) > 0 ? intval($_POST['tentativas']) : 1;

        if (!empty($titulo) && !empty($materia)) {
            try {
                // Inserir prova
                $stmt = $pdo->prepare("INSERT INTO provas (titulo, descricao, materia, tipo, tempo_limite, professor_id, ativa, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$titulo, $descricao, $materia, $tipo, $tempo_limite, $professor_id, $ativa]);

                $prova_id = $pdo->lastInsertId();

                // Inserir disponibilidade
                $stmt2 = $pdo->prepare("INSERT INTO prova_disponibilidade (prova_id, aluno_id, turma, tentativas_permitidas, data_inicio, data_fim, created_at) VALUES (?, NULL, ?, ?, NOW(), NULL, NOW())");
                $stmt2->execute([$prova_id, $turma, $tentativas]);

                setFlashMessage('Prova criada com sucesso! Agora adicione as questões.', 'success');
                header("Location: editar_prova.php?id=" . $prova_id);
                exit();
            } catch (PDOException $e) {
                setFlashMessage('Erro ao criar prova: ' . $e->getMessage(), 'danger');
            }
        } else {
            setFlashMessage('Título e matéria são obrigatórios.', 'warning');
        }
    }
}

// Buscar provas do professor
try {
    $stmt = $pdo->prepare("SELECT * FROM provas WHERE professor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$professor_id]);
    $provas = $stmt->fetchAll();
} catch (PDOException $e) {
    $provas = [];
    setFlashMessage('Erro ao carregar provas: ' . $e->getMessage(), 'danger');
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Provas - Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <style>
        /* ===== Reset básico ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ===== Layout ===== */
body {
    background: #f0f2f5;
    color: #333;
    line-height: 1.6;
}

/* ===== Header ===== */
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

/* ===== Container Principal ===== */
.container {
    max-width: 900px;
    margin: 30px auto;
    padding: 25px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

/* ===== Títulos ===== */
h1, h2 {
    margin-bottom: 20px;
    font-weight: bold;
    color: #222;
}

/* ===== Formulário ===== */
form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 15px;
    color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    max-width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    line-height: 1.4;
    transition: border-color 0.3s, box-shadow 0.3s;
    display: block;
    background: #fafafa;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #0ef;
    box-shadow: 0 0 6px rgba(0, 238, 255, 0.5);
    background: #fff;
}

small {
    font-size: 13px;
    color: #666;
}

/* ===== Botões ===== */
.btn-primary {
    background: linear-gradient(45deg, #0ef, #00d4ff);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: transform 0.2s, box-shadow 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 212, 255, 0.4);
}

/* Botões pequenos */
.btn-small {
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    transition: transform 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-small:hover {
    transform: translateY(-1px);
}

.btn-edit {
    background: #28a745;
    color: white;
}

.btn-view {
    background: #17a2b8;
    color: white;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

/* ===== Flash Messages ===== */
.flash-message {
    margin: 15px 0;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
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

/* ===== Lista de Provas ===== */
.provas-list {
    margin-top: 40px;
}

.prova-card {
    background: #f8f9fa;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 10px;
    border-left: 4px solid #0ef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.prova-info h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.prova-info p {
    margin: 5px 0;
    color: #666;
}

.prova-actions {
    display: flex;
    gap: 10px;
}

/* ===== Badges de tipo de prova ===== */
.tipo-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.tipo-diagnostica {
    background: #e3f2fd;
    color: #1976d2;
}

.tipo-avaliativa {
    background: #f3e5f5;
    color: #7b1fa2;
}

/* ===== Responsividade ===== */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }

    .prova-card {
        flex-direction: column;
        align-items: flex-start;
    }

    .btn-primary {
        width: 100%;
        justify-content: center;
    }

    .btn-small {
        flex: 1;
        justify-content: center;
    }
}

    </style>
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
            <a href="area_professor.php">Voltar</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <h1>Criar Nova Prova</h1>
        
       <form method="POST" action="">
    <input type="hidden" name="action" value="criar_prova">

    <div class="form-group">
        <label for="titulo">Título da Prova *</label>
        <input type="text" id="titulo" name="titulo" required maxlength="255">
    </div>

    <div class="form-group">
        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="3"></textarea>
    </div>

    <div class="form-group">
        <label for="materia">Matéria *</label>
        <select id="materia" name="materia" required>
            <option value="">Selecione a matéria</option>
            <option value="Matemática">Matemática</option>
            <option value="Português">Português</option>
            <option value="Ciências">Ciências</option>
            <option value="História">História</option>
            <option value="Geografia">Geografia</option>
            <option value="Inglês">Inglês</option>
            <option value="Educação Física">Educação Física</option>
            <option value="Artes">Artes</option>
            <option value="Programação">Programação</option>
            <option value="Outras">Outras</option>
        </select>
    </div>

    <div class="form-group">
        <label for="tipo">Tipo de Prova *</label>
        <select id="tipo" name="tipo" required>
            <option value="diagnostica">Diagnóstica</option>
            <option value="avaliativa">Avaliativa</option>
        </select>
    </div>

    <div class="form-group">
        <label for="tempo_limite">Tempo Limite (minutos)</label>
        <input type="number" id="tempo_limite" name="tempo_limite" min="5" max="180" value="60">
        <small>Deixe 0 para sem limite de tempo</small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativa" checked> Prova Ativa
        </label>
    </div>

    <div class="form-group">
        <label for="turma">Disponível para turma (deixe em branco = todos)</label>
        <input type="text" id="turma" name="turma">
    </div>

    <div class="form-group">
        <label for="tentativas">Tentativas Permitidas</label>
        <input type="number" id="tentativas" name="tentativas" min="1" max="10" value="1">
    </div>

    <button type="submit" class="btn-primary">Criar Prova</button>
</form>


        <div class="provas-list">
            <h2>Minhas Provas</h2>
            
            <?php if (empty($provas)): ?>
                <p>Você ainda não criou nenhuma prova. Crie sua primeira prova acima!</p>
            <?php else: ?>
                <?php foreach ($provas as $prova): ?>
                    <div class="prova-card">
                        <div class="prova-info">
                            <h3><?php echo htmlspecialchars($prova['titulo']); ?></h3>
                            <p><strong>Matéria:</strong> <?php echo htmlspecialchars($prova['materia']); ?></p>
                            <p><strong>Tipo:</strong> 
                                <span class="tipo-badge tipo-<?php echo $prova['tipo']; ?>">
                                    <?php echo ucfirst($prova['tipo']); ?>
                                </span>
                            </p>
                            <p><strong>Tempo:</strong> <?php echo $prova['tempo_limite'] > 0 ? $prova['tempo_limite'] . ' min' : 'Sem limite'; ?></p>
                            <p><strong>Criada em:</strong> <?php echo date('d/m/Y H:i', strtotime($prova['created_at'])); ?></p>
                        </div>
                        
                        <div class="prova-actions">
                            <a href="editar_prova.php?id=<?php echo $prova['id']; ?>" class="btn-small btn-edit">
                                <i class='bx bx-edit'></i> Editar
                            </a>
                            <a href="visualizar_prova.php?id=<?php echo $prova['id']; ?>" class="btn-small btn-view">
                                <i class='bx bx-show'></i> Visualizar
                            </a>
                            <a href="resultados_prova.php?id=<?php echo $prova['id']; ?>" class="btn-small btn-view">
                                <i class='bx bx-bar-chart'></i> Resultados
                            </a>
                            <button onclick="confirmarExclusao(<?php echo $prova['id']; ?>)" class="btn-small btn-delete">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarExclusao(provaId) {
            if (confirm('Tem certeza que deseja excluir esta prova? Esta ação não pode ser desfeita.')) {
                window.location.href = 'excluir_prova.php?id=' + provaId;
            }
        }
    </script>
</body>
</html>

