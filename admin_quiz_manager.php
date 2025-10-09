<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Conectar ao banco de dados
$database = new Database();
$pdo = $database->getConnection();

// Verificar se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$nome = $_SESSION['nome'];

// Verificar se o ID da atividade foi fornecido
if (!isset($_GET['activity_id']) || !is_numeric($_GET['activity_id'])) {
    setFlashMessage('Atividade não encontrada.', 'danger');
    header("Location: admin_study_tracks.php");
    exit();
}

$activity_id = (int)$_GET['activity_id'];

// Buscar informações da atividade
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.title, ta.description, ta.track_id, st.name as track_name
        FROM track_activities ta
        JOIN study_tracks st ON ta.track_id = st.id
        WHERE ta.id = ?
    ");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activity) {
        setFlashMessage('Atividade não encontrada.', 'danger');
        header("Location: admin_study_tracks.php");
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar atividade.', 'danger');
    header("Location: admin_study_tracks.php");
    exit();
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        // Adicionar nova questão
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_quizzes (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, question_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Buscar próximo order
            $stmt_order = $pdo->prepare("SELECT COALESCE(MAX(question_order), 0) + 1 as next_order FROM activity_quizzes WHERE activity_id = ?");
            $stmt_order->execute([$activity_id]);
            $next_order = $stmt_order->fetch(PDO::FETCH_ASSOC)['next_order'];
            
            $stmt->execute([
                $activity_id,
                $_POST['question'],
                $_POST['option_a'],
                $_POST['option_b'],
                $_POST['option_c'],
                $_POST['option_d'],
                $_POST['correct_answer'],
                $_POST['explanation'],
                $next_order
            ]);
            
            setFlashMessage('Questão adicionada com sucesso!', 'success');
        } catch (Exception $e) {
            setFlashMessage('Erro ao adicionar questão: ' . $e->getMessage(), 'danger');
        }
    } elseif (isset($_POST['edit_question'])) {
        // Editar questão
        try {
            $stmt = $pdo->prepare("
                UPDATE activity_quizzes 
                SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, explanation = ?
                WHERE id = ? AND activity_id = ?
            ");
            
            $stmt->execute([
                $_POST['question'],
                $_POST['option_a'],
                $_POST['option_b'],
                $_POST['option_c'],
                $_POST['option_d'],
                $_POST['correct_answer'],
                $_POST['explanation'],
                $_POST['question_id'],
                $activity_id
            ]);
            
            setFlashMessage('Questão atualizada com sucesso!', 'success');
        } catch (Exception $e) {
            setFlashMessage('Erro ao atualizar questão: ' . $e->getMessage(), 'danger');
        }
    } elseif (isset($_POST['delete_question'])) {
        // Deletar questão
        try {
            $stmt = $pdo->prepare("DELETE FROM activity_quizzes WHERE id = ? AND activity_id = ?");
            $stmt->execute([$_POST['question_id'], $activity_id]);
            
            setFlashMessage('Questão removida com sucesso!', 'success');
        } catch (Exception $e) {
            setFlashMessage('Erro ao remover questão: ' . $e->getMessage(), 'danger');
        }
    } elseif (isset($_POST['reorder_questions'])) {
        // Reordenar questões
        try {
            $questions_order = json_decode($_POST['questions_order'], true);
            
            foreach ($questions_order as $index => $question_id) {
                $stmt = $pdo->prepare("UPDATE activity_quizzes SET question_order = ? WHERE id = ? AND activity_id = ?");
                $stmt->execute([$index + 1, $question_id, $activity_id]);
            }
            
            setFlashMessage('Ordem das questões atualizada com sucesso!', 'success');
        } catch (Exception $e) {
            setFlashMessage('Erro ao reordenar questões: ' . $e->getMessage(), 'danger');
        }
    }
    
    header("Location: admin_quiz_manager.php?activity_id=" . $activity_id);
    exit();
}

