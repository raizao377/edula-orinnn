<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Verificar se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$professor_id = $_SESSION['user_id'];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adicionar_material') {
        $titulo = trim($_POST['titulo']);
        $descricao = trim($_POST['descricao']);
        $categoria_id = intval($_POST['categoria_id']);
        $materia = trim($_POST['materia']);
        $tipo_material = $_POST['tipo_material'];
        $publico = isset($_POST['publico']) ? 1 : 0;
        $tags = trim($_POST['tags']);
        
        if (!empty($titulo) && !empty($materia)) {
            try {
                $pdo->beginTransaction();
                
                if ($tipo_material === 'arquivo' && isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                    // Upload de arquivo
                    $arquivo = $_FILES['arquivo'];
                    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                    $tipos_permitidos = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'md', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mp3', 'wav'];
                    
                    if (!in_array($extensao, $tipos_permitidos)) {
                        throw new Exception('Tipo de arquivo não permitido.');
                    }
                    
                    // Criar diretório se não existir
                    $upload_dir = 'static/materials/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Gerar nome único para o arquivo
                    $nome_arquivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $arquivo['name']);
                    $caminho_arquivo = $upload_dir . $nome_arquivo;
                    
                    if (!move_uploaded_file($arquivo['tmp_name'], $caminho_arquivo)) {
                        throw new Exception('Erro ao fazer upload do arquivo.');
                    }
                    
                    // Determinar tipo do arquivo
                    $tipos_arquivo = [
                        'pdf' => 'pdf',
                        'doc' => 'doc', 'docx' => 'docx',
                        'ppt' => 'ppt', 'pptx' => 'pptx',
                        'txt' => 'txt', 'md' => 'md',
                        'mp4' => 'video', 'avi' => 'video',
                        'mp3' => 'audio', 'wav' => 'audio',
                        'jpg' => 'imagem', 'jpeg' => 'imagem', 'png' => 'imagem', 'gif' => 'imagem'
                    ];
                    $tipo_arquivo = $tipos_arquivo[$extensao] ?? 'pdf';
                    
                    $stmt = $pdo->prepare("INSERT INTO materiais_didaticos (titulo, descricao, categoria_id, materia, tipo_arquivo, nome_arquivo, caminho_arquivo, tamanho_arquivo, professor_id, publico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$titulo, $descricao, $categoria_id ?: null, $materia, $tipo_arquivo, $nome_arquivo, $caminho_arquivo, $arquivo['size'], $professor_id, $publico]);
                    
                } elseif ($tipo_material === 'link') {
                    // Link externo
                    $url_externa = trim($_POST['url_externa']);
                    if (empty($url_externa)) {
                        throw new Exception('URL é obrigatória para links externos.');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO materiais_didaticos (titulo, descricao, categoria_id, materia, tipo_arquivo, url_externa, professor_id, publico) VALUES (?, ?, ?, ?, 'link', ?, ?, ?)");
                    $stmt->execute([$titulo, $descricao, $categoria_id ?: null, $materia, $url_externa, $professor_id, $publico]);
                } else {
                    throw new Exception('Tipo de material inválido.');
                }
                
                $material_id = $pdo->lastInsertId();
                
                // Adicionar tags
                if (!empty($tags)) {
                    $tags_array = array_map('trim', explode(',', $tags));
                    foreach ($tags_array as $tag) {
                        if (!empty($tag)) {
                            $stmt = $pdo->prepare("INSERT IGNORE INTO material_tags (material_id, tag) VALUES (?, ?)");
                            $stmt->execute([$material_id, strtolower($tag)]);
                        }
                    }
                }
                
                // Disponibilizar para todos os alunos se público
                if ($publico) {
                    $stmt = $pdo->prepare("INSERT INTO material_acesso (material_id, aluno_id) VALUES (?, NULL)");
                    $stmt->execute([$material_id]);
                }
                
                $pdo->commit();
                setFlashMessage('Material adicionado com sucesso!', 'success');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlashMessage('Erro ao adicionar material: ' . $e->getMessage(), 'danger');
            }
        } else {
            setFlashMessage('Título e matéria são obrigatórios.', 'warning');
        }
    }
    
    if ($_POST['action'] === 'excluir_material') {
        $material_id = intval($_POST['material_id']);
        try {
            // Buscar material para excluir arquivo
            $stmt = $pdo->prepare("SELECT caminho_arquivo FROM materiais_didaticos WHERE id = ? AND professor_id = ?");
            $stmt->execute([$material_id, $professor_id]);
            $material = $stmt->fetch();
            
            if ($material) {
                // Excluir arquivo físico se existir
                if (!empty($material['caminho_arquivo']) && file_exists($material['caminho_arquivo'])) {
                    unlink($material['caminho_arquivo']);
                }
                
                // Excluir do banco
                $stmt = $pdo->prepare("DELETE FROM materiais_didaticos WHERE id = ? AND professor_id = ?");
                $stmt->execute([$material_id, $professor_id]);
                
                setFlashMessage('Material excluído com sucesso!', 'success');
            } else {
                setFlashMessage('Material não encontrado.', 'danger');
            }
        } catch (PDOException $e) {
            setFlashMessage('Erro ao excluir material: ' . $e->getMessage(), 'danger');
        }
    }
}

