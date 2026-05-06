<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");
$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$unreadStmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM messages 
    WHERE receiver_email = ? AND is_read = 0
");
$unreadStmt->bind_param("s", $email);
$unreadStmt->execute();
$unreadRes   = $unreadStmt->get_result()->fetch_assoc();
$unreadCount = $unreadRes['total'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM patients WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user) die("User not found");

$kg     = (int)$user['kg'];
$age    = (int)$user['age'];
$target = max(1, round(($kg * 33) / 250));
$today  = date("Y-m-d");
$nowDT  = date("Y-m-d H:i:s");

if(empty($user['last_reset']) || $user['last_reset'] != $today){
    $reset = $conn->prepare("UPDATE patients SET water_today = 0, last_reset = ? WHERE email = ?");
    $reset->bind_param("ss", $today, $email);
    $reset->execute();
    $stmt = $conn->prepare("SELECT * FROM patients WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

$current       = (int)($user['water_today'] ?? 0);
$glucose_today = $user['glucose_today'] ?? null;
$tension_today = $user['tension_today'] ?? "";

$advice = "";
if($age < 18)       { $advice = "👶 Eat healthy + sleep well"; }
elseif($age < 40)   { $advice = "💪 Stay active + hydrate"; }
else                { $advice = "❤️ Regular checkups"; }

$medical = strtolower(($user['history'] ?? "") . " " . ($user['allergies'] ?? ""));
if(strpos($medical,"diabetes") !== false) $advice .= "<br>💊 Diabetes care required";
if(strpos($medical,"asthma")   !== false) $advice .= "<br>💨 Avoid dust & smoke";

if($glucose_today !== null && $glucose_today !== ""){
    if($glucose_today > 140)      $advice .= "<br>⚠️ High glucose ($glucose_today)";
    elseif($glucose_today < 70)   $advice .= "<br>⚠️ Low glucose ($glucose_today)";
    else                          $advice .= "<br>✅ Glucose normal";
}
if(!empty($tension_today)){
    $parts2 = explode("/", $tension_today);
    if(count($parts2) == 2){
        $sys = (int)$parts2[0]; $dia = (int)$parts2[1];
        if($sys > 140 || $dia > 90)      $advice .= "<br>⚠️ High blood pressure ($sys/$dia)";
        elseif($sys < 90 || $dia < 60)   $advice .= "<br>⚠️ Low blood pressure ($sys/$dia)";
        else                              $advice .= "<br>✅ Blood pressure normal";
    }
}

$calories = ($age < 18) ? 2000 : (($age < 40) ? 2200 : 2000);
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$parts = explode(" ", $user['fname']." ".$user['lname']);
$initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):""));


