<?php
require_once 'includes/session.php';
require_once 'config/database.php';

requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$professor_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Prova inválida.', 'danger');
    header("Location: area_professor.php");
    exit();
}

$prova_id = intval($_GET['id']);

// Verificar se a prova pertence ao professor
$stmt = $pdo->prepare("SELECT * FROM provas WHERE id = ? AND professor_id = ?");
$stmt->execute([$prova_id, $professor_id]);
$prova = $stmt->fetch();

if (!$prova) {
    setFlashMessage('Prova não encontrada.', 'danger');
    header("Location: area_professor.php");
    exit();
}

// Buscar resultados
$stmt = $pdo->prepare("
    SELECT pt.*, u.nome AS aluno_nome
    FROM prova_tentativas pt
    JOIN users u ON pt.aluno_id = u.id
    WHERE pt.prova_id = ?
    ORDER BY pt.pontuacao_total DESC, pt.concluida_em ASC
");
$stmt->execute([$prova_id]);
$resultados = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - <?php echo htmlspecialchars($prova['titulo']); ?></title>
    <style>
        /* ===== Reset Básico ===== */
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background:#f0f2f5; color:#333; line-height:1.6; }

        /* ===== Header ===== */
        .header { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#0ef; color:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.15);}
        .header .logo img { height:40px; }
        .navbar a { margin-left:20px; color:#fff; text-decoration:none; font-weight:bold; transition:opacity 0.3s; }
        .navbar a:hover { opacity:0.8; }

        /* ===== Container ===== */
        .container { max-width:1100px; margin:30px auto; padding:25px; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }

        /* ===== Títulos ===== */
        h1, h2 { margin-bottom:20px; font-weight:bold; color:#222; }

        /* ===== Flash Messages ===== */
        .flash-message { margin:15px 0; padding:15px; border-radius:8px; text-align:center; font-weight:500; }
        .flash-success { background-color:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .flash-danger { background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .flash-warning { background-color:#fff3cd; color:#856404; border:1px solid #ffeaa7; }

        /* ===== Tabela de Resultados ===== */
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        thead { background:#0ef; color:#fff; }
        thead th { padding:12px; text-align:left; font-weight:600; }
        tbody td { padding:12px; border-bottom:1px solid #ddd; }
        tbody tr:nth-child(even) { background-color:#f9f9f9; }
        tbody tr:hover { background-color:#e0f7ff; }

        /* ===== Status Badges ===== */
        .status { display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; text-transform:capitalize; color:#fff; }
        .status.iniciada { background:#ffc107; }
        .status.concluida { background:#28a745; }
        .status.abandonada { background:#dc3545; }

        /* ===== Responsividade ===== */
        @media (max-width: 768px) {
            .container { padding:15px; }
            table, thead, tbody, th, td, tr { display:block; }
            thead { display:none; }
            tbody tr { margin-bottom:20px; border-bottom:2px solid #0ef; padding:10px; }
            tbody td { padding:10px 5px; text-align:right; position:relative; }
            tbody td::before { content: attr(data-label); position:absolute; left:0; width:50%; font-weight:bold; text-align:left; color:#555; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="static/img/logo.png" alt="Edula Logo">
        </div>
        <nav class="navbar">
            <a href="area_professor.php">Voltar</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <h1>Resultados da Prova: <?php echo htmlspecialchars($prova['titulo']); ?></h1>
        <p><strong>Matéria:</strong> <?php echo htmlspecialchars($prova['materia']); ?></p>

        <?php if (empty($resultados)): ?>
            <p>Nenhum aluno realizou esta prova ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Pontuação Obtida</th>
                        <th>Pontuação Máxima</th>
                        <th>Percentual (%)</th>
                        <th>Tempo Gasto</th>
                        <th>Status</th>
                        <th>Concluída em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $r): ?>
                        <tr>
                            <td data-label="Aluno"><?php echo htmlspecialchars($r['aluno_nome']); ?></td>
                            <td data-label="Pontuação Obtida"><?php echo $r['pontuacao_total']; ?></td>
                            <td data-label="Pontuação Máxima"><?php echo $r['pontuacao_maxima']; ?></td>
                            <td data-label="Percentual (%)"><?php echo $r['percentual']; ?>%</td>
                            <td data-label="Tempo Gasto"><?php echo gmdate("H:i:s", $r['tempo_gasto']); ?></td>
                            <td data-label="Status"><span class="status <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                            <td data-label="Concluída em"><?php echo $r['concluida_em'] ? date('d/m/Y H:i', strtotime($r['concluida_em'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
