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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Atividade não encontrada.', 'danger');
    header("Location: study_tracks.php");
    exit();
}

$activity_id = (int)$_GET['id'];

// Processar conclusão da atividade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_activity'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, activity_id, status, completed_at) 
            VALUES (?, ?, 'completed', NOW())
            ON DUPLICATE KEY UPDATE 
            status = 'completed', 
            completed_at = NOW()
        ");
        $stmt->execute([$user_id, $activity_id]);
        setFlashMessage('Atividade concluída com sucesso! Parabéns!', 'success');
        
        // Redirecionar de volta para a trilha
        $stmt = $pdo->prepare("SELECT track_id FROM track_activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $track_id = $stmt->fetchColumn();
        
        header("Location: track_activities.php?track_id=" . $track_id);
        exit();
    } catch (Exception $e) {
        setFlashMessage('Erro ao marcar atividade como concluída.', 'danger');
    }
}

// Buscar informações da atividade
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.title, ta.description, ta.content_url, ta.order_index, ta.track_id,
               st.name as track_name,
               up.status, up.completed_at
        FROM track_activities ta
        JOIN study_tracks st ON ta.track_id = st.id
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = ?
        WHERE ta.id = ?
    ");
    $stmt->execute([$user_id, $activity_id]);
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

// Verificar se o usuário pode acessar esta atividade
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.order_index,
               up.status
        FROM track_activities ta
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.user_id = ?
        WHERE ta.track_id = ?
        ORDER BY ta.order_index ASC
    ");
    $stmt->execute([$user_id, $activity['track_id']]);
    $all_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $can_access = false;
    foreach ($all_activities as $index => $act) {
        if ($act['id'] == $activity_id) {
            // Pode acessar se for a primeira atividade ou se a anterior foi concluída
            if ($index === 0) {
                $can_access = true;
            } else {
                $previous_activity = $all_activities[$index - 1];
                $can_access = $previous_activity['status'] === 'completed';
            }
            break;
        }
    }
    
    if (!$can_access && $activity['status'] !== 'completed') {
        setFlashMessage('Você precisa completar a atividade anterior primeiro.', 'warning');
        header("Location: track_activities.php?track_id=" . $activity['track_id']);
        exit();
    }
} catch (Exception $e) {
    // Em caso de erro, permitir acesso
}

$is_completed = $activity['status'] === 'completed';
?>

