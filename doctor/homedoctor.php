<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$today     = date("Y-m-d");
$weekStart = date("Y-m-d", strtotime("monday this week"));
$weekEnd   = date("Y-m-d", strtotime("sunday this week"));

$stmt = $conn->prepare("SELECT * FROM doctors WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
if(!$doctor) die("Doctor not found");

$stmt = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? AND date=? ORDER BY time ASC");
$stmt->bind_param("ss",$email,$today);
$stmt->execute();
$todayTasks = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? AND date BETWEEN ? AND ? ORDER BY date ASC");
$stmt->bind_param("sss",$email,$weekStart,$weekEnd);
$stmt->execute();
$weekTasks = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM patient_doctors WHERE doctor_email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$patientsCount = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_email=? AND is_read=0");
$stmt->bind_param("s",$email);
$stmt->execute();
$unread = $stmt->get_result()->fetch_assoc()['unread'];


$stmt = $conn->prepare("
    SELECT a.*, p.fname, p.lname
    FROM appointments a
    JOIN patients p ON p.email = a.patient_email
    WHERE a.doctor_email = ? AND a.date = ? AND a.status != 'cancelled'
    ORDER BY a.time ASC
");
$stmt->bind_param("ss",$email,$today);
$stmt->execute();
$todayAppts = $stmt->get_result();
$todayApptsCount = $todayAppts->num_rows;



$stmt = $conn->prepare("
    SELECT a.*, p.fname, p.lname
    FROM appointments a
    JOIN patients p ON p.email = a.patient_email
    WHERE a.doctor_email = ? AND a.date BETWEEN ? AND ? AND a.status != 'cancelled'
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("sss",$email,$weekStart,$weekEnd);
$stmt->execute();
$weekAppts = $stmt->get_result();

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$fname = preg_replace('/\bdr\b\.?\s*/i', '', $doctor['fname']);
$lname = preg_replace('/\bdr\b\.?\s*/i', '', $doctor['lname']);
$initials = strtoupper(substr($fname,0,1).substr($lname,0,1));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Doctor Dashboard</title>
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
    flex: 1;
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 22px;
    overflow-y: auto;
}



.welcome-header {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 26px;
    display: flex;
    align-items: center;
    gap: 18px;
}
.welcome-avatar {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: bold;
    flex-shrink: 0;
    border: 3px solid #e5e7eb;
}
.welcome-header h1 { font-size: 22px; color: #1f4e5f; margin-bottom: 4px; }
.welcome-header p  { font-size: 13px; color: #6b7280; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}
.stat-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-icon-box {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}
.stat-icon-box.blue    { background: #dbeafe; }
.stat-icon-box.green   { background: #d1fae5; }
.stat-icon-box.orange  { background: #fef3c7; }

.stat-info-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.stat-info-value { font-size: 26px; font-weight: bold; color: #1f4e5f; }
.stat-info-sub   { font-size: 12px; color: #9ca3af; margin-top: 2px; }


.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.card-header h2 { font-size: 15px; color: #1f4e5f; font-weight: bold; }
.card-date { font-size: 12px; color: #6b7280; background: #f4f7fb; padding: 4px 10px; border-radius: 20px; }

.item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f4f7fb;
}
.item:last-child { border-bottom: none; }

.item-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.item-time {
    font-size: 11px;
    color: #6b7280;
    font-weight: 600;
    white-space: nowrap;
}
.item-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #2c7a7b;
    border: 2px solid #e5e7eb;
}

.item-body { flex: 1; min-width: 0; }
.item-title { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 3px; }
.item-desc  { font-size: 12px; color: #6b7280; }

.type-badge {
    font-size: 10px;
    padding: 2px 9px;
    border-radius: 20px;
    background: #d1fae5;
    color: #065f46;
    font-weight: bold;
    flex-shrink: 0;
    align-self: flex-start;
    margin-top: 2px;
}

.week-date-chip {
    font-size: 10px;
    color: #1f4e5f;
    background: #dbeafe;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: bold;
    margin-bottom: 6px;
    display: inline-block;
}

.empty-msg {
    text-align: center;
    padding: 28px 16px;
    color: #9ca3af;
    font-size: 13px;
}
.empty-msg .icon { font-size: 32px; margin-bottom: 8px; }

.appt-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f4f7fb;
}
.appt-item:last-child { border-bottom: none; }
.appt-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: bold;
    flex-shrink: 0;
}
.appt-badge-rdv {
    font-size: 10px; padding: 2px 9px;
    border-radius: 20px; background: #dbeafe;
    color: #1e40af; font-weight: bold; flex-shrink: 0; align-self: flex-start; margin-top: 2px;
}
.appt-badge-pending { background:#fef3c7; color:#92400e; }
.appt-badge-confirmed { background:#d1fae5; color:#065f46; }
</style>
</head>
<body>


<?php include 'sidebar.php'; ?>


<div class="main">

    
    <div class="welcome-header">
        <div class="welcome-avatar"><?= $initials ?></div>
        <div>
            <h1>Welcome Dr. <?= htmlspecialchars($fname." ".$lname) ?> 👋</h1>
            <p>📅 <?= date("l, d F Y") ?> &nbsp;·&nbsp; 🏥 <?= htmlspecialchars($doctor['speciality'] ?? '') ?></p>
        </div>
    </div>


    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box green">🧑‍⚕️</div>
            <div>
                <div class="stat-info-label">Total Patients</div>
                <div class="stat-info-value"><?= $patientsCount ?></div>
                <div class="stat-info-sub">Assigned to you</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box blue">💬</div>
            <div>
                <div class="stat-info-label">Unread Messages</div>
                <div class="stat-info-value"><?= $unread ?></div>
                <div class="stat-info-sub"><?= $unread>0?'<span style="color:#ef4444">New messages</span>':'All read ✓' ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box orange">📅</div>
            <div>
                <div class="stat-info-label">Today's Appointments</div>
                <div class="stat-info-value"><?= $todayTasks->num_rows + $todayApptsCount ?></div>
                <div class="stat-info-sub"><?= date("d M Y") ?></div>
            </div>
        </div>
    </div>

  
    <div class="schedule-grid">

        
    
        <div class="card">
            <div class="card-header">
                <h2>📅 Today's Schedule</h2>
                <span class="card-date"><?= date("d M Y") ?></span>
            </div>

            <?php
            $stmt = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? AND date=? ORDER BY time ASC");
            $stmt->bind_param("ss",$email,$today);
            $stmt->execute();
            $todayList = $stmt->get_result();
            if($todayList->num_rows == 0): ?>
            <div class="empty-msg">
                <div class="icon">✅</div>
                No tasks scheduled for today.
            </div>
            <?php endif;
            while($t = $todayList->fetch_assoc()): ?>
            <div class="item">
                <div class="item-left">
                    <div class="item-time"><?= date("H:i", strtotime($t['time'])) ?></div>
                    <div class="item-dot"></div>
                </div>
                <div class="item-body">
                    <div class="item-title"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="item-desc"><?= htmlspecialchars($t['description'] ?? '') ?></div>
                </div>
                <span class="type-badge"><?= htmlspecialchars($t['type']) ?></span>
            </div>
            <?php endwhile; ?>
        </div>

        
        <div class="card">
            <div class="card-header">
                <h2>👨‍⚕️ Today's Patient Appointments</h2>
                <span class="card-date"><?= date("d M Y") ?></span>
            </div>
            <?php
            $todayAppts->data_seek(0);
            if($todayAppts->num_rows == 0): ?>
            <div class="empty-msg">
                <div class="icon">📭</div>
                No patient appointments today.
            </div>
            <?php endif;
            while($a = $todayAppts->fetch_assoc()):
                $ap = explode(" ", $a['fname']." ".$a['lname']);
                $ai = strtoupper(substr($ap[0],0,1).(isset($ap[1])?substr($ap[1],0,1):""));
                $statusClass = $a['status']==='confirmed' ? 'appt-badge-confirmed' : 'appt-badge-pending';
                $statusLabel = $a['status']==='confirmed' ? '✅ Confirmed' : '⏳ Pending';
            ?>
            <div class="appt-item">
                <div class="item-left">
                    <div class="item-time"><?= date("H:i", strtotime($a['time'])) ?></div>
                    <div class="item-dot" style="background:#2c7a7b;"></div>
                </div>
                <div class="appt-avatar"><?= $ai ?></div>
                <div class="item-body">
                    <div class="item-title"><?= htmlspecialchars($a['fname']." ".$a['lname']) ?></div>
                    <div class="item-desc"><?= $a['reason'] ? htmlspecialchars(mb_substr($a['reason'],0,40)) : '—' ?></div>
                </div>
                <span class="appt-badge-rdv <?= $statusClass ?>"><?= $statusLabel ?></span>
            </div>
            <?php endwhile; ?>
        </div>

        
        
        <div class="card">
            <div class="card-header">
                <h2>📆 This Week's Tasks</h2>
                <span class="card-date"><?= date("d M", strtotime($weekStart)) ?> – <?= date("d M", strtotime($weekEnd)) ?></span>
            </div>

            <?php
            $stmt = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? AND date BETWEEN ? AND ? ORDER BY date ASC, time ASC");
            $stmt->bind_param("sss",$email,$weekStart,$weekEnd);
            $stmt->execute();
            $weekList = $stmt->get_result();
            if($weekList->num_rows == 0): ?>
            <div class="empty-msg">
                <div class="icon">📆</div>
                No tasks this week.
            </div>
            <?php endif;
            while($w = $weekList->fetch_assoc()): ?>
            <div class="item">
                <div class="item-left">
                    <div class="item-time"><?= date("H:i", strtotime($w['time'])) ?></div>
                    <div class="item-dot"></div>
                </div>
                <div class="item-body">
                    <div class="week-date-chip"><?= date("D d M", strtotime($w['date'])) ?></div>
                    <div class="item-title"><?= htmlspecialchars($w['title']) ?></div>
                </div>
                <span class="type-badge"><?= htmlspecialchars($w['type']) ?></span>
            </div>
            <?php endwhile; ?>
        </div>

       
        
        <div class="card">
            <div class="card-header">
                <h2>👥 This Week's Patient Appointments</h2>
                <span class="card-date"><?= date("d M", strtotime($weekStart)) ?> – <?= date("d M", strtotime($weekEnd)) ?></span>
            </div>
            <?php
            $weekAppts->data_seek(0);
            if($weekAppts->num_rows == 0): ?>
            <div class="empty-msg">
                <div class="icon">📭</div>
                No patient appointments this week.
            </div>
            <?php endif;
            while($wa = $weekAppts->fetch_assoc()):
                $wp = explode(" ", $wa['fname']." ".$wa['lname']);
                $wi = strtoupper(substr($wp[0],0,1).(isset($wp[1])?substr($wp[1],0,1):""));
                $wStatusClass = $wa['status']==='confirmed' ? 'appt-badge-confirmed' : 'appt-badge-pending';
                $wStatusLabel = $wa['status']==='confirmed' ? '✅ Confirmed' : '⏳ Pending';
            ?>
            <div class="appt-item">
                <div class="item-left">
                    <div class="item-time"><?= date("H:i", strtotime($wa['time'])) ?></div>
                    <div class="item-dot" style="background:#2c7a7b;"></div>
                </div>
                <div class="appt-avatar"><?= $wi ?></div>
                <div class="item-body">
                    <div class="week-date-chip"><?= date("D d M", strtotime($wa['date'])) ?></div>
                    <div class="item-title"><?= htmlspecialchars($wa['fname']." ".$wa['lname']) ?></div>
                    <div class="item-desc"><?= $wa['reason'] ? htmlspecialchars(mb_substr($wa['reason'],0,40)) : '—' ?></div>
                </div>
                <span class="appt-badge-rdv <?= $wStatusClass ?>"><?= $wStatusLabel ?></span>
            </div>
            <?php endwhile; ?>
        </div>

    </div>
</div>

</body>
</html>