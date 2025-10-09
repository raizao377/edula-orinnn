<?php
require_once 'includes/session.php';
require_once 'config/database.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Criar a conexão
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erro: não foi possível conectar ao banco de dados.");
}
// Verificar se está logado e é usuário
requireLogin();
if (!isUser()) {
    setFlashMessage('Acesso negado. Área restrita para alunos.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$aluno_id = $_SESSION['user_id'];

// Parâmetros de busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$materia_filtro = isset($_GET['materia']) ? trim($_GET['materia']) : '';
$tipo_filtro = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

// Buscar categorias para filtro
try {
    $stmt = $pdo->query("SELECT * FROM material_categorias ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $categorias = [];
}

// Buscar matérias disponíveis
try {
    $stmt = $pdo->query("SELECT DISTINCT materia FROM materiais_didaticos WHERE ativo = 1 AND publico = 1 ORDER BY materia");
    $materias = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $materias = [];
}

// Construir query de busca
$where_conditions = ["md.ativo = 1", "md.publico = 1"];
$params = [];

// Verificar acesso específico do aluno
$where_conditions[] = "(ma.aluno_id IS NULL OR ma.aluno_id = ?)";
$params[] = $aluno_id;

if (!empty($busca)) {
    $where_conditions[] = "(md.titulo LIKE ? OR md.descricao LIKE ? OR mt.tag LIKE ?)";
    $busca_param = '%' . $busca . '%';
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

if ($categoria_filtro > 0) {
    $where_conditions[] = "md.categoria_id = ?";
    $params[] = $categoria_filtro;
}

if (!empty($materia_filtro)) {
    $where_conditions[] = "md.materia = ?";
    $params[] = $materia_filtro;
}

if (!empty($tipo_filtro)) {
    $where_conditions[] = "md.tipo_arquivo = ?";
    $params[] = $tipo_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

// Buscar materiais disponíveis
try {
    $stmt = $pdo->prepare("
        SELECT 
            md.*,
            mc.nome as categoria_nome,
            mc.cor as categoria_cor,
            mc.icone as categoria_icone,
            u.nome as professor_nome,
            GROUP_CONCAT(DISTINCT mt.tag) as tags
        FROM materiais_didaticos md
        LEFT JOIN material_categorias mc ON md.categoria_id = mc.id
        LEFT JOIN material_acesso ma ON md.id = ma.material_id
        LEFT JOIN users u ON md.professor_id = u.id
        LEFT JOIN material_tags mt ON md.id = mt.material_id
        WHERE $where_clause
        AND (ma.data_inicio IS NULL OR ma.data_inicio <= NOW())
        AND (ma.data_fim IS NULL OR ma.data_fim >= NOW())
        GROUP BY md.id
        ORDER BY md.created_at DESC
    ");
    $stmt->execute($params);
    $materiais = $stmt->fetchAll();
} catch (PDOException $e) {
    $materiais = [];
    setFlashMessage('Erro ao carregar materiais: ' . $e->getMessage(), 'danger');
}

// Registrar visualização se for um material específico
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $material_id = intval($_GET['view']);
    try {
        // Incrementar contador de visualizações
        $stmt = $pdo->prepare("UPDATE materiais_didaticos SET visualizacoes = visualizacoes + 1 WHERE id = ?");
        $stmt->execute([$material_id]);
        
        // Registrar no histórico
        $stmt = $pdo->prepare("INSERT INTO material_historico (material_id, aluno_id, acao, ip_address, user_agent) VALUES (?, ?, 'visualizacao', ?, ?)");
        $stmt->execute([$material_id, $aluno_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (PDOException $e) {
        // Erro silencioso para não interromper a experiência do usuário
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiais Didáticos - Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/cod.aluno.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .page-header {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.8em;
        }
        
        .page-header p {
            margin: 0;
            font-size: 1.3em;
            opacity: 0.9;
        }
        
        .search-filters {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .search-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 0.9em;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0ef;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
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
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .results-count {
            font-size: 1.1em;
            color: #666;
        }
        
        .view-toggle {
            display: flex;
            gap: 10px;
        }
        
        .view-btn {
            padding: 8px 12px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .view-btn.active {
            border-color: #0ef;
            background: #0ef;
            color: white;
        }
        
        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .material-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .material-card {
            background: #fff;
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .material-card:hover {
            border-color: #0ef;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .material-card.list-view {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 20px 25px;
        }
        
        .material-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .material-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .material-icon i {
            font-size: 28px;
            color: white;
        }
        
        .material-info {
            flex: 1;
        }
        
        .material-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.4em;
        }
        
        .material-info p {
            margin: 0 0 15px 0;
            color: #666;
            line-height: 1.5;
        }
        
        .material-meta {
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
            gap: 6px;
        }
        
        .material-tags {
            margin: 15px 0;
        }
        
        .tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin: 2px 4px 2px 0;
        }
        
        .material-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .material-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 5em;
            color: #ddd;
            margin-bottom: 25px;
        }
        
        .empty-state h3 {
            margin: 0 0 15px 0;
            font-size: 1.8em;
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
        
        .navbar a:hover,
        .navbar a.active {
            background: #0ef;
        }
        
        @media (max-width: 768px) {
            .search-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .material-grid {
                grid-template-columns: 1fr;
            }
            
            .material-card.list-view {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <ul>
            <li><a href="area_user.php"><i class='bx bx-home'></i> Início</a></li>
            <li><a href="provas_disponiveis.php"><i class='bx bx-book-bookmark'></i> Provas</a></li>
            <li><a href="materiais_aluno.php" class="active"><i class='bx bx-folder'></i> Materiais</a></li>
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
            <h1><i class='bx bx-folder-open'></i> Materiais Didáticos</h1>
            <p>Explore recursos educacionais e materiais de apoio para seus estudos</p>
        </div>

        <div class="search-filters">
            <form method="GET" action="">
                <div class="search-row">
                    <div class="form-group">
                        <label for="busca">Buscar materiais</label>
                        <input type="text" id="busca" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Digite palavras-chave, título ou tags...">
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria">Categoria</label>
                        <select id="categoria" name="categoria">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" <?php echo $categoria_filtro == $categoria['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="materia">Matéria</label>
                        <select id="materia" name="materia">
                            <option value="">Todas as matérias</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?php echo htmlspecialchars($materia); ?>" <?php echo $materia_filtro === $materia ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($materia); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <option value="">Todos os tipos</option>
                            <option value="pdf" <?php echo $tipo_filtro === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                            <option value="doc" <?php echo $tipo_filtro === 'doc' ? 'selected' : ''; ?>>DOC</option>
                            <option value="ppt" <?php echo $tipo_filtro === 'ppt' ? 'selected' : ''; ?>>PPT</option>
                            <option value="video" <?php echo $tipo_filtro === 'video' ? 'selected' : ''; ?>>Vídeo</option>
                            <option value="audio" <?php echo $tipo_filtro === 'audio' ? 'selected' : ''; ?>>Áudio</option>
                            <option value="imagem" <?php echo $tipo_filtro === 'imagem' ? 'selected' : ''; ?>>Imagem</option>
                            <option value="link" <?php echo $tipo_filtro === 'link' ? 'selected' : ''; ?>>Link</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-search'></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($busca) || $categoria_filtro || !empty($materia_filtro) || !empty($tipo_filtro)): ?>
                <div style="margin-top: 15px;">
                    <a href="materiais_aluno.php" class="btn btn-secondary btn-small">
                        <i class='bx bx-x'></i> Limpar Filtros
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="results-header">
            <div class="results-count">
                <strong><?php echo count($materiais); ?></strong> material(is) encontrado(s)
            </div>
            
            <div class="view-toggle">
                <button class="view-btn active" onclick="toggleView('grid')" id="grid-btn">
                    <i class='bx bx-grid-alt'></i>
                </button>
                <button class="view-btn" onclick="toggleView('list')" id="list-btn">
                    <i class='bx bx-list-ul'></i>
                </button>
            </div>
        </div>

        <?php if (empty($materiais)): ?>
            <div class="empty-state">
                <i class='bx bx-folder-open'></i>
                <h3>Nenhum material encontrado</h3>
                <p>Não foram encontrados materiais com os critérios de busca especificados.</p>
                <?php if (!empty($busca) || $categoria_filtro || !empty($materia_filtro) || !empty($tipo_filtro)): ?>
                    <p>Tente ajustar seus filtros ou <a href="materiais_aluno.php">ver todos os materiais</a>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div id="materials-container" class="material-grid">
                <?php foreach ($materiais as $material): ?>
                    <div class="material-card" onclick="viewMaterial(<?php echo $material['id']; ?>)">
                        <div class="material-header">
                            <div class="material-icon" style="background: <?php echo $material['categoria_cor'] ?: '#0ef'; ?>">
                                <i class='bx <?php echo $material['categoria_icone'] ?: 'bx-file'; ?>'></i>
                            </div>
                            <div class="material-info">
                                <h3><?php echo htmlspecialchars($material['titulo']); ?></h3>
                                <p><?php echo htmlspecialchars($material['categoria_nome'] ?: 'Sem categoria'); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($material['descricao'])): ?>
                            <p><?php echo htmlspecialchars(substr($material['descricao'], 0, 150)) . (strlen($material['descricao']) > 150 ? '...' : ''); ?></p>
                        <?php endif; ?>
                        
                        <div class="material-meta">
                            <div class="meta-item">
                                <i class='bx bx-book'></i>
                                <span><?php echo htmlspecialchars($material['materia']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-user'></i>
                                <span>Prof. <?php echo htmlspecialchars($material['professor_nome']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class='bx bx-file'></i>
                                <span><?php echo strtoupper($material['tipo_arquivo']); ?></span>
                            </div>
                            <?php if ($material['tamanho_arquivo'] > 0): ?>
                                <div class="meta-item">
                                    <i class='bx bx-data'></i>
                                    <span><?php echo formatFileSize($material['tamanho_arquivo']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($material['tags'])): ?>
                            <div class="material-tags">
                                <?php foreach (explode(',', $material['tags']) as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="material-stats">
                            <div class="stat-item">
                                <i class='bx bx-show'></i>
                                <span><?php echo $material['visualizacoes']; ?> visualizações</span>
                            </div>
                            <div class="stat-item">
                                <i class='bx bx-download'></i>
                                <span><?php echo $material['downloads']; ?> downloads</span>
                            </div>
                        </div>
                        
                        <div class="material-actions" onclick="event.stopPropagation()">
                            <?php if ($material['tipo_arquivo'] !== 'link'): ?>
                                <a href="<?php echo htmlspecialchars($material['caminho_arquivo']); ?>?view=<?php echo $material['id']; ?>" target="_blank" class="btn btn-info btn-small">
                                    <i class='bx bx-show'></i> Visualizar
                                </a>
                                <a href="download_material.php?id=<?php echo $material['id']; ?>" class="btn btn-success btn-small">
                                    <i class='bx bx-download'></i> Download
                                </a>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($material['url_externa']); ?>?view=<?php echo $material['id']; ?>" target="_blank" class="btn btn-info btn-small">
                                    <i class='bx bx-link-external'></i> Acessar Link
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleView(viewType) {
            const container = document.getElementById('materials-container');
            const gridBtn = document.getElementById('grid-btn');
            const listBtn = document.getElementById('list-btn');
            const cards = document.querySelectorAll('.material-card');
            
            if (viewType === 'grid') {
                container.className = 'material-grid';
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
                cards.forEach(card => card.classList.remove('list-view'));
            } else {
                container.className = 'material-list';
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
                cards.forEach(card => card.classList.add('list-view'));
            }
        }
        
        function viewMaterial(materialId) {
            // Esta função pode ser expandida para mostrar detalhes do material
            console.log('Visualizando material:', materialId);
        }
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

