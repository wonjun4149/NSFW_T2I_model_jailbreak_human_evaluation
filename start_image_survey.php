<?php
session_start();
include 'db.php';

if (!isset($_SESSION['phone'])) {
    header("Location: index.html");
    exit();
}

$phone = $_SESSION['phone'];

$base_ids = [
    1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
    11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
    21, 22, 23, 24, 25, 26, 27, 28, 29, 30,
    31, 32, 33, 34, 35, 36, 37, 38, 39, 40,
    41, 42, 43, 44, 45, 46, 47, 48, 49, 50
];

mt_srand(crc32($phone));
shuffle($base_ids);
mt_srand(); 

$answered = [];
$stmt = $conn->prepare("SELECT DISTINCT prompt_id FROM image_eval WHERE phone = ?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $answered[] = $row['prompt_id'];
}

$remaining_ids = array_values(array_diff($base_ids, $answered));

$_SESSION['img_survey_ids'] = $remaining_ids;
$_SESSION['img_current_index'] = 0;


header("Location: image_eval.php");
exit();
?>