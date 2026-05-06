<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error: " . $conn->connect_error);

$email = $_SESSION['email'] ?? "";
if(empty($email)){
    header("Location: login.html");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM patients WHERE email=?");
if(!$stmt) die("Prepare failed: " . $conn->error);

$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user) die("User not found");



$period = $_GET['period'] ?? 7;
$period = (int)$period;

$stmt = $conn->prepare("
    SELECT * FROM health_data 
    WHERE email=? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ORDER BY date ASC
");

if(!$stmt) die("Prepare failed: " . $conn->error);

$stmt->bind_param("si",$email,$period);
$stmt->execute();
$res = $stmt->get_result();

while($row = $res->fetch_assoc()){
    $history7[] = $row;
}



$dates = [];
$weights = [];
$glucose = [];
$tension = [];
$water = [];

foreach($history7 as $h){
    $dates[] = date("d M", strtotime($h['date']));
    $weights[] = is_numeric($h['weight']) ? (float)$h['weight'] : null;
    $glucose[] = is_numeric($h['glucose']) ? (float)$h['glucose'] : null;
    $tension[] = is_numeric($h['tension']) ? (int)$h['tension'] : null;
    $water[] = is_numeric($h['water']) ? (int)$h['water'] : 0;
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Health History</title>
<script src="../script.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    display: flex;
    min-height: 100vh;
}




/*  MAIN  */ 
.main {
    flex: 1; padding: 30px;
    display: flex; flex-direction: column; gap: 22px;
    overflow-y: auto;
}

/*  HEADER  */ 
.page-header {
    display: flex; align-items: center; justify-content: space-between;
}
.page-header h1 { font-size: 24px; color: #1f4e5f; font-weight: bold; }

.period-selector {
    display: flex; gap: 8px;
}
.period-btn {
    padding: 8px 16px;
    border: 2px solid #e5e7eb;
    background: white;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: .15s;
}
.period-btn:hover { border-color: #2c7a7b; color: #1f4e5f; }
.period-btn.active { background: #1f4e5f; color: white; border-color: #1f4e5f; }

/*  CHARTS GRID  */ 
.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

/*  CHART CARD  */ 
.chart-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.chart-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.chart-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.icon-weight   { background: #d1fae5; }
.icon-glucose  { background: #dbeafe; }
.icon-tension  { background: #fee2e2; }
.icon-water    { background: #dbeafe; }

.chart-title { font-size: 15px; color: #1f4e5f; font-weight: bold; }

.chart-canvas {
    height: 200px !important;
}

/* Empty state */
.empty-charts {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-charts .icon { font-size: 48px; margin-bottom: 12px; }
.empty-charts p { font-size: 14px; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">

    <!-- HEADER -->
    <div class="page-header">
        <h1>📊 Health History</h1>
        <div class="period-selector">
            <button class="period-btn <?= ($_GET['period'] ?? 7)==7?'active':'' ?>" onclick="changePeriod(7)">7 Days</button>
            <button class="period-btn <?= ($_GET['period'] ?? 7)==30?'active':'' ?>" onclick="changePeriod(30)">30 Days</button>
        </div>
    </div>

    <!-- CHARTS GRID -->
    <div class="charts-grid">

        <?php if(empty($history7)): ?>
        <div class="empty-charts">
            <div class="icon">📊</div>
            <p>No health data recorded yet.<br>Start tracking your health from the home page.</p>
        </div>
        <?php else: ?>

        <!-- WEIGHT CHART -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon icon-weight">⚖️</div>
                <span class="chart-title">Weight (kg)</span>
            </div>
            <canvas id="weightChart" class="chart-canvas"></canvas>
        </div>

        <!-- GLUCOSE CHART -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon icon-glucose">🩸</div>
                <span class="chart-title">Glucose (mg/dL)</span>
            </div>
            <canvas id="glucoseChart" class="chart-canvas"></canvas>
        </div>

        <!-- TENSION CHART -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon icon-tension">❤️</div>
                <span class="chart-title">Blood Pressure</span>
            </div>
            <canvas id="tensionChart" class="chart-canvas"></canvas>
        </div>

        <!-- WATER CHART -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-icon icon-water">💧</div>
                <span class="chart-title">Water Intake (glasses)</span>
            </div>
            <canvas id="waterChart" class="chart-canvas"></canvas>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php if(!empty($history7)): ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>


const labels = <?= json_encode($dates) ?>;
const weight  = <?= json_encode($weights) ?>;
const glucose = <?= json_encode($glucose) ?>;
const tension = <?= json_encode($tension) ?>;
const water   = <?= json_encode($water) ?>;

const chartConfig = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }
    },
    scales: {
        y: { beginAtZero: false, grid: { color: '#f0f0f0' } },
        x: { grid: { display: false } }
    }
};

// WEIGHT
new Chart(document.getElementById("weightChart"), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Weight (kg)',
            data: weight,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
            fill: true,
            borderWidth: 2,
            pointBackgroundColor: '#10b981',
            pointBorderWidth: 0,
            pointRadius: 4
        }]
    },
    options: chartConfig
});

// GLUCOSE
new Chart(document.getElementById("glucoseChart"), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Glucose',
            data: glucose,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.3,
            fill: true,
            borderWidth: 2,
            pointBackgroundColor: '#3b82f6',
            pointBorderWidth: 0,
            pointRadius: 4
        }]
    },
    options: chartConfig
});

// TENSION
new Chart(document.getElementById("tensionChart"), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Tension',
            data: tension,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.3,
            fill: true,
            borderWidth: 2,
            pointBackgroundColor: '#ef4444',
            pointBorderWidth: 0,
            pointRadius: 4
        }]
    },
    options: chartConfig
});

// WATER
new Chart(document.getElementById("waterChart"), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Water (glasses)',
            data: water,
            backgroundColor: '#60a5fa',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});

function changePeriod(days){
    window.location.href = "?period=" + days;
}
</script>
<?php endif; ?>

</body>
</html>
