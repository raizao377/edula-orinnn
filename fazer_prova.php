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
$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$prova_id) {
    setFlashMessage('Prova não encontrada.', 'danger');
    header("Location: provas_disponiveis.php");
    exit();
}

// Carregar prova
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            pd.tentativas_permitidas,
            pd.data_inicio,
            pd.data_fim,
            COUNT(pq.id) as total_questoes,
            SUM(pq.pontuacao) as pontuacao_maxima,
            u.nome as professor_nome
        FROM provas p
        JOIN prova_disponibilidade pd ON p.id = pd.prova_id
        LEFT JOIN prova_questoes pq ON p.id = pq.prova_id
        LEFT JOIN users u ON p.professor_id = u.id
        WHERE p.id = ? 
        AND p.ativa = 1 
        AND (pd.aluno_id IS NULL OR pd.aluno_id = ?)
        AND (pd.data_inicio IS NULL OR pd.data_inicio <= NOW())
        AND (pd.data_fim IS NULL OR pd.data_fim >= NOW())
        GROUP BY p.id, pd.id
    ");
    $stmt->execute([$prova_id, $aluno_id]);
    $prova = $stmt->fetch();

    if (!$prova) {
        setFlashMessage('Prova não encontrada ou não disponível.', 'danger');
        header("Location: provas_disponiveis.php");
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('Erro ao carregar prova: ' . $e->getMessage(), 'danger');
    header("Location: provas_disponiveis.php");
    exit();
}

// Iniciar prova
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'iniciar_prova') {
    try {
     $stmt = $pdo->prepare("INSERT INTO prova_tentativas (prova_id, aluno_id, status, iniciada_em) VALUES (?, ?, 'iniciada', NOW())");
$stmt->execute([$prova_id, $aluno_id]);
$tentativa_id = $pdo->lastInsertId();
$stmt = $pdo->prepare("SELECT * FROM prova_tentativas WHERE id = ?");
$stmt->execute([$tentativa_id]);
$tentativa = $stmt->fetch();

if (!$tentativa) {
    setFlashMessage('Tentativa não encontrada!', 'danger');
    exit();
}


        // Redirecionar para a prova
        header("Location: fazer_prova.php?id=$prova_id&tentativa=$tentativa_id");
        exit();
    } catch (PDOException $e) {
        setFlashMessage('Erro ao iniciar prova: ' . $e->getMessage(), 'danger');
        header("Location: fazer_prova.php?id=$prova_id");
        exit();
    }
}

// Submissão da prova
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submeter_prova') {
    $tentativa_id = intval($_POST['tentativa_id']);
    $respostas = $_POST['respostas'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM prova_questoes WHERE prova_id = ? ORDER BY ordem ASC");
        $stmt->execute([$prova_id]);
        $questoes = $stmt->fetchAll();

        $pontuacao_total = 0;

        foreach ($questoes as $questao) {
            $resposta_escolhida = $respostas[$questao['id']] ?? '';
$correta = ($resposta_escolhida !== '' && $resposta_escolhida === $questao['resposta_correta']) ? 1 : 0;
$pontuacao_obtida = $correta ? $questao['pontuacao'] : 0;

            $pontuacao_total += $pontuacao_obtida;

            $stmt = $pdo->prepare("
                INSERT INTO prova_respostas (tentativa_id, questao_id, resposta_escolhida, pontuacao_obtida, correta) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    resposta_escolhida = VALUES(resposta_escolhida),
                    pontuacao_obtida = VALUES(pontuacao_obtida),
                    correta = VALUES(correta),
                    respondida_em = NOW()
            ");
            $stmt->execute([$tentativa_id, $questao['id'], $resposta_escolhida, $pontuacao_obtida, $correta]);
        }

        $percentual = ($prova['pontuacao_maxima'] > 0) ? ($pontuacao_total / $prova['pontuacao_maxima']) * 100 : 0;

        $stmt = $pdo->prepare("
            UPDATE prova_tentativas 
            SET pontuacao_total = ?, 
                total_questions = ?, 
                percentual = ?, 
                status = 'concluida', 
                concluida_em = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$pontuacao_total, count($questoes), $percentual, $tentativa_id]);

        $pdo->commit();

        setFlashMessage('Prova concluída com sucesso!', 'success');
        header("Location: resultado_prova.php?id=" . $tentativa_id);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlashMessage('Erro ao submeter prova: ' . $e->getMessage(), 'danger');
    }
}

// Se chegou até aqui, verificar se há tentativa em andamento
$tentativa_id = isset($_GET['tentativa']) ? intval($_GET['tentativa']) : null;

