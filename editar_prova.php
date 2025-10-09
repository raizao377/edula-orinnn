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
$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$prova_id) {
    setFlashMessage('Prova não encontrada.', 'danger');
    header("Location: criar_prova.php");
    exit();
}

// Verificar se a prova pertence ao professor
try {
    $stmt = $pdo->prepare("SELECT * FROM provas WHERE id = ? AND professor_id = ?");
    $stmt->execute([$prova_id, $professor_id]);
    $prova = $stmt->fetch();
    
    if (!$prova) {
        setFlashMessage('Prova não encontrada ou você não tem permissão para editá-la.', 'danger');
        header("Location: criar_prova.php");
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('Erro ao carregar prova: ' . $e->getMessage(), 'danger');
    header("Location: criar_prova.php");
    exit();
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adicionar_questao') {
        $questao = trim($_POST['questao']);
        $opcao_a = trim($_POST['opcao_a']);
        $opcao_b = trim($_POST['opcao_b']);
        $opcao_c = trim($_POST['opcao_c']);
        $opcao_d = trim($_POST['opcao_d']);
        $resposta_correta = $_POST['resposta_correta'];
        $pontuacao = floatval($_POST['pontuacao']);
        
        if (!empty($questao) && !empty($opcao_a) && !empty($opcao_b) && !empty($opcao_c) && !empty($opcao_d) && in_array($resposta_correta, ['a', 'b', 'c', 'd'])) {
            try {
                // Obter próxima ordem
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem FROM prova_questoes WHERE prova_id = ?");
                $stmt->execute([$prova_id]);
                $proxima_ordem = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("INSERT INTO prova_questoes (prova_id, questao, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta, pontuacao, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$prova_id, $questao, $opcao_a, $opcao_b, $opcao_c, $opcao_d, $resposta_correta, $pontuacao, $proxima_ordem]);
                
                setFlashMessage('Questão adicionada com sucesso!', 'success');
            } catch (PDOException $e) {
                setFlashMessage('Erro ao adicionar questão: ' . $e->getMessage(), 'danger');
            }
        } else {
            setFlashMessage('Todos os campos são obrigatórios e uma resposta correta deve ser selecionada.', 'warning');
        }
    }
    
    if ($_POST['action'] === 'excluir_questao') {
        $questao_id = intval($_POST['questao_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM prova_questoes WHERE id = ? AND prova_id = ?");
            $stmt->execute([$questao_id, $prova_id]);
            setFlashMessage('Questão excluída com sucesso!', 'success');
        } catch (PDOException $e) {
            setFlashMessage('Erro ao excluir questão: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($_POST['action'] === 'atualizar_prova') {
        $titulo = trim($_POST['titulo']);
        $descricao = trim($_POST['descricao']);
        $materia = trim($_POST['materia']);
        $tipo = $_POST['tipo'];
        $tempo_limite = intval($_POST['tempo_limite']);
        
        if (!empty($titulo) && !empty($materia)) {
            try {
                $stmt = $pdo->prepare("UPDATE provas SET titulo = ?, descricao = ?, materia = ?, tipo = ?, tempo_limite = ?, updated_at = NOW() WHERE id = ? AND professor_id = ?");
                $stmt->execute([$titulo, $descricao, $materia, $tipo, $tempo_limite, $prova_id, $professor_id]);
                
                setFlashMessage('Prova atualizada com sucesso!', 'success');
                
                // Recarregar dados da prova
                $stmt = $pdo->prepare("SELECT * FROM provas WHERE id = ? AND professor_id = ?");
                $stmt->execute([$prova_id, $professor_id]);
                $prova = $stmt->fetch();
            } catch (PDOException $e) {
                setFlashMessage('Erro ao atualizar prova: ' . $e->getMessage(), 'danger');
            }
        } else {
            setFlashMessage('Título e matéria são obrigatórios.', 'warning');
        }
    }
}

// Buscar questões da prova
try {
    $stmt = $pdo->prepare("SELECT * FROM prova_questoes WHERE prova_id = ? ORDER BY ordem ASC");
    $stmt->execute([$prova_id]);
    $questoes = $stmt->fetchAll();
} catch (PDOException $e) {
    $questoes = [];
    setFlashMessage('Erro ao carregar questões: ' . $e->getMessage(), 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Prova - <?php echo htmlspecialchars($prova['titulo']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .prova-header {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .prova-header h1 {
            margin: 0 0 10px 0;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: bold;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #0ef;
            border-bottom-color: #0ef;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0ef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        .btn-danger:hover {
            transform: translateY(-1px);
        }
        
        .questao-card {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 4px solid #0ef;
        }
        
        .questao-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .questao-numero {
            background: #0ef;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .questao-texto {
            flex: 1;
            font-weight: bold;
            color: #333;
            margin-right: 15px;
        }
        
        .opcoes {
            margin: 15px 0;
            padding-left: 45px;
        }
        
        .opcao {
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        
        .opcao.correta {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        
        .opcao-letra {
            font-weight: bold;
            margin-right: 10px;
            width: 20px;
        }
        
        .questao-info {
            padding-left: 45px;
            font-size: 14px;
            color: #666;
        }
        
        .flash-message {
            margin: 15px 0;
            padding: 15px;
            border-radius: 8px;
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
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option input[type="radio"] {
            width: auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #0ef;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0ef;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
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
            <a href="criar_prova.php">Voltar</a>
            <a href="area_professor.php">Área do Professor</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="prova-header">
            <h1><?php echo htmlspecialchars($prova['titulo']); ?></h1>
            <p><strong>Matéria:</strong> <?php echo htmlspecialchars($prova['materia']); ?> | 
               <strong>Tipo:</strong> <?php echo ucfirst($prova['tipo']); ?> | 
               <strong>Tempo:</strong> <?php echo $prova['tempo_limite'] > 0 ? $prova['tempo_limite'] . ' min' : 'Sem limite'; ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($questoes); ?></div>
                <div class="stat-label">Questões</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($questoes, 'pontuacao')); ?></div>
                <div class="stat-label">Pontos Totais</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $prova['ativa'] ? 'Ativa' : 'Inativa'; ?></div>
                <div class="stat-label">Status</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('questoes')">
                <i class='bx bx-list-ul'></i> Questões
            </button>
            <button class="tab" onclick="showTab('adicionar')">
                <i class='bx bx-plus'></i> Adicionar Questão
            </button>
            <button class="tab" onclick="showTab('configuracoes')">
                <i class='bx bx-cog'></i> Configurações
            </button>
        </div>

        <!-- Tab: Questões -->
        <div id="questoes" class="tab-content active">
            <h2>Questões da Prova</h2>
            
            <?php if (empty($questoes)): ?>
                <p>Esta prova ainda não possui questões. Use a aba "Adicionar Questão" para começar.</p>
            <?php else: ?>
                <?php foreach ($questoes as $index => $questao): ?>
                    <div class="questao-card">
                        <div class="questao-header">
                            <div class="questao-numero"><?php echo $index + 1; ?></div>
                            <div class="questao-texto"><?php echo htmlspecialchars($questao['questao']); ?></div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta questão?')">
                                <input type="hidden" name="action" value="excluir_questao">
                                <input type="hidden" name="questao_id" value="<?php echo $questao['id']; ?>">
                                <button type="submit" class="btn-danger">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </form>
                        </div>
                        
                        <div class="opcoes">
                            <div class="opcao <?php echo $questao['resposta_correta'] === 'a' ? 'correta' : ''; ?>">
                                <span class="opcao-letra">A)</span>
                                <span><?php echo htmlspecialchars($questao['opcao_a']); ?></span>
                            </div>
                            <div class="opcao <?php echo $questao['resposta_correta'] === 'b' ? 'correta' : ''; ?>">
                                <span class="opcao-letra">B)</span>
                                <span><?php echo htmlspecialchars($questao['opcao_b']); ?></span>
                            </div>
                            <div class="opcao <?php echo $questao['resposta_correta'] === 'c' ? 'correta' : ''; ?>">
                                <span class="opcao-letra">C)</span>
                                <span><?php echo htmlspecialchars($questao['opcao_c']); ?></span>
                            </div>
                            <div class="opcao <?php echo $questao['resposta_correta'] === 'd' ? 'correta' : ''; ?>">
                                <span class="opcao-letra">D)</span>
                                <span><?php echo htmlspecialchars($questao['opcao_d']); ?></span>
                            </div>
                        </div>
                        
                        <div class="questao-info">
                            <strong>Pontuação:</strong> <?php echo $questao['pontuacao']; ?> pontos
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tab: Adicionar Questão -->
        <div id="adicionar" class="tab-content">
            <h2>Adicionar Nova Questão</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="adicionar_questao">
                
                <div class="form-group">
                    <label for="questao">Pergunta *</label>
                    <textarea id="questao" name="questao" rows="3" required placeholder="Digite a pergunta aqui..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="opcao_a">Opção A *</label>
                    <input type="text" id="opcao_a" name="opcao_a" required placeholder="Primeira opção de resposta">
                </div>
                
                <div class="form-group">
                    <label for="opcao_b">Opção B *</label>
                    <input type="text" id="opcao_b" name="opcao_b" required placeholder="Segunda opção de resposta">
                </div>
                
                <div class="form-group">
                    <label for="opcao_c">Opção C *</label>
                    <input type="text" id="opcao_c" name="opcao_c" required placeholder="Terceira opção de resposta">
                </div>
                
                <div class="form-group">
                    <label for="opcao_d">Opção D *</label>
                    <input type="text" id="opcao_d" name="opcao_d" required placeholder="Quarta opção de resposta">
                </div>
                
                <div class="form-group">
                    <label>Resposta Correta *</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="resp_a" name="resposta_correta" value="a" required>
                            <label for="resp_a">Opção A</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="resp_b" name="resposta_correta" value="b">
                            <label for="resp_b">Opção B</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="resp_c" name="resposta_correta" value="c">
                            <label for="resp_c">Opção C</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="resp_d" name="resposta_correta" value="d">
                            <label for="resp_d">Opção D</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pontuacao">Pontuação</label>
                    <input type="number" id="pontuacao" name="pontuacao" min="0.1" max="10" step="0.1" value="1.0" placeholder="1.0">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class='bx bx-plus'></i> Adicionar Questão
                </button>
            </form>
        </div>

        <!-- Tab: Configurações -->
        <div id="configuracoes" class="tab-content">
            <h2>Configurações da Prova</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="atualizar_prova">
                
                <div class="form-group">
                    <label for="titulo_edit">Título da Prova *</label>
                    <input type="text" id="titulo_edit" name="titulo" value="<?php echo htmlspecialchars($prova['titulo']); ?>" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="descricao_edit">Descrição</label>
                    <textarea id="descricao_edit" name="descricao" rows="3"><?php echo htmlspecialchars($prova['descricao']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="materia_edit">Matéria *</label>
                        <select id="materia_edit" name="materia" required>
                            <option value="">Selecione a matéria</option>
                            <?php 
                            $materias = ['Matemática', 'Português', 'Ciências', 'História', 'Geografia', 'Inglês', 'Educação Física', 'Artes', 'Programação', 'Outras'];
                            foreach ($materias as $materia): 
                            ?>
                                <option value="<?php echo $materia; ?>" <?php echo $prova['materia'] === $materia ? 'selected' : ''; ?>>
                                    <?php echo $materia; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_edit">Tipo de Prova *</label>
                        <select id="tipo_edit" name="tipo" required>
                            <option value="diagnostica" <?php echo $prova['tipo'] === 'diagnostica' ? 'selected' : ''; ?>>Diagnóstica</option>
                            <option value="avaliativa" <?php echo $prova['tipo'] === 'avaliativa' ? 'selected' : ''; ?>>Avaliativa</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="tempo_limite_edit">Tempo Limite (minutos)</label>
                    <input type="number" id="tempo_limite_edit" name="tempo_limite" min="0" max="180" value="<?php echo $prova['tempo_limite']; ?>">
                    <small>Deixe 0 para sem limite de tempo</small>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class='bx bx-save'></i> Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todas as abas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remover classe active de todos os botões
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar aba selecionada
            document.getElementById(tabName).classList.add('active');
            
            // Adicionar classe active ao botão clicado
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

