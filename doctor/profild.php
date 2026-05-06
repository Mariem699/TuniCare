<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");
if ($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if (empty($email)) { header("Location: login.html"); exit(); }

$stmt = $conn->prepare("SELECT * FROM doctors WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
if (!$doctor) die("Doctor not found");

$unread = 0;
$stmt3 = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_email = ? AND is_read = 0");
if($stmt3){ $stmt3->bind_param("s",$email); $stmt3->execute(); $unread = $stmt3->get_result()->fetch_assoc()['total'] ?? 0; }

$stmt2 = $conn->prepare("SELECT COUNT(*) as total FROM patient_doctors WHERE doctor_email = ?");
$stmt2->bind_param("s", $email);
$stmt2->execute();
$patientsCount = $stmt2->get_result()->fetch_assoc()['total'] ?? 0;

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$fname = preg_replace('/\bdr\b\.?\s*/i', '', $doctor['fname']);
$lname = preg_replace('/\bdr\b\.?\s*/i', '', $doctor['lname']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Doctor Profile</title>
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
    gap: 24px;
}

.page-title {
    font-size: 26px;
    color: #1f4e5f;
    font-weight: bold;
}


.profile-header {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    gap: 22px;
}
.profile-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: bold;
    flex-shrink: 0;
    border: 3px solid #e5e7eb;
}
.profile-header-info h2 { color: #1f4e5f; font-size: 20px; margin-bottom: 4px; }
.profile-header-info p  { color: #6b7280; font-size: 13px; }



.cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.cards-grid .card-full { grid-column: 1 / -1; }


.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.card-header h3 { color: #1f4e5f; font-size: 15px; font-weight: bold; }


.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid #f4f7fb;
    font-size: 14px;
    color: #374151;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #6b7280; font-size: 12px; width: 90px; flex-shrink: 0; }
.info-value { color: #111827; font-weight: 500; }


.stat-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.stat-number { 
    font-size: 36px;
    font-weight: bold;
    color: #1f4e5f; }
.stat-label { 
    font-size: 12px;
    color: #6b7280;
}


.edit-btn {
    background: linear-gradient(135deg, #2c7a7b, #1f4e5f);
    color: white;
    border: none;
    padding: 7px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
}
.edit-btn:hover { 
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
     background: #29b8aa;
}

.cancel-btn {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 7px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
}
.cancel-btn:hover { background: #e5e7eb; }


.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 100;
}
.modal-overlay.open { display: flex; }

.modal-box {
    background: white;
    border-radius: 16px;
    padding: 28px;
    width: 420px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 50px rgba(0,0,0,.2);
    animation: modalIn .22s ease;
}


.modal-title {
    font-size: 16px;
    font-weight: bold;
    color: #1f4e5f;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

label {
    display: block;
    margin-top: 14px;
    margin-bottom: 5px;
    font-weight: 600;
    color: #1f4e5f;
    font-size: 13px;
}

input, select {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #d1d5db;
    background: #f9fafb;
    border-radius: 8px;
    color: #111827;
    font-size: 14px;
    margin: 0;
}
input:focus, select:focus {
    outline: none;
    border-color: #2c7a7b;
    box-shadow: 0 0 0 3px rgba(44,122,123,.15);
}

.modal-footer {
    display: flex;
    gap: 10px;
    margin-top: 22px;
    padding-top: 14px;
    border-top: 1px solid #f0f0f0;
}


.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 4px;
}
.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
    cursor: pointer;
    font-weight: normal;
    color: #374151;
    margin: 0;
    transition: background .15s;
}
.checkbox-group label:hover { background: #e8f4f8; }
.checkbox-group input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: #1f4e5f;
    margin: 0;
    flex-shrink: 0;
}
</style>
</head>
<body>


<?php include 'sidebar.php'; ?>


<div class="main">

    <div class="page-title">👤 Doctor Profile</div>

    
    <div class="profile-header">
        <div class="profile-avatar">
            <?= strtoupper(substr($fname,0,1).substr($lname,0,1)) ?>
        </div>
        <div class="profile-header-info">
            <h2>Dr. <?= htmlspecialchars($fname." ".$lname) ?> 👋</h2>
            <p>📧 <?= htmlspecialchars($doctor['email']) ?> &nbsp;·&nbsp; 🏥 <?= htmlspecialchars($doctor['speciality'] ?? 'N/A') ?></p>
        </div>
    </div>

    
    <div class="cards-grid">

      
        <div class="card">
            <div class="card-header">
                <h3>👤 Personal Info</h3>
                <button class="edit-btn" onclick="openModal('name')">✏️ Modifier</button>
            </div>
            <div class="info-row">
                <span class="info-label">First Name</span>
                <span class="info-value"><?= htmlspecialchars($fname) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Name</span>
                <span class="info-value"><?= htmlspecialchars($lname) ?></span>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <h3>📞 Contact</h3>
                <button class="edit-btn" onclick="openModal('contact')">✏️ Modifier</button>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($doctor['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($doctor['phone'] ?? '—') ?></span>
            </div>
        </div>

       
        <div class="card">
            <div class="card-header">
                <h3>🏥 Speciality</h3>
                <button class="edit-btn" onclick="openModal('speciality')">✏️ Modifier</button>
            </div>
            <div class="info-row">
                <span class="info-label">Speciality</span>
                <span class="info-value"><?= htmlspecialchars($doctor['speciality'] ?? '—') ?></span>
            </div>
        </div>

        
        <div class="stat-card">
            <h3>🧑‍⚕️ Patients</h3>
            <div class="stat-number"><?= $patientsCount ?></div>
            <div class="stat-label">Total Assigned Patients</div>
        </div>

    </div>
</div>


<div class="modal-overlay" id="modalOverlay" onclick="closeModalOutside(event)">
    <div class="modal-box" id="modalBox"></div>
</div>

<script>
function openModal(type){
    let content = "";
    let title = "";

    if(type === "name"){
        title = "✏️ Edit Personal Info";
        content = `
        <form action="update_doctor.php" method="POST">
        <input type="hidden" name="type" value="name">
        <label>First Name</label>
        <input name="fname" value="<?= htmlspecialchars($fname) ?>">
        <label>Last Name</label>
        <input name="lname" value="<?= htmlspecialchars($lname) ?>">
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    if(type === "contact"){
        title = "✏️ Edit Contact";
        content = `
        <form action="update_doctor.php" method="POST">
        <input type="hidden" name="type" value="contact">
        <label>Email</label>
        <input name="email" value="<?= htmlspecialchars($doctor['email']) ?>">
        <label>Phone</label>
        <input name="phone" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>">
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    if(type === "speciality"){
        title = "✏️ Edit Speciality";
        content = `
        <form action="update_doctor.php" method="POST">
        <input type="hidden" name="type" value="speciality">
        <label>Choose Speciality</label>
        <div class="checkbox-group">
            <label><input type="checkbox" name="d_speciality[]" value="general"> General Medicine</label>
            <label><input type="checkbox" name="d_speciality[]" value="cardiology"> Cardiology</label>
            <label><input type="checkbox" name="d_speciality[]" value="dermatology"> Dermatology</label>
            <label><input type="checkbox" name="d_speciality[]" value="pediatrics"> Pediatrics</label>
            <label><input type="checkbox" name="d_speciality[]" value="neurology"> Neurology</label>
            <label><input type="checkbox" name="d_speciality[]" value="pulmonology"> Pulmonology</label>
            <label><input type="checkbox" name="d_speciality[]" value="allergist"> Allergist</label>
        </div>
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    document.getElementById("modalBox").innerHTML = `<div class="modal-title">${title}</div>` + content;
    document.getElementById("modalOverlay").classList.add("open");
}

function closeModal(){
    document.getElementById("modalOverlay").classList.remove("open");
}

function closeModalOutside(e){
    if(e.target === document.getElementById("modalOverlay")) closeModal();
}

document.addEventListener("keydown", e => { if(e.key === "Escape") closeModal(); });
</script>
</body>
</html>
