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

// Verificar se o ID da atividade foi fornecido
if (!isset($_GET['activity_id']) || !is_numeric($_GET['activity_id'])) {
    setFlashMessage('Atividade não encontrada.', 'danger');
    header("Location: study_tracks.php");
    exit();
}

$activity_id = (int)$_GET['activity_id'];

// Buscar informações da atividade
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.title, ta.track_id, st.name as track_name
        FROM track_activities ta
        JOIN study_tracks st ON ta.track_id = st.id
        WHERE ta.id = ?
    ");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activity) {
        setFlashMessage('Atividade não encontrada.', 'danger');
        header("Location: study_tracks.php");
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar atividade.', 'danger');
    header("Location: study_tracks.php");
    exit();
}

// Verificar se já fez o quiz
try {
    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$user_id, $activity_id]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_attempt) {
        setFlashMessage('Você já completou este quiz!', 'info');
        header("Location: activity.php?id=" . $activity_id);
        exit();
    }
} catch (Exception $e) {
    // Continue se houver erro
}

// Processar envio do quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    try {
        // Buscar questões do quiz
        $stmt = $pdo->prepare("
            SELECT id, correct_answer 
            FROM activity_quizzes 
            WHERE activity_id = ? 
            ORDER BY question_order
        ");
        $stmt->execute([$activity_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $score = 0;
        $total_questions = count($questions);
        
        // Calcular pontuação
        foreach ($questions as $question) {
            $answer_key = 'question_' . $question['id'];
            if (isset($_POST[$answer_key]) && $_POST[$answer_key] === $question['correct_answer']) {
                $score++;
            }
        }
        
        // Salvar tentativa
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, activity_id, score, total_questions) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity_id, $score, $total_questions]);
        
        // Se passou (60% ou mais), marcar atividade como concluída
        $percentage = ($score / $total_questions) * 100;
        if ($percentage >= 60) {
            $stmt = $pdo->prepare("
                INSERT INTO user_progress (user_id, activity_id, status, completed_at) 
                VALUES (?, ?, 'completed', NOW())
                ON DUPLICATE KEY UPDATE 
                status = 'completed', 
                completed_at = NOW()
            ");
            $stmt->execute([$user_id, $activity_id]);
            
            setFlashMessage("Parabéns! Você acertou $score de $total_questions questões (" . round($percentage) . "%). Atividade concluída!", 'success');
        } else {
            setFlashMessage("Você acertou $score de $total_questions questões (" . round($percentage) . "%). Precisa de pelo menos 60% para passar. Estude mais e tente novamente!", 'warning');
        }
        
        header("Location: activity.php?id=" . $activity_id);
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('Erro ao processar quiz.', 'danger');
    }
}

