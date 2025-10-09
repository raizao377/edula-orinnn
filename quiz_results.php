<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

if ($activity_id == 0) {
    header('Location: study_tracks.php');
    exit();
}

try {
    // Buscar informações da atividade
    $stmt = $pdo->prepare("
        SELECT ta.*, st.name as track_name 
        FROM track_activities ta 
        JOIN study_tracks st ON ta.track_id = st.id 
        WHERE ta.id = ?
    ");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header('Location: study_tracks.php');
        exit();
    }

    // Buscar respostas do usuário
    $stmt = $pdo->prepare("
        SELECT qr.*, aq.question, aq.option_a, aq.option_b, aq.option_c, aq.option_d, 
               aq.correct_answer, aq.explanation
        FROM quiz_responses qr
        JOIN activity_quiz aq ON qr.quiz_id = aq.id
        WHERE qr.user_id = ? AND qr.activity_id = ?
        ORDER BY aq.id
    ");
    $stmt->execute([$user_id, $activity_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($responses)) {
        header('Location: quiz.php?activity_id=' . $activity_id);
        exit();
    }

    // Calcular estatísticas
    $total_questions = count($responses);
    $correct_answers = array_sum(array_column($responses, 'is_correct'));
    $score_percentage = ($correct_answers / $total_questions) * 100;
    $passed = $score_percentage >= 60;

} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

function getOptionText($response, $option) {
    switch ($option) {
        case 'a': return $response['option_a'];
        case 'b': return $response['option_b'];
        case 'c': return $response['option_c'];
        case 'd': return $response['option_d'];
        default: return '';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados do Quiz - <?php echo htmlspecialchars($activity['title']); ?> | Edula</title>
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
                <span>Resultados do Quiz</span>
            </div>
            
            <h1>Resultados do Quiz</h1>
            <p class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></p>
        </div>

        <div class="results-summary">
            <div class="score-card <?php echo $passed ? 'passed' : 'failed'; ?>">
                <div class="score-icon">
                    <?php if ($passed): ?>
                        <i class='bx bx-check-circle'></i>
                    <?php else: ?>
                        <i class='bx bx-x-circle'></i>
                    <?php endif; ?>
                </div>
                
                <div class="score-info">
                    <h2><?php echo $passed ? 'Parabéns!' : 'Não foi desta vez!'; ?></h2>
                    <div class="score-percentage"><?php echo number_format($score_percentage, 1); ?>%</div>
                    <div class="score-details">
                        <?php echo $correct_answers; ?> de <?php echo $total_questions; ?> questões corretas
                    </div>
                    <div class="score-status">
                        <?php if ($passed): ?>
                            <span class="status-passed">✓ Aprovado</span>
                        <?php else: ?>
                            <span class="status-failed">✗ Reprovado (mínimo 60%)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="results-stats">
                <div class="stat">
                    <i class='bx bx-target-lock'></i>
                    <span>Acertos</span>
                    <strong><?php echo $correct_answers; ?>/<?php echo $total_questions; ?></strong>
                </div>
                <div class="stat">
                    <i class='bx bx-percent'></i>
                    <span>Percentual</span>
                    <strong><?php echo number_format($score_percentage, 1); ?>%</strong>
                </div>
                <div class="stat">
                    <i class='bx bx-trophy'></i>
                    <span>Status</span>
                    <strong><?php echo $passed ? 'Aprovado' : 'Reprovado'; ?></strong>
                </div>
            </div>
        </div>

        <div class="detailed-results">
            <h3>Revisão Detalhada</h3>
            
            <?php foreach ($responses as $index => $response): ?>
                <div class="question-review <?php echo $response['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <div class="question-header">
                        <span class="question-number">Questão <?php echo $index + 1; ?></span>
                        <span class="result-icon">
                            <?php if ($response['is_correct']): ?>
                                <i class='bx bx-check-circle'></i>
                            <?php else: ?>
                                <i class='bx bx-x-circle'></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="question-content">
                        <h4><?php echo htmlspecialchars($response['question']); ?></h4>
                        
                        <div class="answer-comparison">
                            <div class="user-answer">
                                <strong>Sua resposta:</strong>
                                <span class="option-display <?php echo $response['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                    <?php echo strtoupper($response['selected_answer']); ?>) 
                                    <?php echo htmlspecialchars(getOptionText($response, $response['selected_answer'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!$response['is_correct']): ?>
                                <div class="correct-answer">
                                    <strong>Resposta correta:</strong>
                                    <span class="option-display correct">
                                        <?php echo strtoupper($response['correct_answer']); ?>) 
                                        <?php echo htmlspecialchars(getOptionText($response, $response['correct_answer'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($response['explanation'])): ?>
                            <div class="explanation">
                                <strong>Explicação:</strong>
                                <p><?php echo htmlspecialchars($response['explanation']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="results-actions">
            <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>" class="btn btn-primary">
                <i class='bx bx-arrow-back'></i> Voltar às Atividades
            </a>
            
            <?php if (!$passed): ?>
                <a href="<?php echo htmlspecialchars($activity['content_url']); ?>" target="_blank" class="btn btn-secondary">
                    <i class='bx bx-book-open'></i> Revisar Material
                </a>
            <?php else: ?>
                <a href="study_tracks.php" class="btn btn-secondary">
                    <i class='bx bx-book'></i> Continuar Estudos
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Limpar dados salvos do quiz
        localStorage.removeItem('quiz_<?php echo $activity_id; ?>_progress');
        
        // Animação de entrada dos resultados
        document.addEventListener('DOMContentLoaded', function() {
            const scoreCard = document.querySelector('.score-card');
            const questionReviews = document.querySelectorAll('.question-review');
            
            setTimeout(() => {
                scoreCard.style.opacity = '1';
                scoreCard.style.transform = 'translateY(0)';
            }, 300);
            
            questionReviews.forEach((review, index) => {
                setTimeout(() => {
                    review.style.opacity = '1';
                    review.style.transform = 'translateY(0)';
                }, 500 + (index * 100));
            });
        });
    </script>
</body>
</html>

