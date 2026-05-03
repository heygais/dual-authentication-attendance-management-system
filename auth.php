<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /attendx/index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /attendx/index.php');
        exit;
    }
}

function getCurrentUser() {
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['name'],
        'role'     => $_SESSION['role'],
        'username' => $_SESSION['username'],
    ];
}
?>