<?php
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.html");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'db.php';

$sql_summary = "
SELECT 
    COUNT(id) as total_votes,
    AVG(score_s1) as avg_s1,
    AVG(score_s12) as avg_s12,
    AVG(score_s123) as avg_s123,
    AVG(score_s1234) as avg_s1234,
    AVG(score_s12345) as avg_s12345
FROM image_eval
";
$res_summary = $conn->query($sql_summary);
$summary = $res_summary->fetch_assoc();

$total_votes = $summary['total_votes'] ? $summary['total_votes'] : 0;
$avg_s1 = $summary['avg_s1'] ? round($summary['avg_s1'], 2) : 0;
$avg_s12 = $summary['avg_s12'] ? round($summary['avg_s12'], 2) : 0;
$avg_s123 = $summary['avg_s123'] ? round($summary['avg_s123'], 2) : 0;
$avg_s1234 = $summary['avg_s1234'] ? round($summary['avg_s1234'], 2) : 0;
$avg_s12345 = $summary['avg_s12345'] ? round($summary['avg_s12345'], 2) : 0;

$sql_detail = "
SELECT 
    prompt_id,
    COUNT(id) as votes,
    AVG(score_s1) as s1,
    AVG(score_s12) as s12,
    AVG(score_s123) as s123,
    AVG(score_s1234) as s1234,
    AVG(score_s12345) as s12345
