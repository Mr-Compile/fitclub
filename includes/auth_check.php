<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    header('Location: /fitclub/login.php');
    exit();
}

// Function to check if user has required role
function checkRole($required_role) {
    if ($_SESSION['role'] !== $required_role) {
        // Redirect to appropriate page based on role
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: /fitclub/admin/dashboard.php');
                break;
            case 'coach':
                header('Location: /fitclub/coach/dashboard.php');
                break;
            case 'member':
                header('Location: /fitclub/member/dashboard.php');
                break;
            default:
                header('Location: /fitclub/index.php');
        }
        exit();
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Function to get current user's ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Function to get current user's role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to get current user's username
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// Function to check if user is admin
function isAdmin() {
    return $_SESSION['role'] === 'admin';
}

// Function to check if user is coach
function isCoach() {
    return $_SESSION['role'] === 'coach';
}

// Function to check if user is member
function isMember() {
    return $_SESSION['role'] === 'member';
} 