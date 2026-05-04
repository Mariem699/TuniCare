<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");
if($conn->connect_error) die("Connection failed");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: login.html"); exit(); }

$stmt = $conn->prepare("SELECT * FROM patients WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user) die("User not found");

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$parts = explode(" ", $user['fname']." ".$user['lname']);
$initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):""));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profile</title>
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
}

.page-title { font-size: 24px; color: #1f4e5f; font-weight: bold; }


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


.content-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
}

.left-col { display: flex; flex-direction: column; gap: 18px; }


.cards-grid {
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
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.card-header h3 { font-size: 15px; color: #1f4e5f; font-weight: bold; }

.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid #f4f7fb;
    font-size: 14px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #6b7280; font-size: 12px; width: 70px; flex-shrink: 0; }
.info-value { color: #111827; font-weight: 500; }


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
    background: #29b8aa;
    box-shadow: 0 4px 12px rgba(37, 162, 173, 0.2);
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

.delete-btn {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: .2s;
    width: 100%;
}
.delete-btn:hover { background: #ef4444; color: white; border-color: #ef4444; }

.doctors-panel {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.07);
    padding: 22px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.doctors-panel h3 { font-size: 15px; color: #1f4e5f; font-weight: bold; margin-bottom: 4px; }

.doctor-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
}
.doc-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f4e5f, #2c7a7b);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: bold;
    flex-shrink: 0;
}
.doc-name { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 2px; }
.doc-spec { font-size: 11px; color: #6b7280; margin-bottom: 2px; }
.doc-email { font-size: 10px; color: #9ca3af; }

.divider {
    height: 1px;
    background: #e5e7eb;
    margin: 4px 0;
}

.add-doctor-form { display: flex; flex-direction: column; gap: 10px; }
.add-doctor-form select {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #d1d5db;
    background: #f9fafb;
    border-radius: 8px;
    color: #111827;
    font-size: 14px;
}
.add-doctor-form select:focus {
    outline: none;
    border-color: #2c7a7b;
    box-shadow: 0 0 0 3px rgba(44,122,123,.15);
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
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
    transition: background .15s;
}
.checkbox-group label:hover { background: #e8f4f8; }
.checkbox-group input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: #1f4e5f;
    margin: 0;
    flex-shrink: 0;
}

.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(4px);
    z-index: 100;
    align-items: center;
    justify-content: center;
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
</style>
</head>
<body>


<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="page-title">👤 Patient Profile</div>


    <div class="profile-header">
        <div class="profile-avatar"><?= $initials ?></div>
        <div class="profile-header-info">
            <h2><?= htmlspecialchars($user['fname']." ".$user['lname']) ?> 👋</h2>
            <p>📧 <?= htmlspecialchars($user['email']) ?> &nbsp;·&nbsp; 📞 <?= htmlspecialchars($user['phone'] ?? '—') ?></p>
        </div>
    </div>

    <div class="content-grid">


        <div class="left-col">
            <div class="cards-grid">

                <div class="card">
                    <div class="card-header">
                        <h3>👤 Personal Info</h3>
                        <button class="edit-btn" onclick="openModal('personal')">✏️ Modifier</button>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($user['fname']." ".$user['lname']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age</span>
                        <span class="info-value"><?= htmlspecialchars($user['age'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Weight</span>
                        <span class="info-value"><?= htmlspecialchars($user['kg'] ?? '—') ?> kg</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sex</span>
                        <span class="info-value"><?= htmlspecialchars($user['sex'] ?? '—') ?></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>📞 Contact</h3>
                        <button class="edit-btn" onclick="openModal('contact')">✏️ Modifier</button>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($user['phone'] ?? '—') ?></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>🏥 Medical History</h3>
                        <button class="edit-btn" onclick="openModal('history')">✏️ Modifier</button>
                    </div>
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
                    <div class="card-header">
                        <h3>⚠️ Allergies</h3>
                        <button class="edit-btn" onclick="openModal('allergies')">✏️ Modifier</button>
                    </div>
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
        </div>

        
        <div class="doctors-panel">
            <h3>👨‍⚕️ My Doctors</h3>

            <?php
            $stmt = $conn->prepare("
                SELECT d.fname, d.lname, d.speciality, d.email
                FROM doctors d
                JOIN patient_doctors pd ON pd.doctor_email = d.email
                WHERE pd.patient_email = ?
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if($res->num_rows == 0):
            ?>
                <p style="color:#9ca3af;font-size:13px;text-align:center;padding:12px;">No doctor assigned yet.</p>
            <?php endif;

            while($doc = $res->fetch_assoc()):
                $dparts = explode(" ", $doc['fname']." ".$doc['lname']);
                $dinit = strtoupper(substr($dparts[0],0,1).(isset($dparts[1])?substr($dparts[1],0,1):""));
            ?>
            <div class="doctor-item">
                <div class="doc-avatar"><?= $dinit ?></div>
                <div style="flex:1;">
                    <div class="doc-name">Dr. <?= htmlspecialchars($doc['fname']." ".$doc['lname']) ?></div>
                    <div class="doc-spec">🩺 <?= htmlspecialchars($doc['speciality']) ?></div>
                    <div class="doc-email">📧 <?= htmlspecialchars($doc['email']) ?></div>
                </div>
            </div>
            <?php endwhile; ?>

            <div class="divider"></div>

            
            <h3 style="font-size:14px;margin-top:4px;">➕ Add Doctor</h3>
            <form class="add-doctor-form" action="add_doctor.php" method="POST">
                <select name="doctor_email" required>
                    <option value="">Select doctor...</option>
                    <?php
                    $all = $conn->prepare("
                        SELECT fname, lname, email, speciality
                        FROM doctors
                        WHERE email NOT IN (
                            SELECT doctor_email 
                            FROM patient_doctors 
                            WHERE patient_email = ?
                        )
                    ");
                    $all->bind_param("s", $email);
                    $all->execute();
                    $result = $all->get_result();
                    while($d = $result->fetch_assoc()):
                    ?>
                    <option value="<?= $d['email'] ?>">
                        Dr. <?= $d['fname']." ".$d['lname'] ?> - <?= $d['speciality'] ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="edit-btn" style="width:100%;">➕ Add Doctor</button>
            </form>

            <div class="divider"></div>

            
            <h3 style="font-size:14px;margin-top:4px;">🗑 Remove Doctor</h3>
            <form action="remove_doctor.php" method="POST">
                <div class="checkbox-group">
                    <?php
                    $stmt = $conn->prepare("
                        SELECT d.fname, d.lname, d.email
                        FROM doctors d
                        JOIN patient_doctors pd ON pd.doctor_email = d.email
                        WHERE pd.patient_email = ?
                    ");
                    $stmt->bind_param("s",$email);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    while($doc = $res->fetch_assoc()):
                    ?>
                    <label>
                        <input type="checkbox" name="remove[]" value="<?= $doc['email'] ?>">
                        Dr. <?= $doc['fname']." ".$doc['lname'] ?>
                    </label>
                    <?php endwhile; ?>
                </div>
                <button type="submit" class="delete-btn" style="margin-top:10px;">🗑 Delete Selected</button>
            </form>

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

    if(type === "personal"){
        title = "✏️ Edit Personal Info";
        content = `
        <form action="update_profile.php" method="POST">
        <input type="hidden" name="type" value="personal">
        <label>First Name</label>
        <input name="fname" value="<?= htmlspecialchars($user['fname']) ?>">
        <label>Last Name</label>
        <input name="lname" value="<?= htmlspecialchars($user['lname']) ?>">
        <label>Age</label>
        <input name="age" type="number" value="<?= htmlspecialchars($user['age']) ?>">
        <label>Weight (kg)</label>
        <input name="kg" type="number" step="0.1" value="<?= htmlspecialchars($user['kg']) ?>">
        <label>Sex</label>
        <select name="sex">
            <option <?= $user['sex']=="Male"?"selected":"" ?>>Male</option>
            <option <?= $user['sex']=="Female"?"selected":"" ?>>Female</option>
        </select>
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    if(type === "contact"){
        title = "✏️ Edit Contact";
        content = `
        <form action="update_profile.php" method="POST">
        <input type="hidden" name="type" value="contact">
        <label>Email</label>
        <input name="email" value="<?= htmlspecialchars($user['email']) ?>">
        <label>Phone</label>
        <input name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    if(type === "history"){
        title = "✏️ Edit Medical History";
        content = `
        <form action="update_profile.php" method="POST">
        <input type="hidden" name="type" value="history">
        <label>Select conditions</label>
        <div class="checkbox-group">
            <label><input type="checkbox" name="history[]" value="diabetes"> Diabetes</label>
            <label><input type="checkbox" name="history[]" value="hypertension"> Hypertension</label>
            <label><input type="checkbox" name="history[]" value="asthma"> Asthma</label>
            <label><input type="checkbox" name="history[]" value="heart"> Heart Disease</label>
            <label><input type="checkbox" name="history[]" value="other_history"> Other</label>
            <label><input type="checkbox" name="history[]" value="none_history"> None</label>
        </div>
        <div class="modal-footer">
            <button class="edit-btn">💾 Save</button>
            <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
        </div>
        </form>`;
    }

    if(type === "allergies"){
        title = "✏️ Edit Allergies";
        content = `
        <form action="update_profile.php" method="POST">
        <input type="hidden" name="type" value="allergies">
        <label>Select allergies</label>
        <div class="checkbox-group">
            <label><input type="checkbox" name="allergies[]" value="food"> Food</label>
            <label><input type="checkbox" name="allergies[]" value="respiratory"> Respiratory</label>
            <label><input type="checkbox" name="allergies[]" value="skin"> Skin</label>
            <label><input type="checkbox" name="allergies[]" value="drug"> Drug</label>
            <label><input type="checkbox" name="allergies[]" value="insect"> Insect</label>
            <label><input type="checkbox" name="allergies[]" value="work"> Work</label>
            <label><input type="checkbox" name="allergies[]" value="other"> Other</label>
            <label><input type="checkbox" name="allergies[]" value="none"> None</label>
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