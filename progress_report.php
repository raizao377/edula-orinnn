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

// Filtros
$selected_track = isset($_GET['track_id']) ? (int)$_GET['track_id'] : 0;
$selected_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Buscar todas as trilhas para o filtro
try {
    $stmt = $pdo->prepare("SELECT id, name FROM study_tracks ORDER BY name");
    $stmt->execute();
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracks = [];
}

// Buscar todos os usuários (estudantes) para o filtro
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM users WHERE role = 'user' ORDER BY nome");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Construir query para buscar progresso dos alunos
$where_conditions = [];
$params = [];

if ($selected_track > 0) {
    $where_conditions[] = "st.id = ?";
    $params[] = $selected_track;
}

if ($selected_user > 0) {
    $where_conditions[] = "u.id = ?";
    $params[] = $selected_user;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Buscar progresso detalhado
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.nome as user_name,
            st.id as track_id,
            st.name as track_name,
            COUNT(ta.id) as total_activities,
            COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_activities,
            ROUND((COUNT(CASE WHEN up.status = 'completed' THEN 1 END) / COUNT(ta.id)) * 100, 1) as progress_percentage,
            MAX(up.completed_at) as last_activity_date
        FROM users u
        CROSS JOIN study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = u.id
        $where_clause
        AND u.role = 'user'
        GROUP BY u.id, u.nome, st.id, st.name
        HAVING total_activities > 0
        ORDER BY u.nome, st.name
    ");
    $stmt->execute($params);
    $progress_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $progress_data = [];
    setFlashMessage('Erro ao carregar dados de progresso.', 'danger');
}

// Estatísticas gerais
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            COUNT(DISTINCT st.id) as total_tracks,
            COUNT(ta.id) as total_activities,
            COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_activities
        FROM users u
        CROSS JOIN study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = u.id
        WHERE u.role = 'user'
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_students' => 0, 'total_tracks' => 0, 'total_activities' => 0, 'completed_activities' => 0];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <title>Progresso dos Alunos - Edula</title>
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
            <a href="area_professor.php">Área do Professor</a>
            <a href="criar_prova.php">Avaliações</a>
            <a href="ver_respostas.php">Relatórios</a>
            <a href="progress_report.php" class="active">Progresso dos Alunos</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="progress-container">
        <div class="progress-header">
            <h1>Acompanhamento de Progresso dos Alunos</h1>
            <p>Monitore o progresso dos estudantes nas trilhas de aprendizado</p>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-group'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p>Estudantes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-map'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_tracks']; ?></h3>
                    <p>Trilhas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-list-ul'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_activities']; ?></h3>
                    <p>Atividades</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed_activities']; ?></h3>
                    <p>Concluídas</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <h2>Filtros</h2>
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="track_id">Trilha:</label>
                    <select id="track_id" name="track_id">
                        <option value="0">Todas as trilhas</option>
                        <?php foreach ($tracks as $track): ?>
                            <option value="<?php echo $track['id']; ?>" <?php echo $selected_track == $track['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($track['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="user_id">Estudante:</label>
                    <select id="user_id" name="user_id">
                        <option value="0">Todos os estudantes</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $selected_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                    <i class='bx bx-filter'></i> Filtrar
                </button>
                <a href="progress_report.php" class="btn-clear">
                    <i class='bx bx-x'></i> Limpar
                </a>
            </form>
        </div>

        <!-- Tabela de Progresso -->
        <div class="progress-section">
            <h2>Progresso Detalhado</h2>
            <?php if (empty($progress_data)): ?>
                <div class="no-data">
                    <i class='bx bx-data'></i>
                    <p>Nenhum dado de progresso encontrado com os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <div class="progress-table-container">
                    <table class="progress-table">
                        <thead>
                            <tr>
                                <th>Estudante</th>
                                <th>Trilha</th>
                                <th>Progresso</th>
                                <th>Atividades</th>
                                <th>Última Atividade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($progress_data as $row): ?>
                                <tr>
                                    <td class="student-name">
                                        <i class='bx bx-user'></i>
                                        <?php echo htmlspecialchars($row['user_name']); ?>
                                    </td>
                                    <td class="track-name">
                                        <?php echo htmlspecialchars($row['track_name']); ?>
                                    </td>
                                    <td class="progress-cell">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $row['progress_percentage']; ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo $row['progress_percentage']; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="activities-count">
                                        <?php echo $row['completed_activities']; ?>/<?php echo $row['total_activities']; ?>
                                    </td>
                                    <td class="last-activity">
                                        <?php if ($row['last_activity_date']): ?>
                                            <?php echo date('d/m/Y', strtotime($row['last_activity_date'])); ?>
                                        <?php else: ?>
                                            <span class="no-activity">Não iniciado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-cell">
                                        <?php if ($row['progress_percentage'] == 100): ?>
                                            <span class="status completed">
                                                <i class='bx bx-check-circle'></i> Concluída
                                            </span>
                                        <?php elseif ($row['progress_percentage'] > 0): ?>
                                            <span class="status in-progress">
                                                <i class='bx bx-time'></i> Em andamento
                                            </span>
                                        <?php else: ?>
                                            <span class="status not-started">
                                                <i class='bx bx-circle'></i> Não iniciado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .progress-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .progress-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .progress-header p {
            color: #666;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 24px;
            color: white;
        }

        .stat-info h3 {
            font-size: 28px;
            color: #333;
            margin: 0;
        }

        .stat-info p {
            color: #666;
            margin: 5px 0 0 0;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .filters-section h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .filters-form {
            display: flex;
            align-items: end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: bold;
            color: #333;
        }

        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
        }

        .btn-filter, .btn-clear {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter {
            background: #007bff;
            color: white;
        }

        .btn-filter:hover {
            background: #0056b3;
        }

        .btn-clear {
            background: #6c757d;
            color: white;
        }

        .btn-clear:hover {
            background: #545b62;
            text-decoration: none;
            color: white;
        }

        .progress-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .progress-section h2 {
            color: #333;
            margin: 0;
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .progress-table-container {
            overflow-x: auto;
        }

        .progress-table {
            width: 100%;
            border-collapse: collapse;
        }

        .progress-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .progress-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .progress-table tr:hover {
            background: #f8f9fa;
        }

        .student-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .student-name i {
            color: #007bff;
        }

        .progress-cell {
            min-width: 150px;
        }

        .progress-bar-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            transition: width 0.3s ease;
        }

        .progress-text {
            font-weight: bold;
            color: #007bff;
            min-width: 40px;
        }

        .activities-count {
            font-weight: 500;
            color: #333;
        }

        .no-activity {
            color: #6c757d;
            font-style: italic;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status.completed {
            background: #d4edda;
            color: #155724;
        }

        .status.in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status.not-started {
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
            
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group select {
                min-width: auto;
            }
        }
    </style>
</body>
</html>