if (!$tentativa_id) {
    $modo = 'inicio';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM prova_tentativas WHERE id = ? AND aluno_id = ? AND prova_id = ?");
        $stmt->execute([$tentativa_id, $aluno_id, $prova_id]);
        $tentativa = $stmt->fetch();

        if (!$tentativa || $tentativa['status'] === 'concluida') {
            setFlashMessage('Tentativa inválida ou já concluída.', 'danger');
            header("Location: provas_disponiveis.php");
            exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM prova_questoes WHERE prova_id = ? ORDER BY ordem ASC");
        $stmt->execute([$prova_id]);
        $questoes = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT questao_id, resposta_escolhida FROM prova_respostas WHERE tentativa_id = ?");
        $stmt->execute([$tentativa_id]);
        $respostas_dadas = [];
        foreach ($stmt->fetchAll() as $resposta) {
            $respostas_dadas[$resposta['questao_id']] = $resposta['resposta_escolhida'];
        }

        $modo = 'fazendo';
    } catch (PDOException $e) {
        setFlashMessage('Erro ao carregar tentativa: ' . $e->getMessage(), 'danger');
        header("Location: provas_disponiveis.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($prova['titulo']); ?> - Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <style>
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .prova-header {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .prova-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.2em;
        }

        .prova-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 20px;
            font-size: 1.1em;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instrucoes {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #0ef;
        }

        .instrucoes h3 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .instrucoes ul {
            margin: 10px 0;
            padding-left: 25px;
        }

        .instrucoes li {
            margin: 8px 0;
            color: #666;
        }

        .questao {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            border: 2px solid #eee;
            transition: border-color 0.3s;
        }

        .questao:hover {
            border-color: #0ef;
        }

        .questao-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .questao-numero {
            background: #0ef;
            color: white;
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
        }

        .opcao {
            margin: 12px 0;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }

        .opcao:hover {
            border-color: #0ef;
            background: #f0f9ff;
        }

        .opcao.selecionada {
            border-color: #0ef;
            background: #e3f2fd;
        }

        .opcao input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }

        .opcao-letra {
            font-weight: bold;
            margin-right: 15px;
            color: #0ef;
            font-size: 1.1em;
        }

        .opcao-texto {
            flex: 1;
            color: #333;
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .actions {
            text-align: center;
            margin-top: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border-left: 5px solid #ffc107;
            font-weight: bold;
            color: #333;
            z-index: 1000;
        }

        .timer.urgente {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .progresso {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .progresso-barra {
            background: #e9ecef;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progresso-preenchimento {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            height: 100%;
            transition: width 0.3s;
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
    </style>
</head>

<body>
    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="prova-header">
            <h1><?php echo htmlspecialchars($prova['titulo']); ?></h1>
            <p><?php echo htmlspecialchars($prova['descricao']); ?></p>

            <div class="prova-info">
                <div class="info-item">
                    <i class='bx bx-book'></i>
                    <span><?php echo htmlspecialchars($prova['materia']); ?></span>
                </div>
                <div class="info-item">
                    <i class='bx bx-user'></i>
                    <span>Prof. <?php echo htmlspecialchars($prova['professor_nome']); ?></span>
                </div>
                <div class="info-item">
                    <i class='bx bx-list-ol'></i>
                    <span><?php echo $prova['total_questoes']; ?> questões</span>
                </div>
                <div class="info-item">
                    <i class='bx bx-award'></i>
                    <span><?php echo $prova['pontuacao_maxima']; ?> pontos</span>
                </div>
                <?php if ($prova['tempo_limite'] > 0): ?>
                    <div class="info-item">
                        <i class='bx bx-time'></i>
                        <span><?php echo $prova['tempo_limite']; ?> minutos</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($modo === 'inicio'): ?>
            <!-- Página de início da prova -->
            <div class="instrucoes">
                <h3><i class='bx bx-info-circle'></i> Instruções</h3>
                <ul>
                    <li>Leia cada questão com atenção antes de responder</li>
                    <li>Cada questão possui apenas uma resposta correta</li>
                    <li>Marque a opção que considera correta clicando sobre ela</li>
                    <?php if ($prova['tempo_limite'] > 0): ?>
                        <li>Você tem <strong><?php echo $prova['tempo_limite']; ?> minutos</strong> para completar a prova</li>
                        <li>O tempo será exibido no canto superior direito da tela</li>
                    <?php endif; ?>
                    <li>Você pode revisar suas respostas antes de submeter</li>
                    <li>Após submeter, não será possível alterar as respostas</li>
                    <li>Certifique-se de ter uma conexão estável com a internet</li>
                </ul>
            </div>

            <div class="actions">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="iniciar_prova">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-play'></i> Iniciar Prova
                    </button>
                    <a href="provas_disponiveis.php" class="btn btn-secondary">
                        <i class='bx bx-arrow-back'></i> Voltar
                    </a>
                </form>
            </div>

        <?php else: ?>
            <!-- Fazendo a prova -->
            <?php if ($prova['tempo_limite'] > 0): ?>
                <div class="timer" id="timer">
                    <i class='bx bx-time'></i>
                    <span id="tempo-restante"><?php echo $prova['tempo_limite']; ?>:00</span>
                </div>
            <?php endif; ?>

            <div class="progresso">
                <h4>Progresso da Prova</h4>
                <div class="progresso-barra">
                    <div class="progresso-preenchimento" id="progresso-barra" style="width: 0%"></div>
                </div>
                <span id="progresso-texto">0 de <?php echo count($questoes); ?> questões respondidas</span>
            </div>

            <form method="POST" action="" id="form-prova">
                <input type="hidden" name="action" value="submeter_prova">
                <input type="hidden" name="tentativa_id" value="<?php echo $tentativa_id; ?>">

                <?php foreach ($questoes as $index => $questao): ?>
                    <div class="questao">
                        <div class="questao-header">
                            <div class="questao-numero"><?php echo $index + 1; ?></div>
                            <div class="questao-texto"><?php echo htmlspecialchars($questao['questao']); ?></div>
                            <div class="questao-pontos"><?php echo $questao['pontuacao']; ?> pts</div>
                        </div>

                        <div class="opcoes">
                            <?php
                            $opcoes = ['a' => $questao['opcao_a'], 'b' => $questao['opcao_b'], 'c' => $questao['opcao_c'], 'd' => $questao['opcao_d']];
                            foreach ($opcoes as $letra => $texto):
                                $selecionada = isset($respostas_dadas[$questao['id']]) && $respostas_dadas[$questao['id']] === $letra;
                            ?>
                                <div class="opcao <?php echo $selecionada ? 'selecionada' : ''; ?>" onclick="selecionarOpcao(this, <?php echo $questao['id']; ?>, '<?php echo $letra; ?>')">
                                    <input type="radio"
                                        name="respostas[<?php echo $questao['id']; ?>]"
                                        value="<?php echo $letra; ?>"
                                        id="q<?php echo $questao['id']; ?>_<?php echo $letra; ?>"
                                        <?php echo $selecionada ? 'checked' : ''; ?>>
                                    <span class="opcao-letra"><?php echo strtoupper($letra); ?>)</span>
                                    <span class="opcao-texto"><?php echo htmlspecialchars($texto); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="actions">
                    <button type="submit" class="btn btn-success" onclick="return confirmarSubmissao()">
                        <i class='bx bx-check'></i> Finalizar e Submeter Prova
                    </button>
                    <a href="provas_disponiveis.php" class="btn btn-secondary" onclick="return confirm('Tem certeza que deseja sair? Seu progresso será perdido.')">
                        <i class='bx bx-x'></i> Cancelar
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function selecionarOpcao(elemento, questaoId, letra) {
            // Remover seleção de outras opções da mesma questão
            const questao = elemento.closest('.questao');
            const opcoes = questao.querySelectorAll('.opcao');
            opcoes.forEach(opcao => opcao.classList.remove('selecionada'));

            // Selecionar a opção clicada
            elemento.classList.add('selecionada');
            elemento.querySelector('input[type="radio"]').checked = true;

            // Atualizar progresso
            atualizarProgresso();
        }

        function atualizarProgresso() {
            const totalQuestoes = <?php echo isset($questoes) ? count($questoes) : 0; ?>;
            const questoesRespondidas = document.querySelectorAll('input[type="radio"]:checked').length;
            const percentual = (questoesRespondidas / totalQuestoes) * 100;

            document.getElementById('progresso-barra').style.width = percentual + '%';
            document.getElementById('progresso-texto').textContent = questoesRespondidas + ' de ' + totalQuestoes + ' questões respondidas';
        }

        function confirmarSubmissao() {
            const totalQuestoes = <?php echo isset($questoes) ? count($questoes) : 0; ?>;
            const questoesRespondidas = document.querySelectorAll('input[type="radio"]:checked').length;

            if (questoesRespondidas < totalQuestoes) {
                const naoRespondidas = totalQuestoes - questoesRespondidas;
                return confirm('Você ainda tem ' + naoRespondidas + ' questão(ões) não respondida(s). Deseja realmente finalizar a prova?');
            }

            return confirm('Tem certeza que deseja finalizar e submeter a prova? Esta ação não pode ser desfeita.');
        }

        <?php if ($modo === 'fazendo' && $prova['tempo_limite'] > 0): ?>
            // Timer
            let tempoRestante = <?php echo $prova['tempo_limite'] * 60; ?>; // em segundos

            function atualizarTimer() {
                const minutos = Math.floor(tempoRestante / 60);
                const segundos = tempoRestante % 60;
                const display = minutos + ':' + (segundos < 10 ? '0' : '') + segundos;

                document.getElementById('tempo-restante').textContent = display;

                // Mudar cor quando restam 5 minutos
                if (tempoRestante <= 300) {
                    document.getElementById('timer').classList.add('urgente');
                }

                if (tempoRestante <= 0) {
                    alert('Tempo esgotado! A prova será submetida automaticamente.');
                    document.getElementById('form-prova').submit();
                    return;
                }

                tempoRestante--;
            }

            // Atualizar timer a cada segundo
            setInterval(atualizarTimer, 1000);

            // Atualizar progresso inicial
            atualizarProgresso();
        <?php endif; ?>
    </script>
</body>

</html>