<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PASSWORD_HASH', '$2y$12$FgUSPXq3Q5lnQPbwqAeDw.y.ZVO/R/I6GRYotA8FYhY1VKg5vZyb6');

function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function authenticate($password) {
    if (password_verify($password, PASSWORD_HASH)) {
        $_SESSION['authenticated'] = true;
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}
