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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_track':
                try {
                    $stmt = $pdo->prepare("INSERT INTO study_tracks (name, description) VALUES (?, ?)");
                    $stmt->execute([$_POST['name'], $_POST['description']]);
                    setFlashMessage('Trilha criada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao criar trilha: ' . $e->getMessage(), 'danger');
                }
                break;
                
            case 'update_track':
                try {
                    $stmt = $pdo->prepare("UPDATE study_tracks SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['track_id']]);
                    setFlashMessage('Trilha atualizada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao atualizar trilha: ' . $e->getMessage(), 'danger');
                }
                break;
                
            case 'delete_track':
                try {
                    // Primeiro deletar atividades relacionadas
                    $stmt = $pdo->prepare("DELETE FROM track_activities WHERE track_id = ?");
                    $stmt->execute([$_POST['track_id']]);
                    
                    // Depois deletar a trilha
                    $stmt = $pdo->prepare("DELETE FROM study_tracks WHERE id = ?");
                    $stmt->execute([$_POST['track_id']]);
                    
                    setFlashMessage('Trilha deletada com sucesso!', 'success');
                } catch (Exception $e) {
                    setFlashMessage('Erro ao deletar trilha: ' . $e->getMessage(), 'danger');
                }
                break;
        }
        header("Location: admin_study_tracks.php");
        exit();
    }
}

// Buscar todas as trilhas
try {
    $stmt = $pdo->prepare("
        SELECT st.id, st.name, st.description, st.created_at,
               COUNT(ta.id) as total_activities
        FROM study_tracks st
        LEFT JOIN track_activities ta ON st.id = ta.track_id
        GROUP BY st.id, st.name, st.description, st.created_at
        ORDER BY st.created_at DESC
    ");
    $stmt->execute();
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tracks = [];
    setFlashMessage('Erro ao carregar trilhas.', 'danger');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="static/css/professorOFC.css">
    <title>Administrar Trilhas de Estudo - Edula</title>
</head>
<body>
    <div class="navbar">
        <h2><span>EDULA.com</span> - Administrar Trilhas de Estudo</h2>
        <p>Olá, Professor(a) <?php echo htmlspecialchars($nome); ?>. Gerencie as trilhas de aprendizado!</p>
        <ul>
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
            <h1>Administrar Trilhas de Estudo</h1>
            <p>Crie, edite e gerencie as trilhas de aprendizado para seus alunos</p>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class='bx bx-plus'></i> Nova Trilha
            </button>
        </div>

        <div class="tracks-grid">
            <?php if (empty($tracks)): ?>
                <div class="no-tracks">
                    <i class='bx bx-map'></i>
                    <h3>Nenhuma trilha criada ainda</h3>
                    <p>Clique em "Nova Trilha" para começar a criar trilhas de aprendizado</p>
                </div>
            <?php else: ?>
                <?php foreach ($tracks as $track): ?>
                    <div class="track-card">
                        <div class="track-header">
                            <h3><?php echo htmlspecialchars($track['name']); ?></h3>
                            <div class="track-actions">
                                <button class="btn-edit" onclick="openEditModal(<?php echo $track['id']; ?>, '<?php echo htmlspecialchars($track['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($track['description'], ENT_QUOTES); ?>')">
                                    <i class='bx bx-edit'></i>
                                </button>
                                <button class="btn-delete" onclick="confirmDelete(<?php echo $track['id']; ?>, '<?php echo htmlspecialchars($track['name'], ENT_QUOTES); ?>')">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="track-content">
                            <p><?php echo htmlspecialchars($track['description']); ?></p>
                            
                            <div class="track-stats">
                                <div class="stat">
                                    <i class='bx bx-list-ul'></i>
                                    <span><?php echo $track['total_activities']; ?> atividades</span>
                                </div>
                                <div class="stat">
                                    <i class='bx bx-calendar'></i>
                                    <span>Criada em <?php echo date('d/m/Y', strtotime($track['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="track-footer">
                            <a href="admin_track_activities.php?track_id=<?php echo $track['id']; ?>" class="btn-manage">
                                <i class='bx bx-cog'></i> Gerenciar Atividades
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para criar trilha -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nova Trilha de Estudo</h2>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_track">
                
                <div class="form-group">
                    <label for="name">Nome da Trilha:</label>
                    <input type="text" id="name" name="name" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Criar Trilha</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar trilha -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Trilha de Estudo</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_track">
                <input type="hidden" id="edit_track_id" name="track_id">
                
                <div class="form-group">
                    <label for="edit_name">Nome da Trilha:</label>
                    <input type="text" id="edit_name" name="name" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Descrição:</label>
                    <textarea id="edit_description" name="description" rows="4" required></textarea>
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
                <p>Tem certeza que deseja excluir a trilha "<span id="delete_track_name"></span>"?</p>
                <p class="warning">Esta ação não pode ser desfeita e todas as atividades da trilha também serão removidas.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_track">
                <input type="hidden" id="delete_track_id" name="track_id">
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                    <button type="submit" class="btn-danger">Excluir Trilha</button>
                </div>
            </form>
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

        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .admin-header {
            text-align: center;
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
        }

        .admin-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .admin-header p {
            color: #666;
            margin-bottom: 20px;
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

        .tracks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .no-tracks {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-tracks i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .track-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .track-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .track-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .track-header h3 {
            margin: 0;
            color: #333;
        }

        .track-actions {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .btn-edit {
            background: #28a745;
            color: white;
        }

        .btn-edit:hover {
            background: #218838;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .track-content {
            padding: 20px;
        }

        .track-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .track-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .stat i {
            color: #007bff;
        }

        .track-footer {
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .btn-manage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #6c757d;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-manage:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
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
            max-width: 500px;
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

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
            .tracks-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-container {
                padding: 15px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }


        .navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    position: sticky;
    top: 0;
    z-index: 1000;
    flex-wrap: wrap;
    gap: 15px;
}

.nav-brand h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.nav-brand h2 span {
    color: #ffd700;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.nav-subtitle {
    margin: 5px 0 0 0;
    font-size: 0.9rem;
    opacity: 0.9;
    font-weight: 400;
}

.nav-user {
    text-align: center;
    flex-grow: 1;
    margin: 0 20px;
}

.nav-user p {
    margin: 0;
    font-size: 1rem;
}

.nav-user strong {
    color: #ffd700;
}

.nav-menu ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-menu li {
    margin: 0;
}

.nav-menu a {
    color: white;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    white-space: nowrap;
}

.nav-menu a:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

.nav-menu a i {
    font-size: 1.2rem;
}

/* Responsividade */
@media (max-width: 768px) {
    .navbar {
        flex-direction: column;
        text-align: center;
        padding: 15px 20px;
        gap: 10px;
    }

    .nav-user {
        margin: 5px 0;
        order: 3;
        width: 100%;
    }

    .nav-menu {
        width: 100%;
    }

    .nav-menu ul {
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .nav-menu a {
        padding: 6px 12px;
        font-size: 0.9rem;
    }

    .nav-brand h2 {
        font-size: 1.3rem;
    }

    .nav-subtitle {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .navbar {
        padding: 12px 15px;
    }

    .nav-menu ul {
        flex-direction: column;
        gap: 8px;
    }

    .nav-menu a {
        justify-content: center;
        width: 100%;
        padding: 8px;
    }

    .nav-brand h2 {
        font-size: 1.2rem;
    }
}
    </style>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        function openEditModal(id, name, description) {
            document.getElementById('edit_track_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id, name) {
            document.getElementById('delete_track_id').value = id;
            document.getElementById('delete_track_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
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

