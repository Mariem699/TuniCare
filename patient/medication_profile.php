<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$stmt = $conn->prepare("SELECT fname, lname FROM patients WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* Deactivate expired medications */
$conn->query("
    UPDATE medications
    SET is_active = 0
    WHERE email = '".mysqli_real_escape_string($conn,$email)."'
    AND DATE_ADD(start_date, INTERVAL duration_days DAY) < CURDATE()
");

/* Load active medications */
$stmt = $conn->prepare("
    SELECT * FROM medications
    WHERE email = ?
    AND is_active = 1
    AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL)
    ORDER BY start_date DESC
");
$stmt->bind_param("ss",$email,$email);
$stmt->execute();
$medications = $stmt->get_result();

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$parts = explode(" ", ($user['fname'] ?? '')." ".($user['lname'] ?? ''));
$initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):""));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Medication Profile</title>
<script src="../script.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family: Arial, sans-serif; background: #f4f7fb; display: flex; min-height: 100vh; }

.main { flex:1; padding:30px; display:flex; flex-direction:column; gap:22px; overflow-y:auto; }

.welcome-header {
    background:white; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,.07);
    padding:22px 26px; display:flex; align-items:center; gap:18px;
}
.welcome-avatar {
    width:60px; height:60px; border-radius:50%;
    background:linear-gradient(135deg,#1f4e5f,#2c7a7b); color:white;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; font-weight:bold; flex-shrink:0; border:3px solid #e5e7eb;
}
.welcome-header h1 { font-size:22px; color:#1f4e5f; margin-bottom:4px; }
.welcome-header p  { font-size:13px; color:#6b7280; }

.add-card {
    background:white; border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.07); padding:24px 28px;
}
.add-card h3 {
    font-size:16px; color:#1f4e5f; font-weight:bold;
    margin-bottom:18px; padding-bottom:12px; border-bottom:1px solid #f0f0f0;
}
.med-form { display:flex; flex-direction:column; gap:14px; }

label { display:block; margin-bottom:5px; font-weight:600; color:#1f4e5f; font-size:13px; }
input, select {
    width:100%; padding:10px 12px;
    border:2px solid #d1d5db; background:#f9fafb;
    border-radius:8px; color:#111827; font-size:14px; margin:0;
}
input:focus, select:focus {
    outline:none; border-color:#2c7a7b;
    box-shadow:0 0 0 3px rgba(44,122,123,.15);
}

.time-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }

.btn-add {
    padding:12px; background:linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color:white; border:none; border-radius:10px;
    font-size:14px; font-weight:bold; cursor:pointer; transition:.2s; margin-top:8px;
}
.btn-add:hover { opacity:.9; transform:scale(1.02); background:#29b8aa; }

.meds-card {
    background:white; border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.07); padding:24px 28px;
}
.meds-card h3 {
    font-size:16px; color:#1f4e5f; font-weight:bold;
    margin-bottom:18px; padding-bottom:12px; border-bottom:1px solid #f0f0f0;
}

.med-item {
    display:flex; gap:14px; padding:16px 18px;
    background:#f9fafb; border-radius:12px;
    border-left:4px solid #10b981; margin-bottom:12px;
    transition:transform .15s, box-shadow .15s;
}
.med-item:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.08); }

