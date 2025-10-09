<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Verificar se está logado e é usuário
requireLogin();
if (!isUser()) {
    setFlashMessage('Acesso negado. Área restrita para alunos.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$aluno_id = $_SESSION['user_id'];

// Buscar provas disponíveis para o aluno
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            pd.tentativas_permitidas,
            pd.data_inicio,
            pd.data_fim,
            COUNT(pq.id) as total_questoes,
            SUM(pq.pontuacao) as pontuacao_maxima,
            pt.id as tentativa_id,
            pt.status as tentativa_status,
            pt.pontuacao_total,
            pt.percentual,
            pt.concluida_em,
            u.nome as professor_nome
        FROM provas p
        JOIN prova_disponibilidade pd ON p.id = pd.prova_id
        LEFT JOIN prova_questoes pq ON p.id = pq.prova_id
        LEFT JOIN prova_tentativas pt ON p.id = pt.prova_id AND pt.aluno_id = ?
        LEFT JOIN users u ON p.professor_id = u.id
        WHERE p.ativa = 1 
        AND (pd.aluno_id IS NULL OR pd.aluno_id = ?)
        AND (pd.data_inicio IS NULL OR pd.data_inicio <= NOW())
        AND (pd.data_fim IS NULL OR pd.data_fim >= NOW())
        GROUP BY p.id, pd.id, pt.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$aluno_id, $aluno_id]);
    $provas = $stmt->fetchAll();
} catch (PDOException $e) {
    $provas = [];
    setFlashMessage('Erro ao carregar provas: ' . $e->getMessage(), 'danger');
}

// Separar provas por status
$provas_disponiveis = [];
$provas_concluidas = [];
$provas_iniciadas = [];

