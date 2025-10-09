<?php
session_start();
require_once 'config/database.php';

// Conectar ao banco de dados
$database = new Database();
$pdo = $database->getConnection();

// Verificar se o usuário está logado e é professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];

try {
    // Buscar estatísticas gerais
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            COUNT(DISTINCT st.id) as total_tracks,
            COUNT(DISTINCT ta.id) as total_activities,
            COUNT(DISTINCT qr.id) as total_quiz_responses
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN track_activities ta ON up.activity_id = ta.id
        LEFT JOIN study_tracks st ON ta.track_id = st.id
        LEFT JOIN quiz_responses qr ON u.id = qr.user_id
        WHERE u.role = 'user'
    ");
    $stmt->execute();
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar progresso por trilha
    $stmt = $pdo->prepare("
        SELECT 
            st.id,
            st.name,
            st.description,
            COUNT(DISTINCT ta.id) as total_activities,
            COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.user_id END) as completed_users,
            COUNT(DISTINCT up.user_id) as enrolled_users,
            ROUND(AVG(CASE WHEN qr.is_correct = 1 THEN 100 ELSE 0 END), 2) as avg_quiz_score
        FROM study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id
        LEFT JOIN quiz_responses qr ON ta.id = qr.activity_id
        GROUP BY st.id, st.name, st.description
        ORDER BY st.id
    ");
    $stmt->execute();
    $track_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar atividade recente dos alunos
    $stmt = $pdo->prepare("
        SELECT 
            u.nome,
            u.matricula,
            st.name as track_name,
            ta.title as activity_title,
            up.status,
            up.completed_at,
            up.started_at
        FROM user_progress up
        JOIN users u ON up.user_id = u.id
        JOIN track_activities ta ON up.activity_id = ta.id
        JOIN study_tracks st ON ta.track_id = st.id
        WHERE u.role = 'user'
        ORDER BY COALESCE(up.completed_at, up.started_at) DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar top performers
    $stmt = $pdo->prepare("
        SELECT 
            u.nome,
            u.matricula,
            COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN ta.id END) as completed_activities,
            COUNT(DISTINCT qr.id) as quiz_responses,
            ROUND(AVG(CASE WHEN qr.is_correct = 1 THEN 100 ELSE 0 END), 2) as avg_score
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN track_activities ta ON up.activity_id = ta.id
        LEFT JOIN quiz_responses qr ON u.id = qr.user_id
        WHERE u.role = 'user'
        GROUP BY u.id, u.nome, u.matricula
        HAVING quiz_responses > 0
        ORDER BY avg_score DESC, completed_activities DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_performers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

function getStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="badge badge-success">Concluído</span>';
        case 'em_progresso':
            return '<span class="badge badge-warning">Em Progresso</span>';
        case 'pendente':
        default:
            return '<span class="badge badge-secondary">Pendente</span>';
    }
}