.med-icon-box {
    width:48px; height:48px; border-radius:12px;
    background:#d1fae5; display:flex; align-items:center;
    justify-content:center; font-size:24px; flex-shrink:0;
}
.med-body { flex:1; min-width:0; }
.med-name { font-size:15px; font-weight:bold; color:#111827; margin-bottom:6px; }
.med-details { display:flex; flex-wrap:wrap; gap:12px; font-size:12px; color:#6b7280; }
.med-detail-item { display:flex; align-items:center; gap:4px; }
.med-times { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.time-badge {
    background:#fef3c7; color:#92400e;
    padding:4px 10px; border-radius:20px;
    font-size:11px; font-weight:bold;
}

.empty-meds { text-align:center; padding:40px 20px; color:#9ca3af; }
.empty-meds .icon { font-size:44px; margin-bottom:12px; }
.empty-meds p { font-size:14px; }

.edit-btn, .cancel-btn, .save-btn, .delete-btn {
    display:inline-flex; align-items:center; justify-content:center;
    padding:8px 16px; font-size:13px; font-weight:bold;
    border-radius:20px; cursor:pointer; transition:.2s;
}
.edit-btn, .save-btn {
    background:linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color:white; border:none; height:40px; min-width:110px;
}
.edit-btn { height:50px; }
.edit-btn:hover, .save-btn:hover { transform:scale(1.05); background:#29b8aa; }
.delete-btn, .cancel-btn {
    background:#fee2e2; color:#991b1b;
    border:1px solid #fca5a5; height:50px; min-width:110px;
}
.cancel-btn { height:40px; }
.delete-btn:hover, .cancel-btn:hover { background:#ef4444; color:white; border-color:#ef4444; }

.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.45); backdrop-filter:blur(6px);
    z-index:1000; align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:white; border-radius:16px; padding:24px 28px;
    width:420px; max-height:80vh; overflow-y:auto;
    box-shadow:0 20px 50px rgba(0,0,0,.2);
    animation:modalIn .2s ease;
}
@keyframes modalIn { from{transform:scale(.9);opacity:0;} to{transform:scale(1);opacity:1;} }
.modal-title { font-size:16px; font-weight:bold; color:#1f4e5f; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }
.modal-footer { display:flex; gap:10px; margin-top:18px; padding-top:14px; border-top:1px solid #f0f0f0; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="welcome-header">
        <div class="welcome-avatar"><?= $initials ?></div>
        <div>
            <h1>Welcome <?= htmlspecialchars($user['fname'] ?? 'User') ?> 👋</h1>
            <p>💊 Manage your medications and reminders</p>
        </div>
    </div>

    <!-- Add form -->
    <div class="add-card">
        <h3>➕ Add New Medication</h3>
        <form class="med-form" action="add_med.php" method="POST">
            <div>
                <label>💊 Medication Name *</label>
                <input type="text" name="name" placeholder="e.g., Aspirin, Paracetamol" required>
            </div>
            <div>
                <label>💉 Dosage *</label>
                <input type="text" name="dosage" placeholder="e.g., 1 pill, 5ml" required>
            </div>
            <div>
                <label>⏰ Time(s) to Take</label>
                <div class="time-grid">
                    <input type="time" name="time1" placeholder="Morning">
                    <input type="time" name="time2" placeholder="Afternoon">
                    <input type="time" name="time3" placeholder="Evening">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label>📅 Start Date *</label>
                    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label>⏳ Duration (days) *</label>
                    <input type="number" name="duration_days" placeholder="e.g., 7, 14, 30" min="1" required>
                </div>
            </div>
            <button type="submit" class="btn-add">➕ Add Medication</button>
        </form>
    </div>

    <!-- Medications list -->
    <div class="meds-card">
        <h3>📋 Your Active Medications</h3>

        <?php if($medications->num_rows == 0): ?>
        <div class="empty-meds">
            <div class="icon">💊</div>
            <p>No active medications.<br>Add your first medication above.</p>
        </div>
        <?php endif; ?>

        <?php while($med = $medications->fetch_assoc()): ?>
        <div class="med-item">
            <div class="med-icon-box">💊</div>
            <div class="med-body">
                <div class="med-name"><?= htmlspecialchars($med['name']) ?></div>
                <div class="med-details">
                    <div class="med-detail-item"><span>💉</span><span><?= htmlspecialchars($med['dosage']) ?></span></div>
                    <div class="med-detail-item"><span>📅</span><span>Started: <?= date("d M Y", strtotime($med['start_date'])) ?></span></div>
                    <div class="med-detail-item"><span>⏳</span><span><?= htmlspecialchars($med['duration_days']) ?> days</span></div>
                </div>
                <div class="med-times">
                    <?php if(!empty($med['time_take'])): ?>
                    <span class="time-badge">⏰ <?= date("H:i", strtotime($med['time_take'])) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($med['time2'])): ?>
                    <span class="time-badge">⏰ <?= date("H:i", strtotime($med['time2'])) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($med['time3'])): ?>
                    <span class="time-badge">⏰ <?= date("H:i", strtotime($med['time3'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;">
                <button type="button" class="edit-btn"
                    onclick="openEdit(
                        '<?= $med['id'] ?>',
                        '<?= htmlspecialchars(addslashes($med['name'])) ?>',
                        '<?= htmlspecialchars(addslashes($med['dosage'])) ?>',
                        '<?= $med['time_take'] ?>',
                        '<?= $med['time2'] ?>',
                        '<?= $med['time3'] ?>',
                        '<?= $med['start_date'] ?>',
                        '<?= $med['duration_days'] ?>'
                    )">✏️ Edit</button>

                <!-- FIXED: form has correct action -->
                <form action="delete_med.php" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this medication?')">
                    <input type="hidden" name="id" value="<?= $med['id'] ?>">
                    <button type="submit" class="delete-btn">❌ Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal" onclick="closeEditOutside(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-title">✏️ Edit Medication</div>
        <form action="update_med.php" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <label>💊 Medication Name</label>
            <input type="text" name="name" id="edit_name" required>
            <label>💉 Dosage</label>
            <input type="text" name="dosage" id="edit_dosage" required>
            <label>⏰ Time 1</label>
            <input type="time" name="time1" id="edit_time1">
            <label>⏰ Time 2</label>
            <input type="time" name="time2" id="edit_time2">
            <label>⏰ Time 3</label>
            <input type="time" name="time3" id="edit_time3">
            <label>📅 Start Date</label>
            <input type="date" name="start_date" id="edit_start" required>
            <label>⏳ Duration (days)</label>
            <input type="number" name="duration_days" id="edit_duration" required min="1">
            <div class="modal-footer">
                <button type="submit" class="save-btn">💾 Save</button>
                <button type="button" class="cancel-btn" onclick="closeEdit()">❌ Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id,name,dosage,t1,t2,t3,start,duration){
    document.getElementById("edit_id").value       = id;
    document.getElementById("edit_name").value     = name;
    document.getElementById("edit_dosage").value   = dosage;
    document.getElementById("edit_time1").value    = t1;
    document.getElementById("edit_time2").value    = t2;
    document.getElementById("edit_time3").value    = t3;
    document.getElementById("edit_start").value    = start;
    document.getElementById("edit_duration").value = duration;
    document.getElementById("editModal").classList.add("open");
}
function closeEdit(){
    document.getElementById("editModal").classList.remove("open");
}
function closeEditOutside(e){
    if(e.target === document.getElementById("editModal")) closeEdit();
}
document.addEventListener("keydown", e => { if(e.key === "Escape") closeEdit(); });
</script>
</body>
</html>