// Buscar questões do quiz
try {
    $stmt = $pdo->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, question_order
        FROM activity_quizzes 
        WHERE activity_id = ? 
        ORDER BY question_order
    ");
    $stmt->execute([$activity_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questions)) {
        setFlashMessage('Quiz não disponível para esta atividade.', 'warning');
        header("Location: activity.php?id=" . $activity_id);
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar quiz.', 'danger');
    header("Location: activity.php?id=" . $activity_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - <?php echo htmlspecialchars($activity['title']); ?> | Edula</title>
    <link rel="stylesheet" href="static/css/style.css">
    <link rel="stylesheet" href="static/css/quiz.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="static/img/logo.png" alt="Edula">
                <span>Edula</span>
            </div>
            <div class="nav-menu">
                <a href="area_user.php" class="nav-link">
                    <i class='bx bx-home'></i> Início
                </a>
                <a href="study_tracks.php" class="nav-link">
                    <i class='bx bx-book'></i> Trilhas
                </a>
                <a href="logout.php" class="nav-link">
                    <i class='bx bx-log-out'></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="quiz-header">
            <div class="breadcrumb">
                <a href="study_tracks.php">Trilhas de Estudo</a>
                <span>/</span>
                <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>">
                    <?php echo htmlspecialchars($activity['track_name']); ?>
                </a>
                <span>/</span>
                <span>Quiz</span>
            </div>
            
            <h1><?php echo htmlspecialchars($activity['title']); ?></h1>
            <p class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></p>
        </div>

        <?php if ($already_answered): ?>
            <div class="quiz-completed">
                <div class="success-message">
                    <i class='bx bx-check-circle'></i>
                    <h2>Quiz já respondido!</h2>
                    <p>Você já completou este quiz. Confira seus resultados na área de progresso.</p>
                    <div class="action-buttons">
                        <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>" class="btn btn-primary">
                            Voltar às Atividades
                        </a>
                        <a href="quiz_results.php?activity_id=<?php echo $activity_id; ?>" class="btn btn-secondary">
                            Ver Resultados
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif (empty($quiz_questions)): ?>
            <div class="no-quiz">
                <div class="info-message">
                    <i class='bx bx-info-circle'></i>
                    <h2>Quiz não disponível</h2>
                    <p>Este conteúdo ainda não possui um quiz associado.</p>
                    <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>" class="btn btn-primary">
                        Voltar às Atividades
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="quiz-container">
                <div class="quiz-info">
                    <div class="quiz-stats">
                        <div class="stat">
                            <i class='bx bx-question-mark'></i>
                            <span><?php echo count($quiz_questions); ?> questões</span>
                        </div>
                        <div class="stat">
                            <i class='bx bx-time'></i>
                            <span>Sem limite de tempo</span>
                        </div>
                        <div class="stat">
                            <i class='bx bx-target-lock'></i>
                            <span>Mínimo 60% para aprovação</span>
                        </div>
                    </div>
                </div>

                <form id="quizForm" action="process_quiz.php" method="POST">
                    <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
                    
                    <?php foreach ($quiz_questions as $index => $question): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <span class="question-number">Questão <?php echo $index + 1; ?></span>
                            </div>
                            
                            <div class="question-content">
                                <h3><?php echo htmlspecialchars($question['question']); ?></h3>
                                
                                <div class="options">
                                    <label class="option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="a" required>
                                        <span class="option-letter">A</span>
                                        <span class="option-text"><?php echo htmlspecialchars($question['option_a']); ?></span>
                                    </label>
                                    
                                    <label class="option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="b" required>
                                        <span class="option-letter">B</span>
                                        <span class="option-text"><?php echo htmlspecialchars($question['option_b']); ?></span>
                                    </label>
                                    
                                    <label class="option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="c" required>
                                        <span class="option-letter">C</span>
                                        <span class="option-text"><?php echo htmlspecialchars($question['option_c']); ?></span>
                                    </label>
                                    
                                    <label class="option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="d" required>
                                        <span class="option-letter">D</span>
                                        <span class="option-text"><?php echo htmlspecialchars($question['option_d']); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="quiz-actions">
                        <button type="button" class="btn btn-secondary" onclick="history.back()">
                            <i class='bx bx-arrow-back'></i> Voltar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-check'></i> Finalizar Quiz
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Confirmação antes de enviar o quiz
        document.getElementById('quizForm')?.addEventListener('submit', function(e) {
            if (!confirm('Tem certeza que deseja finalizar o quiz? Não será possível alterar as respostas depois.')) {
                e.preventDefault();
            }
        });

        // Salvar progresso localmente (opcional)
        const form = document.getElementById('quizForm');
        if (form) {
            const inputs = form.querySelectorAll('input[type="radio"]');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    localStorage.setItem('quiz_' + <?php echo $activity_id; ?> + '_' + this.name, this.value);
                });
            });

            // Restaurar respostas salvas
            inputs.forEach(input => {
                const saved = localStorage.getItem('quiz_' + <?php echo $activity_id; ?> + '_' + input.name);
                if (saved && input.value === saved) {
                    input.checked = true;
                }
            });
        }
    </script>
