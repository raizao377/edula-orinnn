<?php
require_once 'includes/session.php';
require_once 'config/database.php';

// Conectar ao banco de dados
$database = new Database();
$pdo = $database->getConnection();

// Verificar se está logado e é professor
requireLogin();
if (!isProfessor()) {
    setFlashMessage('Acesso negado. Área restrita para professores.', 'danger');
    header("Location: login.php");
    exit();
}

$flash = getFlashMessage();
$nome = $_SESSION['nome'];

// Verificar se o ID da trilha foi fornecido
if (!isset($_GET['track_id']) || !is_numeric($_GET['track_id'])) {
    setFlashMessage('Trilha não encontrada.', 'danger');
    header("Location: admin_study_tracks.php");
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
        header("Location: admin_study_tracks.php");
        exit();
    }
} catch (Exception $e) {
    setFlashMessage('Erro ao carregar trilha.', 'danger');
    header("Location: admin_study_tracks.php");
    exit();
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_activity':
                try {
                    // Buscar o próximo order_index
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM track_activities WHERE track_id = ?");
                    $stmt->execute([$track_id]);
                    $next_order = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("INSERT INTO track_activities (track_id, title, description, content_url, order_index) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$track_id, $_POST['title'], $_POST['description'], $_POST['content_url'], $next_order]);
                    setFlashMessage('Atividade criada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao criar atividade: ' . $e->getMessage(), 'danger');
                }
                break;
                
            case 'update_activity':
                try {
                    $stmt = $pdo->prepare("UPDATE track_activities SET title = ?, description = ?, content_url = ? WHERE id = ? AND track_id = ?");
                    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['content_url'], $_POST['activity_id'], $track_id]);
                    setFlashMessage('Atividade atualizada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao atualizar atividade: ' . $e->getMessage(), 'danger');
                }
                break;
                
            case 'delete_activity':
                try {
                    // Deletar progresso dos usuários primeiro
                    $stmt = $pdo->prepare("DELETE FROM user_progress WHERE activity_id = ?");
                    $stmt->execute([$_POST['activity_id']]);
                    
                    // Deletar a atividade
                    $stmt = $pdo->prepare("DELETE FROM track_activities WHERE id = ? AND track_id = ?");
                    $stmt->execute([$_POST['activity_id'], $track_id]);
                    
                    // Reordenar as atividades restantes
                    $stmt = $pdo->prepare("SELECT id FROM track_activities WHERE track_id = ? ORDER BY order_index");
                    $stmt->execute([$track_id]);
                    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($activities as $index => $activity) {
                        $stmt = $pdo->prepare("UPDATE track_activities SET order_index = ? WHERE id = ?");
                        $stmt->execute([$index + 1, $activity['id']]);
                    }
                    
                    setFlashMessage('Atividade deletada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao deletar atividade: ' . $e->getMessage(), 'danger');
                }
                break;
                
            case 'reorder_activities':
                try {
                    $activity_ids = json_decode($_POST['activity_order'], true);
                    foreach ($activity_ids as $index => $activity_id) {
                        $stmt = $pdo->prepare("UPDATE track_activities SET order_index = ? WHERE id = ? AND track_id = ?");
                        $stmt->execute([$index + 1, $activity_id, $track_id]);
                    }
                    setFlashMessage('Ordem das atividades atualizada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao reordenar atividades: ' . $e->getMessage(), 'danger');
                }
                break;
        }
        header("Location: admin_track_activities.php?track_id=" . $track_id);
        exit();
    }
}

