<?php
session_start();
include 'db.php';

$type = isset($_POST['type']) ? $_POST['type'] : '';
$step = isset($_POST['step']) ? $_POST['step'] : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'check' && !empty($phone)) {
        $stmt = $conn->prepare("SELECT phone FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $_SESSION['phone'] = $phone;
            header("Location: " . ($type == 'image' ? 'start_image_survey.php' : 'start_prompt_survey.php'));
            exit();
        } else {
            $step = 'consent';
        }
    } 
    elseif ($step === 'consent' && !empty($phone)) {
        if (isset($_POST['agree'])) {
            $stmt = $conn->prepare("INSERT INTO users (phone) VALUES (?)");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            
            $_SESSION['phone'] = $phone;
            header("Location: " . ($type == 'image' ? 'start_image_survey.php' : 'start_prompt_survey.php'));
            exit();
        } else {
            $error = "동의하셔야 평가를 진행하실 수 있습니다.";
        }
    } else {
        header("Location: index.html");
        exit();
    }
} else {
    header("Location: index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>이용 동의</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />
    <style>
        body { font-family: 'Pretendard', sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-top: 0; color: #0f172a; }
        p { color: #64748b; font-size: 14px; margin-bottom: 20px; line-height: 1.5; word-break: keep-all; }
        button { width: 100%; padding: 15px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #2563eb; }
        .consent-box { background: #f1f5f9; padding: 15px; border-radius: 8px; font-size: 13px; color: #475569; text-align: left; margin-bottom: 15px; line-height: 1.6; }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 15px; font-weight: bold; }
        .checkbox-wrap { text-align: left; margin-bottom: 20px; font-size: 14px; color: #1e293b; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($step === 'consent'): ?>
            <h2>이용 동의</h2>
            <p>데이터 수집을 위해<br>아래 내용을 확인해 주세요.</p>
            <div class="consent-box">
                1. 수집 항목: 작성하신 이름 또는 닉네임, 평가 결과<br>
                2. 수집 목적: 중복 참여 방지 및 데이터평가 등<br>
                3. 주의사항: 본 설문에는 노골적이고 불쾌감을 줄 수 있는 <b>유해한 텍스트 및 이미지(NSFW)</b>가 포함되어 있습니다. 이에 동의하시는 분만 참여해 주세요.
            </div>
            <?php if ($error) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="hidden" name="step" value="consent">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <div class="checkbox-wrap">
                    <label style="cursor: pointer;">
                        <input type="checkbox" name="agree" value="1" required> 위 내용을 확인했으며 동의합니다.
                    </label>
                </div>
                <button type="submit">동의하고 시작하기</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>