/// Raigay

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <title><?php echo htmlspecialchars($activity['title']); ?> - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Atividade</h2>
        <p>Olá, <?php echo htmlspecialchars($nome); ?>. Foque no aprendizado!</p>
        <ul>
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

    <div class="activity-container">
        <div class="activity-header">
            <div class="breadcrumb">
                <a href="study_tracks.php">Trilhas</a>
                <i class='bx bx-chevron-right'></i>
                <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>"><?php echo htmlspecialchars($activity['track_name']); ?></a>
                <i class='bx bx-chevron-right'></i>
                <span>Atividade <?php echo $activity['order_index']; ?></span>
            </div>
            
            <h1><?php echo htmlspecialchars($activity['title']); ?></h1>
            
            <?php if ($is_completed): ?>
                <div class="completion-badge">
                    <i class='bx bx-check-circle'></i>
                    <span>Atividade Concluída em <?php echo date('d/m/Y H:i', strtotime($activity['completed_at'])); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="activity-content">
            <div class="content-section">
                <h2>Descrição da Atividade</h2>
                <p><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
            </div>

            <div class="content-section">
                <h2>Conteúdo de Estudo</h2>
                <div class="study-content">
                    <?php if ($activity['content_url'] && $activity['content_url'] !== '#'): ?>
                        <div class="pdf-content">
                            <?php if (strpos($activity['content_url'], '.pdf') !== false): ?>
                                <div class="pdf-viewer">
                                    <div class="pdf-header">
                                        <i class='bx bx-file-pdf'></i>
                                        <h3>Material de Estudo - <?php echo htmlspecialchars($activity['title']); ?></h3>
                                        <a href="<?php echo htmlspecialchars($activity['content_url']); ?>" target="_blank" class="btn-download">
                                            <i class='bx bx-download'></i> Baixar PDF
                                        </a>
                                    </div>
                                    <div class="pdf-embed">
                                        <iframe src="<?php echo htmlspecialchars($activity['content_url']); ?>" 
                                                width="100%" 
                                                height="600" 
                                                style="border: none; border-radius: 8px;">
                                            <p>Seu navegador não suporta a visualização de PDFs. 
                                               <a href="<?php echo htmlspecialchars($activity['content_url']); ?>" target="_blank">
                                                   Clique aqui para baixar o PDF
                                               </a>
                                            </p>
                                        </iframe>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="external-content">
                                    <i class='bx bx-link-external'></i>
                                    <a href="<?php echo htmlspecialchars($activity['content_url']); ?>" target="_blank">
                                        Acessar Material de Estudo
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sample-content">
                            <h3>Material de Estudo - <?php echo htmlspecialchars($activity['title']); ?></h3>
                            
                            <?php if (strpos($activity['title'], 'Números') !== false): ?>
                                <div class="lesson-content">
                                    <h4>Números Naturais</h4>
                                    <p>Os números naturais são os números que usamos para contar: 1, 2, 3, 4, 5, ...</p>
                                    
                                    <h4>Operações Básicas</h4>
                                    <ul>
                                        <li><strong>Adição (+):</strong> Juntar quantidades. Exemplo: 3 + 2 = 5</li>
                                        <li><strong>Subtração (-):</strong> Tirar quantidades. Exemplo: 5 - 2 = 3</li>
                                        <li><strong>Multiplicação (×):</strong> Somar várias vezes. Exemplo: 3 × 2 = 6</li>
                                        <li><strong>Divisão (÷):</strong> Dividir em partes iguais. Exemplo: 6 ÷ 2 = 3</li>
                                    </ul>
                                    
                                    <h4>Exercício Prático</h4>
                                    <p>Resolva: Se você tem 8 maçãs e come 3, quantas maçãs restam?</p>
                                    <p><em>Resposta: 8 - 3 = 5 maçãs</em></p>
                                </div>
                            <?php elseif (strpos($activity['title'], 'Alfabeto') !== false): ?>
                                <div class="lesson-content">
                                    <h4>O Alfabeto Português</h4>
                                    <p>O alfabeto português tem 26 letras:</p>
                                    <p><strong>A B C D E F G H I J K L M N O P Q R S T U V W X Y Z</strong></p>
                                    
                                    <h4>Vogais e Consoantes</h4>
                                    <ul>
                                        <li><strong>Vogais:</strong> A, E, I, O, U</li>
                                        <li><strong>Consoantes:</strong> Todas as outras letras</li>
                                    </ul>
                                    
                                    <h4>Sons das Letras</h4>
                                    <p>Cada letra tem um som específico. Algumas letras podem ter sons diferentes dependendo da palavra.</p>
                                </div>
                            <?php elseif (strpos($activity['title'], 'Corpo Humano') !== false): ?>
                                <div class="lesson-content">
                                    <h4>Sistemas do Corpo Humano</h4>
                                    <ul>
                                        <li><strong>Sistema Respiratório:</strong> Responsável pela respiração</li>
                                        <li><strong>Sistema Circulatório:</strong> Transporta sangue pelo corpo</li>
                                        <li><strong>Sistema Digestivo:</strong> Processa os alimentos</li>
                                        <li><strong>Sistema Nervoso:</strong> Controla as funções do corpo</li>
                                    </ul>
                                    
                                    <h4>Órgãos Importantes</h4>
                                    <p>Coração, pulmões, estômago, cérebro, fígado e rins são alguns dos órgãos mais importantes.</p>
                                </div>
                            <?php else: ?>
                                <div class="lesson-content">
                                    <h4>Conteúdo de Estudo</h4>
                                    <p>Este é um exemplo de conteúdo educativo para a atividade "<?php echo htmlspecialchars($activity['title']); ?>".</p>
                                    
                                    <h4>Objetivos de Aprendizagem</h4>
                                    <ul>
                                        <li>Compreender os conceitos fundamentais</li>
                                        <li>Aplicar o conhecimento em situações práticas</li>
                                        <li>Desenvolver habilidades de resolução de problemas</li>
                                    </ul>
                                    
                                    <h4>Atividade Prática</h4>
                                    <p>Leia o conteúdo com atenção e reflita sobre como aplicar esses conhecimentos no seu dia a dia.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="activity-actions">
                <div class="activity-flow">
                    <div class="flow-step">
                        <i class='bx bx-book-open'></i>
                        <p>1. Estude o material</p>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-step">
                        <i class='bx bx-question-mark'></i>
                        <p>2. Faça o quiz</p>
                    </div>
                    <div class="flow-arrow">→</div>
                    <div class="flow-step">
                        <i class='bx bx-check-circle'></i>
                        <p>3. Atividade concluída</p>
                    </div>
                </div>
                
                <div class="completion-info">
                    <i class='bx bx-info-circle'></i>
                    <p>Você pode fazer o quiz a qualquer momento, mesmo sem ter lido o PDF completamente. O material estará sempre disponível para consulta!</p>
                </div>
                
                <div class="action-buttons">
                    <a href="quiz_new.php?activity_id=<?php echo $activity_id; ?>" class="btn-quiz">
                        <i class='bx bx-play-circle'></i> 
                        <?php echo $is_completed ? 'Refazer Quiz' : 'Fazer Quiz'; ?>
                    </a>
                    
                    <?php if ($is_completed): ?>
                        <a href="track_activities.php?track_id=<?php echo $activity['track_id']; ?>" class="btn-continue">
                            <i class='bx bx-arrow-back'></i> Voltar à Trilha
                        </a>
                    <?php endif; ?>
                </div>
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
        .flash-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .activity-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }

        .activity-header {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
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

        .activity-header h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .completion-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .activity-content {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .content-section {
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .content-section:last-child {
            border-bottom: none;
        }

        .content-section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }

        .study-content {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
        }

        .pdf-content {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .pdf-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #007bff;
            color: white;
        }

        .pdf-header i {
            font-size: 24px;
            margin-right: 10px;
        }

        .pdf-header h3 {
            margin: 0;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-download:hover {
            background: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            color: white;
        }

        .pdf-embed {
            padding: 0;
            background: white;
        }

        .pdf-embed iframe {
            display: block;
            width: 100%;
            min-height: 600px;
        }

        .external-content {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }

        .external-content i {
            font-size: 24px;
            color: #2196f3;
        }

        .external-content a {
            color: #1976d2;
            text-decoration: none;
            font-weight: 500;
        }

        .external-content a:hover {
            text-decoration: underline;
        }

        .lesson-content h3 {
            color: #333;
            margin-bottom: 20px;
        }

        .lesson-content h4 {
            color: #007bff;
            margin: 20px 0 10px 0;
        }

        .lesson-content p {
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .lesson-content ul {
            margin-bottom: 20px;
        }

        .lesson-content li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .activity-actions {
            padding: 30px;
            text-align: center;
        }

        .completion-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 8px;
            color: #2d5a2d;
            border-left: 4px solid #28a745;
        }

        .completion-info i {
            font-size: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-quiz {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #007bff;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: background 0.2s;
        }

        .btn-quiz:hover {
            background: #0056b3;
            text-decoration: none;
            color: white;
        }

        .btn-quiz i {
            font-size: 18px;
        }

        .btn-continue {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #6c757d;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: background 0.2s;
        }

        .btn-continue:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
        }

        @media (max-width: 768px) {
            .activity-container {
                padding: 15px;
            }
            
            .activity-header, .content-section, .activity-actions {
                padding: 20px;
            }
            
            .breadcrumb {
                flex-wrap: wrap;
            }
        }
    </style>
</body>
</html>


        .activity-flow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .flow-step {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 120px;
        }

        .flow-step i {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 8px;
            display: block;
        }

        .flow-step p {
            margin: 0;
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .flow-arrow {
            font-size: 20px;
            color: #007bff;
            font-weight: bold;
        }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-quiz, .btn-continue {
                width: 100%;
                max-width: 300px;
            }

        .btn-quiz {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #007bff;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: background 0.2s;
        }

        .btn-quiz:hover {
            background: #0056b3;
            text-decoration: none;
            color: white;
        }

        .btn-quiz i {
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .activity-flow {
                flex-direction: column;
                gap: 15px;
            }
            
            .flow-arrow {
                transform: rotate(90deg);
            }
            
            .flow-step {
                min-width: auto;
                width: 100%;
            }
        }

