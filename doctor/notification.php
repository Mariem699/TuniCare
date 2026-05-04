<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }

$today = date("Y-m-d");

/*  Confirm appointment  */ 
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_appt'])){
    $appt_id = (int)$_POST['appt_id'];
    $stmt = $conn->prepare("UPDATE appointments SET status='confirmed' WHERE id=? AND doctor_email=?");
    $stmt->bind_param("is",$appt_id,$email);
    $stmt->execute();
    header("Location: notification.php"); exit();
}

/*  ALL messages from patients (excluding deleted_for me)  */ 
$meEsc = mysqli_real_escape_string($conn,$email);
$msg_res = $conn->query("
    SELECT m.*, p.fname, p.lname, m.sender_email AS patient_email
    FROM messages m
    JOIN patients p ON p.email = m.sender_email
    WHERE m.receiver_email = '$meEsc'
    AND (m.deleted_for IS NULL OR JSON_SEARCH(m.deleted_for,'one','$meEsc') IS NULL)
    ORDER BY m.created_at DESC
");

/*  Unread count  */ 
$uq = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_email='$meEsc' AND is_read=0 AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for,'one','$meEsc') IS NULL)");
$unreadCount = (int)($uq->fetch_assoc()['c'] ?? 0);

/*  Schedule: upcoming  */ 
$schedule = $conn->prepare("SELECT * FROM doctor_schedule WHERE doctor_email=? AND date>=CURDATE() ORDER BY date ASC, time ASC");
$schedule->bind_param("s",$email); $schedule->execute();
$schedule_res = $schedule->get_result();

$schedTodayQ = $conn->query("SELECT COUNT(*) as c FROM doctor_schedule WHERE doctor_email='$meEsc' AND date=CURDATE()");
$scheduleTodayCount = (int)($schedTodayQ->fetch_assoc()['c'] ?? 0);

/*  Pending appointments  */ 
$rdvPending = $conn->prepare("SELECT a.*,p.fname,p.lname FROM appointments a JOIN patients p ON p.email=a.patient_email WHERE a.doctor_email=? AND a.status='pending' AND a.date>=CURDATE() ORDER BY a.date ASC,a.time ASC");
$rdvPending->bind_param("s",$email); $rdvPending->execute();
$rdvPending_res = $rdvPending->get_result();

/*  Soon < 1h  */ 
$rdvSoon = $conn->prepare("SELECT a.*,p.fname,p.lname FROM appointments a JOIN patients p ON p.email=a.patient_email WHERE a.doctor_email=? AND a.status!='cancelled' AND a.date=CURDATE() AND TIMEDIFF(a.time,CURTIME()) BETWEEN '00:00:00' AND '01:00:00' ORDER BY a.time ASC");
$rdvSoon->bind_param("s",$email); $rdvSoon->execute();
$rdvSoon_res = $rdvSoon->get_result();

/*  All upcoming  */ 
$rdvAll = $conn->prepare("SELECT a.*,p.fname,p.lname,p.email AS patient_email_col FROM appointments a JOIN patients p ON p.email=a.patient_email WHERE a.doctor_email=? AND a.status!='cancelled' AND a.date>=CURDATE() ORDER BY a.date ASC,a.time ASC");
$rdvAll->bind_param("s",$email); $rdvAll->execute();
$rdvAll_res = $rdvAll->get_result();

$totalMessages = $msg_res ? $msg_res->num_rows : 0;
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$typeConfig = [
    'operation'    => ['label'=>'Opération',   'color'=>'#fee2e2','text'=>'#991b1b','icon'=>'🔪','border'=>'#ef4444'],
    'consultation' => ['label'=>'Consultation', 'color'=>'#d1fae5','text'=>'#065f46','icon'=>'🩺','border'=>'#10b981'],
    'reunion'      => ['label'=>'Réunion',      'color'=>'#dbeafe','text'=>'#1e40af','icon'=>'👥','border'=>'#3b82f6'],
    'urgence'      => ['label'=>'Urgence',      'color'=>'#fef3c7','text'=>'#92400e','icon'=>'🚨','border'=>'#f59e0b'],
    'autre'        => ['label'=>'Autre',        'color'=>'#f3f4f6','text'=>'#374151','icon'=>'📌','border'=>'#6b7280'],
];



