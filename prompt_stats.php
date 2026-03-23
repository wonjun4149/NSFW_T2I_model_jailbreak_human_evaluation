<?php
session_start();

if (isset($_POST['admin_pw'])) {
    if ($_POST['admin_pw'] === 'nsfw') {
        $_SESSION['is_admin'] = true;
    } else {
        echo "<script>alert('비밀번호가 올바르지 않습니다.'); location.href='index.html';</script>";
        exit();
    }
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.html");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db.php';

$sql = "
SELECT 
    p.id, 
    p.implicit_unsafe, 
    p.original_category,
    IFNULL(p.source, 'Ours') as source,
    COUNT(e.score) as total_votes,
    SUM(CASE WHEN e.score = 1 THEN 1 ELSE 0 END) as harmful_votes,
    SUM(CASE WHEN e.score = 0 THEN 1 ELSE 0 END) as harmless_votes
FROM prompts p
JOIN entailment e ON p.id = e.prompt_id
GROUP BY p.id, p.implicit_unsafe, p.original_category, p.source
ORDER BY p.id ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("데이터베이스 에러: " . $conn->error);
}

$grouped_data = ['Ours' => [], 'PGJ' => [], 'DACA' => []];
$summary = [
    'Ours' => ['total' => 0, 'harmful' => 0, 'cats' => []],
    'PGJ'  => ['total' => 0, 'harmful' => 0, 'cats' => []],
    'DACA' => ['total' => 0, 'harmful' => 0, 'cats' => []]
];

$categories = ['Hate / Harassment', 'Illegal / Crime', 'Self-harm / Suicide', 'Sexual', 'Violence / Gore'];

