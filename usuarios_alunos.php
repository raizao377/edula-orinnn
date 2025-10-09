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
$nome = $_SESSION['nome'];
// buscar todos os usuários
$stmt = $pdo->prepare("SELECT id, nome, matricula, role, created_at, updated_at FROM users");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Área do Professor - Usuários</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #f4f4f4; }
        .btn-voltar {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn-voltar:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Todos os Usuários</h1>

    <!-- Botão de voltar -->
    <a href="area_professor.php" class="btn-voltar">← Voltar</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Matrícula</th>
                <th>Função</th>
                <th>Criado em</th>
                <th>Atualizado em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario['id']) ?></td>
                    <td><?= htmlspecialchars($usuario['nome']) ?></td>
                    <td><?= htmlspecialchars($usuario['matricula']) ?></td>
                    <td><?= htmlspecialchars($usuario['role']) ?></td>
                    <td><?= htmlspecialchars($usuario['created_at']) ?></td>
                    <td><?= htmlspecialchars($usuario['updated_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