function timeAgo($datetime) {
    if (!$datetime) return 'Nunca';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Agora mesmo';
    if ($time < 3600) return floor($time/60) . ' min atrás';
    if ($time < 86400) return floor($time/3600) . ' h atrás';
    if ($time < 2592000) return floor($time/86400) . ' dias atrás';
    
    return date('d/m/Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Professor | Edula</title>
    
    <link rel="stylesheet" href="static/css/reports.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="static/img/logo.png" alt="Edula">
                <span>Edula</span>
            </div>
            <div class="nav-menu">
                <a href="area_professor.php" class="nav-link">
                    <i class='bx bx-home'></i> Início
                </a>
                <a href="teacher_reports.php" class="nav-link active">
                    <i class='bx bx-bar-chart-alt-2'></i> Relatórios
                </a>
                <a href="student_list.php" class="nav-link">
                    <i class='bx bx-group'></i> Alunos
                </a>
                <a href="logout.php" class="nav-link">
                    <i class='bx bx-log-out'></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-bar-chart-alt-2'></i> Relatórios e Análises</h1>
            <p>Acompanhe o progresso dos alunos e o desempenho das trilhas de estudo</p>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-group'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $general_stats['total_students']; ?></h3>
                    <p>Alunos Cadastrados</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-book'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $general_stats['total_tracks']; ?></h3>
                    <p>Trilhas de Estudo</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-task'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $general_stats['total_activities']; ?></h3>
                    <p>Atividades Disponíveis</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $general_stats['total_quiz_responses']; ?></h3>
                    <p>Quizzes Respondidos</p>
                </div>
            </div>
        </div>

        <!-- Gráficos e Análises -->
        <div class="charts-section">
            <div class="chart-container">
                <h3>Progresso por Trilha de Estudo</h3>
                <canvas id="trackProgressChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3>Desempenho nos Quizzes</h3>
                <canvas id="quizPerformanceChart"></canvas>
            </div>
        </div>

        <!-- Tabela de Trilhas -->
        <div class="section">
            <h2>Desempenho por Trilha</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Trilha</th>
                            <th>Atividades</th>
                            <th>Alunos Inscritos</th>
                            <th>Concluíram</th>
                            <th>Taxa de Conclusão</th>
                            <th>Média Quiz</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($track_stats as $track): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($track['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($track['description'], 0, 60)) . '...'; ?></small>
                                </td>
                                <td><?php echo $track['total_activities']; ?></td>
                                <td><?php echo $track['enrolled_users']; ?></td>
                                <td><?php echo $track['completed_users']; ?></td>
                                <td>
                                    <?php 
                                    $completion_rate = $track['enrolled_users'] > 0 ? 
                                        round(($track['completed_users'] / $track['enrolled_users']) * 100, 1) : 0;
                                    ?>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                        <span class="progress-text"><?php echo $completion_rate; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($track['avg_quiz_score']): ?>
                                        <span class="score-badge <?php echo $track['avg_quiz_score'] >= 70 ? 'good' : ($track['avg_quiz_score'] >= 50 ? 'average' : 'poor'); ?>">
                                            <?php echo $track['avg_quiz_score']; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="track_detailed_report.php?track_id=<?php echo $track['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class='bx bx-detail'></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Atividade Recente e Top Performers -->
        <div class="two-column-section">
            <div class="section">
                <h3>Atividade Recente</h3>
                <div class="activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-info">
                                <strong><?php echo htmlspecialchars($activity['nome']); ?></strong>
                                <span class="matricula">(<?php echo htmlspecialchars($activity['matricula']); ?>)</span>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($activity['track_name']); ?> - 
                                    <?php echo htmlspecialchars($activity['activity_title']); ?>
                                </small>
                            </div>
                            <div class="activity-status">
                                <?php echo getStatusBadge($activity['status']); ?>
                                <small class="time-ago">
                                    <?php echo timeAgo($activity['completed_at'] ?: $activity['started_at']); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section">
                <h3>Melhores Desempenhos</h3>
                <div class="top-performers">
                    <?php foreach ($top_performers as $index => $performer): ?>
                        <div class="performer-item">
                            <div class="rank">#<?php echo $index + 1; ?></div>
                            <div class="performer-info">
                                <strong><?php echo htmlspecialchars($performer['nome']); ?></strong>
                                <span class="matricula">(<?php echo htmlspecialchars($performer['matricula']); ?>)</span>
                                <br>
                                <small class="stats">
                                    <?php echo $performer['completed_activities']; ?> atividades • 
                                    <?php echo $performer['avg_score']; ?>% média
                                </small>
                            </div>
                            <div class="score-badge <?php echo $performer['avg_score'] >= 80 ? 'excellent' : ($performer['avg_score'] >= 70 ? 'good' : 'average'); ?>">
                                <?php echo $performer['avg_score']; ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="quick-actions">
            <h3>Ações Rápidas</h3>
            <div class="action-buttons">
                <a href="generate_full_report.php" class="btn btn-primary">
                    <i class='bx bx-download'></i> Gerar Relatório Completo
                </a>
                <a href="student_list.php" class="btn btn-secondary">
                    <i class='bx bx-group'></i> Ver Todos os Alunos
                </a>
                <a href="export_data.php" class="btn btn-secondary">
                    <i class='bx bx-export'></i> Exportar Dados
                </a>
            </div>
        </div>
    </div>

    <script>
        // Dados para os gráficos
        const trackData = <?php echo json_encode($track_stats); ?>;
        
        // Gráfico de Progresso por Trilha
        const trackProgressCtx = document.getElementById('trackProgressChart').getContext('2d');
        new Chart(trackProgressCtx, {
            type: 'bar',
            data: {
                labels: trackData.map(track => track.name),
                datasets: [{
                    label: 'Alunos Inscritos',
                    data: trackData.map(track => track.enrolled_users),
                    backgroundColor: 'rgba(102, 126, 234, 0.6)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }, {
                    label: 'Concluíram',
                    data: trackData.map(track => track.completed_users),
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de Desempenho nos Quizzes
        const quizPerformanceCtx = document.getElementById('quizPerformanceChart').getContext('2d');
        new Chart(quizPerformanceCtx, {
            type: 'doughnut',
            data: {
                labels: trackData.map(track => track.name),
                datasets: [{
                    data: trackData.map(track => track.avg_quiz_score || 0),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 186, 27, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

