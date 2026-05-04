<?php
session_start();
$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$stmt = $conn->prepare("SELECT * FROM patients WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user) die("User not found");


if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='book'){
    $doc_email = $_POST['doctor_email'] ?? '';
    $date      = $_POST['date'] ?? '';
    $time      = $_POST['time'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if($doc_email && $date && $time){
        
    
        $check = $conn->prepare("SELECT id FROM appointments WHERE doctor_email=? AND date=? AND time=? AND status != 'cancelled'");
        $check->bind_param("sss",$doc_email,$date,$time);
        $check->execute();
        if($check->get_result()->num_rows > 0){
            $bookingError = "This time slot is already booked. Please choose another.";
        } else {
            $ins = $conn->prepare("INSERT INTO appointments(patient_email,doctor_email,date,time,reason,status) VALUES(?,?,?,?,?,'pending')");
            if($ins){
                $ins->bind_param("sssss",$email,$doc_email,$date,$time,$reason);
                $ins->execute();
                $bookingSuccess = "Appointment booked successfully!";
            } else {
                
                $ins2 = $conn->prepare("INSERT INTO appointments(patient_email,doctor_email,date,time,status) VALUES(?,?,?,?,'pending')");
                $ins2->bind_param("ssss",$email,$doc_email,$date,$time);
                $ins2->execute();
                $bookingSuccess = "Appointment booked successfully!";
            }
        }
    }
}


if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='cancel'){
    $appt_id = (int)$_POST['appt_id'];
    $stmt = $conn->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND patient_email=?");
    $stmt->bind_param("is",$appt_id,$email);
    $stmt->execute();
    header("Location: rendezvous.php"); exit();
}



$doctors = [];
$rd = $conn->query("SELECT d.email, d.fname, d.lname, d.speciality FROM doctors d JOIN patient_doctors pd ON pd.doctor_email=d.email WHERE pd.patient_email='".mysqli_real_escape_string($conn,$email)."'");
while($d = $rd->fetch_assoc()) $doctors[] = $d;



