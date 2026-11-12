<?php
// API d'authentification : ?action=register | login | logout | me
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

function reply(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT pseudo, pseudo_key FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ? ['name' => $u['pseudo'], 'key' => $u['pseudo_key']] : null;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'me') {
        reply(['user' => current_user()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reply(['error' => 'Méthode non autorisée.'], 405);
    }
    // Protection CSRF simple : on n'accepte que du JSON (impossible à envoyer depuis un <form> externe)
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        reply(['error' => 'Requête invalide.'], 400);
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        reply(['ok' => true]);
    }

    $pseudo = trim((string)($in['pseudo'] ?? ''));
    $pass   = (string)($in['password'] ?? '');
    if ($pseudo === '' || $pass === '') {
        reply(['error' => 'Pseudo et mot de passe requis.'], 400);
    }
    if (mb_strlen($pseudo) > 24 || strlen($pass) > 64) {
        reply(['error' => 'Pseudo ou mot de passe trop long.'], 400);
    }
    $key = mb_strtolower($pseudo, 'UTF-8');

    if ($action === 'register') {
        if (strlen($pass) < 4) {
            reply(['error' => 'Mot de passe trop court (4 caractères min).'], 400);
        }
        $st = db()->prepare('SELECT id FROM users WHERE pseudo_key = ?');
        $st->execute([$key]);
        if ($st->fetch()) {
            reply(['error' => 'Ce pseudo est déjà pris — connecte-toi plutôt.'], 409);
        }
        $st = db()->prepare('INSERT INTO users (pseudo, pseudo_key, password_hash) VALUES (?, ?, ?)');
        $st->execute([$pseudo, $key, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        reply(['user' => ['name' => $pseudo, 'key' => $key]], 201);
    }

    if ($action === 'login') {
        $st = db()->prepare('SELECT id, pseudo, pseudo_key, password_hash FROM users WHERE pseudo_key = ?');
        $st->execute([$key]);
        $u = $st->fetch();
        if (!$u) {
            reply(['error' => "Ce pseudo n'existe pas encore. Inscris-toi d'abord."], 404);
        }
        if (!password_verify($pass, $u['password_hash'])) {
            usleep(400000); // ralentit un peu les essais répétés
            reply(['error' => 'Mot de passe incorrect.'], 401);
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        reply(['user' => ['name' => $u['pseudo'], 'key' => $u['pseudo_key']]]);
    }

    reply(['error' => 'Action inconnue.'], 400);
} catch (PDOException $e) {
    error_log('Orbite DB: ' . $e->getMessage());
    reply(['error' => 'Erreur de base de données. Vérifie api/config.php et que MySQL est lancé.'], 500);
}