// Buscar categorias
try {
    $stmt = $pdo->query("SELECT * FROM material_categorias ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $categorias = [];
}

// Buscar materiais do professor
try {
    $stmt = $pdo->prepare("
        SELECT 
            md.*,
            mc.nome as categoria_nome,
            mc.cor as categoria_cor,
            mc.icone as categoria_icone,
            GROUP_CONCAT(mt.tag) as tags
        FROM materiais_didaticos md
        LEFT JOIN material_categorias mc ON md.categoria_id = mc.id
        LEFT JOIN material_tags mt ON md.id = mt.material_id
        WHERE md.professor_id = ?
        GROUP BY md.id
        ORDER BY md.created_at DESC
    ");
    $stmt->execute([$professor_id]);
    $materiais = $stmt->fetchAll();
} catch (PDOException $e) {
    $materiais = [];
    setFlashMessage('Erro ao carregar materiais: ' . $e->getMessage(), 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Materiais Didáticos - Edula</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
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
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: bold;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #0ef;
            border-bottom-color: #0ef;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0ef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option input[type="radio"] {
            width: auto;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #0ef, #00d4ff);
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
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
        
        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .material-card {
            background: #fff;
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s;
        }
        
        .material-card:hover {
            border-color: #0ef;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .material-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .material-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .material-icon i {
            font-size: 24px;
            color: white;
        }
        
        .material-info h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 1.3em;
        }
        
        .material-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .material-tags {
            margin: 15px 0;
        }
        
        .tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin: 2px;
        }
        
        .material-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border-left: 5px solid #0ef;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0ef;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1em;
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
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: border-color 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #0ef;
        }
        
        .upload-area.dragover {
            border-color: #0ef;
            background: #f0f9ff;
        }
        
        .file-info {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 5em;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
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
            <a href="criar_prova.php">Provas</a>
            <a href="gerenciar_materiais.php" class="active">Materiais</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1><i class='bx bx-folder'></i> Materiais Didáticos</h1>
            <p>Gerencie seus materiais educacionais e recursos de ensino</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($materiais); ?></div>
                <div class="stat-label">Total de Materiais</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($materiais, function($m) { return $m['publico']; })); ?></div>
                <div class="stat-label">Materiais Públicos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($materiais, 'downloads')); ?></div>
                <div class="stat-label">Total de Downloads</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($materiais, 'visualizacoes')); ?></div>
                <div class="stat-label">Total de Visualizações</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('materiais')">
                <i class='bx bx-folder'></i> Meus Materiais
            </button>
            <button class="tab" onclick="showTab('adicionar')">
                <i class='bx bx-plus'></i> Adicionar Material
            </button>
        </div>

        <!-- Tab: Meus Materiais -->
        <div id="materiais" class="tab-content active">
            <?php if (empty($materiais)): ?>
                <div class="empty-state">
                    <i class='bx bx-folder-open'></i>
                    <h3>Nenhum material encontrado</h3>
                    <p>Você ainda não adicionou nenhum material. Use a aba "Adicionar Material" para começar.</p>
                </div>
            <?php else: ?>
                <div class="material-grid">
                    <?php foreach ($materiais as $material): ?>
                        <div class="material-card">
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
                                <p><?php echo htmlspecialchars(substr($material['descricao'], 0, 100)) . (strlen($material['descricao']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="material-meta">
                                <div class="meta-item">
                                    <i class='bx bx-book'></i>
                                    <span><?php echo htmlspecialchars($material['materia']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-file'></i>
                                    <span><?php echo strtoupper($material['tipo_arquivo']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-download'></i>
                                    <span><?php echo $material['downloads']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-show'></i>
                                    <span><?php echo $material['visualizacoes']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx <?php echo $material['publico'] ? 'bx-globe' : 'bx-lock'; ?>'></i>
                                    <span><?php echo $material['publico'] ? 'Público' : 'Privado'; ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($material['tags'])): ?>
                                <div class="material-tags">
                                    <?php foreach (explode(',', $material['tags']) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="material-actions">
                                <?php if ($material['tipo_arquivo'] !== 'link'): ?>
                                    <a href="<?php echo htmlspecialchars($material['caminho_arquivo']); ?>" target="_blank" class="btn btn-info btn-small">
                                        <i class='bx bx-show'></i> Visualizar
                                    </a>
                                    <a href="download_material.php?id=<?php echo $material['id']; ?>" class="btn btn-success btn-small">
                                        <i class='bx bx-download'></i> Download
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($material['url_externa']); ?>" target="_blank" class="btn btn-info btn-small">
                                        <i class='bx bx-link-external'></i> Acessar Link
                                    </a>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este material?')">
                                    <input type="hidden" name="action" value="excluir_material">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">
                                        <i class='bx bx-trash'></i> Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Adicionar Material -->
        <div id="adicionar" class="tab-content">
            <h2>Adicionar Novo Material</h2>
            
            <form method="POST" enctype="multipart/form-data" id="form-material">
                <input type="hidden" name="action" value="adicionar_material">
                
                <div class="form-group">
                    <label for="titulo">Título do Material *</label>
                    <input type="text" id="titulo" name="titulo" required maxlength="255" placeholder="Ex: Introdução à Álgebra">
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Descreva o conteúdo e objetivo do material..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id">
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="materia">Matéria *</label>
                        <select id="materia" name="materia" required>
                            <option value="">Selecione a matéria</option>
                            <option value="Matemática">Matemática</option>
                            <option value="Português">Português</option>
                            <option value="Ciências">Ciências</option>
                            <option value="História">História</option>
                            <option value="Geografia">Geografia</option>
                            <option value="Inglês">Inglês</option>
                            <option value="Educação Física">Educação Física</option>
                            <option value="Artes">Artes</option>
                            <option value="Programação">Programação</option>
                            <option value="Outras">Outras</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Material *</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="tipo_arquivo" name="tipo_material" value="arquivo" checked onchange="toggleTipoMaterial()">
                            <label for="tipo_arquivo">Upload de Arquivo</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="tipo_link" name="tipo_material" value="link" onchange="toggleTipoMaterial()">
                            <label for="tipo_link">Link Externo</label>
                        </div>
                    </div>
                </div>
                
                <div id="area-arquivo" class="form-group">
                    <label for="arquivo">Arquivo *</label>
                    <div class="upload-area" onclick="document.getElementById('arquivo').click()">
                        <i class='bx bx-cloud-upload' style="font-size: 3em; color: #0ef; margin-bottom: 15px;"></i>
                        <p>Clique aqui ou arraste um arquivo para fazer upload</p>
                        <small>Tipos permitidos: PDF, DOC, DOCX, PPT, PPTX, TXT, MD, JPG, PNG, GIF, MP4, AVI, MP3, WAV</small>
                    </div>
                    <input type="file" id="arquivo" name="arquivo" style="display: none;" onchange="showFileInfo(this)">
                    <div class="file-info" id="file-info">
                        <i class='bx bx-file'></i>
                        <span id="file-name"></span>
                        <span id="file-size"></span>
                    </div>
                </div>
                
                <div id="area-link" class="form-group" style="display: none;">
                    <label for="url_externa">URL do Link *</label>
                    <input type="url" id="url_externa" name="url_externa" placeholder="https://exemplo.com/recurso">
                </div>
                
                <div class="form-group">
                    <label for="tags">Tags (separadas por vírgula)</label>
                    <input type="text" id="tags" name="tags" placeholder="Ex: álgebra, equações, matemática básica">
                    <small>Use tags para facilitar a busca pelos materiais</small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="publico" name="publico" checked>
                    <label for="publico">Disponibilizar para todos os alunos</label>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Adicionar Material
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todas as abas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remover classe active de todos os botões
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar aba selecionada
            document.getElementById(tabName).classList.add('active');
            
            // Adicionar classe active ao botão clicado
            event.target.classList.add('active');
        }
        
        function toggleTipoMaterial() {
            const tipoArquivo = document.getElementById('tipo_arquivo').checked;
            const areaArquivo = document.getElementById('area-arquivo');
            const areaLink = document.getElementById('area-link');
            
            if (tipoArquivo) {
                areaArquivo.style.display = 'block';
                areaLink.style.display = 'none';
                document.getElementById('arquivo').required = true;
                document.getElementById('url_externa').required = false;
            } else {
                areaArquivo.style.display = 'none';
                areaLink.style.display = 'block';
                document.getElementById('arquivo').required = false;
                document.getElementById('url_externa').required = true;
            }
        }
        
        function showFileInfo(input) {
            const fileInfo = document.getElementById('file-info');
            const fileName = document.getElementById('file-name');
            const fileSize = document.getElementById('file-size');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = ' (' + formatFileSize(file.size) + ')';
                fileInfo.style.display = 'block';
            } else {
                fileInfo.style.display = 'none';
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Drag and drop
        const uploadArea = document.querySelector('.upload-area');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('arquivo').files = files;
                showFileInfo(document.getElementById('arquivo'));
            }
        });
    </script>
</body>
</html>