foreach (['Ours', 'PGJ', 'DACA'] as $src) {
    foreach ($categories as $cat) {
        $summary[$src]['cats'][$cat] = ['total' => 0, 'harmful' => 0];
    }
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $src = $row['source'];
        $cat = trim($row['original_category']);
        
        $grouped_data[$src][] = $row;
        
        $summary[$src]['total'] += $row['total_votes'];
        $summary[$src]['harmful'] += $row['harmful_votes'];
        
        if (isset($summary[$src]['cats'][$cat])) {
            $summary[$src]['cats'][$cat]['total'] += $row['total_votes'];
            $summary[$src]['cats'][$cat]['harmful'] += $row['harmful_votes'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>설문 통계 결과</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />
    <style>
        body { font-family: 'Pretendard', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f8fafc; color: #334155; }
        h2 { text-align: center; color: #0f172a; margin-bottom: 20px; }
        
        .header-actions { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; }
        .btn-action { padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; display: flex; align-items: center; }
        .btn-back { background: #eff6ff; color: #3b82f6; }
        .btn-back:hover { background: #dbeafe; }
        .btn-refresh { background: #334155; color: #ffffff; }
        .btn-refresh:hover { background: #0f172a; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .refresh-text { font-size: 13px; font-weight: normal; margin-left: 8px; opacity: 0.8; }
        
        .summary-wrapper { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; align-items: flex-start; }
        .summary-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); flex: 1; min-width: 280px; max-width: 350px; border-top: 4px solid #3b82f6; }
        .summary-card.PGJ { border-top-color: #8b5cf6; }
        .summary-card.DACA { border-top-color: #10b981; }
        .summary-title { font-size: 20px; font-weight: 800; margin-bottom: 15px; color: #1e293b; text-align: center; }
        
        .summary-stat { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .stat-label { color: #64748b; font-weight: 600; }
        .stat-value { font-weight: 800; color: #0f172a; }
        
        .summary-divider { font-size: 13px; font-weight: 700; color: #94a3b8; margin: 15px 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        .cat-stat { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; }
        .cat-label { color: #475569; }
        .cat-value { font-weight: bold; color: #ef4444; }

        details { background: #ffffff; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
        summary { padding: 18px 24px; font-size: 18px; font-weight: 700; cursor: pointer; background: #f1f5f9; color: #1e293b; transition: background 0.2s; list-style: none; display: flex; justify-content: space-between; align-items: center; }
        summary::-webkit-details-marker { display: none; }
        summary:hover { background: #e2e8f0; }
        summary::after { content: '▼'; font-size: 14px; color: #64748b; transition: transform 0.3s; }
        details[open] summary::after { transform: rotate(180deg); }
        
        .table-container { overflow-x: auto; padding: 0; border-top: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 1000px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 15px 12px; text-align: center; vertical-align: middle; }
        th { background-color: #ffffff; font-weight: 700; color: #475569; white-space: nowrap; }
        td:nth-child(2) { text-align: left; }
        tr:hover { background-color: #f8fafc; }
        .text-wrap { max-width: 350px; word-break: break-all; line-height: 1.4; }
        
        .badge { display: inline-block; padding: 4px 10px; background: #e0e7ff; color: #4338ca; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .cnt-harmful { font-weight: 800; color: #ef4444; }
        .cnt-harmless { font-weight: 800; color: #3b82f6; }
        
        .empty-msg { text-align: center; padding: 40px; color: #94a3b8; font-size: 15px; }
    </style>
</head>
<body>
    <h2>설문조사 통합 통계 결과</h2>
    
    <div class="header-actions">
        <a href="index.html" class="btn-action btn-back">&larr; 메인화면</a>
        <a href="image_stats.php" class="btn-action" style="background: #8b5cf6; color: #fff;">이미지 통계 보기 &rarr;</a>
        <button onclick="window.location.reload();" class="btn-action btn-refresh">
            새로고침 <span class="refresh-text">(10초마다 자동 새로고침)</span>
        </button>
    </div>

    <div class="summary-wrapper">
        <?php foreach (['Ours', 'PGJ', 'DACA'] as $src): ?>
            <?php 
            $s_data = $summary[$src];
            $s_avg = $s_data['total'] > 0 ? round(($s_data['harmful'] / $s_data['total']) * 100, 1) : 0;
            ?>
            <div class="summary-card <?php echo $src; ?>">
                <div class="summary-title"><?php echo $src; ?> 모델</div>
                
                <div class="summary-stat">
                    <span class="stat-label">수집된 응답 수</span>
                    <span class="stat-value"><?php echo number_format($s_data['total']); ?>건</span>
                </div>
                <div class="summary-stat" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                    <span class="stat-label">전체 유해율</span>
                    <span class="stat-value" style="color: #ef4444; font-size: 18px;"><?php echo $s_avg; ?>%</span>
                </div>
                
                <div class="summary-divider">카테고리 별 유해율</div>
                <?php foreach ($categories as $cat): ?>
                    <?php 
                    $c_tot = $s_data['cats'][$cat]['total'];
                    $c_harm = $s_data['cats'][$cat]['harmful'];
                    $c_pct = $c_tot > 0 ? round(($c_harm / $c_tot) * 100, 1) : 0;
                    ?>
                    <div class="cat-stat">
                        <span class="cat-label"><?php echo htmlspecialchars($cat); ?></span>
                        <span class="cat-value"><?php echo $c_pct; ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

   <?php foreach (['Ours', 'PGJ', 'DACA'] as $src): ?>
        <details id="details-<?php echo $src; ?>"> 
            <summary><?php echo $src; ?> 프롬프트 상세 결과 (<?php echo count($grouped_data[$src]); ?>건)</summary>
            <div class="table-container">
                <?php if (count($grouped_data[$src]) > 0): ?>
                    <table>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="35%">Implicit 문장</th>
                            <th width="15%">카테고리</th>
                            <th width="8%">응답수</th>
                            <th width="8%">유해함 (예)</th>
                            <th width="8%">무해함 (아니오)</th>
                            <th width="21%">유해율 분포</th>
                        </tr>
                        <?php foreach ($grouped_data[$src] as $row): ?>
                            <?php
                            $total = $row['total_votes'];
                            $harmful = $row['harmful_votes'];
                            $harmless = $row['harmless_votes'];
                            $harm_pct = $total > 0 ? round(($harmful / $total) * 100, 1) : 0;
                            
                            $original_cat = trim($row['original_category']);
                            $original_cat_display = $original_cat ? htmlspecialchars($original_cat) : '미지정';
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td class='text-wrap'><?php echo htmlspecialchars($row['implicit_unsafe']); ?></td>
                                <td><span class='badge'><?php echo $original_cat_display; ?></span></td>
                                <td><?php echo $total; ?></td>
                                <td class="cnt-harmful"><?php echo $harmful; ?></td>
                                <td class="cnt-harmless"><?php echo $harmless; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex-grow: 1; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
                                            <div style="height: 100%; width: <?php echo $harm_pct; ?>%; background: #ef4444; border-radius: 5px; transition: width 0.3s ease;"></div>
                                        </div>
                                        <span style="font-weight: 800; color: #ef4444; width: 45px; text-align: right;"><?php echo $harm_pct; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-msg">해당 모델에 참여한 설문 데이터가 아직 없습니다.</div>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var detailsElements = document.querySelectorAll('details');
            
            detailsElements.forEach(function(detail) {
                var id = detail.getAttribute('id');
                
                if (sessionStorage.getItem(id) === 'open') {
                    detail.setAttribute('open', 'open');
                }
                
                detail.addEventListener('toggle', function() {
                    if (detail.open) {
                        sessionStorage.setItem(id, 'open');
                    } else {
                        sessionStorage.removeItem(id);
                    }
                });
            });

            setTimeout(function() {
                window.location.reload();
            }, 10000);
        });
    </script>
</body>
</html>