<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$stmt = $conn->prepare("SELECT * FROM doctors WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
if(!$doctor) die("Doctor not found");

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM patient_doctors WHERE doctor_email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$patientsCount = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as unread 
    FROM messages 
    WHERE receiver_email=? AND is_read=0
");
$stmt->bind_param("s",$email);
$stmt->execute();
$unread = $stmt->get_result()->fetch_assoc()['unread'];

$stmt = $conn->prepare("
    SELECT p.*
    FROM patients p
    JOIN patient_doctors pd ON pd.patient_email = p.email
    WHERE pd.doctor_email = ?
");
$stmt->bind_param("s",$email);
$stmt->execute();
$patients = $stmt->get_result();

$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Patients</title>
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


.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.page-header h1 { font-size: 24px; color: #1f4e5f; font-weight: bold; }
.count-badge {
    background: #29b8aa;
    color: white;
    padding:10px 20px;
    border-radius: 20px;
    font-size: 18px;
    font-weight: bold;
}


.search-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 16px;
    transition: border-color .2s;
}
.search-bar:focus-within { border-color: #2c7a7b; }
.search-bar span { color: #9ca3af; font-size: 15px; }
.search-bar input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #111;
    background: transparent;
    padding: 0;
    margin: 0;
    box-shadow: none;
}
.search-bar input::placeholder { color: #9ca3af; }


.patients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 18px;
}


.patient-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    transition: transform .2s, box-shadow .2s;
}
.patient-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,.1);
}

.patient-top {
    display: flex;
    align-items: center;
    gap: 14px;
}
.patient-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: bold;
    flex-shrink: 0;
    border: 2px solid #e5e7eb;
}
.patient-name { font-size: 15px; font-weight: bold; color: #111827; }
.patient-email { font-size: 12px; color: #6b7280; margin-top: 2px; }

.patient-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
}
.info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #374151;
}
.info-icon { width: 18px; text-align: center; flex-shrink: 0; }

.patient-actions {
    display: flex;
    gap: 8px;
}

.btn-view {
    flex: 1;
    padding: 9px;
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.btn-view:hover { 
    opacity: .9;
    transform: scale(1.02);
     background: #29b8aa;
}

.btn-chat {
    flex: 1;
    padding: 9px;
    background: #f4f7fb;
    color: #1f4e5f;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.btn-chat:hover { background: #e8f4f8; border-color: #2c7a7b; }

/* Empty state */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; }
</style>
</head>
<body>


<?php include 'sidebar.php'; ?>
<div class="main">

    
    <div class="page-header">
        <h1>🧑‍⚕️ My Patients</h1>
        <span class="count-badge">👥 <?= $patientsCount ?> patients</span>
    </div>

    
    <div class="search-bar">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Search by name, email or phone...">
    </div>

    <div class="patients-grid" id="patientsGrid">

        <?php if($patients->num_rows == 0): ?>
        <div class="empty-state">
            <div class="icon">🧑‍⚕️</div>
            <p>No patients assigned yet.</p>
        </div>
        <?php endif; ?>

        <?php while($p = $patients->fetch_assoc()):
            $pname = $p['fname']." ".$p['lname'];
            $parts = explode(" ", $pname);
            $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):""));
        ?>
        <div class="patient-card" data-search="<?= htmlspecialchars(strtolower($pname." ".$p['email']." ".($p['phone']??''))) ?>">
            <div class="patient-top">
                <div class="patient-avatar"><?= $initials ?></div>
                <div>
                    <div class="patient-name"><?= htmlspecialchars($pname) ?></div>
                    <div class="patient-email"><?= htmlspecialchars($p['email']) ?></div>
                </div>
            </div>

            <div class="patient-info">
                <div class="info-row">
                    <span class="info-icon">📧</span>
                    <?= htmlspecialchars($p['email']) ?>
                </div>
                <div class="info-row">
                    <span class="info-icon">📞</span>
                    <?= htmlspecialchars($p['phone'] ?? '—') ?>
                </div>
            </div>

            <div class="patient-actions">
                <a href="view_patient.php?email=<?= urlencode($p['email']) ?>" class="btn-view">👁 View Profile</a>
                <a href="../chat.php?user=<?= urlencode($p['email']) ?>" class="btn-chat">💬 Chat</a>
            </div>
        </div>
        <?php endwhile; ?>

    </div>
</div>

<script>
document.getElementById("searchInput").addEventListener("input", function(){
    let q = this.value.toLowerCase();
    document.querySelectorAll(".patient-card").forEach(card => {
        let data = card.getAttribute("data-search") || "";
        card.style.display = data.includes(q) ? "" : "none";
    });
});
</script>
</body>
</html>