$stmtAppt = $conn->prepare("
    SELECT a.*, d.fname, d.lname, d.speciality, d.email AS doctor_email
    FROM appointments a
    JOIN doctors d ON d.email = a.doctor_email
    WHERE a.patient_email = ?
    AND a.status != 'cancelled'
    AND (
        /* confirmed: show until date+time passes */
        (a.status = 'confirmed' AND CONCAT(a.date,' ',a.time) >= NOW())
        OR
        /* pending: show only if date is today or future */
        (a.status = 'pending' AND a.date >= CURDATE())
    )
    ORDER BY a.date ASC, a.time ASC
");
$stmtAppt->bind_param("s", $email);
$stmtAppt->execute();
$apptRes = $stmtAppt->get_result();
$apptList = $apptRes->fetch_all(MYSQLI_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Dashboard</title>
<script src="../script.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    display: flex;
    min-height: 100vh;
}

.main {
    flex: 1; padding: 30px;
    display: flex; flex-direction: column; gap: 22px;
    overflow-y: auto;
}

/*  Welcome header  */ 
.welcome-header {
    background: white; border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 26px; display: flex; align-items: center; gap: 18px;
}
.welcome-avatar {
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: bold; flex-shrink: 0; border: 3px solid #e5e7eb;
}
.welcome-header h1 { font-size: 22px; color: #1f4e5f; margin-bottom: 4px; }
.welcome-header p  { font-size: 13px; color: #6b7280; }

/*  Cards grid  */ 
.cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.card {
    background: white; border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.card h3 {
    font-size: 15px; color: #1f4e5f; font-weight: bold;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: center; justify-content: space-between;
}
.card h3 a {
    font-size: 12px; color: #2c7a7b; font-weight: 600;
    text-decoration: none;
}
.card h3 a:hover { text-decoration: underline; }

/*  Water tracker  */ 
.water-count { font-size: 42px; font-weight: bold; color: #1f4e5f; text-align: center; margin: 16px 0; }
.water-label { text-align: center; font-size: 13px; color: #6b7280; margin-bottom: 16px; }
.progress { width: 100%; height: 12px; background: #e5e7eb; border-radius: 20px; overflow: hidden; margin-bottom: 16px; }
.bar { height: 12px; width: 0%; background: linear-gradient(90deg, #2c7a7b, #1f4e5f); transition: width .3s ease; border-radius: 20px; }
.bar.complete { background: linear-gradient(90deg, #10b981, #059669); }
.btn-drink {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white; border: none; border-radius: 10px;
    font-size: 14px; font-weight: bold; cursor: pointer; transition: .2s;
}
.btn-drink:hover { opacity: .9; transform: scale(1.02); background: #29b8aa; }

/*  Health advice  */ 
.advice-text { font-size: 13px; color: #374151; line-height: 1.8; margin-bottom: 16px; }
.calories-box { background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px; text-align: center; margin-top: 12px; }
.calories-num { font-size: 28px; font-weight: bold; color: #1f4e5f; }
.calories-label { font-size: 11px; color: #6b7280; margin-top: 4px; }

/*  Health form  */ 
.health-form { display: flex; flex-direction: column; gap: 10px; }
.health-form input {
    padding: 10px 12px; border: 2px solid #d1d5db;
    background: #f9fafb; border-radius: 8px; font-size: 14px; color: #111827;
}
.health-form input:focus { outline: none; border-color: #2c7a7b; box-shadow: 0 0 0 3px rgba(44,122,123,.15); }
.health-form button {
    padding: 11px;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white; border: none; border-radius: 8px;
    font-size: 14px; font-weight: bold; cursor: pointer; transition: .2s;
}
.health-form button:hover { opacity: .9; background: #29b8aa; }

/*  Doctors card  */ 
.doctor-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; background: #f9fafb;
    border-radius: 10px; border: 1px solid #f0f0f0;
    margin-bottom: 10px; position: relative;
}
.doctor-item:last-child { margin-bottom: 0; }
.doc-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: bold; flex-shrink: 0;
}
.doc-info { flex: 1; min-width: 0; }
.doc-name  { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 2px; }
.doc-spec  { font-size: 11px; color: #6b7280; margin-bottom: 2px; }
.doc-email { font-size: 11px; color: #2c7a7b; text-decoration: none; font-weight: 600; }
.doc-email:hover { text-decoration: underline; }
.unread-dot {
    position: absolute; top: 12px; right: 12px;
    width: 10px; height: 10px; background: #ef4444;
    border-radius: 50%; border: 2px solid white; animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{transform:scale(1);opacity:1;} 50%{transform:scale(1.3);opacity:.7;} }
.no-doctor { text-align: center; padding: 20px; color: #9ca3af; font-size: 13px; }

/*  Save overlay  */ 
.overlay {
    position: fixed; top:0; left:0; width:100vw; height:100vh;
    background: rgba(0,0,0,.35); backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: 0.3s ease; z-index: 999;
}
.overlay.show { opacity: 1; pointer-events: all; }
.save { background: #0be8adb9; color: white; padding: 16px 26px; border-radius: 14px; font-weight: bold; font-size: 15px; }

/* ══════════════════════════════════════════
   APPOINTMENTS CARD
══════════════════════════════════════════ */
.appt-card-full {
    background: white; border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.appt-card-full h3 {
    font-size: 15px; color: #1f4e5f; font-weight: bold;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: center; justify-content: space-between;
}
.appt-card-full h3 a { font-size: 12px; color: #2c7a7b; font-weight: 600; text-decoration: none; }
.appt-card-full h3 a:hover { text-decoration: underline; }

.appt-list { display: flex; flex-direction: column; gap: 10px; }

.appt-row {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 16px; border-radius: 12px;
    border: 1px solid #e5e7eb; background: #fafafa;
    transition: transform .15s, box-shadow .15s;
    border-left: 4px solid #2c7a7b;
}
.appt-row:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.08); }
.appt-row.today-row { border-left-color: #f59e0b; background: linear-gradient(135deg,#fffbeb,#fafafa); }
.appt-row.confirmed-row { border-left-color: #10b981; }
.appt-row.soon-row { border-left-color: #ef4444; background: linear-gradient(135deg,#fff5f5,#fafafa); animation: pulseBorder 2s infinite; }
@keyframes pulseBorder { 0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.1);} 50%{box-shadow:0 0 0 4px rgba(239,68,68,.15);} }

/* Date column */
.appt-date-col {
    display: flex; flex-direction: column; align-items: center;
    min-width: 54px; flex-shrink: 0;
    background: #f0f9ff; border-radius: 10px;
    padding: 8px 6px; border: 1px solid #bfdbfe;
}
.appt-date-day   { font-size: 22px; font-weight: 900; color: #1f4e5f; line-height: 1; }
.appt-date-month { font-size: 10px; font-weight: 700; color: #2c7a7b; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
.appt-date-year  { font-size: 9px; color: #94a3b8; margin-top: 1px; }

/* today chip */
.appt-date-col.today-col { background: #fef3c7; border-color: #fcd34d; }
.appt-date-col.today-col .appt-date-day   { color: #92400e; }
.appt-date-col.today-col .appt-date-month { color: #b45309; }

/* Doctor avatar */
.appt-doc-av {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg,#1f4e5f,#2c7a7b);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: bold; flex-shrink: 0;
}

/* Body */
.appt-body { flex: 1; min-width: 0; }
.appt-doc-name { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 2px; }
.appt-doc-spec { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
.appt-meta     { display: flex; gap: 10px; flex-wrap: wrap; font-size: 11px; color: #9ca3af; }

/* Status badge */
.appt-status {
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; font-weight: bold; flex-shrink: 0; align-self: flex-start;
    white-space: nowrap;
}
.status-confirmed { background: #d1fae5; color: #065f46; }
.status-pending   { background: #fef3c7; color: #92400e; }
.status-soon      { background: #fee2e2; color: #991b1b; }

/* Today chip label */
.today-chip {
    font-size: 10px; background: #f59e0b; color: white;
    padding: 2px 8px; border-radius: 20px; font-weight: bold;
    margin-left: 4px;
}
.soon-chip {
    font-size: 10px; background: #ef4444; color: white;
    padding: 2px 8px; border-radius: 20px; font-weight: bold;
    margin-left: 4px;
}

/* Empty appointments */
.appt-empty {
    text-align: center; padding: 30px 20px; color: #9ca3af;
}
.appt-empty .icon { font-size: 36px; margin-bottom: 8px; }
.appt-empty p { font-size: 13px; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="welcome-header">
        <div class="welcome-avatar"><?= $initials ?></div>
        <div>
            <h1>Welcome <?= htmlspecialchars($user['fname']) ?> 👋</h1>
            <p>📅 <?= date("l, d F Y") ?> • Stay healthy with Tunicare</p>
        </div>
    </div>


    <!-- ══════════════════════════════════
         APPOINTMENTS SECTION (full width)
    ══════════════════════════════════ -->
    <div class="appt-card-full">
        <h3>
            📆 Upcoming Appointments
            <a href="rendezvous.php">View all →</a>
        </h3>

        <?php if(empty($apptList)): ?>
        <div class="appt-empty">
            <div class="icon">📭</div>
            <p>No upcoming appointments.<br>
               <a href="rendezvous.php" style="color:#2c7a7b;font-weight:bold;">Book one now →</a>
            </p>
        </div>
        <?php else: ?>
        <div class="appt-list">
        <?php foreach($apptList as $a):
            $aDate    = $a['date'];
            $aTime    = $a['time'];
            $aStatus  = $a['status'];

            $isToday  = ($aDate === $today);
            $diffSecs = strtotime($aDate.' '.$aTime) - time();
            $isSoon   = $isToday && $diffSecs >= 0 && $diffSecs <= 3600;

            /* Row class */
            $rowClass = '';
            if($isSoon)            $rowClass = 'appt-row soon-row';
            elseif($aStatus==='confirmed') $rowClass = 'appt-row confirmed-row';
            elseif($isToday)       $rowClass = 'appt-row today-row';
            else                   $rowClass = 'appt-row';

            /* Date display */
            $dayNum   = date("d", strtotime($aDate));
            $monthStr = date("M", strtotime($aDate));
            $yearStr  = date("Y", strtotime($aDate));
            $timeStr  = date("H:i", strtotime($aTime));

            /* Doctor initials */
            $dp   = explode(" ", $a['fname']." ".$a['lname']);
            $dinit = strtoupper(substr($dp[0],0,1).(isset($dp[1])?substr($dp[1],0,1):""));

            /* Status badge */
            if($isSoon){
                $statusClass = 'appt-status status-soon';
                $statusLabel = '⚡ Now!';
            } elseif($aStatus === 'confirmed'){
                $statusClass = 'appt-status status-confirmed';
                $statusLabel = '✅ Confirmed';
            } else {
                $statusClass = 'appt-status status-pending';
                $statusLabel = '⏳ Pending';
            }

            /* countdown label */
            $countLabel = '';
            if($diffSecs > 0){
                $hrs  = floor($diffSecs / 3600);
                $mins = floor(($diffSecs % 3600) / 60);
                if($hrs > 0)       $countLabel = "In {$hrs}h {$mins}m";
                elseif($mins > 0)  $countLabel = "In {$mins} min";
                else               $countLabel = "Now";
            }
        ?>
        <div class="<?= $rowClass ?>">

            <!-- Date box -->
            <div class="appt-date-col <?= $isToday ? 'today-col' : '' ?>">
                <div class="appt-date-day"><?= $dayNum ?></div>
                <div class="appt-date-month"><?= $monthStr ?></div>
                <div class="appt-date-year"><?= $yearStr ?></div>
            </div>

            <!-- Doctor avatar -->
            <div class="appt-doc-av"><?= $dinit ?></div>

            <!-- Info -->
            <div class="appt-body">
                <div class="appt-doc-name">
                    Dr. <?= htmlspecialchars($a['fname']." ".$a['lname']) ?>
                    <?php if($isSoon): ?>
                        <span class="soon-chip">⚡ &lt;1h</span>
                    <?php elseif($isToday): ?>
                        <span class="today-chip">Today</span>
                    <?php endif; ?>
                </div>
                <div class="appt-doc-spec">🩺 <?= htmlspecialchars($a['speciality']) ?></div>
                <div class="appt-meta">
                    <span>⏰ <?= $timeStr ?></span>
                    <?php if(!empty($a['reason'])): ?>
                    <span>📝 <?= htmlspecialchars(mb_substr($a['reason'],0,40)) ?><?= mb_strlen($a['reason']??'')>40?'...':'' ?></span>
                    <?php endif; ?>
                    <?php if($countLabel): ?>
                    <span style="color:#2c7a7b;font-weight:600;">⏱ <?= $countLabel ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status -->
            <span class="<?= $statusClass ?>"><?= $statusLabel ?></span>

        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="cards-grid">


        <!-- Water Tracker -->
        <div class="card">
            <h3>💧 Water Tracker</h3>
            <div class="water-count" id="count"><?= $current ?></div>
            <div class="water-label">Goal: <?= $target ?> glasses today</div>
            <div class="progress">
                <div class="bar" id="bar"></div>
            </div>
            <button class="btn-drink" onclick="drink()">💧 + Drink water</button>
        </div>

        <!-- Health Advice -->
        <div class="card">
            <h3>🧠 Health Advice</h3>
            <div class="advice-text"><?= $advice ?></div>
            <div class="calories-box">
                <div class="calories-num"><?= $calories ?></div>
                <div class="calories-label">kcal / day recommended</div>
            </div>
        </div>

        <!-- Record Health Data -->
        <div class="card">
            <h3>📊 Record Health Data</h3>
            <form class="health-form" onsubmit="saveHealth(event)">
                <input type="number" name="weight"  placeholder="⚖️ Weight (kg)" step="0.1">
                <input type="text"   name="tension" placeholder="❤️ Blood Pressure (120/80)">
                <input type="number" name="glucose" placeholder="🩸 Glucose (mg/dL)">
                <button type="submit">💾 Save Data</button>
            </form>
            <div id="overlay" class="overlay">
                <div id="saveMsg" class="save">✅ Saved successfully</div>
            </div>
        </div>

        <!-- My Doctors -->
        <div class="card">
            <h3>👨‍⚕️ My Doctors</h3>
            <?php
            $stmt = $conn->prepare("
                SELECT d.fname, d.lname, d.speciality, d.email,
                (SELECT COUNT(*) FROM messages m
                 WHERE m.receiver_email=? AND m.sender_email=d.email AND m.is_read=0) as unread
                FROM doctors d
                JOIN patient_doctors pd ON pd.doctor_email=d.email
                WHERE pd.patient_email=?
            ");
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if($res->num_rows == 0): ?>
                <div class="no-doctor">👨‍⚕️ No doctor assigned yet.</div>
            <?php endif;
            while($doc = $res->fetch_assoc()):
                $dparts = explode(" ", $doc['fname']." ".$doc['lname']);
                $dinit  = strtoupper(substr($dparts[0],0,1).(isset($dparts[1])?substr($dparts[1],0,1):""));
            ?>
            <div class="doctor-item">
                <?php if($doc['unread'] > 0): ?><span class="unread-dot"></span><?php endif; ?>
                <div class="doc-avatar"><?= $dinit ?></div>
                <div class="doc-info">
                    <div class="doc-name">Dr. <?= htmlspecialchars($doc['fname']." ".$doc['lname']) ?></div>
                    <div class="doc-spec">🩺 <?= htmlspecialchars($doc['speciality']) ?></div>
                    <a href="../chat.php?user=<?= urlencode($doc['email']) ?>" class="doc-email">💬 Chat with doctor</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

    </div>

    

</div>

<script>
let current = <?= $current ?>;
let target  = <?= $target ?>;

setInterval(() => {
    fetch("check_med.php")
    .then(res => res.text())
    .then(data => {
        if(data.trim() !== "") showToast("💊 Time for: " + data, '#3b82f6', '#1d4ed8');
    });
}, 60000);

function updateUI(){
    let percent = (current / target) * 100;
    let bar = document.getElementById("bar");
    bar.style.width = Math.min(percent, 100) + "%";
    bar.classList.toggle("complete", percent >= 100);
    document.getElementById("count").innerText = current;
}
updateUI();

function drink(){
    fetch("update_water.php", { method: "POST", credentials: "include" })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === "ok"){ current++; updateUI(); }
        else alert("Error: " + data);
    })
    .catch(err => console.log("FETCH ERROR:", err));
}

function saveHealth(e){
    e.preventDefault();
    let form = document.querySelector(".health-form");
    fetch("save_health.php", { method: "POST", body: new FormData(form) })
    .then(res => res.text())
    .then(data => {
        if(data.trim().includes("saved")){
            let overlay = document.getElementById("overlay");
            overlay.classList.add("show");
            setTimeout(() => overlay.classList.remove("show"), 2500);
            form.reset();
        }
    });
}


const upcomingAppts = <?= json_encode(array_map(function($a){
    return [
        'id'     => $a['id'],
        'doctor' => 'Dr. '.$a['fname'].' '.$a['lname'],
        'date'   => $a['date'],
        'time'   => substr($a['time'],0,5),
        'status' => $a['status'],
    ];
}, $apptList)); 
?>;

setTimeout(() => location.reload(), 5 * 60 * 1000);



</script>

</body>
</html>
