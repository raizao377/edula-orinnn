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
    // Buscar dados completos para o relatório
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nome,
            u.matricula,
            u.created_at as data_cadastro,
            COUNT(DISTINCT up.activity_id) as atividades_iniciadas,
            COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.activity_id END) as atividades_concluidas,
            COUNT(DISTINCT qr.id) as quizzes_respondidos,
            ROUND(AVG(CASE WHEN qr.is_correct = 1 THEN 100 ELSE 0 END), 2) as media_quizzes,
            MAX(COALESCE(up.completed_at, up.started_at)) as ultima_atividade
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN quiz_responses qr ON u.id = qr.user_id
        WHERE u.role = 'user'
        GROUP BY u.id, u.nome, u.matricula, u.created_at
        ORDER BY u.nome
    ");
    $stmt->execute();
    $students_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados das trilhas
    $stmt = $pdo->prepare("
        SELECT 
            st.id,
            st.name,
            st.description,
            COUNT(DISTINCT ta.id) as total_atividades,
            COUNT(DISTINCT up.user_id) as alunos_inscritos,
            COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.user_id END) as alunos_concluiram,
            ROUND(AVG(CASE WHEN qr.is_correct = 1 THEN 100 ELSE 0 END), 2) as media_quiz_trilha
        FROM study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        LEFT JOIN user_progress up ON ta.id = up.activity_id
        LEFT JOIN quiz_responses qr ON ta.id = qr.activity_id
        GROUP BY st.id, st.name, st.description
        ORDER BY st.id
    ");
    $stmt->execute();
    $tracks_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar estatísticas gerais
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_alunos,
            COUNT(DISTINCT st.id) as total_trilhas,
            COUNT(DISTINCT ta.id) as total_atividades,
            COUNT(DISTINCT qr.id) as total_respostas_quiz,
            ROUND(AVG(CASE WHEN qr.is_correct = 1 THEN 100 ELSE 0 END), 2) as media_geral_quizzes
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN track_activities ta ON up.activity_id = ta.id
        LEFT JOIN study_tracks st ON ta.track_id = st.id
        LEFT JOIN quiz_responses qr ON u.id = qr.user_id
        WHERE u.role = 'user'
    ");
    $stmt->execute();
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

