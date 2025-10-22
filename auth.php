<?php
session_start();

define('USERS_FILE', __DIR__ . '/data/users.json');

function get_users() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $json = file_get_contents(USERS_FILE);
    $users = json_decode($json, true);
    return is_array($users) ? $users : [];
}

function save_users($users) {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    file_put_contents(USERS_FILE, $json);
}

function has_users() {
    $users = get_users();
    return !empty($users);
}

function register_first_user($username, $password) {
    if (has_users()) {
        return false; // A user already exists
    }
    $users = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT)
    ];
    save_users($users);
    return true;
}

function login_user($username, $password) {
    $users = get_users();
    if (empty($users) || $users['username'] !== $username) {
        return false; // User not found
    }
    if (password_verify($password, $users['password_hash'])) {
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['username']);
}

function logout() {
    session_destroy();
}

function inanna_get_current_user(){
    return $_SESSION['username'] ?? null;
}
?>