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

// Verificar se o ID da trilha foi fornecido
if (!isset($_GET['track_id']) || !is_numeric($_GET['track_id'])) {
    setFlashMessage('Trilha não encontrada.', 'danger');
    header("Location: study_tracks.php");
    exit();
}

$track_id = (int)$_GET['track_id'];

// Buscar informações da trilha
try {
    $stmt = $pdo->prepare("SELECT id, name, description FROM study_tracks WHERE id = ?");
    $stmt->execute([$track_id]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$track) {
        setFlashMessage('Trilha não encontrada.', 'danger');
        header("Location: study_tracks.php");
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar trilha.', 'danger');
    header("Location: study_tracks.php");
    exit();
}

// Buscar atividades da trilha com progresso do usuário
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.title, ta.description, ta.content_url, ta.order_index,
               up.status, up.completed_at
        FROM track_activities ta
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = ?
        WHERE ta.track_id = ?
        ORDER BY ta.order_index ASC
    ");
    $stmt->execute([$user_id, $track_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
    setFlashMessage('Erro ao carregar atividades.', 'danger');
}

// Determinar qual atividade está disponível
$available_activity_index = 0;
foreach ($activities as $index => $activity) {
    if ($activity['status'] !== 'completed') {
        $available_activity_index = $index;
        break;
    }
    if ($index === count($activities) - 1) {
        $available_activity_index = $index; // Todas concluídas
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <title><?php echo htmlspecialchars($track['name']); ?> - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - <?php echo htmlspecialchars($track['name']); ?></h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Continue sua jornada de aprendizado!</p>
        <ul>
            <li><a href="study_tracks.php">Voltar às Trilhas</a></li>
            <li><a href="area_user.php">Área do Usuário</a></li>
            <li><a href="INICIO.php">Início</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="activities-container">
        <div class="track-header">
            <h1><?php echo htmlspecialchars($track['name']); ?></h1>
            <p><?php echo htmlspecialchars($track['description']); ?></p>
        </div>

        <div class="activities-timeline">
            <?php if (empty($activities)): ?>
                <div class="no-activities">
                    <i class='bx bx-list-ul'></i>
                    <p>Ainda não há atividades nesta trilha.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $index => $activity): ?>
                    <?php 
                        $is_completed = $activity['status'] === 'completed';
                        $is_available = $index <= $available_activity_index;
                        $is_current = $index === $available_activity_index && !$is_completed;
                    ?>
                    <div class="activity-item <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ($is_available ? 'available' : 'locked')); ?>">
                        <div class="activity-number">
                            <?php if ($is_completed): ?>
                                <i class='bx bx-check'></i>
                            <?php elseif ($is_available): ?>
                                <?php echo $index + 1; ?>
                            <?php else: ?>
                                <i class='bx bx-lock'></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="activity-content">
                            <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                            
                            <?php if ($is_completed): ?>
                                <div class="completion-info">
                                    <i class='bx bx-check-circle'></i>
                                    <span>Concluída em <?php echo date('d/m/Y H:i', strtotime($activity['completed_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="activity-actions">
                                <?php if ($is_available): ?>
                                    <a href="activity.php?id=<?php echo $activity['id']; ?>" class="btn-activity">
                                        <?php if ($is_completed): ?>
                                            <i class='bx bx-refresh'></i> Revisar
                                        <?php elseif ($is_current): ?>
                                            <i class='bx bx-play'></i> Iniciar
                                        <?php else: ?>
                                            <i class='bx bx-eye'></i> Ver
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="btn-locked">
                                        <i class='bx bx-lock'></i> Bloqueada
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($index < count($activities) - 1): ?>
                            <div class="activity-connector <?php echo $is_completed ? 'completed' : ''; ?>"></div>
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

        .activities-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .track-header {
            text-align: center;
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
        }

        .track-header h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .track-header p {
            color: #666;
            font-size: 16px;
        }

        .activities-timeline {
            position: relative;
        }

        .no-activities {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-activities i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .activity-item {
            position: relative;
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .activity-item.completed {
            border-left: 4px solid #28a745;
            background: #f8fff9;
        }

        .activity-item.current {
            border-left: 4px solid #007bff;
            background: #f8fbff;
            transform: scale(1.02);
        }

        .activity-item.locked {
            opacity: 0.6;
            background: #f5f5f5;
        }

        .activity-number {
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-right: 20px;
        }

        .activity-item.completed .activity-number {
            background: #28a745;
            color: white;
        }

        .activity-item.current .activity-number {
            background: #007bff;
            color: white;
        }

        .activity-item.available .activity-number {
            background: #e9ecef;
            color: #333;
        }

        .activity-item.locked .activity-number {
            background: #dee2e6;
            color: #6c757d;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .activity-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .completion-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #28a745;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .completion-info i {
            font-size: 16px;
        }

        .activity-actions {
            margin-top: 15px;
        }

        .btn-activity {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-activity:hover {
            background: #0056b3;
            text-decoration: none;
            color: white;
        }

        .activity-item.completed .btn-activity {
            background: #28a745;
        }

        .activity-item.completed .btn-activity:hover {
            background: #218838;
        }

        .btn-locked {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
        }

        .activity-connector {
            position: absolute;
            left: 45px;
            top: 100%;
            width: 2px;
            height: 30px;
            background: #dee2e6;
        }

        .activity-connector.completed {
            background: #28a745;
        }

        @media (max-width: 768px) {
            .activities-container {
                padding: 15px;
            }
            
            .activity-item {
                padding: 20px;
            }
            
            .activity-number {
                width: 40px;
                height: 40px;
                font-size: 16px;
                margin-right: 15px;
            }
            
            .activity-connector {
                left: 40px;
            }
        }
    </style>
</body>
</html>

