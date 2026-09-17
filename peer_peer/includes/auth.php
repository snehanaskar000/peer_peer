<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Gets the ID of the currently logged-in user.
 * 
 * @return int|null
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Gets the name of the currently logged-in user.
 * 
 * @return string|null
 */
function getCurrentUserName() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
}

/**
 * Gets the role of the currently logged-in user.
 * 
 * @return string|null
 */
function getCurrentUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Checks if the currently logged-in user is an admin.
 * 
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && getCurrentUserRole() === 'admin';
}

/**
 * Enforces authentication. Redirects to login page if user is not logged in.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . getBaseUrl() . "login.php");
        exit;
    }
}

/**
 * Enforces admin authorization. Redirects to home page or shows access denied if user is not admin.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . getBaseUrl() . "index.php?error=unauthorized");
        exit;
    }
}

/**
 * Gets the base URL path of the application.
 * 
 * @return string
 */
function getBaseUrl() {
    // Relative back-path detection depending on whether we are in user/ or admin/ or api/
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    if (in_array($current_dir, ['user', 'admin', 'api', 'config'])) {
        return '../';
    }
    return '';
}

/**
 * Generates and returns a CSRF token.
 * 
 * @return string
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a given CSRF token against the session token.
 * 
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitizes input data to prevent XSS attacks.
 * 
 * @param string $data
 * @return string
 */
function sanitize($data) {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper to display alert messages.
 */
function displayAlert() {
    if (isset($_SESSION['success_msg'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' .
             sanitize($_SESSION['success_msg']) .
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['success_msg']);
    }
    if (isset($_SESSION['error_msg'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' .
             sanitize($_SESSION['error_msg']) .
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['error_msg']);
    }
}
