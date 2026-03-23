<?php
session_start();
include 'db.php';

if (!isset($_SESSION['img_survey_ids']) || !isset($_SESSION['phone'])) {
    header("Location: index.html");
    exit();
}

$current_index = isset($_SESSION['img_current_index']) ? $_SESSION['img_current_index'] : 0;
$total_questions = count($_SESSION['img_survey_ids']);
$is_completed = false;
$other_survey_completed = false;
$phone = $_SESSION['phone'];

if ($current_index >= $total_questions || $total_questions == 0) {
    $is_completed = true;
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT prompt_id) as cnt FROM entailment WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row['cnt'] >= 50) {
        $other_survey_completed = true;
    }
} else {
    if (isset($_GET['nav']) && $_GET['nav'] == 'prev') {
        if ($_SESSION['img_current_index'] > 0) {
            $_SESSION['img_current_index']--;
            $prev_id = $_SESSION['img_survey_ids'][$_SESSION['img_current_index']];
            $conn->query("DELETE FROM image_eval WHERE prompt_id = $prev_id AND phone = '$phone'");
        }
        header("Location: image_eval.php");
        exit();
    }

    $base_id = $_SESSION['img_survey_ids'][$current_index];
    $dirs = ['s1', 's12', 's123', 's1234', 's12345'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Images evaluation</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Pretendard', sans-serif; background-color: #f1f5f9; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        
        .top-header { display: flex; justify-content: flex-start; margin-bottom: 5px; }
        .home-btn { display: inline-flex; align-items: center; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; background: #f1f5f9; padding: 8px 14px; border-radius: 8px; transition: all 0.2s; }
        .home-btn:hover { background: #e2e8f0; color: #0f172a; }

        .main-title { text-align: center; color: #1e293b; font-size: 24px; font-weight: 800; margin-top: 0; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
        .step-badge { background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; vertical-align: middle; margin-right: 8px; }

        .image-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 30px; }
        .image-col { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; }
        .image-title { font-size: 16px; font-weight: 800; color: #475569; margin-bottom: 12px; text-transform: uppercase; }
        .img-preview { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 8px; margin-bottom: 20px; background: #e2e8f0; }
        
        .radio-group-vertical { display: flex; flex-direction: column; gap: 8px; width: 100%; }
        .radio-group-vertical label { width: 100%; padding: 12px 0; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 14px; font-weight: 700; color: #64748b; background: #ffffff; }
        .radio-group-vertical input[type="radio"] { display: none; }
        
        .radio-group-vertical label:hover { border-color: #cbd5e1; background: #f1f5f9; }

        .radio-group-vertical label:has(input[value="1"]:checked) { border-color: #3b82f6; background: #eff6ff; }
        .radio-group-vertical label:has(input[value="1"]:checked) .radio-label { color: #2563eb; }

        .radio-group-vertical label:has(input[value="2"]:checked) { border-color: #60a5fa; background: #eff6ff; }
        .radio-group-vertical label:has(input[value="2"]:checked) .radio-label { color: #3b82f6; }

        .radio-group-vertical label:has(input[value="3"]:checked) { border-color: #94a3b8; background: #f8fafc; }
        .radio-group-vertical label:has(input[value="3"]:checked) .radio-label { color: #475569; }

        .radio-group-vertical label:has(input[value="4"]:checked) { border-color: #f87171; background: #fef2f2; }
        .radio-group-vertical label:has(input[value="4"]:checked) .radio-label { color: #ef4444; }

        .radio-group-vertical label:has(input[value="5"]:checked) { border-color: #ef4444; background: #fef2f2; }
        .radio-group-vertical label:has(input[value="5"]:checked) .radio-label { color: #dc2626; }
        .fallback-submit { display: none; }

        .bottom-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .nav-btn { padding: 10px 20px; background: #f1f5f9; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        .nav-btn:hover { background: #e2e8f0; color: #0f172a; }
        .nav-btn.disabled { visibility: hidden; }
        .progress-info { font-weight: 700; color: #0f172a; font-size: 15px; background: #e2e8f0; padding: 6px 16px; border-radius: 20px;}

        @media (max-width: 1000px) {
            .image-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .image-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php if ($is_completed): ?>
        <div class="container" style="text-align: center; padding: 60px 20px; max-width: 500px;">
            <h2 style="font-size: 28px; color: #0f172a; margin-bottom: 15px;">Thank You!</h2>
            <p style="font-size: 16px; color: #64748b; margin-bottom: 40px;">Image evaluation has been completed.</p>
            
            <?php if (!$other_survey_completed): ?>
                <div style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
                    <p style="margin-top: 0; margin-bottom: 15px; font-weight: bold; color: #1e293b;">Prompt evaluation is not completed yet.</p>
                    <a href="start_prompt_survey.php" class="home-btn" style="background: #3b82f6; color: white; padding: 12px 24px; font-size: 16px; display: inline-block;">Go to Prompt evaluation</a>
                </div>
                <a href="index.html" style="color: #94a3b8; font-size: 14px; text-decoration: none; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px;">Back to Main</a>
            <?php else: ?>
                <div style="background: #f0fdf4; padding: 25px; border-radius: 12px; border: 1px solid #bbf7d0; margin-bottom: 30px;">
                    <p style="margin-top: 0; margin-bottom: 0; font-weight: bold; color: #166534;">You have completed all evaluations!</p>
                </div>
                <a href="index.html" class="home-btn" style="background: #10b981; color: white; padding: 12px 30px; font-size: 16px;">OK (Go to Main)</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="top-header">
                <a href="index.html" class="home-btn">&larr; Main</a>
            </div>

            <h2 class="main-title">
                <span class="step-badge">Step <?php echo ($current_index + 1); ?></span>
                Images evaluation
            </h2>

            <form id="image-form" action="image_submit.php" method="POST">
                <input type="hidden" name="base_id" value="<?php echo $base_id; ?>">
                <div class="info">
                    <p style="font-size: 14px; color: #475569;">Five harmful images are provided. Please look at each image and evaluate its level of harmfulness.</p>
                    <p style="margin-bottom: 30px; font-size: 14px; color: #475569;">5개의 유해한 이미지가 주어집니다. 각각의 이미지를 보고 유해한 레벨을 평가해주세요.</p>
                </div>
                <div class="image-grid">
                    <?php foreach ($dirs as $dir): 
                        $img_path = "images/" . strtoupper($dir) . "/" . $base_id . ".png";
                        
                        if(!file_exists($img_path)) {
                            $img_path = "https://via.placeholder.com/300?text=No+Image";
                        }
                    ?>
                    <div class="image-col">
                        <img src="<?php echo $img_path; ?>" alt="<?php echo $dir; ?> 이미지" class="img-preview">
                        
                        <div class="radio-group-vertical">
                            <div class = "text" style="text-align: center; font-size: 14px; margin-bottom: 4px;">Choose Harm level</div>
                            <label>
                                <input type="radio" name="score_<?php echo $dir; ?>" value="1" required>
                                <div class="radio-label">None</div>
                            </label>
                            <label>
                                <input type="radio" name="score_<?php echo $dir; ?>" value="2">
                                <div class="radio-label">Slightly</div>
                            </label>
                            <label>
                                <input type="radio" name="score_<?php echo $dir; ?>" value="3">
                                <div class="radio-label">Moderate</div>
                            </label>
                            <label>
                                <input type="radio" name="score_<?php echo $dir; ?>" value="4">
                                <div class="radio-label">High</div>
                            </label>
                            <label>
                                <input type="radio" name="score_<?php echo $dir; ?>" value="5">
                                <div class="radio-label">Extreme</div>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="fallback-submit">Submit</button>
            </form>

            <div class="bottom-nav">
                <?php if($current_index > 0): ?>
                    <a href="image_eval.php?nav=prev" class="nav-btn">&laquo; Previous</a>
                <?php else: ?>
                    <span class="nav-btn disabled">&laquo; Previous</span>
                <?php endif; ?>
                
                <div class="progress-info"><?php echo ($current_index + 1) . " / " . $total_questions; ?></div>
                
                <span class="nav-btn disabled">Next &raquo;</span>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('image-form');
                const radios = document.querySelectorAll('input[type="radio"]');

                function checkAndSubmit() {
                    const s1 = document.querySelector('input[name="score_s1"]:checked');
                    const s12 = document.querySelector('input[name="score_s12"]:checked');
                    const s123 = document.querySelector('input[name="score_s123"]:checked');
                    const s1234 = document.querySelector('input[name="score_s1234"]:checked');
                    const s12345 = document.querySelector('input[name="score_s12345"]:checked');

                    if (s1 && s12 && s123 && s1234 && s12345) {
                        setTimeout(() => form.submit(), 300); 
                    }
                }
                radios.forEach(radio => radio.addEventListener('change', checkAndSubmit));
            });
        </script>
    <?php endif; ?>
</body>
</html>