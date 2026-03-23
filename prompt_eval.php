<?php
session_start();
include 'db.php';

if (!isset($_SESSION['survey_ids']) || !isset($_SESSION['phone'])) {
    header("Location: index.html");
    exit();
}

$current_index = isset($_SESSION['current_index']) ? $_SESSION['current_index'] : 0;
$total_questions = count($_SESSION['survey_ids']);
$is_completed = false;
$other_survey_completed = false;
$phone = $_SESSION['phone'];

if ($current_index >= $total_questions || $total_questions == 0) {
    $is_completed = true;
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT prompt_id) as cnt FROM image_eval WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row['cnt'] >= 50) {
        $other_survey_completed = true;
    }
} else {
    if (isset($_GET['nav']) && $_GET['nav'] == 'prev') {
        if ($_SESSION['current_index'] > 0) {
            $_SESSION['current_index']--;
            $prev_base_id = $_SESSION['survey_ids'][$_SESSION['current_index']];
            $id_ours = $prev_base_id;
            $id_pgj = $prev_base_id + 500;
            $id_daca = $prev_base_id + 1000;
            $conn->query("DELETE FROM entailment WHERE prompt_id IN ($id_ours, $id_pgj, $id_daca) AND phone = '$phone'");
        }
        header("Location: prompt_eval.php");
        exit();
    }

    $base_id = $_SESSION['survey_ids'][$current_index];
    $id_ours = $base_id;
    $id_pgj = $base_id + 500;
    $id_daca = $base_id + 1000;

    $sql = "SELECT * FROM prompts WHERE id IN ($id_ours, $id_pgj, $id_daca)";
    $result = $conn->query($sql);

    $prompts = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $prompts[$row['source']] = $row;
        }
    }

    if (count($prompts) < 3) {
        die("오류: 데이터베이스에서 일부 프롬프트를 찾을 수 없습니다.");
    }

    $explicit = $prompts['Ours']['explicit_unsafe'];
    $explicit_kr = $prompts['Ours']['explicit_unsafe_kr'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompts evaluation</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Pretendard', sans-serif; background-color: #f1f5f9; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        
        .top-header { display: flex; justify-content: flex-start; margin-bottom: 5px; }
        .home-btn { display: inline-flex; align-items: center; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; background: #f1f5f9; padding: 8px 14px; border-radius: 8px; transition: all 0.2s; }
        .home-btn:hover { background: #e2e8f0; color: #0f172a; }

        .main-title { text-align: center; color: #1e293b; font-size: 24px; font-weight: 800; margin-top: 0; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
        .step-badge { background: #1e293b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; vertical-align: middle; margin-right: 8px; }

        .target-box { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #cbd5e1; position: relative; }
        .label-badge { position: absolute; top: -12px; left: 20px; background: #64748b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .target-box .label-badge { background: #0f172a; font-size: 14px; }
        
        .en-text { font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 8px; line-height: 1.5; margin-top: 5px; }
        
        details.translation-dropdown { margin-top: 8px; }
        details.translation-dropdown summary { font-size: 13px; color: #94a3b8; cursor: pointer; user-select: none; outline: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: color 0.2s; }
        details.translation-dropdown summary:hover { color: #64748b; }
        .kr-text { font-size: 14px; color: #64748b; line-height: 1.5; margin-top: 8px; padding-left: 12px; border-left: 2px solid #cbd5e1; }
        
        .prompt-section { background: #ffffff; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .question-title { font-size: 16px; font-weight: 700; margin: 20px 0 12px; color: #1e293b; text-align: center; }
        
        .radio-group { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .radio-group label { text-align: center; padding: 15px 10px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; background: #f8fafc; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .radio-group input[type="radio"] { display: none; }
        .radio-title { font-size: 15px; font-weight: 700; color: #475569; margin-bottom: 4px; line-height: 1.2; word-break: keep-all; }
        .radio-group label:hover { border-color: #cbd5e1; }
        
        .radio-group input[type="radio"][value="1"]:checked + .radio-content .radio-title { color: #3b82f6; }
        .radio-group label:has(input[type="radio"][value="1"]:checked) { border-color: #3b82f6; background: #eff6ff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1); transform: translateY(-2px); }

        .radio-group input[type="radio"][value="0"]:checked + .radio-content .radio-title { color: #ef4444; }
        .radio-group label:has(input[type="radio"][value="0"]:checked) { border-color: #ef4444; background: #fef2f2; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1); transform: translateY(-2px); }

        .fallback-submit { display: none; }

        .bottom-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .nav-btn { padding: 10px 20px; background: #f1f5f9; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; transition: background 0.2s; }
        .nav-btn:hover { background: #e2e8f0; color: #0f172a; }
        .nav-btn.disabled { visibility: hidden; }
        .progress-info { font-weight: 700; color: #0f172a; font-size: 15px; background: #e2e8f0; padding: 6px 16px; border-radius: 20px;}

        @media (max-width: 600px) {
            .container { padding: 15px; }
            .radio-group { grid-template-columns: 1fr; gap: 8px; }
            .main-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <?php if ($is_completed): ?>
        <div class="container" style="text-align: center; padding: 60px 20px; max-width: 500px;">
            <h2 style="font-size: 28px; color: #0f172a; margin-bottom: 15px;">Thank You!</h2>
            <p style="font-size: 16px; color: #64748b; margin-bottom: 40px;">Prompt evaluation has been completed.</p>
            
            <?php if (!$other_survey_completed): ?>
                <div style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
                    <p style="margin-top: 0; margin-bottom: 15px; font-weight: bold; color: #1e293b;">Image evaluation is not completed yet.</p>
                    <a href="start_image_survey.php" class="home-btn" style="background: #8b5cf6; color: white; padding: 12px 24px; font-size: 16px; display: inline-block;">Go to Image evaluation</a>
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
                Prompts evaluation
            </h2>

            <div class="target-box">
                <span class="label-badge">A</span>
                <div class="en-text"><?php echo htmlspecialchars($explicit); ?></div>
                <?php if (!empty(trim($explicit_kr))): ?>
                <details class="translation-dropdown">
                    <summary>한국어 번역 보기 (영어 프롬프트 평가입니다. 최대한 영어 원문을 기준으로 평가해주세요.)</summary>
                    <div class="kr-text"><?php echo htmlspecialchars($explicit_kr); ?></div>
                </details>
                <?php endif; ?>
            </div>

            <form id="survey-form" action="prompt_submit.php" method="POST">
                <input type="hidden" name="base_id" value="<?php echo $base_id; ?>">

                <?php 
                $sources = ['Ours', 'PGJ', 'DACA'];
                foreach ($sources as $src): 
                    $p = $prompts[$src];
                ?>
                <div class="prompt-section">
                    <span class="label-badge" style="background: <?php echo $src == 'Ours' ? '#3b82f6' : ($src == 'PGJ' ? '#8b5cf6' : '#10b981'); ?>"><?php echo $src; ?> Prompt</span>
                    <div class="en-text" style="margin-top: 15px;"><?php echo htmlspecialchars($p['implicit_unsafe']); ?></div>
                    
                    <?php if (!empty(trim($p['implicit_unsafe_kr']))): ?>
                    <details class="translation-dropdown">
                        <summary>한국어 번역 보기 (영어 프롬프트 평가입니다. 최대한 영어 원문을 기준으로 평가해주세요.)</summary>
                        <div class="kr-text"><?php echo htmlspecialchars($p['implicit_unsafe_kr']); ?></div>
                    </details>
                    <?php endif; ?>
                    
                    <div class="question-title">Can you infer A from this sentence?</div>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="score_<?php echo $src; ?>" value="1" required>
                            <div class="radio-content">
                                <div class="radio-title">Yes</div>
                            </div>
                        </label>
                        <label>
                            <input type="radio" name="score_<?php echo $src; ?>" value="0">
                            <div class="radio-content">
                                <div class="radio-title">No</div>
                            </div>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="fallback-submit">Submit</button>
            </form>

            <div class="bottom-nav">
                <?php if($current_index > 0): ?>
                    <a href="prompt_eval.php?nav=prev" class="nav-btn">&laquo; Previous</a>
                <?php else: ?>
                    <span class="nav-btn disabled">&laquo; Previous</span>
                <?php endif; ?>
                
                <div class="progress-info"><?php echo ($current_index + 1) . " / " . $total_questions; ?></div>
                
                <span class="nav-btn disabled">Next &raquo;</span>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('survey-form');
                const radios = document.querySelectorAll('input[type="radio"]');

                function checkAndSubmit() {
                    const ansOurs = document.querySelector('input[name="score_Ours"]:checked');
                    const ansPGJ = document.querySelector('input[name="score_PGJ"]:checked');
                    const ansDACA = document.querySelector('input[name="score_DACA"]:checked');

                    if (ansOurs && ansPGJ && ansDACA) {
                        setTimeout(() => form.submit(), 300); 
                    }
                }
                radios.forEach(radio => radio.addEventListener('change', checkAndSubmit));
            });
        </script>
    <?php endif; ?>
</body>
</html>