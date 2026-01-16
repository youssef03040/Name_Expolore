<?php

function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function gen_alpabets($length) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function is_admin_authenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function authenticate_admin($username, $password, $expectedUsername, $passwordHash) {
    if ($passwordHash === '' || strlen($passwordHash) < 20) {
        return false;
    }

    if ($username === $expectedUsername && password_verify($password, $passwordHash)) {
        $_SESSION['admin_authenticated'] = true;
        return true;
    }
    return false;
}

function admin_logout() {
    unset($_SESSION['admin_authenticated']);
}

function require_admin_auth($redirectUrl) {
    if (!is_admin_authenticated()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}





?>
