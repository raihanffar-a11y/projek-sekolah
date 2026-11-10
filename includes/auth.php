<?php
require_once __DIR__ . '/../config/config.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): void {
    if (!current_user()) redirect('login.php');
}
function require_role(string $role): void {
    require_login();
    if ((current_user()['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}
