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

// Buscar estatísticas gerais
try {
    // Total de alunos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE tipo = 'user'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    // Total de atividades
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM track_activities");
    $stmt->execute();
    $total_activities = $stmt->fetchColumn();
    
    // Total de quizzes realizados
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM quiz_attempts");
    $stmt->execute();
    $total_quiz_attempts = $stmt->fetchColumn();
    
    // Média geral de aprovação
    $stmt = $pdo->prepare("SELECT AVG(score/total_questions * 100) as avg_score FROM quiz_attempts");
    $stmt->execute();
    $avg_score = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $total_students = 0;
    $total_activities = 0;
    $total_quiz_attempts = 0;
    $avg_score = 0;
}

// Buscar progresso detalhado dos alunos
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nome,
            u.email,
            COUNT(DISTINCT up.activity_id) as completed_activities,
            COUNT(DISTINCT qa.activity_id) as quiz_attempts,
            AVG(qa.score/qa.total_questions * 100) as avg_quiz_score,
            MAX(up.completed_at) as last_activity
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id AND up.status = 'completed'
        LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
        WHERE u.tipo = 'user'
        GROUP BY u.id, u.nome, u.email
        ORDER BY completed_activities DESC, u.nome ASC
    ");
    $stmt->execute();
    $students_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $students_progress = [];
}

// Buscar progresso por trilha
try {
    $stmt = $pdo->prepare("
        SELECT 
            st.id,
            st.name as track_name,
            COUNT(DISTINCT ta.id) as total_activities,
            COUNT(DISTINCT up.activity_id) as completed_activities,
            COUNT(DISTINCT qa.activity_id) as quiz_attempts,
            AVG(qa.score/qa.total_questions * 100) as avg_score
        FROM study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.status = 'completed'
        LEFT JOIN quiz_attempts qa ON ta.id = qa.activity_id
        GROUP BY st.id, st.name
        ORDER BY st.name
    ");
    $stmt->execute();
    $tracks_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $tracks_progress = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.professor.css">
    <title>Progresso dos Alunos - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Progresso dos Alunos</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Acompanhe o desempenho dos estudantes!</p>
        <ul>
            <li><a href="area_teacher.php">Área do Professor</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="progress-container">
        <!-- Estatísticas Gerais -->
        <div class="stats-section">
            <h2><i class='bx bx-bar-chart'></i> Estatísticas Gerais</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-user'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Alunos Cadastrados</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-book'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_activities; ?></h3>
                        <p>Atividades Disponíveis</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_quiz_attempts; ?></h3>
                        <p>Quizzes Realizados</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-trophy'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo round($avg_score, 1); ?>%</h3>
                        <p>Média Geral</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progresso por Trilha -->
        <div class="tracks-section">
            <h2><i class='bx bx-map'></i> Progresso por Trilha</h2>
            <div class="tracks-grid">
                <?php foreach ($tracks_progress as $track): ?>
                    <div class="track-card">
                        <h3><?php echo htmlspecialchars($track['track_name']); ?></h3>
                        <div class="track-stats">
                            <div class="track-stat">
                                <span class="label">Atividades:</span>
                                <span class="value"><?php echo $track['total_activities']; ?></span>
                            </div>
                            <div class="track-stat">
                                <span class="label">Concluídas:</span>
                                <span class="value"><?php echo $track['completed_activities']; ?></span>
                            </div>
                            <div class="track-stat">
                                <span class="label">Quizzes:</span>
                                <span class="value"><?php echo $track['quiz_attempts']; ?></span>
                            </div>
                            <div class="track-stat">
                                <span class="label">Média:</span>
                                <span class="value"><?php echo round($track['avg_score'] ?: 0, 1); ?>%</span>
                            </div>
                        </div>
                        
                        <?php if ($track['total_activities'] > 0): ?>
                            <?php $completion_rate = ($track['completed_activities'] / $track['total_activities']) * 100; ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                            </div>
                            <p class="completion-text"><?php echo round($completion_rate, 1); ?>% de conclusão</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Progresso Individual dos Alunos -->
        <div class="students-section">
            <h2><i class='bx bx-group'></i> Progresso Individual dos Alunos</h2>
            <div class="students-table">
                <table>
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Email</th>
                            <th>Atividades Concluídas</th>
                            <th>Quizzes Realizados</th>
                            <th>Média dos Quizzes</th>
                            <th>Última Atividade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_progress as $student): ?>
                            <tr>
                                <td class="student-name">
                                    <i class='bx bx-user-circle'></i>
                                    <?php echo htmlspecialchars($student['nome']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?php echo $student['completed_activities']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-primary"><?php echo $student['quiz_attempts']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($student['avg_quiz_score']): ?>
                                        <?php $score = round($student['avg_quiz_score'], 1); ?>
                                        <span class="score <?php echo $score >= 70 ? 'score-good' : ($score >= 60 ? 'score-ok' : 'score-low'); ?>">
                                            <?php echo $score; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="score score-none">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['last_activity']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($student['last_activity'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nenhuma atividade</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['completed_activities'] > 0): ?>
                                        <span class="status status-active">Ativo</span>
                                    <?php else: ?>
                                        <span class="status status-inactive">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($students_progress)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Nenhum aluno cadastrado ainda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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

        .progress-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .stats-section, .tracks-section, .students-section {
            margin-bottom: 40px;
        }

        .stats-section h2, .tracks-section h2, .students-section h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 0 0 5px 0;
        }

        .stat-info p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }

        .tracks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .track-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .track-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .track-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .track-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .track-stat .label {
            color: #666;
            font-size: 14px;
        }

        .track-stat .value {
            font-weight: bold;
            color: #333;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }

        .completion-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .students-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .students-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .students-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #dee2e6;
        }

        .students-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .students-table tr:hover {
            background: #f8f9fa;
        }

        .student-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .student-name i {
            font-size: 20px;
            color: #007bff;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #6c757d;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }

        .score {
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
        }

        .score-good {
            background: #d4edda;
            color: #155724;
        }

        .score-ok {
            background: #fff3cd;
            color: #856404;
        }

        .score-low {
            background: #f8d7da;
            color: #721c24;
        }

        .score-none {
            color: #6c757d;
        }

        .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .progress-container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tracks-grid {
                grid-template-columns: 1fr;
            }
            
            .students-table {
                overflow-x: auto;
            }
            
            .track-stats {
                grid-template-columns: 1fr;
            }
        }
        /* Navbar Styles */
.navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar h2 {
    margin: 0 0 8px 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.navbar h2 span {
    color: #ffd700;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.navbar p {
    margin: 0 0 15px 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

.navbar ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 20px;
    align-items: center;
}

.navbar li {
    margin: 0;
}

.navbar a {
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
    display: inline-block;
    font-weight: 500;
}

.navbar a:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

/* Responsividade */
@media (max-width: 768px) {
    .navbar {
        padding: 15px 20px;
        text-align: center;
    }
    
    .navbar ul {
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .navbar a {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .navbar h2 {
        font-size: 1.3rem;
    }
    
    .navbar p {
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .navbar {
        padding: 12px 15px;
    }
    
    .navbar ul {
        flex-direction: column;
        gap: 8px;
    }
    
    .navbar a {
        justify-content: center;
        width: 100%;
        padding: 8px;
    }
    
    .navbar h2 {
        font-size: 1.2rem;
    }
}
    </style>
</body>
</html>

