<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · EnglishHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark">E</span> EnglishHub</a>
  <nav>
    <?php if ($user): ?>
      <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= BASE_URL ?>/admin/quizzes.php">Quizzes</a><a href="<?= BASE_URL ?>/admin/quiz_rooms.php">Quiz Rooms</a><a href="<?= BASE_URL ?>/admin/vocabularies.php">Vocab</a><?php endif; ?>
      <?php if ($user['role'] === 'student'): ?><a href="<?= BASE_URL ?>/pronunciation.php">Pronunciation</a><a href="<?= BASE_URL ?>/progress.php">Progress</a><?php endif; ?>
      <span class="nav-user"><?= e($user['name']) ?></span>
      <a class="nav-button" href="<?= BASE_URL ?>/logout.php">Logout</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php">Login</a>
      <a class="nav-button" href="<?= BASE_URL ?>/register.php">Create account</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
