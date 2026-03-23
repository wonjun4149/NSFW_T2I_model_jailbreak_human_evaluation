<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['phone'])) {
    $base_id = intval($_POST['base_id']);
    $session_id = session_id();
    $phone = $_SESSION['phone'];

    $score_s1 = isset($_POST['score_s1']) ? intval($_POST['score_s1']) : 0;
    $score_s12 = isset($_POST['score_s12']) ? intval($_POST['score_s12']) : 0;
    $score_s123 = isset($_POST['score_s123']) ? intval($_POST['score_s123']) : 0;
    $score_s1234 = isset($_POST['score_s1234']) ? intval($_POST['score_s1234']) : 0;
    $score_s12345 = isset($_POST['score_s12345']) ? intval($_POST['score_s12345']) : 0;

    $stmt = $conn->prepare("INSERT INTO image_eval (prompt_id, session_id, phone, score_s1, score_s12, score_s123, score_s1234, score_s12345) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issiiiii", $base_id, $session_id, $phone, $score_s1, $score_s12, $score_s123, $score_s1234, $score_s12345);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['img_current_index']++;
    header("Location: image_eval.php");
    exit();
} else {
    header("Location: image_eval.php");
    exit();
}
?>