// Buscar questões existentes
try {
    $stmt = $pdo->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, question_order
        FROM activity_quizzes 
        WHERE activity_id = ? 
        ORDER BY question_order
    ");
    $stmt->execute([$activity_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $questions = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Quiz - <?php echo htmlspecialchars($activity['title']); ?> | Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <style>
        .quiz-manager {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--second-bg-color);
            border-radius: 1rem;
            margin-top: 12rem;
        }

        .quiz-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .quiz-header h1 {
            color: var(--main-color);
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 1.4rem;
        }

        .breadcrumb a {
            color: var(--text-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--main-color);
        }

        .breadcrumb i {
            color: var(--main-color);
        }

        .quiz-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--bg-color);
            padding: 2rem;
            border-radius: 1rem;
            text-align: center;
            border: 2px solid var(--main-color);
        }

        .stat-card i {
            font-size: 3rem;
            color: var(--main-color);
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: var(--main-color);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--text-color);
            font-size: 1.4rem;
        }

        .add-question-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--main-color);
            color: var(--bg-color);
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .add-question-btn:hover {
            background: #00e67a;
            transform: translateY(-2px);
        }

        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .question-card {
            background: var(--bg-color);
            border: 2px solid var(--main-color);
            border-radius: 1rem;
            padding: 2rem;
            position: relative;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .question-number {
            background: var(--main-color);
            color: var(--bg-color);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
        }

        .question-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-edit, .btn-delete {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .question-content h3 {
            color: var(--text-color);
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--second-bg-color);
            border-radius: 0.5rem;
            border: 2px solid transparent;
        }

        .option.correct {
            border-color: var(--main-color);
            background: rgba(0, 255, 136, 0.1);
        }

        .option-letter {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--main-color);
            color: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }

        .option.correct .option-letter {
            background: #28a745;
        }

        .explanation {
            background: var(--second-bg-color);
            padding: 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--main-color);
        }

        .explanation strong {
            color: var(--main-color);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            background-color: var(--second-bg-color);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .modal-header h2 {
            color: var(--main-color);
            font-size: 2.4rem;
        }

        .close {
            color: var(--text-color);
            font-size: 3rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--main-color);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            color: var(--text-color);
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--main-color);
            border-radius: 0.5rem;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 1.4rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .options-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-primary, .btn-secondary {
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--main-color);
            color: var(--bg-color);
        }

        .btn-primary:hover {
            background: #00e67a;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .flash-message {
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 1.4rem;
        }

        .flash-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .flash-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .drag-handle {
            cursor: move;
            color: var(--main-color);
            font-size: 2rem;
            margin-right: 1rem;
        }

        .sortable-ghost {
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .quiz-manager {
                margin-top: 8rem;
                padding: 1rem;
            }

            .quiz-stats {
                grid-template-columns: 1fr;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }

            .options-form {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 2% auto;
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <i class='bx bx-menu' id="menu-icon"></i>
        <nav class="nav-logo">
            <div class="logo">
                <img src="static/img/logo.png" alt="Edula Logo">
            </div>
        </nav>
        <nav class="navbar">
            <a href="area_professor.php">Início</a>
            <a href="admin_study_tracks.php">Trilhas de Estudo</a>
            <a href="admin_track_activities.php?track_id=<?php echo $activity['track_id']; ?>">Atividades</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="quiz-manager">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="quiz-header">
            <div class="breadcrumb">
                <a href="admin_study_tracks.php">Trilhas de Estudo</a>
                <i class='bx bx-chevron-right'></i>
                <a href="admin_track_activities.php?track_id=<?php echo $activity['track_id']; ?>">
                    <?php echo htmlspecialchars($activity['track_name']); ?>
                </a>
                <i class='bx bx-chevron-right'></i>
                <span>Quiz: <?php echo htmlspecialchars($activity['title']); ?></span>
            </div>
            
            <h1>Gerenciar Quiz</h1>
            <p>Adicione, edite e organize as questões do quiz para esta atividade</p>
        </div>

        <div class="quiz-stats">
            <div class="stat-card">
                <i class='bx bx-question-mark'></i>
                <h3><?php echo count($questions); ?></h3>
                <p>Questões</p>
            </div>
            <div class="stat-card">
                <i class='bx bx-time'></i>
                <h3><?php echo count($questions) * 2; ?> min</h3>
                <p>Tempo Estimado</p>
            </div>
            <div class="stat-card">
                <i class='bx bx-check-circle'></i>
                <h3>60%</h3>
                <p>Nota Mínima</p>
            </div>
        </div>

        <button class="add-question-btn" onclick="openAddModal()">
            <i class='bx bx-plus'></i>
            Adicionar Questão
        </button>

        <div class="questions-list" id="questionsList">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card" data-question-id="<?php echo $question['id']; ?>">
                    <div class="question-header">
                        <div style="display: flex; align-items: center;">
                            <i class='bx bx-menu drag-handle'></i>
                            <span class="question-number">Questão <?php echo $index + 1; ?></span>
                        </div>
                        <div class="question-actions">
                            <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($question)); ?>)">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                            <button class="btn-delete" onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                <i class='bx bx-trash'></i> Excluir
                            </button>
                        </div>
                    </div>
                    
                    <div class="question-content">
                        <h3><?php echo htmlspecialchars($question['question']); ?></h3>
                        
                        <div class="options-grid">
                            <div class="option <?php echo $question['correct_answer'] === 'a' ? 'correct' : ''; ?>">
                                <span class="option-letter">A</span>
                                <span><?php echo htmlspecialchars($question['option_a']); ?></span>
                            </div>
                            <div class="option <?php echo $question['correct_answer'] === 'b' ? 'correct' : ''; ?>">
                                <span class="option-letter">B</span>
                                <span><?php echo htmlspecialchars($question['option_b']); ?></span>
                            </div>
                            <div class="option <?php echo $question['correct_answer'] === 'c' ? 'correct' : ''; ?>">
                                <span class="option-letter">C</span>
                                <span><?php echo htmlspecialchars($question['option_c']); ?></span>
                            </div>
                            <div class="option <?php echo $question['correct_answer'] === 'd' ? 'correct' : ''; ?>">
                                <span class="option-letter">D</span>
                                <span><?php echo htmlspecialchars($question['option_d']); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($question['explanation'])): ?>
                            <div class="explanation">
                                <strong>Explicação:</strong> <?php echo htmlspecialchars($question['explanation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal para Adicionar/Editar Questão -->
    <div id="questionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Questão</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form id="questionForm" method="POST">
                <input type="hidden" id="questionId" name="question_id">
                
                <div class="form-group">
                    <label for="question">Pergunta *</label>
                    <textarea id="question" name="question" required placeholder="Digite a pergunta..."></textarea>
                </div>
                
                <div class="options-form">
                    <div class="form-group">
                        <label for="option_a">Opção A *</label>
                        <input type="text" id="option_a" name="option_a" required placeholder="Primeira opção...">
                    </div>
                    
                    <div class="form-group">
                        <label for="option_b">Opção B *</label>
                        <input type="text" id="option_b" name="option_b" required placeholder="Segunda opção...">
                    </div>
                    
                    <div class="form-group">
                        <label for="option_c">Opção C *</label>
                        <input type="text" id="option_c" name="option_c" required placeholder="Terceira opção...">
                    </div>
                    
                    <div class="form-group">
                        <label for="option_d">Opção D *</label>
                        <input type="text" id="option_d" name="option_d" required placeholder="Quarta opção...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="correct_answer">Resposta Correta *</label>
                    <select id="correct_answer" name="correct_answer" required>
                        <option value="">Selecione a resposta correta</option>
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="explanation">Explicação (opcional)</label>
                    <textarea id="explanation" name="explanation" placeholder="Explique por que esta é a resposta correta..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Adicionar Questão</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        // Inicializar Sortable para reordenação
        const questionsList = document.getElementById('questionsList');
        const sortable = Sortable.create(questionsList, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                updateQuestionsOrder();
            }
        });

        function updateQuestionsOrder() {
            const questionCards = document.querySelectorAll('.question-card');
            const questionsOrder = Array.from(questionCards).map(card => 
                card.getAttribute('data-question-id')
            );
            
            // Enviar nova ordem para o servidor
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reorder_questions';
            input.value = '1';
            form.appendChild(input);
            
            const orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = 'questions_order';
            orderInput.value = JSON.stringify(questionsOrder);
            form.appendChild(orderInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Adicionar Questão';
            document.getElementById('submitBtn').textContent = 'Adicionar Questão';
            document.getElementById('questionForm').reset();
            document.getElementById('questionId').value = '';
            
            // Remover name de edit e adicionar name de add
            const form = document.getElementById('questionForm');
            const existingInput = form.querySelector('input[name="edit_question"]');
            if (existingInput) {
                existingInput.remove();
            }
            
            const addInput = document.createElement('input');
            addInput.type = 'hidden';
            addInput.name = 'add_question';
            addInput.value = '1';
            form.appendChild(addInput);
            
            document.getElementById('questionModal').style.display = 'block';
        }

        function openEditModal(question) {
            document.getElementById('modalTitle').textContent = 'Editar Questão';
            document.getElementById('submitBtn').textContent = 'Salvar Alterações';
            
            // Preencher formulário
            document.getElementById('questionId').value = question.id;
            document.getElementById('question').value = question.question;
            document.getElementById('option_a').value = question.option_a;
            document.getElementById('option_b').value = question.option_b;
            document.getElementById('option_c').value = question.option_c;
            document.getElementById('option_d').value = question.option_d;
            document.getElementById('correct_answer').value = question.correct_answer;
            document.getElementById('explanation').value = question.explanation || '';
            
            // Remover name de add e adicionar name de edit
            const form = document.getElementById('questionForm');
            const existingInput = form.querySelector('input[name="add_question"]');
            if (existingInput) {
                existingInput.remove();
            }
            
            const editInput = document.createElement('input');
            editInput.type = 'hidden';
            editInput.name = 'edit_question';
            editInput.value = '1';
            form.appendChild(editInput);
            
            document.getElementById('questionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('questionModal').style.display = 'none';
        }

        function deleteQuestion(questionId) {
            if (confirm('Tem certeza que deseja excluir esta questão? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'delete_question';
                input1.value = '1';
                form.appendChild(input1);
                
                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'question_id';
                input2.value = questionId;
                form.appendChild(input2);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('questionModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Menu mobile
        const menuIcon = document.getElementById('menu-icon');
        const navbar = document.querySelector('.navbar');

        menuIcon.addEventListener('click', () => {
            navbar.classList.toggle('active');
        });
    </script>
</body>
</html>


