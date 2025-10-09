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
        SELECT ta.id, ta.title, ta.description, ta.track_id, st.name as track_name
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
$already_answered = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$user_id, $activity_id]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_attempt) {
        $already_answered = true;
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
        
        $percentage = ($score / $total_questions) * 100;
        
        // Salvar tentativa
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, activity_id, score, total_questions, percentage) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score), 
            total_questions = VALUES(total_questions), 
            percentage = VALUES(percentage),
            completed_at = NOW()
        ");
        $stmt->execute([$user_id, $activity_id, $score, $total_questions, $percentage]);
        
        // Se passou (60% ou mais), marcar atividade como concluída
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
        setFlashMessage('Erro ao processar quiz: ' . $e->getMessage(), 'danger');
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
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Quiz</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Teste seus conhecimentos!</p>
        <ul>
            <li><a href="activity.php?id=<?php echo $activity_id; ?>">Voltar à Atividade</a></li>
            <li><a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>">Trilha</a></li>
            <li><a href="study_tracks.php">Trilhas de Estudo</a></li>
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
                <a href="study_tracks.php">Trilhas de Estudo</a>
                <i class='bx bx-chevron-right'></i>
                <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>">
                    <?php echo htmlspecialchars($activity['track_name']); ?>
                </a>
                <i class='bx bx-chevron-right'></i>
                <span>Quiz</span>
            </div>
            
            <h1>Quiz: <?php echo htmlspecialchars($activity['title']); ?></h1>
            <p class="quiz-description">Responda às questões abaixo para concluir a atividade. Você precisa acertar pelo menos 60% das questões.</p>
        </div>

        <?php if ($already_answered): ?>
            <div class="quiz-completed">
                <div class="success-message">
                    <i class='bx bx-check-circle'></i>
                    <h2>Quiz já respondido!</h2>
                    <p>Você já completou este quiz. Confira seus resultados na atividade.</p>
                    <div class="action-buttons">
                        <a href="activity.php?id=<?php echo $activity_id; ?>" class="btn-primary">
                            <i class='bx bx-arrow-back'></i> Voltar à Atividade
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="quiz-content">
                <form method="POST" class="quiz-form">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <span class="question-number">Questão <?php echo $index + 1; ?></span>
                                <span class="question-total">de <?php echo count($questions); ?></span>
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
                        <button type="submit" name="submit_quiz" class="btn-submit">
                            <i class='bx bx-check'></i> Finalizar Quiz
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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

        .quiz-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .quiz-header {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb i {
            color: #6c757d;
        }

        .breadcrumb span {
            color: #6c757d;
        }

        .quiz-header h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .quiz-description {
            color: #666;
            font-size: 16px;
        }

        .quiz-completed {
            background: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .success-message i {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
            display: block;
        }

        .success-message h2 {
            color: #28a745;
            margin-bottom: 15px;
        }

        .success-message p {
            color: #666;
            margin-bottom: 25px;
        }

        .quiz-content {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .question-card {
            border-bottom: 1px solid #e9ecef;
            padding: 30px;
        }

        .question-card:last-child {
            border-bottom: none;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .question-number {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }

        .question-total {
            color: #6c757d;
            font-size: 14px;
        }

        .question-content h3 {
            color: #333;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .option:hover {
            border-color: #007bff;
            background: #f8fbff;
        }

        .option input[type="radio"] {
            display: none;
        }

        .option input[type="radio"]:checked + .option-letter {
            background: #007bff;
            color: white;
        }

        .option input[type="radio"]:checked ~ .option-text {
            color: #007bff;
            font-weight: 500;
        }

        .option-letter {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            transition: all 0.2s;
        }

        .option-text {
            flex: 1;
            line-height: 1.4;
        }

        .quiz-actions {
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: #0056b3;
            text-decoration: none;
            color: white;
        }

        @media (max-width: 768px) {
            .quiz-container {
                padding: 15px;
            }
            
            .quiz-header, .question-card, .quiz-actions {
                padding: 20px;
            }
            
            .breadcrumb {
                flex-wrap: wrap;
            }
        }
    </style>
</body>
</html>

