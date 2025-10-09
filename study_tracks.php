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

// Buscar todas as trilhas de estudo
try {
    $stmt = $pdo->prepare("
        SELECT st.id, st.name, st.description,
               COUNT(ta.id) as total_activities,
               COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_activities
        FROM study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = ?
        GROUP BY st.id, st.name, st.description
        ORDER BY st.id
    ");
    $stmt->execute([$user_id]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracks = [];
    setFlashMessage('Erro ao carregar trilhas de estudo.', 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <title>Trilhas de Estudo - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Trilhas de Estudo</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Escolha sua trilha de aprendizado!</p>
        <ul>
            <li><a href="area_user.php">Voltar à Área do Usuário</a></li>
            <li><a href="INICIO.php">Início</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="tracks-container">
        <div class="tracks-header">
            <h1>Trilhas de Estudo</h1>
            <p>Aprenda de forma estruturada e progressiva. Complete uma atividade para desbloquear a próxima!</p>
        </div>

        <div class="tracks-grid">
            <?php if (empty($tracks)): ?>
                <div class="no-tracks">
                    <i class='bx bx-map'></i>
                    <p>Ainda não há trilhas de estudo disponíveis.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tracks as $track): ?>
                    <?php 
                        $progress_percentage = $track['total_activities'] > 0 
                            ? round(($track['completed_activities'] / $track['total_activities']) * 100) 
                            : 0;
                    ?>
                    <div class="track-card">
                        <div class="track-icon">
                            <i class='bx bx-book-open'></i>
                        </div>
                        <div class="track-content">
                            <h3><?php echo htmlspecialchars($track['name']); ?></h3>
                            <p><?php echo htmlspecialchars($track['description']); ?></p>
                            
                            <div class="track-progress">
                                <div class="progress-info">
                                    <span>Progresso: <?php echo $track['completed_activities']; ?>/<?php echo $track['total_activities']; ?> atividades</span>
                                    <span class="percentage"><?php echo $progress_percentage; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="track-actions">
                                <a href="track_activities.php?track_id=<?php echo $track['id']; ?>" class="btn-start">
                                    <?php if ($track['completed_activities'] > 0): ?>
                                        <i class='bx bx-play-circle'></i> Continuar
                                    <?php else: ?>
                                        <i class='bx bx-play-circle'></i> Começar
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($progress_percentage == 100): ?>
                            <div class="completion-badge">
                                <i class='bx bx-check-circle'></i>
                                <span>Concluída!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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
        .flash-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .tracks-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .tracks-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .tracks-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .tracks-header p {
            color: #666;
            font-size: 16px;
        }

        .tracks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .no-tracks {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-tracks i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .track-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .track-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .track-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .track-icon i {
            font-size: 48px;
            color: #007bff;
        }

        .track-content h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .track-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            text-align: center;
        }

        .track-progress {
            margin-bottom: 25px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }

        .percentage {
            font-weight: bold;
            color: #007bff;
        }

        .progress-bar {
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

        .track-actions {
            text-align: center;
        }

        .btn-start {
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

        .btn-start:hover {
            background: #0056b3;
            text-decoration: none;
            color: white;
        }

        .completion-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .completion-badge i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .tracks-grid {
                grid-template-columns: 1fr;
            }
            
            .tracks-container {
                padding: 15px;
            }
        }
    </style>
</body>
</html>

