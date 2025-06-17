<?php
session_start();
require_once 'db.php';

$type = $_GET['type'];
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT name, price FROM components WHERE id = ?");
$stmt->execute([$id]);
$comp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($comp) {
    $_SESSION['selected'][$type] = [
        'id' => $id,
        'name' => $comp['name'],
        'price' => $comp['price']
    ];
}

header("Location: index.php");
exit;
