<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: study_tracks.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;

if ($activity_id == 0) {
    header('Location: study_tracks.php');
    exit();
}

try {
    // Verificar se o usuário já respondeu este quiz
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as answered 
        FROM quiz_responses 
        WHERE user_id = ? AND activity_id = ?
    ");
    $stmt->execute([$user_id, $activity_id]);
    $already_answered = $stmt->fetch(PDO::FETCH_ASSOC)['answered'] > 0;

    if ($already_answered) {
        header('Location: quiz_results.php?activity_id=' . $activity_id);
        exit();
    }

    // Buscar todas as questões do quiz
    $stmt = $pdo->prepare("
        SELECT * FROM activity_quiz 
        WHERE activity_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$activity_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        header('Location: quiz.php?activity_id=' . $activity_id);
        exit();
    }

    // Processar respostas
    $total_questions = count($questions);
    $correct_answers = 0;
    $responses = [];

    $pdo->beginTransaction();

    foreach ($questions as $question) {
        $question_key = 'question_' . $question['id'];
        $selected_answer = isset($_POST[$question_key]) ? $_POST[$question_key] : '';
        
        if (empty($selected_answer)) {
            $pdo->rollBack();
            header('Location: quiz.php?activity_id=' . $activity_id . '&error=incomplete');
            exit();
        }

        $is_correct = ($selected_answer === $question['correct_answer']);
        if ($is_correct) {
            $correct_answers++;
        }

        // Salvar resposta no banco
        $stmt = $pdo->prepare("
            INSERT INTO quiz_responses (user_id, activity_id, quiz_id, selected_answer, is_correct) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity_id, $question['id'], $selected_answer, $is_correct]);

        $responses[] = [
            'question_id' => $question['id'],
            'question' => $question['question'],
            'selected' => $selected_answer,
            'correct' => $question['correct_answer'],
            'is_correct' => $is_correct,
            'explanation' => $question['explanation']
        ];
    }

    // Calcular pontuação
    $score_percentage = ($correct_answers / $total_questions) * 100;
    $passed = $score_percentage >= 60; // 60% para aprovação

    // Atualizar progresso da atividade
    if ($passed) {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, activity_id, status, completed_at) 
            VALUES (?, ?, 'completed', NOW()) 
            ON DUPLICATE KEY UPDATE 
            status = 'completed', completed_at = NOW()
        ");
        $stmt->execute([$user_id, $activity_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, activity_id, status) 
            VALUES (?, ?, 'em_progresso') 
            ON DUPLICATE KEY UPDATE 
            status = 'em_progresso'
        ");
        $stmt->execute([$user_id, $activity_id]);
    }

    // Salvar resultado do quiz na sessão para exibir
    $_SESSION['quiz_result'] = [
        'activity_id' => $activity_id,
        'total_questions' => $total_questions,
        'correct_answers' => $correct_answers,
        'score_percentage' => $score_percentage,
        'passed' => $passed,
        'responses' => $responses
    ];

    $pdo->commit();

    // Limpar dados salvos localmente
    echo "<script>
        localStorage.removeItem('quiz_" . $activity_id . "_progress');
        window.location.href = 'quiz_results.php?activity_id=" . $activity_id . "';
    </script>";

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erro ao processar quiz: " . $e->getMessage());
    header('Location: quiz.php?activity_id=' . $activity_id . '&error=database');
    exit();
}
?>

