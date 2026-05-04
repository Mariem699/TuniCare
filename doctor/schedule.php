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

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$today = date("Y-m-d");

/*  Handle ADD event  */ 
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add'){
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $date     = $_POST['date'] ?? '';
    $time     = $_POST['time'] ?? '';
    $type     = $_POST['type'] ?? 'other';
    $duration = (int)($_POST['duration_hours'] ?? 1);

    if($title && $date && $time){
        $stmt = $conn->prepare("INSERT INTO doctor_schedule (doctor_email,title,description,date,time,type) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss",$email,$title,$desc,$date,$time,$type);
        $stmt->execute();
    }
    header("Location: schedule.php"); exit();
}

/*  Handle DELETE event  */ 
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete'){
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM doctor_schedule WHERE id=? AND doctor_email=?");
    $stmt->bind_param("is",$id,$email);
    $stmt->execute();
    header("Location: schedule.php"); exit();
}

/*  Load schedule events  */ 
$stmt = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? ORDER BY date ASC, time ASC");
$stmt->bind_param("s",$email);
$stmt->execute();
$allEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/*  Load confirmed appointments  */ 
$stmt = $conn->prepare("
    SELECT a.*, p.fname, p.lname, p.email AS patient_email
    FROM appointments a
    JOIN patients p ON p.email = a.patient_email
    WHERE a.doctor_email = ?
    AND a.status = 'confirmed'
    AND a.date >= CURDATE()
    ORDER BY a.date ASC, a.time ASC
");
$stmt->bind_param("s",$email);
$stmt->execute();
$confirmedAppts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Group schedule events by date */
$grouped = [];
foreach($allEvents as $ev){
    $grouped[$ev['date']][] = ['type_src'=>'event', 'data'=>$ev];
}
/* Merge confirmed appointments into grouping */
foreach($confirmedAppts as $ap){
    $grouped[$ap['date']][] = ['type_src'=>'appointment', 'data'=>$ap];
}
/* Sort each day by time */
foreach($grouped as $date => &$items){
    usort($items, fn($a,$b) => strcmp(
        $a['data']['time'] ?? '00:00',
        $b['data']['time'] ?? '00:00'
    ));
}
unset($items);
ksort($grouped);

$typeConfig = [
    'operation'    => ['label'=>'Surgery',      'color'=>'#fee2e2', 'text'=>'#991b1b', 'icon'=>'🔪'],
    'consultation' => ['label'=>'Consultation', 'color'=>'#d1fae5', 'text'=>'#065f46', 'icon'=>'🩺'],
    'reunion'      => ['label'=>'Meeting',      'color'=>'#dbeafe', 'text'=>'#1e40af', 'icon'=>'👥'],
    'urgence'      => ['label'=>'Emergency',    'color'=>'#fef3c7', 'text'=>'#92400e', 'icon'=>'🚨'],
    'autre'        => ['label'=>'Other',        'color'=>'#f3f4f6', 'text'=>'#374151', 'icon'=>'📌'],
    'other'        => ['label'=>'Other',        'color'=>'#f3f4f6', 'text'=>'#374151', 'icon'=>'📌'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Schedule</title>
<script src="../script.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family: Arial, sans-serif; background: #f4f7fb; display: flex; min-height: 100vh; }

.main { flex:1; padding:30px; display:flex; flex-direction:column; gap:22px; overflow-y:auto; }

.page-header { display:flex; align-items:center; justify-content:space-between; }
.page-header h1 { font-size:24px; color:#1f4e5f; font-weight:bold; }

.btn-add {
    display:flex; align-items:center; gap:8px;
    background:linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color:white; border:none; padding:10px 20px;
    border-radius:20px; font-size:14px; font-weight:bold;
    cursor:pointer; transition:.2s;
}
.btn-add:hover { opacity:.9; transform:scale(1.03); background:#29b8aa; }

/* Filter tabs */
.filter-tabs { display:flex; gap:8px; flex-wrap:wrap; }
.filter-tab {
    padding:7px 16px; border-radius:20px;
    border:2px solid #e5e7eb; background:white;
    font-size:13px; font-weight:600; color:#6b7280;
    cursor:pointer; transition:.15s;
}
.filter-tab:hover { border-color:#2c7a7b; color:#1f4e5f; }
.filter-tab.active { background:#1f4e5f; color:white; border-color:#1f4e5f; }

/* Timeline */
.timeline { display:flex; flex-direction:column; gap:20px; }

.day-label { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.day-label-date { font-size:14px; font-weight:bold; color:#1f4e5f; }
.day-label-today { font-size:11px; background:#1f4e5f; color:white; padding:2px 10px; border-radius:20px; font-weight:bold; }
.day-label-line { flex:1; height:1px; background:#e5e7eb; }

.events-col { display:flex; flex-direction:column; gap:10px; padding-left:12px; }

/* Event card */
.event-card {
    background:white; border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
    padding:14px 16px; display:flex; align-items:flex-start;
    gap:14px; transition:transform .15s, box-shadow .15s;
    border-left:4px solid #e5e7eb;
}
.event-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.1); }

/* Appointment card */
.appt-card {
    background:white; border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,.07);
    padding:14px 16px; display:flex; align-items:flex-start;
    gap:14px; transition:transform .15s, box-shadow .15s;
    border-left:4px solid #10b981;
}
.appt-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.1); }

.event-time-col { display:flex; flex-direction:column; align-items:center; gap:4px; min-width:52px; }
.event-time { font-size:15px; font-weight:bold; color:#1f4e5f; }
.event-time-label { font-size:10px; color:#9ca3af; }

.event-icon-box { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.appt-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#10b981,#065f46); color:white; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:bold; flex-shrink:0; }

.event-body { flex:1; min-width:0; }
.event-title { font-size:15px; font-weight:bold; color:#111827; margin-bottom:4px; }
.event-desc  { font-size:13px; color:#6b7280; line-height:1.5; }

.event-badge { font-size:10px; padding:3px 10px; border-radius:20px; font-weight:bold; flex-shrink:0; align-self:flex-start; }
.appt-badge  { font-size:10px; padding:3px 10px; border-radius:20px; font-weight:bold; flex-shrink:0; align-self:flex-start; background:#d1fae5; color:#065f46; }

.btn-delete-ev {
    background:none; border:none; cursor:pointer;
    font-size:16px; color:#d1d5db; padding:4px; border-radius:6px;
    transition:.15s; flex-shrink:0; align-self:flex-start;
}
.btn-delete-ev:hover { color:#ef4444; background:#fee2e2; }

.empty-timeline { text-align:center; padding:60px 20px; color:#9ca3af; }
.empty-timeline .icon { font-size:48px; margin-bottom:12px; }
.empty-timeline p { font-size:14px; }

/* Modal */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
    z-index:100; align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:white; border-radius:16px;
    padding:28px; width:460px; max-height:90vh; overflow-y:auto;
    box-shadow:0 20px 50px rgba(0,0,0,.2);
    animation:modalIn .22s ease;
}
@keyframes modalIn { from{transform:scale(.9);opacity:0;} to{transform:scale(1);opacity:1;} }
.modal-title { font-size:17px; font-weight:bold; color:#1f4e5f; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }

label { display:block; margin-top:14px; margin-bottom:5px; font-weight:600; color:#1f4e5f; font-size:13px; }
input[type="text"], input[type="date"], input[type="time"], input[type="number"], textarea, select {
    width:100%; padding:10px 12px;
    border:2px solid #d1d5db; background:#f9fafb;
    border-radius:8px; color:#111827; font-size:14px;
    margin:0; font-family:Arial,sans-serif;
}
input:focus, textarea:focus, select:focus {
    outline:none; border-color:#2c7a7b;
    box-shadow:0 0 0 3px rgba(44,122,123,.15);
}
textarea { resize:vertical; min-height:70px; }

/* Type selector */
.type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:4px; }
.type-btn {
    padding:10px 6px; border:2px solid #e5e7eb; border-radius:10px;
    background:white; cursor:pointer; font-size:12px; font-weight:600;
    color:#6b7280; text-align:center; transition:.15s;
    display:flex; flex-direction:column; align-items:center; gap:4px;
}
.type-btn .t-icon { font-size:20px; }
.type-btn:hover { border-color:#2c7a7b; color:#1f4e5f; }
.type-btn.selected { border-color:#1f4e5f; background:#f0f9ff; color:#1f4e5f; }

.two-col { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

.modal-footer { display:flex; gap:10px; margin-top:22px; padding-top:14px; border-top:1px solid #f0f0f0; }
.btn-save {
    flex:1; padding:11px;
    background:linear-gradient(135deg,#2c7a7b,#1f4e5f);
    color:white; border:none; border-radius:8px;
    font-size:14px; font-weight:bold; cursor:pointer; transition:.2s;
}
.btn-save:hover { opacity:.9; background:#29b8aa; }
.btn-cancel {
    padding:11px 20px; background:#f3f4f6; color:#374151;
    border:1px solid #d1d5db; border-radius:8px;
    font-size:14px; font-weight:bold; cursor:pointer; transition:.2s;
}
.btn-cancel:hover { background:#e5e7eb; }

/* Chat link on appt */
.chat-link {
    display:inline-flex; align-items:center; gap:4px;
    font-size:11px; color:#2c7a7b; text-decoration:none; font-weight:600;
    padding:3px 8px; border-radius:6px; background:#f0f9ff;
    transition:.15s; margin-top:4px;
}
.chat-link:hover { background:#e8f4f8; color:#1f4e5f; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="page-header">
        <h1>📅 My Schedule</h1>
        <button class="btn-add" onclick="openModal()">＋ Add Event</button>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterEvents('all',this)">📋 All</button>
        <button class="filter-tab" onclick="filterEvents('appointment',this)">👥 Appointments</button>
        <button class="filter-tab" onclick="filterEvents('operation',this)">🔪 Surgeries</button>
        <button class="filter-tab" onclick="filterEvents('consultation',this)">🩺 Consultations</button>
        <button class="filter-tab" onclick="filterEvents('reunion',this)">👥 Meetings</button>
        <button class="filter-tab" onclick="filterEvents('urgence',this)">🚨 Emergencies</button>
        <button class="filter-tab" onclick="filterEvents('autre',this)">📌 Other</button>
    </div>

    <!-- Timeline -->
    <div class="timeline" id="timeline">

    <?php if(empty($grouped)): ?>
        <div class="empty-timeline">
            <div class="icon">📅</div>
            <p>No events scheduled yet.<br>Click <b>＋ Add Event</b> to get started.</p>
        </div>
    <?php endif; ?>

    <?php foreach($grouped as $date => $items):
        $isToday       = ($date === $today);
        $dateFormatted = date("l, d F Y", strtotime($date));
        $isPast        = ($date < $today);
    ?>
    <div class="day-block" data-date="<?= $date ?>">
        <div class="day-label">
            <span class="day-label-date" style="<?= $isPast ? 'color:#9ca3af' : '' ?>">
                <?= htmlspecialchars($dateFormatted) ?>
            </span>
            <?php if($isToday): ?><span class="day-label-today">Today</span><?php endif; ?>
            <span class="day-label-line"></span>
        </div>

        <div class="events-col">
        <?php foreach($items as $item):
            $src = $item['type_src'];

            if($src === 'event'):
                $ev  = $item['data'];
                $tc  = $typeConfig[$ev['type']] ?? $typeConfig['other'];
                $timeStr = date("H:i", strtotime($ev['time']));
        ?>
        <!-- SCHEDULE EVENT -->
        <div class="event-card"
             data-type="<?= htmlspecialchars($ev['type']) ?>"
             data-src="event"
             style="border-left-color:<?= $tc['text'] ?>;">
            <div class="event-time-col">
                <div class="event-time"><?= $timeStr ?></div>
                <div class="event-time-label">time</div>
            </div>
            <div class="event-icon-box" style="background:<?= $tc['color'] ?>;">
                <?= $tc['icon'] ?>
            </div>
            <div class="event-body">
                <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                <?php if(!empty($ev['description'])): ?>
                <div class="event-desc"><?= htmlspecialchars($ev['description']) ?></div>
                <?php endif; ?>
            </div>
            <span class="event-badge" style="background:<?= $tc['color'] ?>;color:<?= $tc['text'] ?>;">
                <?= $tc['label'] ?>
            </span>
            <form method="POST" onsubmit="return confirm('Delete this event?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                <button type="submit" class="btn-delete-ev" title="Delete">🗑️</button>
            </form>
        </div>

        <?php else:
            $ap      = $item['data'];
            $pparts  = explode(" ", $ap['fname']." ".$ap['lname']);
            $pinit   = strtoupper(substr($pparts[0],0,1).(isset($pparts[1])?substr($pparts[1],0,1):""));
            $apTime  = date("H:i", strtotime($ap['time']));
        ?>
        <!-- CONFIRMED APPOINTMENT -->
        <div class="appt-card" data-type="appointment" data-src="appointment">
            <div class="event-time-col">
                <div class="event-time"><?= $apTime ?></div>
                <div class="event-time-label">appt</div>
            </div>
            <div class="appt-avatar"><?= $pinit ?></div>
            <div class="event-body">
                <div class="event-title">
                    <?= htmlspecialchars($ap['fname']." ".$ap['lname']) ?>
                </div>
                <?php if(!empty($ap['reason'])): ?>
                <div class="event-desc">📝 <?= htmlspecialchars(mb_substr($ap['reason'],0,60)) ?></div>
                <?php endif; ?>
                <a href="../chat.php?user=<?= urlencode($ap['patient_email']) ?>" class="chat-link">
                    💬 Chat with patient
                </a>
            </div>
            <span class="appt-badge">✅ Confirmed</span>
        </div>
        <?php endif; endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    </div>
</div>

<!-- ADD EVENT MODAL -->
<div class="modal-overlay" id="addModal" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <div class="modal-title">➕ New Event</div>
        <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="type" id="selectedType" value="other">

        <label>Event Type</label>
        <div class="type-grid">
            <button type="button" class="type-btn" onclick="selectType('operation',this)">
                <span class="t-icon">🔪</span> Surgery
            </button>
            <button type="button" class="type-btn" onclick="selectType('consultation',this)">
                <span class="t-icon">🩺</span> Consultation
            </button>
            <button type="button" class="type-btn" onclick="selectType('reunion',this)">
                <span class="t-icon">👥</span> Meeting
            </button>
            <button type="button" class="type-btn" onclick="selectType('urgence',this)">
                <span class="t-icon">🚨</span> Emergency
            </button>
            <button type="button" class="type-btn selected" onclick="selectType('other',this)" id="defaultType">
                <span class="t-icon">📌</span> Other
            </button>
        </div>

        <label>Title *</label>
        <input type="text" name="title" placeholder="e.g., Surgery room 3, Team meeting..." required>

        <label>Description</label>
        <textarea name="description" placeholder="Details, location, notes..."></textarea>

        <div class="two-col">
            <div>
                <label>Date *</label>
                <input type="date" name="date" value="<?= $today ?>" min="<?= $today ?>" required>
            </div>
            <div>
                <label>Start Time *</label>
                <input type="time" name="time" required>
            </div>
        </div>

        <label>Duration (hours)</label>
        <select name="duration_hours">
            <option value="1">1 hour</option>
            <option value="2">2 hours</option>
            <option value="3">3 hours</option>
            <option value="4">4 hours</option>
            <option value="6">Half day (6h)</option>
            <option value="8">Full day (8h)</option>
        </select>

        <div class="modal-footer">
            <button type="submit" class="btn-save">💾 Save Event</button>
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        </div>
        </form>
    </div>
</div>

<script>
function openModal(){
    document.getElementById("addModal").classList.add("open");
}
function closeModal(){
    document.getElementById("addModal").classList.remove("open");
}
function closeModalOutside(e){
    if(e.target === document.getElementById("addModal")) closeModal();
}
document.addEventListener("keydown", e => { if(e.key === "Escape") closeModal(); });

function selectType(type, btn){
    document.querySelectorAll(".type-btn").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
    document.getElementById("selectedType").value = type;
}

function filterEvents(type, btn){
    document.querySelectorAll(".filter-tab").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    document.querySelectorAll(".event-card, .appt-card").forEach(card => {
        const cardType = card.dataset.type;
        const cardSrc  = card.dataset.src;
        let visible = false;

        if(type === 'all'){
            visible = true;
        } else if(type === 'appointment'){
            visible = (cardSrc === 'appointment');
        } else {
            visible = (cardSrc === 'event') &&
                      (cardType === type || (type === 'autre' && (cardType === 'autre' || cardType === 'other')));
        }
        card.style.display = visible ? 'flex' : 'none';
    });

    document.querySelectorAll(".day-block").forEach(block => {
        const visible = [...block.querySelectorAll(".event-card, .appt-card")]
                        .some(c => c.style.display !== "none");
        block.style.display = visible ? "" : "none";
    });
}
</script>
</body>
</html>