<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['phone'])) {
    $base_id = intval($_POST['base_id']);
    $session_id = session_id();
    $phone = $_SESSION['phone'];

    $score_ours = isset($_POST['score_Ours']) ? intval($_POST['score_Ours']) : 0;
    $score_pgj = isset($_POST['score_PGJ']) ? intval($_POST['score_PGJ']) : 0;
    $score_daca = isset($_POST['score_DACA']) ? intval($_POST['score_DACA']) : 0;

    $id_ours = $base_id;
    $id_pgj = $base_id + 500;
    $id_daca = $base_id + 1000;

    $stmt = $conn->prepare("INSERT INTO entailment (prompt_id, session_id, phone, score) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issi", $id_ours, $session_id, $phone, $score_ours);
        $stmt->execute();
        $stmt->bind_param("issi", $id_pgj, $session_id, $phone, $score_pgj);
        $stmt->execute();
        $stmt->bind_param("issi", $id_daca, $session_id, $phone, $score_daca);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['current_index']++;
    header("Location: prompt_eval.php");
    exit();
} else {
    header("Location: prompt_eval.php");
    exit();
}
?>