// Gerar conteúdo do relatório em HTML
$report_date = date('d/m/Y H:i:s');
$report_html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Relatório Completo - Edula</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #667eea; margin: 0; }
        .header p { color: #666; margin: 5px 0; }
        .section { margin-bottom: 30px; }
        .section h2 { color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #667eea; }
        .stat-box h3 { margin: 0; font-size: 2em; color: #667eea; }
        .stat-box p { margin: 5px 0 0 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; color: #2c3e50; }
        tr:hover { background-color: #f5f5f5; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .progress-bar { background: #e9ecef; border-radius: 4px; height: 20px; position: relative; }
        .progress-fill { background: #28a745; height: 100%; border-radius: 4px; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 0.9em; border-top: 1px solid #ddd; padding-top: 20px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>Relatório Completo - Sistema Edula</h1>
        <p>Relatório gerado em: {$report_date}</p>
        <p>Professor: " . htmlspecialchars($_SESSION['nome']) . "</p>
    </div>

    <div class='section'>
        <h2>Resumo Executivo</h2>
        <div class='stats-grid'>
            <div class='stat-box'>
                <h3>{$general_stats['total_alunos']}</h3>
                <p>Alunos Cadastrados</p>
            </div>
            <div class='stat-box'>
                <h3>{$general_stats['total_trilhas']}</h3>
                <p>Trilhas de Estudo</p>
            </div>
            <div class='stat-box'>
                <h3>{$general_stats['total_atividades']}</h3>
                <p>Atividades Disponíveis</p>
            </div>
            <div class='stat-box'>
                <h3>{$general_stats['total_respostas_quiz']}</h3>
                <p>Quizzes Respondidos</p>
            </div>
        </div>
        <p><strong>Média Geral dos Quizzes:</strong> " . ($general_stats['media_geral_quizzes'] ?: 'N/A') . "%</p>
    </div>

    <div class='section'>
        <h2>Desempenho por Trilha de Estudo</h2>
        <table>
            <thead>
                <tr>
                    <th>Trilha</th>
                    <th>Atividades</th>
                    <th>Alunos Inscritos</th>
                    <th>Concluíram</th>
                    <th>Taxa de Conclusão</th>
                    <th>Média Quiz</th>
                </tr>
            </thead>
            <tbody>";

foreach ($tracks_data as $track) {
    $completion_rate = $track['alunos_inscritos'] > 0 ? 
        round(($track['alunos_concluiram'] / $track['alunos_inscritos']) * 100, 1) : 0;
    
    $report_html .= "
                <tr>
                    <td><strong>" . htmlspecialchars($track['name']) . "</strong><br>
                        <small>" . htmlspecialchars(substr($track['description'], 0, 80)) . "...</small></td>
                    <td>{$track['total_atividades']}</td>
                    <td>{$track['alunos_inscritos']}</td>
                    <td>{$track['alunos_concluiram']}</td>
                    <td>{$completion_rate}%</td>
                    <td>" . ($track['media_quiz_trilha'] ?: 'N/A') . "%</td>
                </tr>";
}

$report_html .= "
            </tbody>
        </table>
    </div>

    <div class='section'>
        <h2>Relatório Detalhado por Aluno</h2>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Matrícula</th>
                    <th>Data Cadastro</th>
                    <th>Atividades Iniciadas</th>
                    <th>Atividades Concluídas</th>
                    <th>Quizzes Respondidos</th>
                    <th>Média Quizzes</th>
                    <th>Última Atividade</th>
                </tr>
            </thead>
            <tbody>";

foreach ($students_data as $student) {
    $completion_rate = $student['atividades_iniciadas'] > 0 ? 
        round(($student['atividades_concluidas'] / $student['atividades_iniciadas']) * 100, 1) : 0;
    
    $report_html .= "
                <tr>
                    <td>" . htmlspecialchars($student['nome']) . "</td>
                    <td>" . htmlspecialchars($student['matricula']) . "</td>
                    <td>" . date('d/m/Y', strtotime($student['data_cadastro'])) . "</td>
                    <td>{$student['atividades_iniciadas']}</td>
                    <td>{$student['atividades_concluidas']} ({$completion_rate}%)</td>
                    <td>{$student['quizzes_respondidos']}</td>
                    <td>" . ($student['media_quizzes'] ?: 'N/A') . "%</td>
                    <td>" . ($student['ultima_atividade'] ? date('d/m/Y H:i', strtotime($student['ultima_atividade'])) : 'Nunca') . "</td>
                </tr>";
}

$report_html .= "
            </tbody>
        </table>
    </div>

    <div class='footer'>
        <p>Relatório gerado automaticamente pelo Sistema Edula</p>
        <p>© 2025 Edula - Plataforma de Ensino Digital</p>
    </div>
</body>
</html>";

// Se for solicitado download do PDF
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    // Salvar HTML temporário
    $temp_html = '/tmp/edula_report_' . time() . '.html';
    file_put_contents($temp_html, $report_html);
    
    // Converter para PDF usando wkhtmltopdf (se disponível) ou weasyprint
    $pdf_file = '/tmp/edula_report_' . time() . '.pdf';
    
    // Tentar usar weasyprint (já instalado no ambiente)
    $command = "python3 -c \"
import weasyprint
html = weasyprint.HTML(filename='$temp_html')
html.write_pdf('$pdf_file')
\"";
    
    exec($command, $output, $return_code);
    
    if ($return_code === 0 && file_exists($pdf_file)) {
        // Enviar PDF para download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="relatorio_edula_' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . filesize($pdf_file));
        readfile($pdf_file);
        
        // Limpar arquivos temporários
        unlink($temp_html);
        unlink($pdf_file);
        exit();
    } else {
        // Fallback: mostrar HTML
        echo $report_html;
    }
} else {
    // Mostrar relatório em HTML
    echo $report_html;
}
?>