$myAppts = [];
$stmt = $conn->prepare("
    SELECT a.*, d.fname, d.lname, d.speciality 
    FROM appointments a
    JOIN doctors d ON d.email = a.doctor_email
    WHERE a.patient_email = ?
    AND a.date >= CURDATE()
    AND a.status != 'cancelled'
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("s",$email);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $myAppts[] = $r;


$selectedDoctor = $_GET['doctor'] ?? ($doctors[0]['email'] ?? '');
$calYear  = (int)($_GET['year']  ?? date('Y'));
$calMonth = (int)($_GET['month'] ?? date('m'));
if($calMonth < 1){ $calMonth=12; $calYear--; }
if($calMonth > 12){ $calMonth=1; $calYear++; }


$takenSlots = [];
$mySlots    = [];

if($selectedDoctor){
    $stmt = $conn->prepare("
        SELECT date, time, patient_email FROM appointments 
        WHERE doctor_email=? AND YEAR(date)=? AND MONTH(date)=? AND status != 'cancelled'
    ");
    $stmt->bind_param("sii",$selectedDoctor,$calYear,$calMonth);
    $stmt->execute();
    $sr = $stmt->get_result();
    while($row = $sr->fetch_assoc()){
        $takenSlots[$row['date']][] = $row['time'];
        if($row['patient_email'] === $email){
            $mySlots[$row['date']][] = $row['time'];
        }
    }
}



$timeSlots = ['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30',
              '14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30'];
$maxPerDay = count($timeSlots);

$today      = date("Y-m-d");
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$monthName  = date("F Y", mktime(0,0,0,$calMonth,1,$calYear));
$firstDay   = date("N", mktime(0,0,0,$calMonth,1,$calYear));
$daysInMonth = date("t", mktime(0,0,0,$calMonth,1,$calYear));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Appointments – Tunicare</title>
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

.page-header {
    display: flex; align-items: center; justify-content: space-between;
}
.page-header h1 { font-size: 24px; color: #1f4e5f; font-weight: bold; }

.alert {
    padding: 12px 18px; border-radius: 10px;
    font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
}
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.rdv-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
}

.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.card h3 {
    font-size: 15px; color: #1f4e5f; font-weight: bold;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: center; gap: 8px;
}

/* Doctor selector */
.doc-select-grid { display: flex; flex-direction: column; gap: 8px; }
.doc-option {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 10px; cursor: pointer;
    transition: .15s; background: #fafafa;
    text-decoration: none; color: inherit;
}
.doc-option:hover { border-color: #2c7a7b; background: #f0f9ff; }
.doc-option.selected { border-color: #1f4e5f; background: #e8f4f8; }
.doc-av {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg,#1f4e5f,#2c7a7b);
    color: white; font-size: 15px; font-weight: bold;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.doc-av-name { font-size: 13px; font-weight: bold; color: #111; }
.doc-av-spec { font-size: 11px; color: #6b7280; }
.doc-option .check { font-size: 18px; color: #1f4e5f; display: none; }
.doc-option.selected .check { display: block; }

/* Calendar */
.cal-nav {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}
.cal-nav-btn {
    background: #f3f4f6; border: none; border-radius: 8px;
    padding: 6px 14px; cursor: pointer; font-size: 16px;
    color: #1f4e5f; font-weight: bold; transition: .15s;
    text-decoration: none; display: inline-flex; align-items: center;
}
.cal-nav-btn:hover { background: #e5e7eb; }
.cal-month { font-size: 15px; font-weight: bold; color: #1f4e5f; }

.cal-grid {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;
}
.cal-day-name {
    text-align: center; font-size: 11px; font-weight: bold;
    color: #6b7280; padding: 4px 0; text-transform: uppercase;
}
.cal-day {
    aspect-ratio: 1;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600;
    cursor: pointer; transition: .15s;
    position: relative; border: 2px solid transparent;
}
.cal-day.empty { cursor: default; }
.cal-day.past  { color: #d1d5db; cursor: not-allowed; background: none; }
.cal-day.available { background: #f9fafb; color: #374151; }
.cal-day.available:hover { background: #e8f4f8; border-color: #2c7a7b; transform: scale(1.05); }
.cal-day.my-appt { background: #d1fae5 !important; color: #065f46 !important; border-color: #10b981 !important; }
.cal-day.my-appt:hover { background: #a7f3d0 !important; transform: scale(1.05); }
.cal-day.full   { background: #fee2e2; color: #991b1b; border-color: #fca5a5; cursor: not-allowed; }
.cal-day.partial { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.cal-day.partial:hover { background: #fde68a; border-color: #f59e0b; transform: scale(1.05); }
.cal-day.today-cell { border-color: #1f4e5f !important; font-weight: 900; }
.cal-day.selected { background: #1f4e5f !important; color: white !important; border-color: #1f4e5f !important; box-shadow: 0 3px 10px rgba(31,78,95,.3); transform: scale(1.07); }
.cal-dot { position: absolute; bottom: 3px; width: 5px; height: 5px; border-radius: 50%; background: currentColor; opacity: .6; }

.legend { display: flex; gap: 14px; margin-top: 12px; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #6b7280; }
.legend-dot { width: 12px; height: 12px; border-radius: 3px; }

/* Booking form */
.booking-form { display: flex; flex-direction: column; gap: 12px; margin-top: 4px; }
.booking-form label { font-size: 13px; font-weight: 600; color: #1f4e5f; margin-bottom: 4px; display: block; }
.booking-form input, .booking-form select, .booking-form textarea {
    width: 100%; padding: 10px 12px;
    border: 2px solid #d1d5db; background: #f9fafb;
    border-radius: 8px; font-size: 14px; color: #111;
    outline: none; transition: border-color .2s;
    font-family: Arial, sans-serif;
}
.booking-form input:focus, .booking-form select:focus, .booking-form textarea:focus {
    border-color: #2c7a7b; background: white;
    box-shadow: 0 0 0 3px rgba(44,122,123,.12);
}
.booking-form textarea { resize: vertical; min-height: 70px; }

.time-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; margin-top: 2px; }
.time-slot {
    padding: 8px 4px; border: 2px solid #e5e7eb;
    border-radius: 8px; font-size: 12px; font-weight: 600;
    text-align: center; cursor: pointer; transition: .15s;
    background: white; color: #374151;
}
.time-slot:hover { border-color: #2c7a7b; color: #1f4e5f; }
.time-slot.taken    { background: #fee2e2; color: #991b1b; border-color: #fca5a5; cursor: not-allowed; }
.time-slot.mine     { background: #d1fae5; color: #065f46; border-color: #10b981; cursor: not-allowed; }
.time-slot.selected { background: #1f4e5f; color: white; border-color: #1f4e5f; }

.btn-book {
    padding: 12px;
    background: linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color: white; border: none; border-radius: 10px;
    font-size: 14px; font-weight: bold; cursor: pointer;
    transition: .2s; margin-top: 4px;
}
.btn-book:hover { opacity: .9; transform: scale(1.01); }
.btn-book:disabled { opacity: .5; cursor: not-allowed; transform: none; }

/* Appointments list */
.appt-list { display: flex; flex-direction: column; gap: 10px; }
.appt-card {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    border: 1px solid #e5e7eb; border-radius: 12px;
    background: #fafafa; transition: .15s;
    border-left: 4px solid #2c7a7b;
}
.appt-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.appt-icon { width: 44px; height: 44px; border-radius: 12px; background: #d1fae5; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.appt-info { flex: 1; }
.appt-doc  { font-size: 14px; font-weight: bold; color: #111; margin-bottom: 3px; }
.appt-spec { font-size: 11px; color: #2c7a7b; font-weight: 600; margin-bottom: 4px; }
.appt-details { display: flex; gap: 10px; flex-wrap: wrap; }
.appt-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #6b7280; }
.appt-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; flex-shrink: 0; }
.status-pending   { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #d1fae5; color: #065f46; }
.btn-cancel-appt {
    padding: 6px 14px;
    background: #fee2e2; color: #991b1b;
    border: 1px solid #fca5a5; border-radius: 8px;
    font-size: 12px; font-weight: bold; cursor: pointer; transition: .2s; flex-shrink: 0;
}
.btn-cancel-appt:hover { background: #ef4444; color: white; }
.empty-appt { text-align: center; padding: 30px; color: #9ca3af; font-size: 14px; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="page-header">
        <h1>📆 Book an Appointment</h1>
    </div>

    <?php if(isset($bookingSuccess)): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($bookingSuccess) ?></div>
    <?php endif; ?>
    <?php if(isset($bookingError)): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($bookingError) ?></div>
    <?php endif; ?>

    <div class="rdv-grid">

        
        <div style="display:flex;flex-direction:column;gap:20px;">

            
            <div class="card">
                <h3>👨‍⚕️ Select a Doctor</h3>
                <div class="doc-select-grid">
                <?php if(empty($doctors)): ?>
                    <p style="color:#9ca3af;font-size:13px;text-align:center;padding:20px;">No doctor assigned yet.</p>
                <?php endif; ?>
                <?php foreach($doctors as $doc):
                    $dp = explode(" ",$doc['fname']." ".$doc['lname']);
                    $di = strtoupper(substr($dp[0],0,1).(isset($dp[1])?substr($dp[1],0,1):""));
                    $isSelected = ($doc['email'] === $selectedDoctor);
                    $url = "rendezvous.php?doctor=".urlencode($doc['email'])."&year=$calYear&month=$calMonth";
                ?>
                <a href="<?= $url ?>" class="doc-option <?= $isSelected?'selected':'' ?>">
                    <div class="doc-av"><?= $di ?></div>
                    <div>
                        <div class="doc-av-name">Dr. <?= htmlspecialchars($doc['fname']." ".$doc['lname']) ?></div>
                        <div class="doc-av-spec">🩺 <?= htmlspecialchars($doc['speciality']) ?></div>
                    </div>
                    <span class="check">✓</span>
                </a>
                <?php endforeach; ?>
                </div>
            </div>

            
            
            <div class="card">
                <div class="cal-nav">
                    <a class="cal-nav-btn" href="rendezvous.php?doctor=<?= urlencode($selectedDoctor) ?>&year=<?= ($calMonth==1?$calYear-1:$calYear) ?>&month=<?= ($calMonth==1?12:$calMonth-1) ?>">‹</a>
                    <span class="cal-month">📅 <?= $monthName ?></span>
                    <a class="cal-nav-btn" href="rendezvous.php?doctor=<?= urlencode($selectedDoctor) ?>&year=<?= ($calMonth==12?$calYear+1:$calYear) ?>&month=<?= ($calMonth==12?1:$calMonth+1) ?>">›</a>
                </div>

                <div class="cal-grid">
                    <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dn): ?>
                    <div class="cal-day-name"><?= $dn ?></div>
                    <?php endforeach; ?>

                    <?php for($i=1; $i<$firstDay; $i++): ?>
                    <div class="cal-day empty"></div>
                    <?php endfor; ?>

                    <?php for($d=1; $d<=$daysInMonth; $d++):
                        $dateStr    = sprintf("%04d-%02d-%02d",$calYear,$calMonth,$d);
                        $taken      = $takenSlots[$dateStr] ?? [];
                        $mine       = $mySlots[$dateStr] ?? [];
                        $takenCount = count($taken);
                        $isPast     = ($dateStr < $today);
                        $isToday    = ($dateStr === $today);
                        $isWeekend  = (date("N",strtotime($dateStr)) >= 6);

                        $cls = "available";
                        if($isPast || $isWeekend)       { $cls = "past"; }
                        elseif(!empty($mine))            { $cls = "my-appt"; }
                        elseif($takenCount >= $maxPerDay){ $cls = "full"; }
                        elseif($takenCount > 0)          { $cls = "partial"; }

                        if($isToday) $cls .= " today-cell";
                        $clickable = !$isPast && !$isWeekend && strpos($cls,'full') === false && strpos($cls,'past') === false;
                        $onclick   = $clickable ? "selectDay('$dateStr')" : "";
                    ?>
                    <div class="cal-day <?= $cls ?>"
                         id="day-<?= $dateStr ?>"
                         <?= $onclick ? "onclick=\"$onclick\"" : "" ?>>
                        <?= $d ?>
                        <?php if(!empty($mine) || $takenCount > 0): ?>
                        <span class="cal-dot"></span>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#f9fafb;border:1px solid #e5e7eb;"></div> Available</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#fef3c7;border:1px solid #fcd34d;"></div> Partially booked</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#fee2e2;border:1px solid #fca5a5;"></div> Fully booked</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#d1fae5;border:1px solid #10b981;"></div> My appointment</div>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">

            
        
            <div class="card" id="bookingCard">
                <h3>🗓️ Select a Time Slot</h3>

                <?php if(empty($selectedDoctor)): ?>
                <p style="color:#9ca3af;font-size:13px;text-align:center;padding:20px;">
                    ← Please select a doctor first.
                </p>
                <?php else: ?>

                <form class="booking-form" method="POST" id="bookingForm">
                    <input type="hidden" name="action" value="book">
                    <input type="hidden" name="doctor_email" value="<?= htmlspecialchars($selectedDoctor) ?>">
                    <input type="hidden" name="date" id="selectedDate" required>
                    <input type="hidden" name="time" id="selectedTime" required>

                    <div>
                        <label>📅 Selected Date</label>
                        <input type="text" id="dateDisplay" placeholder="Click a day on the calendar" readonly
                               style="background:#f0f9ff;cursor:pointer;">
                    </div>

                    <div>
                        <label>⏰ Available Time Slots</label>
                        <div class="time-grid" id="timeGrid">
                            <div style="grid-column:1/-1;text-align:center;color:#9ca3af;font-size:12px;padding:10px;">
                                Please select a date first.
                            </div>
                        </div>
                    </div>

                    <div>
                        <label>📝 Reason for Visit</label>
                        <textarea name="reason" placeholder="Briefly describe the reason for your visit..."></textarea>
                    </div>

                    <button type="submit" class="btn-book" id="btnBook" disabled>
                        📆 Confirm Appointment
                    </button>
                </form>
                <?php endif; ?>
            </div>

            
            <div class="card">
                <h3>📋 My Upcoming Appointments</h3>
                <div class="appt-list">
                <?php if(empty($myAppts)): ?>
                    <div class="empty-appt">📭 No upcoming appointments.</div>
                <?php endif; ?>
                <?php foreach($myAppts as $a):
                    $dateF  = date("d/m/Y",strtotime($a['date']));
                    $timeF  = date("H:i",strtotime($a['time']));
                    $dp2    = explode(" ",$a['fname']." ".$a['lname']);
                    $di2    = strtoupper(substr($dp2[0],0,1).(isset($dp2[1])?substr($dp2[1],0,1):""));
                    $statusClass = $a['status']==='confirmed' ? 'status-confirmed' : 'status-pending';
                    $statusLabel = $a['status']==='confirmed' ? '✅ Confirmed' : '⏳ Pending';
                ?>
                <div class="appt-card">
                    <div class="appt-icon">📅</div>
                    <div class="appt-info">
                        <div class="appt-doc">Dr. <?= htmlspecialchars($a['fname']." ".$a['lname']) ?></div>
                        <div class="appt-spec">🩺 <?= htmlspecialchars($a['speciality']) ?></div>
                        <div class="appt-details">
                            <span class="appt-tag">📅 <?= $dateF ?></span>
                            <span class="appt-tag">⏰ <?= $timeF ?></span>
                            <?php if(!empty($a['reason'])): ?>
                            <span class="appt-tag">📝 <?= htmlspecialchars(mb_substr($a['reason'],0,30)) ?><?= mb_strlen($a['reason']??'')>30?'...':'' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="appt-status <?= $statusClass ?>"><?= $statusLabel ?></span>


                    <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="appt_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn-cancel-appt">✕ Cancel</button>
                    </form>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const takenSlots   = <?= json_encode($takenSlots) ?>;
const mySlots      = <?= json_encode($mySlots) ?>;
const allTimeSlots = <?= json_encode($timeSlots) ?>;

let selectedDate = "";
let selectedTime = "";

function selectDay(dateStr){
    document.querySelectorAll(".cal-day.selected").forEach(el => el.classList.remove("selected"));
    const el = document.getElementById("day-"+dateStr);
    if(!el || el.classList.contains("past") || el.classList.contains("full")) return;
    el.classList.add("selected");

    selectedDate = dateStr;
    selectedTime = "";
    document.getElementById("selectedDate").value = dateStr;
    document.getElementById("selectedTime").value  = "";

    const d    = new Date(dateStr+"T00:00:00");
    const opts = {weekday:'long',year:'numeric',month:'long',day:'numeric'};
    document.getElementById("dateDisplay").value = d.toLocaleDateString('en-US',opts);

    renderTimeSlots();
    updateBookBtn();
}

function renderTimeSlots(){
    const grid = document.getElementById("timeGrid");
    if(!grid || !selectedDate) return;
    const taken = takenSlots[selectedDate] || [];
    const mine  = mySlots[selectedDate]    || [];
    grid.innerHTML = "";

    allTimeSlots.forEach(t => {
        const div    = document.createElement("div");
        div.textContent = t;
        div.className   = "time-slot";
        const isMine  = mine.includes(t);
        const isTaken = taken.includes(t);

        if(t === selectedTime)  { div.classList.add("selected"); }
        else if(isMine)         { div.classList.add("mine");  }
        else if(isTaken)        { div.classList.add("taken"); }
        else { div.onclick = () => selectTime(t); }

        grid.appendChild(div);
    });
}

function selectTime(t){
    selectedTime = t;
    document.getElementById("selectedTime").value = t;
    renderTimeSlots();
    updateBookBtn();
}

function updateBookBtn(){
    const btn = document.getElementById("btnBook");
    if(!btn) return;
    btn.disabled = !(selectedDate && selectedTime);
}
</script>
</body>
</html>