</body>
</html>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <title>Quiz: <?php echo htmlspecialchars($activity['title']); ?> - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Quiz</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Teste seus conhecimentos!</p>
        <ul>
            <li><a href="activity.php?id=<?php echo $activity_id; ?>">Voltar à Atividade</a></li>
            <li><a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>">Voltar à Trilha</a></li>
            <li><a href="study_tracks.php">Trilhas de Estudo</a></li>
            <li><a href="area_user.php">Área do Usuário</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="quiz-container">
        <div class="quiz-header">
            <div class="breadcrumb">
                <a href="study_tracks.php">Trilhas</a>
                <i class='bx bx-chevron-right'></i>
                <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>"><?php echo htmlspecialchars($activity['track_name']); ?></a>
                <i class='bx bx-chevron-right'></i>
                <a href="activity.php?id=<?php echo $activity_id; ?>"><?php echo htmlspecialchars($activity['title']); ?></a>
                <i class='bx bx-chevron-right'></i>
                <span>Quiz</span>
            </div>
            
            <h1>Quiz: <?php echo htmlspecialchars($activity['title']); ?></h1>
            <p class="quiz-instructions">
                <i class='bx bx-info-circle'></i>
                Responda todas as questões e clique em "Enviar Respostas". Você precisa de pelo menos 60% de acertos para passar.
            </p>
        </div>

        <form method="POST" class="quiz-form">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="question-header">
                        <span class="question-number">Questão <?php echo $index + 1; ?></span>
                        <span class="question-total">de <?php echo count($questions); ?></span>
                    </div>
                    
                    <div class="question-text">
                        <?php echo nl2br(htmlspecialchars($question['question'])); ?>
                    </div>
                    
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="a" required>
                            <span class="option-letter">A)</span>
                            <span class="option-text"><?php echo htmlspecialchars($question['option_a']); ?></span>
                        </label>
                        
                        <label class="option">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="b" required>
                            <span class="option-letter">B)</span>
                            <span class="option-text"><?php echo htmlspecialchars($question['option_b']); ?></span>
                        </label>
                        
                        <label class="option">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="c" required>
                            <span class="option-letter">C)</span>
                            <span class="option-text"><?php echo htmlspecialchars($question['option_c']); ?></span>
                        </label>
                        
                        <label class="option">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="d" required>
                            <span class="option-letter">D)</span>
                            <span class="option-text"><?php echo htmlspecialchars($question['option_d']); ?></span>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="quiz-actions">
                <button type="submit" name="submit_quiz" class="btn-submit">
                    <i class='bx bx-check'></i> Enviar Respostas
                </button>
            </div>
        </form>
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
        .flash-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .quiz-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .quiz-header {
            margin-bottom: 30px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .quiz-header h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .quiz-instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quiz-instructions i {
            font-size: 20px;
        }

        .quiz-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .question-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .question-number {
            font-weight: bold;
            color: #007bff;
            font-size: 16px;
        }

        .question-total {
            color: #666;
            font-size: 14px;
        }

        .question-text {
            font-size: 18px;
            font-weight: 500;
            color: #333;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .option:hover {
            border-color: #007bff;
            background-color: #f8f9ff;
        }

        .option input[type="radio"] {
            margin: 0;
            margin-top: 2px;
        }

        .option-letter {
            font-weight: bold;
            color: #007bff;
            min-width: 25px;
        }

        .option-text {
            flex: 1;
            line-height: 1.4;
        }

        .option input[type="radio"]:checked + .option-letter {
            color: #28a745;
        }

        .option:has(input[type="radio"]:checked) {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .quiz-actions {
            text-align: center;
            margin-top: 30px;
        }

        .btn-submit {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .btn-submit i {
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .quiz-container {
                padding: 15px;
            }
            
            .question-card {
                padding: 20px;
            }
            
            .question-text {
                font-size: 16px;
            }
            
            .option {
                padding: 12px;
            }
        }
    </style>
</body>
</html>

