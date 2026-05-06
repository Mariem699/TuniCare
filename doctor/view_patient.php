<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");
if ($conn->connect_error) die("DB error");

$doctor_email = $_SESSION['email'] ?? "";
if (empty($doctor_email)) { header("Location: login.html"); exit(); }

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$stmt = $conn->prepare("
    SELECT COUNT(*) as unread 
    FROM messages 
    WHERE receiver_email=? AND is_read=0
");
$stmt->bind_param("s", $doctor_email);
$stmt->execute();
$unread = $stmt->get_result()->fetch_assoc()['unread'];

$patient_email = $_GET['email'] ?? "";
if (empty($patient_email)) die("Patient not specified");

$stmt = $conn->prepare("SELECT * FROM patients WHERE email = ?");
$stmt->bind_param("s", $patient_email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("Patient not found");

$parts = explode(" ", $user['fname']." ".$user['lname']);
$initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):""));



$meds = [];
$stmt = $conn->prepare("
    SELECT * FROM medications
    WHERE email = ?
    ORDER BY start_date DESC
");
$stmt->bind_param("s", $patient_email);
$stmt->execute();
$res = $stmt->get_result();

while($row = $res->fetch_assoc()){
    $key = $row['name'] . '_' . $row['start_date'];

    if(!isset($meds[$key])){
        $meds[$key] = [
            'name' => $row['name'],
            'dosage' => $row['dosage'],
            'start_date' => $row['start_date'],
            'duration' => $row['duration_days'],
            'times' => []
        ];
    }


    
    if($row['time_take']) $meds[$key]['times'][] = $row['time_take'];
    if($row['time2']) $meds[$key]['times'][] = $row['time2'];
    if($row['time3']) $meds[$key]['times'][] = $row['time3'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Profile</title>
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
    flex: 1;
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 22px;
    overflow-y: auto;
}

/*  PAGE HEADER  */ 
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.page-header h1 { font-size: 24px; color: #1f4e5f; font-weight: bold; }

.btn-back {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f4f7fb;
    color: #1f4e5f;
    border: 2px solid #e5e7eb;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    transition: .2s;
}
.btn-back:hover { background: #e8f4f8; border-color: #2c7a7b; }

/*  PROFILE HEADER CARD  */ 
.profile-hero {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.hero-avatar {
    width: 70px; height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: bold;
    flex-shrink: 0;
    border: 3px solid #e5e7eb;
}
.hero-info h2 { font-size: 20px; color: #1f4e5f; margin-bottom: 4px; }
.hero-info p  { font-size: 13px; color: #6b7280; }
.hero-actions { margin-left: auto; display: flex; gap: 10px; }

.btn-chat {
    display: flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    transition: .2s;
}
.btn-chat:hover { opacity: .9; transform: scale(1.03); }

/*  GRID  */ 
.cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/*  CARD  */ 
.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.card-header h3 { font-size: 15px; color: #1f4e5f; font-weight: bold; }

/* Info rows */
.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid #f4f7fb;
    font-size: 14px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #6b7280; font-size: 12px; width: 80px; flex-shrink: 0; }
.info-value { color: #111827; font-weight: 500; }

/* Tags */
.tags-wrap { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.tag {
    background: #d1fae5;
    color: #065f46;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}
.tag.allergy {
    background: #fee2e2;
    color: #991b1b;
}
.tag-empty { color: #9ca3af; font-size: 13px; }

/* Doctor item */
.doctor-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
    margin-bottom: 8px;
}
.doctor-item:last-child { margin-bottom: 0; }
.doc-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: bold;
    flex-shrink: 0;
}
.doc-name { font-size: 13px; font-weight: bold; color: #111827; }
.doc-spec { font-size: 11px; color: #6b7280; }
.doc-email { font-size: 11px; color: #9ca3af; }


.med-item{
    background: white;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #10b981;
    padding: 16px 18px;
    border-radius: 12px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    transition: transform .15s, box-shadow .15s;
}
.med-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,.08);
}
.med-name-title {
    font-size: 15px;
    font-weight: bold;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}
.med-row {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}
.time-pill {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    margin-right: 4px;
}
</style>
</head>
<body>


<?php include 'sidebar.php'; ?>

<div class="main">

  
    <div class="page-header">
        <h1>👤 Patient Profile</h1>
        <a href="patients.php" class="btn-back">← Back to Patients</a>
    </div>


    <div class="profile-hero">
        <div class="hero-avatar"><?= $initials ?></div>
        <div class="hero-info">
            <h2><?= htmlspecialchars($user['fname']." ".$user['lname']) ?></h2>
            <p>📧 <?= htmlspecialchars($user['email']) ?> &nbsp;·&nbsp; 📞 <?= htmlspecialchars($user['phone'] ?? '—') ?></p>
        </div>
        <div class="hero-actions">
            <a href="../chat.php?user=<?= urlencode($user['email']) ?>" class="btn-chat">💬 Start Chat</a>
        </div>
    </div>


    <div class="cards-grid">

        <div class="card">
            <div class="card-header"><h3>👤 Personal Info</h3></div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($user['fname']." ".$user['lname']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Age</span>
                <span class="info-value"><?= htmlspecialchars($user['age'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Sex</span>
                <span class="info-value"><?= htmlspecialchars($user['sex'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Weight</span>
                <span class="info-value"><?= htmlspecialchars($user['kg'] ?? '—') ?> kg</span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>👨‍⚕️ Assigned Doctors</h3></div>
            <?php
            $stmt = $conn->prepare("
                SELECT d.fname, d.lname, d.speciality, d.email
                FROM doctors d
                JOIN patient_doctors pd ON pd.doctor_email = d.email
                WHERE pd.patient_email = ?
            ");
            $stmt->bind_param("s", $patient_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if($res->num_rows == 0): ?>
                <p class="tag-empty">No doctor assigned yet.</p>
            <?php endif;
            while($doc = $res->fetch_assoc()):
                $dparts = explode(" ", $doc['fname']." ".$doc['lname']);
                $dinit = strtoupper(substr($dparts[0],0,1).(isset($dparts[1])?substr($dparts[1],0,1):""));
            ?>
            <div class="doctor-item">
                <div class="doc-avatar"><?= $dinit ?></div>
                <div>
                    <div class="doc-name">Dr. <?= htmlspecialchars($doc['fname']." ".$doc['lname']) ?></div>
                    <div class="doc-spec">🩺 <?= htmlspecialchars($doc['speciality']) ?></div>
                    <div class="doc-email">📧 <?= htmlspecialchars($doc['email']) ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        
        <div class="card">
            <div class="card-header"><h3>🏥 Medical History</h3></div>
            <div class="tags-wrap">
                <?php
                $hasHistory = false;
                foreach(explode(",", $user['history'] ?? '') as $h){
                    $h = trim($h);
                    if($h){ echo "<span class='tag'>$h</span>"; $hasHistory = true; }
                }
                if(!$hasHistory) echo "<span class='tag-empty'>No history recorded.</span>";
                ?>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header"><h3>⚠️ Allergies</h3></div>
            <div class="tags-wrap">
                <?php
                $hasAllergy = false;
                foreach(explode(",", $user['allergies'] ?? '') as $a){
                    $a = trim($a);
                    if($a){ echo "<span class='tag allergy'>$a</span>"; $hasAllergy = true; }
                }
                if(!$hasAllergy) echo "<span class='tag-empty'>No allergies recorded.</span>";
                ?>
            </div>
        </div>



    </div>

            <div class="card">
    <div class="card-header"><h3>💊 Medication History</h3></div>

    <?php if(empty($meds)): ?>
        <p class="tag-empty">No medications recorded.</p>
    <?php else: ?>

        <?php foreach($meds as $m): ?>
            <div class="med-item">
                <div class="med-name-title">💊 <?= htmlspecialchars($m['name']) ?></div>
                <div class="med-row">💉 <span><b>Dosage:</b> <?= htmlspecialchars($m['dosage']) ?></span></div>
                <div class="med-row">⏰ <span><b>Times:</b>
                    <?php foreach($m['times'] as $t): ?>
                        <span class="time-pill"><?= date("H:i", strtotime($t)) ?></span>
                    <?php endforeach; ?>
                </span></div>
                <div class="med-row">📅 <span><b>Start:</b> <?= date("d M Y", strtotime($m['start_date'])) ?></span></div>
                <div class="med-row">⏳ <span><b>Duration:</b> <?= htmlspecialchars($m['duration']) ?> days</span></div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
</div>

</body>
</html>
