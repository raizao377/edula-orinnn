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
$tentativa_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tentativa_id) {
    setFlashMessage('Resultado não encontrado.', 'danger');
    header("Location: provas_disponiveis.php");
    exit();
}

// Buscar dados da tentativa
try {
    $stmt = $pdo->prepare("
        SELECT 
            pt.*,
            p.titulo as prova_titulo,
            p.descricao as prova_descricao,
            p.materia,
            p.tipo,
            u.nome as professor_nome
        FROM prova_tentativas pt
        JOIN provas p ON pt.prova_id = p.id
        JOIN users u ON p.professor_id = u.id
        WHERE pt.id = ? AND pt.aluno_id = ? AND pt.status = 'concluida'
    ");
    $stmt->execute([$tentativa_id, $aluno_id]);
    $tentativa = $stmt->fetch();
    
    if (!$tentativa) {
        setFlashMessage('Resultado não encontrado ou prova não concluída.', 'danger');
        header("Location: provas_disponiveis.php");
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('Erro ao carregar resultado: ' . $e->getMessage(), 'danger');
    header("Location: provas_disponiveis.php");
    exit();
}

// Buscar questões e respostas
try {
    $stmt = $pdo->prepare("
        SELECT 
            pq.*,
            pr.resposta_escolhida,
            pr.pontuacao_obtida,
            pr.correta
        FROM prova_questoes pq
        LEFT JOIN prova_respostas pr ON pq.id = pr.questao_id AND pr.tentativa_id = ?
        WHERE pq.prova_id = ?
        ORDER BY pq.ordem ASC
    ");
    $stmt->execute([$tentativa_id, $tentativa['prova_id']]);
    $questoes = $stmt->fetchAll();
} catch (PDOException $e) {
    $questoes = [];
    setFlashMessage('Erro ao carregar questões: ' . $e->getMessage(), 'danger');
}

// Calcular estatísticas
$total_questoes = count($questoes);
$questoes_corretas = count(array_filter($questoes, function($q) { return $q['correta']; }));
$questoes_erradas = $total_questoes - $questoes_corretas;
$aprovado = $tentativa['percentual'] >= 60;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado da Prova - <?php echo htmlspecialchars($tentativa['prova_titulo']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .resultado-header {
            background: linear-gradient(45deg, <?php echo $aprovado ? '#28a745, #34ce57' : '#dc3545, #e74c3c'; ?>);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .resultado-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.5em;
        }
        
        .resultado-header .status {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .resultado-info {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border-left: 5px solid #0ef;
        }
        
        .stat-card.corretas {
            border-left-color: #28a745;
        }
        
        .stat-card.erradas {
            border-left-color: #dc3545;
        }
        
        .stat-card.tempo {
            border-left-color: #ffc107;
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-number.corretas {
            color: #28a745;
        }
        
        .stat-number.erradas {
            color: #dc3545;
        }
        
        .stat-number.tempo {
            color: #ffc107;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1em;
        }
        
        .questoes-detalhes {
            margin-top: 40px;
        }
        
        .questoes-detalhes h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #0ef;
        }
        
        .questao {
            background: #fff;
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        
        .questao.correta {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .questao.errada {
            border-color: #dc3545;
            background: #fff8f8;
        }
        
        .questao-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .questao-numero {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2em;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .questao-numero.correta {
            background: #28a745;
            color: white;
        }
        
        .questao-numero.errada {
            background: #dc3545;
            color: white;
        }
        
        .questao-texto {
            flex: 1;
            font-size: 1.1em;
            color: #333;
            line-height: 1.6;
        }
        
        .questao-pontos {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
            margin-left: 15px;
        }
        
        .opcoes {
            margin-left: 60px;
            margin-bottom: 20px;
        }
        
        .opcao {
            margin: 10px 0;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        
        .opcao.correta {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .opcao.escolhida {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        
        .opcao.escolhida.correta {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .opcao-letra {
            font-weight: bold;
            margin-right: 15px;
            width: 25px;
        }
        
        .opcao-texto {
            flex: 1;
        }
        
        .opcao-status {
            margin-left: 15px;
            font-weight: bold;
        }
        
        .resultado-questao {
            margin-left: 60px;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .resultado-questao.correta {
            background: #d4edda;
            color: #155724;
        }
        
        .resultado-questao.errada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            text-align: center;
            margin-top: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 0 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
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
            <li><a href="materiais_aluno.php"><i class='bx bx-folder'></i> Materiais</a></li>
            <li><a href="study_tracks.php"><i class='bx bx-map'></i> Trilhas</a></li>
            <li><a href="logout.php"><i class='bx bx-log-out'></i> Sair</a></li>
        </ul>
    </div>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="resultado-header">
            <h1><?php echo htmlspecialchars($tentativa['prova_titulo']); ?></h1>
            <div class="status">
                <i class='bx <?php echo $aprovado ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i>
                <?php echo $aprovado ? 'APROVADO' : 'REPROVADO'; ?>
            </div>
            
            <div class="resultado-info">
                <div class="info-item">
                    <div class="info-number"><?php echo number_format($tentativa['percentual'], 1); ?>%</div>
                    <div class="info-label">Percentual</div>
                </div>
                <div class="info-item">
                    <div class="info-number"><?php echo $tentativa['pontuacao_total']; ?></div>
                    <div class="info-label">Pontuação</div>
                </div>
                <div class="info-item">
                    <div class="info-number"><?php echo $questoes_corretas; ?>/<?php echo $total_questoes; ?></div>
                    <div class="info-label">Acertos</div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card corretas">
                <div class="stat-number corretas"><?php echo $questoes_corretas; ?></div>
                <div class="stat-label">Questões Corretas</div>
            </div>
            <div class="stat-card erradas">
                <div class="stat-number erradas"><?php echo $questoes_erradas; ?></div>
                <div class="stat-label">Questões Erradas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $tentativa['pontuacao_maxima']; ?></div>
                <div class="stat-label">Pontuação Máxima</div>
            </div>
            <div class="stat-card tempo">
                <div class="stat-number tempo"><?php echo date('d/m/Y', strtotime($tentativa['concluida_em'])); ?></div>
                <div class="stat-label">Data de Conclusão</div>
            </div>
        </div>

        <div class="questoes-detalhes">
            <h2><i class='bx bx-list-check'></i> Detalhes das Questões</h2>
            
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="questao <?php echo $questao['correta'] ? 'correta' : 'errada'; ?>">
                    <div class="questao-header">
                        <div class="questao-numero <?php echo $questao['correta'] ? 'correta' : 'errada'; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="questao-texto"><?php echo htmlspecialchars($questao['questao']); ?></div>
                        <div class="questao-pontos"><?php echo $questao['pontuacao']; ?> pts</div>
                    </div>

                    <div class="opcoes">
                        <?php 
                        $opcoes = ['a' => $questao['opcao_a'], 'b' => $questao['opcao_b'], 'c' => $questao['opcao_c'], 'd' => $questao['opcao_d']];
                        foreach ($opcoes as $letra => $texto): 
                            $eh_correta = ($letra === $questao['resposta_correta']);
                            $foi_escolhida = ($letra === $questao['resposta_escolhida']);
                            
                            $classe = '';
                            if ($eh_correta) {
                                $classe = 'correta';
                            } elseif ($foi_escolhida) {
                                $classe = 'escolhida';
                            }
                        ?>
                            <div class="opcao <?php echo $classe; ?>">
                                <span class="opcao-letra"><?php echo strtoupper($letra); ?>)</span>
                                <span class="opcao-texto"><?php echo htmlspecialchars($texto); ?></span>
                                <?php if ($eh_correta): ?>
                                    <span class="opcao-status"><i class='bx bx-check'></i> Correta</span>
                                <?php elseif ($foi_escolhida): ?>
                                    <span class="opcao-status"><i class='bx bx-x'></i> Sua resposta</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="resultado-questao <?php echo $questao['correta'] ? 'correta' : 'errada'; ?>">
                        <i class='bx <?php echo $questao['correta'] ? 'bx-check-circle' : 'bx-x-circle'; ?>'></i>
                        <?php if ($questao['correta']): ?>
                            Parabéns! Você acertou esta questão e ganhou <?php echo $questao['pontuacao_obtida']; ?> ponto(s).
                        <?php else: ?>
                            Você errou esta questão. A resposta correta era a opção <?php echo strtoupper($questao['resposta_correta']); ?>.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <a href="provas_disponiveis.php" class="btn btn-primary">
                <i class='bx bx-arrow-back'></i> Voltar às Provas
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class='bx bx-printer'></i> Imprimir Resultado
            </button>
        </div>
    </div>
</body>
</html>

