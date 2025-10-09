<?php
session_start();

// Função para verificar se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['nome']);
}

// Função para fazer login
function loginUser($user_id, $nome, $matricula, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['nome'] = $nome;
    $_SESSION['matricula'] = $matricula;
    $_SESSION['role'] = $role;
}

// Função para fazer logout
function logoutUser() {
    session_unset();
    session_destroy();
}

// Função para redirecionar se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Função para verificar se é aluno/usuário
function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

// Função para verificar se é professor
function isProfessor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'professor';
}

// Função para redirecionar baseado no role
function redirectByRole() {
    if (isUser()) {
        header("Location: area_user.php");
        exit();
    } elseif (isProfessor()) {
        header("Location: area_professor.php");
        exit();
    } else {
        header("Location: INICIO.php");
        exit();
    }
}

// Função para definir mensagens flash
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Função para obter e limpar mensagens flash
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}
?>