foreach ($provas as $prova) {
    if ($prova['tentativa_status'] === 'concluida') {
        $provas_concluidas[] = $prova;
    } elseif ($prova['tentativa_status'] === 'iniciada') {
        $provas_iniciadas[] = $prova;
    } else {
        $provas_disponiveis[] = $prova;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provas Disponíveis - Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
            border-radius: 15px;
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .page-header p {
            margin: 0;
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #0ef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .prova-card {
            background: #f8f9fa;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 15px;
            border-left: 5px solid #0ef;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .prova-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .prova-card.concluida {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        
        .prova-card.iniciada {
            border-left-color: #ffc107;
            background: #fffef8;
        }
        
        .prova-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .prova-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.4em;
        }
        
        .prova-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 15px 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tipo-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .tipo-diagnostica {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .tipo-avaliativa {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-disponivel {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-concluida {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-iniciada {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .prova-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .resultado-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        
        .resultado-info h4 {
            margin: 0 0 10px 0;
            color: #2e7d32;
        }
        
        .resultado-detalhes {
            display: flex;
            gap: 20px;
            font-size: 0.9em;
        }
        
        .flash-message {
            margin: 15px 0;
            padding: 15px;
            border-radius: 8px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4em;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .navbar {
            background: #333;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        .navbar ul {
            list-style: none;
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 0;
            padding: 0;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .navbar a:hover {
            background: #0ef;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <ul>
            <li><a href="area_user.php"><i class='bx bx-home'></i> Início</a></li>
            <li><a href="provas_disponiveis.php"><i class='bx bx-book-bookmark'></i> Provas</a></li>
            <li><a href="study_tracks.php"><i class='bx bx-map'></i> Trilhas</a></li>
            <li><a href="forum.php"><i class='bx bx-chat'></i> Fórum</a></li>
            <li><a href="logout.php"><i class='bx bx-log-out'></i> Sair</a></li>
        </ul>
    </div>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1><i class='bx bx-book-bookmark'></i> Avaliações Diagnósticas</h1>
            <p>Faça as provas disponíveis e acompanhe seu progresso</p>
        </div>

        <!-- Provas Iniciadas -->
        <?php if (!empty($provas_iniciadas)): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class='bx bx-play-circle' style="color: #ffc107;"></i>
                    Provas em Andamento
                </h2>
                
                <?php foreach ($provas_iniciadas as $prova): ?>
                    <div class="prova-card iniciada">
                        <div class="prova-header">
                            <div class="prova-info">
                                <h3><?php echo htmlspecialchars($prova['titulo']); ?></h3>
                                <span class="tipo-badge tipo-<?php echo $prova['tipo']; ?>">
                                    <?php echo ucfirst($prova['tipo']); ?>
                                </span>
                                <span class="status-badge status-iniciada">Em Andamento</span>
                            </div>
                        </div>
                        
                        <div class="prova-meta">
                            <div class="meta-item">
                                <i class='bx bx-book'></i>
                                <span><?php echo htmlspecialchars($prova['materia']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-user'></i>
                                <span>Prof. <?php echo htmlspecialchars($prova['professor_nome']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-list-ol'></i>
                                <span><?php echo $prova['total_questoes']; ?> questões</span>
                            </div>
                            <?php if ($prova['tempo_limite'] > 0): ?>
                                <div class="meta-item">
                                    <i class='bx bx-time'></i>
                                    <span><?php echo $prova['tempo_limite']; ?> minutos</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($prova['descricao'])): ?>
                            <p><?php echo htmlspecialchars($prova['descricao']); ?></p>
                        <?php endif; ?>
                        
                        <div class="prova-actions">
                            <a href="fazer_prova.php?id=<?php echo $prova['id']; ?>" class="btn btn-warning">
                                <i class='bx bx-play'></i> Continuar Prova
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Provas Disponíveis -->
        <?php if (!empty($provas_disponiveis)): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class='bx bx-clipboard' style="color: #0ef;"></i>
                    Provas Disponíveis
                </h2>
                
                <?php foreach ($provas_disponiveis as $prova): ?>
                    <div class="prova-card">
                        <div class="prova-header">
                            <div class="prova-info">
                                <h3><?php echo htmlspecialchars($prova['titulo']); ?></h3>
                                <span class="tipo-badge tipo-<?php echo $prova['tipo']; ?>">
                                    <?php echo ucfirst($prova['tipo']); ?>
                                </span>
                                <span class="status-badge status-disponivel">Disponível</span>
                            </div>
                        </div>
                        
                        <div class="prova-meta">
                            <div class="meta-item">
                                <i class='bx bx-book'></i>
                                <span><?php echo htmlspecialchars($prova['materia']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-user'></i>
                                <span>Prof. <?php echo htmlspecialchars($prova['professor_nome']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-list-ol'></i>
                                <span><?php echo $prova['total_questoes']; ?> questões</span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-award'></i>
                                <span><?php echo $prova['pontuacao_maxima']; ?> pontos</span>
                            </div>
                            <?php if ($prova['tempo_limite'] > 0): ?>
                                <div class="meta-item">
                                    <i class='bx bx-time'></i>
                                    <span><?php echo $prova['tempo_limite']; ?> minutos</span>
                                </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <i class='bx bx-refresh'></i>
                                <span><?php echo $prova['tentativas_permitidas']; ?> tentativa(s)</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($prova['descricao'])): ?>
                            <p><?php echo htmlspecialchars($prova['descricao']); ?></p>
                        <?php endif; ?>
                        
                        <div class="prova-actions">
                            <a href="fazer_prova.php?id=<?php echo $prova['id']; ?>" class="btn btn-primary">
                                <i class='bx bx-play'></i> Iniciar Prova
                            </a>
                            <a href="visualizar_prova.php?id=<?php echo $prova['id']; ?>" class="btn btn-info">
                                <i class='bx bx-show'></i> Visualizar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Provas Concluídas -->
        <?php if (!empty($provas_concluidas)): ?>
            <div class="section">
                <h2 class="section-title">
                    <i class='bx bx-check-circle' style="color: #28a745;"></i>
                    Provas Concluídas
                </h2>
                
                <?php foreach ($provas_concluidas as $prova): ?>
                    <div class="prova-card concluida">
                        <div class="prova-header">
                            <div class="prova-info">
                                <h3><?php echo htmlspecialchars($prova['titulo']); ?></h3>
                                <span class="tipo-badge tipo-<?php echo $prova['tipo']; ?>">
                                    <?php echo ucfirst($prova['tipo']); ?>
                                </span>
                                <span class="status-badge status-concluida">Concluída</span>
                            </div>
                        </div>
                        
                        <div class="prova-meta">
                            <div class="meta-item">
                                <i class='bx bx-book'></i>
                                <span><?php echo htmlspecialchars($prova['materia']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-user'></i>
                                <span>Prof. <?php echo htmlspecialchars($prova['professor_nome']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-calendar'></i>
                                <span>Concluída em <?php echo date('d/m/Y H:i', strtotime($prova['concluida_em'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="resultado-info">
                            <h4><i class='bx bx-trophy'></i> Resultado</h4>
                            <div class="resultado-detalhes">
                                <div><strong>Pontuação:</strong> <?php echo $prova['pontuacao_total']; ?>/<?php echo $prova['pontuacao_maxima']; ?></div>
                                <div><strong>Percentual:</strong> <?php echo number_format($prova['percentual'], 1); ?>%</div>
                                <div><strong>Status:</strong> 
                                    <?php if ($prova['percentual'] >= 60): ?>
                                        <span style="color: #28a745; font-weight: bold;">Aprovado</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: bold;">Reprovado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="prova-actions">
                            <a href="resultado_prova.php?id=<?php echo $prova['tentativa_id']; ?>" class="btn btn-success">
                                <i class='bx bx-bar-chart'></i> Ver Resultado Detalhado
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Estado vazio -->
        <?php if (empty($provas_disponiveis) && empty($provas_concluidas) && empty($provas_iniciadas)): ?>
            <div class="empty-state">
                <i class='bx bx-clipboard'></i>
                <h3>Nenhuma prova disponível</h3>
                <p>Não há provas disponíveis para você no momento. Verifique novamente mais tarde.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