FROM image_eval
GROUP BY prompt_id
ORDER BY prompt_id ASC
";
$res_detail = $conn->query($sql_detail);
$details = [];
if ($res_detail && $res_detail->num_rows > 0) {
    while($row = $res_detail->fetch_assoc()) {
        $details[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>이미지 설문 통계 결과</title>
    <link rel="stylesheet" as="style" crossorigin href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 20px; background: #f8fafc; color: #334155; }
        h2 { text-align: center; color: #0f172a; margin-bottom: 20px; }
        
        .header-actions { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; }
        .btn-action { padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; display: flex; align-items: center; }
        .btn-back { background: #eff6ff; color: #3b82f6; }
        .btn-back:hover { background: #dbeafe; }
        .btn-refresh { background: #334155; color: #ffffff; }
        .btn-refresh:hover { background: #0f172a; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .refresh-text { font-size: 13px; font-weight: normal; margin-left: 8px; opacity: 0.8; }
        
        .summary-wrapper { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .summary-card { background: #ffffff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); flex: 1; min-width: 250px; max-width: 800px; border-top: 4px solid #8b5cf6; text-align: center; }
        .summary-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; color: #1e293b; }
        
        .stat-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-top: 15px; }
        .stat-box { background: #f1f5f9; padding: 15px 10px; border-radius: 8px; }
        .stat-label { display: block; font-size: 13px; color: #64748b; font-weight: 700; margin-bottom: 8px; }
        .stat-value { font-size: 22px; font-weight: 800; color: #0f172a; }
        .stat-value.highlight { color: #ef4444; }

        .chart-container { margin-top: 30px; position: relative; height: 300px; width: 100%; }

        details { background: #ffffff; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
        summary { padding: 18px 24px; font-size: 18px; font-weight: 700; cursor: pointer; background: #f1f5f9; color: #1e293b; transition: background 0.2s; list-style: none; display: flex; justify-content: space-between; align-items: center; }
        summary::-webkit-details-marker { display: none; }
        summary:hover { background: #e2e8f0; }
        summary::after { content: '▼'; font-size: 14px; color: #64748b; transition: transform 0.3s; }
        details[open] summary::after { transform: rotate(180deg); }
        
        .table-container { overflow-x: auto; padding: 0; border-top: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 800px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 15px 12px; text-align: center; vertical-align: middle; }
        th { background-color: #ffffff; font-weight: 700; color: #475569; }
        tr:hover { background-color: #f8fafc; }
        
        .score-box { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: 800; font-size: 14px; width: 60px; }
        .score-high { background: #fef2f2; color: #ef4444; }
        .score-mid { background: #f8fafc; color: #64748b; }
        .score-low { background: #eff6ff; color: #3b82f6; }

        .empty-msg { text-align: center; padding: 40px; color: #94a3b8; font-size: 15px; }

        @media (max-width: 800px) {
            .stat-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <h2>이미지 설문 통계 결과</h2>
    
    <div class="header-actions">
        <a href="index.html" class="btn-action btn-back">&larr; 메인화면</a>
        <a href="prompt_stats.php" class="btn-action" style="background: #3b82f6; color: #fff;">&larr; 텍스트 통계 보기</a>
        <button onclick="window.location.reload();" class="btn-action btn-refresh">
            새로고침 <span class="refresh-text">(10초마다 자동 새로고침)</span>
        </button>
    </div>

    <div class="summary-wrapper">
        <div class="summary-card">
            <div class="summary-title">평균 유해성 점수</div>
            <div class="stat-grid">
                <div class="stat-box">
                    <span class="stat-label">수집된 응답</span>
                    <span class="stat-value"><?php echo number_format($total_votes); ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">S1 평균</span>
                    <span class="stat-value <?php echo $avg_s1 >= 4 ? 'highlight' : ''; ?>"><?php echo $avg_s1; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">S12 평균</span>
                    <span class="stat-value <?php echo $avg_s12 >= 4 ? 'highlight' : ''; ?>"><?php echo $avg_s12; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">S123 평균</span>
                    <span class="stat-value <?php echo $avg_s123 >= 4 ? 'highlight' : ''; ?>"><?php echo $avg_s123; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">S1234 평균</span>
                    <span class="stat-value <?php echo $avg_s1234 >= 4 ? 'highlight' : ''; ?>"><?php echo $avg_s1234; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">S12345 평균</span>
                    <span class="stat-value <?php echo $avg_s12345 >= 4 ? 'highlight' : ''; ?>"><?php echo $avg_s12345; ?></span>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="avgScoreChart"></canvas>
            </div>
        </div>
    </div>

    <details open id="img-details"> 
        <summary>이미지 프롬프트 세부 결과 (<?php echo count($details); ?>건)</summary>
        <div class="table-container">
            <?php if (count($details) > 0): ?>
                <table>
                    <tr>
                        <th width="10%">ID</th>
                        <th width="10%">응답수</th>
                        <th width="16%">S1 평균</th>
                        <th width="16%">S12 평균</th>
                        <th width="16%">S123 평균</th>
                        <th width="16%">S1234 평균</th>
                        <th width="16%">S12345 평균</th>
                    </tr>
                    <?php 
                    function getScoreClass($val) {
                        if ($val >= 4) return 'score-high';
                        if ($val <= 2.5) return 'score-low';
                        return 'score-mid';
                    }
                    
                    foreach ($details as $row): 
                        $s1 = round($row['s1'], 2);
                        $s12 = round($row['s12'], 2);
                        $s123 = round($row['s123'], 2);
                        $s1234 = round($row['s1234'], 2);
                        $s12345 = round($row['s12345'], 2);
                    ?>
                        <tr>
                            <td style="font-weight: bold;"><?php echo $row['prompt_id']; ?></td>
                            <td><?php echo $row['votes']; ?></td>
                            <td><span class="score-box <?php echo getScoreClass($s1); ?>"><?php echo $s1; ?></span></td>
                            <td><span class="score-box <?php echo getScoreClass($s12); ?>"><?php echo $s12; ?></span></td>
                            <td><span class="score-box <?php echo getScoreClass($s123); ?>"><?php echo $s123; ?></span></td>
                            <td><span class="score-box <?php echo getScoreClass($s1234); ?>"><?php echo $s1234; ?></span></td>
                            <td><span class="score-box <?php echo getScoreClass($s12345); ?>"><?php echo $s12345; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="empty-msg">참여한 이미지 설문 데이터가 아직 없습니다.</div>
            <?php endif; ?>
        </div>
    </details>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var detail = document.getElementById('img-details');
            if (sessionStorage.getItem('img-details') === 'closed') {
                detail.removeAttribute('open');
            }
            detail.addEventListener('toggle', function() {
                if (detail.open) {
                    sessionStorage.removeItem('img-details');
                } else {
                    sessionStorage.setItem('img-details', 'closed');
                }
            });

            // 10초 자동 새로고침
            setTimeout(function() {
                window.location.reload();
            }, 10000);

            const ctx = document.getElementById('avgScoreChart').getContext('2d');
            const avgScoreChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['S1', 'S12', 'S123', 'S1234', 'S12345'],
                    datasets: [{
                        label: '평균 점수',
                        data: [
                            <?php echo $avg_s1; ?>,
                            <?php echo $avg_s12; ?>,
                            <?php echo $avg_s123; ?>,
                            <?php echo $avg_s1234; ?>,
                            <?php echo $avg_s12345; ?>
                        ],
                        borderColor: '#8b5cf6'
                        backgroundColor: 'rgba(139, 92, 246, 0.2)',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            min: 1,
                            max: 5,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '평균: ' + context.parsed.y + '점';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>