<?php
require_once 'includes/session.php';

// Fazer logout
logoutUser();

// Redirecionar para login com mensagem
setFlashMessage('Logout realizado com sucesso!', 'success');
header("Location: login.php");
exit();
?>