?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Notifications – Doctor</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family: Arial, sans-serif; background: #f4f7fb; display: flex; min-height: 100vh; }
.main { flex:1; padding:30px; display:flex; flex-direction:column; gap:22px; overflow-y:auto; }

.page-header { display:flex; align-items:center; justify-content:space-between; }
.page-header h1 { font-size:24px; color:#1f4e5f; font-weight:bold; }
.btn-markread {
    display:flex; align-items:center; gap:6px;
    background: #d89696;
    color: #000000;
    border:1px solid #ca27279d;
    padding:8px 16px;
    border-radius:20px;
    font-size:13px;
    font-weight:bold;
    cursor:pointer;
    transition:.2s;
}
.btn-markread:hover { 
    background: #d12121;
    color: white;
}

.tabs { display:flex; gap:8px; border-bottom:2px solid #e5e7eb; flex-wrap:wrap; }
.tab-btn { padding:10px 20px; border:none; background:none; font-size:14px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:.2s; border-radius:8px 8px 0 0; }
.tab-btn:hover { color:#1f4e5f; background:#f4f7fb; }
.tab-btn.active { color:#1f4e5f; border-bottom-color:#1f4e5f; background:white; }
.tab-count { background:#ef4444; color:white; border-radius:50%; padding:1px 6px; font-size:11px; margin-left:5px; }
.tab-content { display:none; flex-direction:column; gap:12px; }
.tab-content.active { display:flex; }

/*  Message card  */ 
.msg-card {
    background:white; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.07);
    padding:18px 20px; border-left:4px solid #2c7a7b;
    transition:transform .15s, box-shadow .15s, opacity .3s;
    position:relative;
}
.msg-card.unread { border-left-color:#3b82f6; background:#f0f6ff; }
.msg-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
.msg-card.removing { opacity:0; transform:translateX(40px) scale(.97); pointer-events:none; }

.msg-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.msg-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#1f4e5f,#2c7a7b); color:white; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:bold; flex-shrink:0; }
.msg-sender-name { font-size:15px; font-weight:bold; color:#1f4e5f; text-decoration:none; display:block; }
.msg-sender-name:hover { color:#29b8aa; text-decoration:underline; }
.msg-sender-email { font-size:11px; color:#6b7280; }
.unread-dot { width:8px; height:8px; border-radius:50%; background:#3b82f6; flex-shrink:0; box-shadow:0 0 6px rgba(59,130,246,.6); }
.btn-del-msg { display:flex; align-items:center; gap:5px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; flex-shrink:0; }
.btn-del-msg:hover { background:#ef4444; color:white; border-color:#ef4444; }
.msg-body { background:#f0f9ff; padding:14px 16px; border-radius:12px; border-left:3px solid #2c7a7b; margin-bottom:10px; }
.msg-text { font-size:14px; color:#374151; line-height:1.6; }
.msg-time { font-size:11px; color:#9ca3af; display:flex; align-items:center; gap:4px; }

/*  Schedule  */ 
.sched-card { background:white; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.07); padding:16px 20px; display:flex; align-items:flex-start; gap:14px; transition:transform .15s; }
.sched-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
.sched-time-col { display:flex; flex-direction:column; align-items:center; gap:4px; min-width:56px; flex-shrink:0; }
.sched-time { font-size:16px; font-weight:bold; color:#1f4e5f; }
.sched-chip { font-size:10px; padding:2px 8px; border-radius:20px; font-weight:bold; white-space:nowrap; }
.chip-today { background:#1f4e5f; color:white; }
.chip-date  { background:#dbeafe; color:#1e40af; }
.sched-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
.sched-body { flex:1; min-width:0; }
.sched-title { font-size:15px; font-weight:bold; color:#111827; margin-bottom:4px; }
.sched-desc  { font-size:13px; color:#6b7280; line-height:1.5; }
.sched-badge { font-size:10px; padding:3px 10px; border-radius:20px; font-weight:bold; flex-shrink:0; align-self:flex-start; }
.day-sep { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; padding:10px 4px 2px; display:flex; align-items:center; gap:8px; }
.day-sep::after { content:''; flex:1; height:1px; background:#e5e7eb; }

/*  RDV  */ 
.rdv-card { background:white; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.07); padding:18px 20px; display:flex; align-items:center; gap:14px; }
.rdv-avatar { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,#2c7a7b,#1f4e5f); color:white; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:bold; flex-shrink:0; }
.rdv-body { flex:1; min-width:0; }
.rdv-name { font-size:14px; font-weight:bold; color:#1f4e5f; text-decoration:none; display:block; margin-bottom:4px; }
.rdv-name:hover { text-decoration:underline; color:#29b8aa; }
.rdv-meta { display:flex; gap:10px; flex-wrap:wrap; font-size:12px; color:#6b7280; }
.rdv-actions { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.status-badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; }
.status-pending   { background:#fef3c7; color:#92400e; }
.status-confirmed { background:#d1fae5; color:#065f46; }
.btn-confirm { background:linear-gradient(135deg,#2c7a7b,#1f4e5f); color:white; border:none; padding:6px 14px; border-radius:8px; font-size:12px; font-weight:bold; cursor:pointer; transition:.2s; }
.btn-confirm:hover { opacity:.9; transform:scale(1.03); }
.alert-soon { background:#fff7ed; border:2px solid #fb923c; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:12px; }
.alert-pending { background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 16px; font-size:13px; color:#1e40af; font-weight:600; }

.empty-state { text-align:center; padding:50px 20px; color:#9ca3af; }
.empty-state .icon { font-size:44px; margin-bottom:12px; }
.empty-state p { font-size:14px; }

/*  DELETE MODAL  */ 
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:20px; padding:32px 28px; width:360px; box-shadow:0 20px 60px rgba(0,0,0,.2); text-align:center; animation:modalIn .2s ease; }
@keyframes modalIn { from{transform:scale(.9);opacity:0;} to{transform:scale(1);opacity:1;} }
.modal-icon { font-size:44px; margin-bottom:14px; }
.modal-box h3 { font-size:17px; color:#1a202c; margin-bottom:8px; font-weight:700; }
.modal-box p  { font-size:13px; color:#718096; margin-bottom:24px; line-height:1.7; }
.modal-actions { display:flex; gap:10px; justify-content:center; }
.btn-confirm-del { padding:10px 24px; background:#ef4444; color:white; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-confirm-del:hover { background:#dc2626; }
.btn-cancel-del { padding:10px 24px; background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-cancel-del:hover { background:#e2e8f0; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="page-header">
        <h1>🔔 Notifications</h1>

        <button class="btn-markread" onclick="clearAllNotif()">✓ Mark all as read</button>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('messages',this)">
            💬 Patient Messages
            <?php if($unreadCount > 0): ?><span class="tab-count"><?= $unreadCount ?></span>
            <?php elseif($totalMessages > 0): ?><span class="tab-count" style="background:#6b7280;"><?= $totalMessages ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('planning',this)" <?= $scheduleTodayCount>0?'style="color:#1e40af;font-weight:800;"':'' ?>>
            📅 My Schedule
            <?php if($scheduleTodayCount>0): ?><span class="tab-count" style="background:#3b82f6;">📌 <?= $scheduleTodayCount ?></span>
            <?php elseif($schedule_res->num_rows>0): ?><span class="tab-count" style="background:#6b7280;"><?= $schedule_res->num_rows ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('rdv',this)" <?= ($rdvSoon_res->num_rows>0||$rdvPending_res->num_rows>0)?'style="color:#ef4444;font-weight:800;"':'' ?>>
            📆 Patient Appointments
            <?php if($rdvSoon_res->num_rows>0): ?><span class="tab-count">⚠️ <?= $rdvSoon_res->num_rows ?></span>
            <?php elseif($rdvPending_res->num_rows>0): ?><span class="tab-count"><?= $rdvPending_res->num_rows ?></span>
            <?php elseif($rdvAll_res->num_rows>0): ?><span class="tab-count" style="background:#1f4e5f;"><?= $rdvAll_res->num_rows ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ══ MESSAGES ══ -->
    <div class="tab-content active" id="tab-messages">
        <?php if(!$msg_res || $msg_res->num_rows===0): ?>
        <div class="empty-state"><div class="icon">💬</div><p>No messages from your patients yet.</p></div>
        <?php else: while($m = $msg_res->fetch_assoc()):
            $sp = explode(" ",($m['fname']??'')." ".($m['lname']??''));
            $si = strtoupper(substr($sp[0],0,1).(isset($sp[1])?substr($sp[1],0,1):""));
            $ts = date("d M Y, H:i",strtotime($m['created_at']));
            $isUnread = ($m['is_read']==0);
        ?>
        <div class="msg-card <?= $isUnread?'unread':'' ?>" id="msg-<?= $m['id'] ?>">
            <div class="msg-header">
                <div class="msg-avatar"><?= $si ?></div>
                <div style="flex:1;min-width:0;">
                    <a href="../chat.php?user=<?= urlencode($m['patient_email']) ?>" class="msg-sender-name">
                        <?= htmlspecialchars(($m['fname']??'')." ".($m['lname']??'')) ?>
                    </a>
                    <span class="msg-sender-email">📧 <?= htmlspecialchars($m['patient_email']) ?></span>
                </div>
                <?php if($isUnread): ?><div class="unread-dot"></div><?php endif; ?>
                <button class="btn-del-msg" onclick="askDelete(<?= $m['id'] ?>)">🗑️ Delete</button>
            </div>
            <div class="msg-body"><div class="msg-text"><?= htmlspecialchars($m['message']) ?></div></div>
            <div class="msg-time"><span>🕒</span><span><?= $ts ?></span></div>
        </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- ══ PLANNING ══ -->
    <div class="tab-content" id="tab-planning">
        <?php $schedule_res->data_seek(0);
        if($schedule_res->num_rows===0): ?>
        <div class="empty-state"><div class="icon">📅</div><p>No upcoming events.<br><a href="schedule.php" style="color:#2c7a7b;font-weight:bold;">Go to Planning →</a></p></div>
        <?php endif;
        $lastDate = null;
        while($ev = $schedule_res->fetch_assoc()):
            $tc = $typeConfig[$ev['type']] ?? $typeConfig['autre'];
            $evTime = date("H:i",strtotime($ev['time']));
            $isToday = ($ev['date']===$today);
            if($ev['date']!==$lastDate): $lastDate=$ev['date']; ?>
        <div class="day-sep"><?= $isToday?'📌 Today — '.date("d M Y"):'📅 '.date("l d M Y",strtotime($ev['date'])) ?></div>
        <?php endif; ?>
        <div class="sched-card" style="border-left:4px solid <?= $tc['border'] ?>;">
            <div class="sched-time-col">
                <div class="sched-time"><?= $evTime ?></div>
                <span class="sched-chip <?= $isToday?'chip-today':'chip-date' ?>"><?= $isToday?'Today':date("d M",strtotime($ev['date'])) ?></span>
            </div>
            <div class="sched-icon" style="background:<?= $tc['color'] ?>;"><?= $tc['icon'] ?></div>
            <div class="sched-body">
                <div class="sched-title"><?= htmlspecialchars($ev['title']) ?></div>
                <?php if(!empty($ev['description'])): ?><div class="sched-desc"><?= htmlspecialchars($ev['description']) ?></div><?php endif; ?>
            </div>
            <span class="sched-badge" style="background:<?= $tc['color'] ?>;color:<?= $tc['text'] ?>;"><?= $tc['label'] ?></span>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- ══ RDV ══ -->
    <div class="tab-content" id="tab-rdv">
        <?php if($rdvSoon_res->num_rows>0): ?>
        <div class="alert-soon"><span style="font-size:28px;">⚠️</span>
        <div><div style="font-weight:bold;color:#c2410c;font-size:14px;">Patient appointment in less than 1 hour!</div>
        <div style="font-size:12px;color:#9a3412;">Please be ready to receive your patient.</div></div></div>
        <?php endif;
        if($rdvPending_res->num_rows>0): ?>
        <div class="alert-pending">📋 <?= $rdvPending_res->num_rows ?> pending appointment(s) waiting for your confirmation.</div>
        <?php endif;
        $rdvAll_res->data_seek(0);
        if($rdvAll_res->num_rows===0): ?>
        <div class="empty-state"><div class="icon">📆</div><p>No upcoming patient appointments.</p></div>
        <?php endif;
        while($rdv = $rdvAll_res->fetch_assoc()):
            $rp = explode(" ",$rdv['fname']." ".$rdv['lname']);
            $ri = strtoupper(substr($rp[0],0,1).(isset($rp[1])?substr($rp[1],0,1):""));
            $rdvDate  = date("d M Y",strtotime($rdv['date']));
            $rdvTime  = date("H:i",strtotime($rdv['time']));
            $rdvToday = ($rdv['date']===$today);
            $diff     = strtotime($rdv['date']." ".$rdv['time'])-time();
            $isSoon   = $rdvToday && $diff>=0 && $diff<=3600;
            $border   = $isSoon?"#fb923c":($rdv['status']==='pending'?"#f59e0b":"#2c7a7b");
            $patEmail = $rdv['patient_email_col'] ?? $rdv['patient_email'];
        ?>
        <div class="rdv-card" style="border-left:4px solid <?= $border ?>;">
            <div class="rdv-avatar"><?= $ri ?></div>
            <div class="rdv-body">
                <a href="../chat.php?user=<?= urlencode($patEmail) ?>" class="rdv-name">
                    <?= htmlspecialchars($rdv['fname']." ".$rdv['lname']) ?>
                </a>
                <div class="rdv-meta">
                    <span><?= $rdvToday?'📅 Today':"📅 $rdvDate" ?></span>
                    <span>⏰ <?= $rdvTime ?></span>
                    <?php if(!empty($rdv['reason'])): ?><span>📝 <?= htmlspecialchars(mb_substr($rdv['reason'],0,40)) ?><?= mb_strlen($rdv['reason'])>40?'...':'' ?></span><?php endif; ?>
                </div>
            </div>
            <div class="rdv-actions">
                <span class="status-badge <?= $rdv['status']==='confirmed'?'status-confirmed':'status-pending' ?>"><?= $rdv['status']==='confirmed'?'✅ Confirmed':'⏳ Pending' ?></span>
                <?php if($isSoon): ?><span style="background:#fff7ed;color:#c2410c;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:bold;">⚠️ &lt;1h</span><?php endif; ?>
                <?php if($rdv['status']==='pending'): ?>
                <form method="POST" action="notification.php">
                    <input type="hidden" name="appt_id" value="<?= $rdv['id'] ?>">
                    <button type="submit" name="confirm_appt" value="1" class="btn-confirm">✅ Confirm</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

</div>

<!-- ══ DELETE MODAL ══ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">🗑️</div>
        <h3>Delete this message?</h3>
        <p>This message will be removed <b>only on your side</b>.<br>The patient can still see it.</p>
        <div class="modal-actions">
            <button class="btn-confirm-del" onclick="confirmDelete()">Yes, delete</button>
            <button class="btn-cancel-del"  onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let pendingId = null;

function switchTab(name, btn){
    document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(c=>c.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById("tab-"+name).classList.add("active");
}

function askDelete(id){
    pendingId = id;
    document.getElementById("deleteModal").classList.add("open");
}
function closeModal(){
    document.getElementById("deleteModal").classList.remove("open");
    pendingId = null;
}
function confirmDelete(){
    if(!pendingId) return;
    const id = pendingId;
    closeModal();
    fetch("../delete_message.php",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"msg_id="+id
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            const card = document.getElementById("msg-"+id);
            if(card){
                card.classList.add("removing");
                setTimeout(()=>{
                    card.remove();
                    const left = document.querySelectorAll("#tab-messages .msg-card");
                    if(left.length===0){
                        document.getElementById("tab-messages").innerHTML=
                            '<div class="empty-state"><div class="icon">💬</div><p>No messages from your patients yet.</p></div>';
                    }
                },350);
            }
        } else { alert("Error: "+(res.error||"Cannot delete.")); }
    });
}
function clearAllNotif(){

   
    document.getElementById("tab-messages").innerHTML =
        '<div class="empty-state"><div class="icon">💬</div><p>No messages.</p></div>';

    document.getElementById("tab-planning").innerHTML =
        '<div class="empty-state"><div class="icon">📅</div><p>No schedule.</p></div>';

    document.getElementById("tab-rdv").innerHTML =
        '<div class="empty-state"><div class="icon">📆</div><p>No appointments.</p></div>';

    document.querySelectorAll(".tab-count").forEach(el => el.remove());


    fetch("clear_notifications.php", {
        method: "POST"
    });
}
document.addEventListener("keydown",e=>{ if(e.key==="Escape") closeModal(); });
</script>
</body>
</html>