// Buscar atividades da trilha
try {
    $stmt = $pdo->prepare("
        SELECT ta.id, ta.title, ta.description, ta.content_url, ta.order_index,
               COUNT(up.id) as total_completions
        FROM track_activities ta
        LEFT JOIN user_progress up ON ta.id = up.activity_id AND up.status = 'completed'
        WHERE ta.track_id = ?
        GROUP BY ta.id, ta.title, ta.description, ta.content_url, ta.order_index
        ORDER BY ta.order_index ASC
    ");
    $stmt->execute([$track_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
    setFlashMessage('Erro ao carregar atividades.', 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <title>Gerenciar Atividades - <?php echo htmlspecialchars($track['name']); ?> - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Gerenciar Atividades</h2>
        <p>Olá, Professor(a) <?php echo htmlspecialchars($nome); ?>. Gerencie as atividades da trilha!</p>
        <ul>
            <li><a href="admin_study_tracks.php">Voltar às Trilhas</a></li>
            <li><a href="area_professor.php">Área do Professor</a></li>
            <li><a href="INICIO.php">Início</a></li>
            <li><a href="logout.php">Sair</a></li>
        </ul>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="admin-container">
        <div class="admin-header">
            <div class="breadcrumb">
                <a href="admin_study_tracks.php">Trilhas de Estudo</a>
                <i class='bx bx-chevron-right'></i>
                <span><?php echo htmlspecialchars($track['name']); ?></span>
            </div>
            
            <h1>Gerenciar Atividades</h1>
            <p><?php echo htmlspecialchars($track['description']); ?></p>
            
            <div class="header-actions">
                <button class="btn-primary" onclick="openCreateModal()">
                    <i class='bx bx-plus'></i> Nova Atividade
                </button>
            </div>
        </div>

        <div class="activities-container">
            <?php if (empty($activities)): ?>
                <div class="no-activities">
                    <i class='bx bx-list-ul'></i>
                    <h3>Nenhuma atividade criada ainda</h3>
                    <p>Clique em "Nova Atividade" para começar a criar atividades para esta trilha</p>
                </div>
            <?php else: ?>
                <div class="activities-header">
                    <h2>Atividades da Trilha (<?php echo count($activities); ?>)</h2>
                    <p>Arraste e solte para reordenar as atividades</p>
                </div>
                
                <div id="activities-list" class="activities-list">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item" data-id="<?php echo $activity['id']; ?>">
                            <div class="activity-drag">
                                <i class='bx bx-menu'></i>
                            </div>
                            
                            <div class="activity-order">
                                <?php echo $activity['order_index']; ?>
                            </div>
                            
                            <div class="activity-content">
                                <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                
                                <?php if ($activity['content_url'] && $activity['content_url'] !== '#'): ?>
                                    <div class="activity-url">
                                        <i class='bx bx-link'></i>
                                        <a href="<?php echo htmlspecialchars($activity['content_url']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($activity['content_url']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="activity-stats">
                                    <span class="stat">
                                        <i class='bx bx-check-circle'></i>
                                        <?php echo $activity['total_completions']; ?> conclusões
                                    </span>
                                </div>
                            </div>
                            
                                <div class="activity-actions">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($activity)); ?>)">
                                        <i class='bx bx-edit'></i> Editar
                                    </button>
                                    <a href="admin_quiz_manager.php?activity_id=<?php echo $activity['id']; ?>" class="btn-quiz">
                                        <i class='bx bx-question-mark'></i> Quiz
                                    </a>
                                    <button class="btn-delete" onclick="deleteActivity(<?php echo $activity['id']; ?>)">
                                        <i class='bx bx-trash'></i> Excluir
                                    </button>
                                </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="reorder-actions">
                    <button class="btn-secondary" onclick="saveOrder()">
                        <i class='bx bx-save'></i> Salvar Ordem
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para criar atividade -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nova Atividade</h2>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_activity">
                
                <div class="form-group">
                    <label for="title">Título da Atividade:</label>
                    <input type="text" id="title" name="title" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="content_url">URL do Conteúdo (PDF ou link):</label>
                    <input type="url" id="content_url" name="content_url" placeholder="https://exemplo.com/material.pdf">
                    <small>Deixe em branco para usar conteúdo padrão</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Criar Atividade</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar atividade -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Atividade</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_activity">
                <input type="hidden" id="edit_activity_id" name="activity_id">
                
                <div class="form-group">
                    <label for="edit_title">Título da Atividade:</label>
                    <input type="text" id="edit_title" name="title" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Descrição:</label>
                    <textarea id="edit_description" name="description" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_content_url">URL do Conteúdo (PDF ou link):</label>
                    <input type="url" id="edit_content_url" name="content_url" placeholder="https://exemplo.com/material.pdf">
                    <small>Deixe em branco para usar conteúdo padrão</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para confirmar exclusão -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir a atividade "<span id="delete_activity_name"></span>"?</p>
                <p class="warning">Esta ação não pode ser desfeita e todo o progresso dos alunos nesta atividade será perdido.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_activity">
                <input type="hidden" id="delete_activity_id" name="activity_id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                    <button type="submit" class="btn-danger">Excluir Atividade</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form oculto para reordenação -->
    <form id="reorderForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reorder_activities">
        <input type="hidden" id="activity_order" name="activity_order">
    </form>

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

        .admin-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .admin-header {
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

        .admin-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .admin-header p {
            color: #666;
            margin-bottom: 20px;
        }

        .header-actions {
            text-align: center;
        }

        .btn-primary {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .activities-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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

        .activities-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .activities-header h2 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .activities-header p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .activities-list {
            padding: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.2s;
            cursor: move;
        }

        .activity-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .activity-item.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }

        .activity-drag {
            color: #6c757d;
            cursor: grab;
        }

        .activity-drag:active {
            cursor: grabbing;
        }

        .activity-order {
            width: 30px;
            height: 30px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h3 {
            margin: 0 0 8px 0;
            color: #333;
        }

        .activity-content p {
            margin: 0 0 10px 0;
            color: #666;
            line-height: 1.4;
        }

        .activity-url {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .activity-url i {
            color: #007bff;
        }

        .activity-url a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .activity-url a:hover {
            text-decoration: underline;
        }

        .activity-stats {
            display: flex;
            gap: 15px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 14px;
        }

        .stat i {
            color: #28a745;
        }

        .activity-actions {
            display: flex;
            gap: 8px;
        }

            .btn-edit, .btn-delete, .btn-quiz {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1.4rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-quiz {
            background: #ffc107;
            color: #212529;
        }

        .btn-quiz:hover {
            background: #e0a800;
            transform: translateY(-2px);
            color: #212529;
            text-decoration: none;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }     padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            text-align: center;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-group small {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .warning {
            color: #dc3545;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 15px;
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .activity-actions {
                align-self: flex-end;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>

    <script>
        let draggedElement = null;

        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        function openEditModal(id, title, description, contentUrl) {
            document.getElementById('edit_activity_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_content_url').value = contentUrl || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id, title) {
            document.getElementById('delete_activity_id').value = id;
            document.getElementById('delete_activity_name').textContent = title;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function saveOrder() {
            const activities = document.querySelectorAll('.activity-item');
            const order = Array.from(activities).map(item => item.dataset.id);
            document.getElementById('activity_order').value = JSON.stringify(order);
            document.getElementById('reorderForm').submit();
        }

        // Drag and Drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const activitiesList = document.getElementById('activities-list');
            if (!activitiesList) return;

            activitiesList.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('activity-item')) {
                    draggedElement = e.target;
                    e.target.classList.add('dragging');
                }
            });

            activitiesList.addEventListener('dragend', function(e) {
                if (e.target.classList.contains('activity-item')) {
                    e.target.classList.remove('dragging');
                    updateOrderNumbers();
                }
            });

            activitiesList.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(activitiesList, e.clientY);
                if (afterElement == null) {
                    activitiesList.appendChild(draggedElement);
                } else {
                    activitiesList.insertBefore(draggedElement, afterElement);
                }
            });

            // Make activity items draggable
            const activityItems = document.querySelectorAll('.activity-item');
            activityItems.forEach(item => {
                item.draggable = true;
            });
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.activity-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        function updateOrderNumbers() {
            const activities = document.querySelectorAll('.activity-item');
            activities.forEach((item, index) => {
                const orderElement = item.querySelector('.activity-order');
                if (orderElement) {
                    orderElement.textContent = index + 1;
                }
            });
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

