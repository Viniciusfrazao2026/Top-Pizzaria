<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function admin_logado() {
    return isset($_SESSION['admin_id']);
}

function exigir_login() {
    if (!admin_logado()) {
        header('Location: login.php');
        exit;
    }
}
