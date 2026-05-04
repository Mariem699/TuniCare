<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)){ header("Location: ../login.html"); exit(); }



$today   = date("Y-m-d");
$nowTime = date("H:i:s");
$meEsc   = mysqli_real_escape_string($conn, $email);


    /*  Mark messages as read  */ 
    $conn->query("UPDATE messages SET is_read=1 WHERE receiver_email='$meEsc' AND is_read=0");

    
    /*  Messages from doctors  */ 
    $msg_res = $conn->query("
        SELECT m.*, d.fname, d.lname, d.email AS doctor_email
        FROM messages m
        JOIN doctors d ON d.email = m.sender_email
        WHERE m.receiver_email = '$meEsc'
        AND (m.deleted_for IS NULL OR JSON_SEARCH(m.deleted_for, 'one', '$meEsc') IS NULL)
        ORDER BY m.created_at DESC
    ");

    $unreadCount = 0;
    if($msg_res){ $msg_res->data_seek(0); while($mm=$msg_res->fetch_assoc()) if($mm['is_read']==0) $unreadCount++; $msg_res->data_seek(0); }
    $totalMessages = $msg_res ? $msg_res->num_rows : 0;

    /*  Today's active medications (NOT soft-deleted)  */ 
    $stmt = $conn->prepare("
        SELECT * FROM medications
        WHERE email=?
        AND is_active=1
        AND start_date <= ?
        AND DATE_ADD(start_date, INTERVAL duration_days DAY) >= ?
        AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL)
        ORDER BY time_take ASC
    ");
    $stmt->bind_param("ssss", $email, $today, $today, $email);
    $stmt->execute();
    $medications = $stmt->get_result();

    /* Build flat list of all doses with their times */
    $allDosesData = [];
    $medications->data_seek(0);
    while($med = $medications->fetch_assoc()){
        $daysLeft = (int)$med['duration_days'] - (int)floor((time() - strtotime($med['start_date'])) / 86400);
        $daysLeft = max(0, $daysLeft);
        $rawTimes = [];
        if(!empty($med['time_take'])) $rawTimes[] = $med['time_take'];
        if(!empty($med['time2']))     $rawTimes[] = $med['time2'];
        if(!empty($med['time3']))     $rawTimes[] = $med['time3'];
        foreach($rawTimes as $rt){
            $hhmm = substr($rt, 0, 5);
            [$h,$m] = explode(':', $hhmm);
            $doseSec = (int)$h * 3600 + (int)$m * 60;
            $allDosesData[] = [
                'uid'      => $med['id'].'-'.str_replace(':','',$hhmm),
                'med_id'   => $med['id'],
                'name'     => $med['name'],
                'dosage'   => $med['dosage'],
                'hhmm'     => $hhmm,
                'doseSec'  => $doseSec,
                'daysLeft' => $daysLeft,
            ];
        }
    }
    usort($allDosesData, fn($a,$b) => $a['doseSec'] <=> $b['doseSec']);

    [$nh,$nm,$ns] = explode(':', $nowTime);
    $nowSec = (int)$nh*3600 + (int)$nm*60 + (int)$ns;

    /*  Appointments soon < 1h  */ 
    $rdvSoon = $conn->prepare("
        SELECT a.*, d.fname, d.lname, d.speciality, d.email AS doctor_email
        FROM appointments a
        JOIN doctors d ON d.email = a.doctor_email
        WHERE a.patient_email=?
        AND a.status != 'cancelled'
        AND a.date = CURDATE()
        AND TIMEDIFF(a.time, CURTIME()) BETWEEN '00:00:00' AND '01:00:00'
        AND (a.deleted_for IS NULL OR JSON_SEARCH(a.deleted_for,'one',?) IS NULL)
        ORDER BY a.time ASC
    ");
    $rdvSoon->bind_param("ss", $email, $email);
    $rdvSoon->execute();
    $rdvSoon_res = $rdvSoon->get_result();

    /*  All upcoming appointments  */ 
    $rdvAll = $conn->prepare("
        SELECT a.*, d.fname, d.lname, d.speciality, d.email AS doctor_email
        FROM appointments a
        JOIN doctors d ON d.email = a.doctor_email
        WHERE a.patient_email=?
        AND a.status != 'cancelled'
        AND CONCAT(a.date,' ',a.time) >= NOW()
        AND (a.deleted_for IS NULL OR JSON_SEARCH(a.deleted_for,'one',?) IS NULL)
        ORDER BY a.date ASC, a.time ASC
    ");
    $rdvAll->bind_param("ss", $email, $email);
    $rdvAll->execute();
    $rdvAll_res = $rdvAll->get_result();

    /*  Confirmed appointments (for banner)  */ 
    $rdvConfirmed = $conn->prepare("
        SELECT a.*, d.fname, d.lname, d.speciality, d.email AS doctor_email
        FROM appointments a
        JOIN doctors d ON d.email = a.doctor_email
        WHERE a.patient_email=?
        AND a.status = 'confirmed'
        AND a.date >= CURDATE()
        AND (a.deleted_for IS NULL OR JSON_SEARCH(a.deleted_for,'one',?) IS NULL)
        ORDER BY a.date ASC, a.time ASC
    ");
    $rdvConfirmed->bind_param("ss", $email, $email);
    $rdvConfirmed->execute();
    $rdvConfirmed_res = $rdvConfirmed->get_result();
    $confirmedCount = $rdvConfirmed_res->num_rows;


$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Notifications </title>
<style>
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family: Arial, sans-serif; background: #f4f7fb; display: flex; min-height: 100vh; }
.main { flex:1; padding:30px; display:flex; flex-direction:column; gap:22px; overflow-y:auto; }

.page-header { display:flex; align-items:center; justify-content:space-between; }
.page-header h1 { font-size:24px; color:#1f4e5f; font-weight:bold; }
.btn-markread {
    display:flex; align-items:center; gap:6px;
    background:#d89696; color:#000;
    border:1px solid #ca27279d;
    padding:8px 16px; border-radius:20px;
    font-size:13px; font-weight:bold; cursor:pointer; transition:.2s;
}
.btn-markread:hover { background:#d12121; color:white; }

.tabs { display:flex; gap:8px; border-bottom:2px solid #e5e7eb; flex-wrap:wrap; }
.tab-btn {
    padding:10px 20px; border:none; background:none;
    font-size:14px; font-weight:600; color:#6b7280;
    cursor:pointer; border-bottom:3px solid transparent;
    margin-bottom:-2px; transition:.2s; border-radius:8px 8px 0 0;
}
.tab-btn:hover { color:#1f4e5f; background:#f4f7fb; }
.tab-btn.active { color:#1f4e5f; border-bottom-color:#1f4e5f; background:white; }
.tab-count { background:#ef4444; color:white; border-radius:50%; padding:1px 6px; font-size:11px; margin-left:5px; }
.tab-content { display:none; flex-direction:column; gap:12px; }
.tab-content.active { display:flex; }

/*  Message cards  */ 
.msg-card {
    background:white; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.07);
    padding:18px 20px; border-left:4px solid #2c7a7b;
    transition:transform .15s, box-shadow .15s, opacity .3s; position:relative;
}
.msg-card.unread { border-left-color:#3b82f6; background:#f0f6ff; }
.msg-card:hover  { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
.msg-card.removing { opacity:0; transform:translateX(40px); pointer-events:none; }
.msg-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.msg-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#1f4e5f,#2c7a7b); color:white; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:bold; flex-shrink:0; }
.msg-sender-name { font-size:15px; font-weight:bold; color:#1f4e5f; text-decoration:none; display:block; }
.msg-sender-name:hover { color:#29b8aa; text-decoration:underline; }
.msg-sender-sub { font-size:11px; color:#6b7280; }
.unread-dot { width:8px; height:8px; border-radius:50%; background:#3b82f6; flex-shrink:0; box-shadow:0 0 6px rgba(59,130,246,.6); }
.btn-del-msg { display:flex; align-items:center; gap:5px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:.2s; flex-shrink:0; }
.btn-del-msg:hover { background:#ef4444; color:white; border-color:#ef4444; }
.msg-body { background:#f0f9ff; padding:14px 16px; border-radius:12px; border-left:3px solid #2c7a7b; margin-bottom:10px; }
.msg-text { font-size:14px; color:#374151; line-height:1.6; }
.msg-time { font-size:11px; color:#9ca3af; display:flex; align-items:center; gap:4px; }

/*  Schedule cards (doctor)  */ 
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

/*  Appointment cards  */ 
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
.alert-confirmed { background:#f0fdf4; border:2px solid #10b981; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:12px; }

/*  Medication cards (patient)  */ 
.live-clock { background:#1f4e5f; color:white; border-radius:12px; padding:12px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 4px 14px rgba(31,78,95,.2); }
.live-clock-time { font-size:28px; font-weight:900; letter-spacing:2px; }
.live-clock-label { font-size:12px; color:#94d0dc; }

.med-section-header { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; padding:10px 4px 4px; display:flex; align-items:center; gap:8px; }
.med-section-header::after { content:''; flex:1; height:1px; background:#e5e7eb; }

.dose-card {
    background:white; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.07);
    padding:16px 20px; display:flex; align-items:center; gap:16px;
    border-left:4px solid #e5e7eb;
    transition:transform .15s, box-shadow .15s;
}
.dose-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
.dose-card.state-now  { border-left-color:#ef4444; background:linear-gradient(135deg,#fff5f5,#fff); animation:pulseCard 2s infinite; }
.dose-card.state-soon { border-left-color:#f59e0b; background:linear-gradient(135deg,#fffbeb,#fff); }
.dose-card.state-future { border-left-color:#3b82f6; background:#fafafa; }
.dose-card.state-past  { border-left-color:#d1d5db; opacity:.55; }
.dose-card.dose-hidden { display:none !important; }

@keyframes pulseCard { 0%,100%{box-shadow:0 4px 14px rgba(239,68,68,.15);} 50%{box-shadow:0 4px 24px rgba(239,68,68,.35);} }
@keyframes pulseDot  { 0%,100%{transform:scale(1);opacity:1;} 50%{transform:scale(1.3);opacity:.7;} }

.dose-time-col { display:flex; flex-direction:column; align-items:center; gap:2px; min-width:58px; flex-shrink:0; }
.dose-time { font-size:22px; font-weight:900; color:#1f4e5f; }
.dose-badge { font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; white-space:nowrap; margin-top:2px; }
.badge-now    { background:#fee2e2; color:#991b1b; animation:pulseDot 1.5s infinite; }
.badge-soon   { background:#fef3c7; color:#92400e; }
.badge-future { background:#dbeafe; color:#1e40af; }
.badge-past   { background:#f3f4f6; color:#6b7280; }

.dose-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0; }
.dose-body { flex:1; min-width:0; }
.dose-name { font-size:15px; font-weight:bold; color:#111827; margin-bottom:4px; }
.dose-meta { display:flex; gap:10px; flex-wrap:wrap; font-size:12px; color:#6b7280; }
.dose-countdown { font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; flex-shrink:0; text-align:right; min-width:80px; }
.cd-now    { background:#fee2e2; color:#991b1b; }
.cd-soon   { background:#fef3c7; color:#92400e; }
.cd-future { background:#dbeafe; color:#1e40af; }
.cd-past   { background:#f3f4f6; color:#9ca3af; }

.empty-state { text-align:center; padding:50px 20px; color:#9ca3af; }
.empty-state .icon { font-size:44px; margin-bottom:12px; }
.empty-state p { font-size:14px; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:20px; padding:32px 28px; width:360px; box-shadow:0 20px 60px rgba(0,0,0,.2); text-align:center; animation:modalIn .2s ease; }
@keyframes modalIn{from{transform:scale(.9);opacity:0;}to{transform:scale(1);opacity:1;}}
.modal-icon { font-size:44px; margin-bottom:14px; }
.modal-box h3 { font-size:17px; color:#1a202c; margin-bottom:8px; font-weight:700; }
.modal-box p  { font-size:13px; color:#718096; margin-bottom:24px; line-height:1.7; }
.modal-actions { display:flex; gap:10px; justify-content:center; }
.btn-confirm-del { padding:10px 24px; background:#ef4444; color:white; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-confirm-del:hover { background:#dc2626; }
.btn-cancel-del  { padding:10px 24px; background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
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

<?php if($isDoctor): /* ═══════════ DOCTOR VIEW ═══════════ */ ?>

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

    <!-- DOCTOR: Messages -->
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
                    <a href="chat.php?user=<?= urlencode($m['patient_email']) ?>" class="msg-sender-name">
                        <?= htmlspecialchars(($m['fname']??'')." ".($m['lname']??'')) ?>
                    </a>
                    <span class="msg-sender-sub">📧 <?= htmlspecialchars($m['patient_email']) ?></span>
                </div>
                <?php if($isUnread): ?><div class="unread-dot"></div><?php endif; ?>
                <button class="btn-del-msg" onclick="askDelete(<?= $m['id'] ?>)">🗑️ Delete</button>
            </div>
            <div class="msg-body"><div class="msg-text"><?= htmlspecialchars($m['message']) ?></div></div>
            <div class="msg-time"><span>🕒</span><span><?= $ts ?></span></div>
        </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- DOCTOR: Planning -->
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

    <!-- DOCTOR: RDV -->
    <div class="tab-content" id="tab-rdv">
        <?php if($rdvSoon_res->num_rows>0): ?>
        <div class="alert-soon"><span style="font-size:28px;">⚠️</span>
        <div><div style="font-weight:bold;color:#c2410c;font-size:14px;">Patient appointment in less than 1 hour!</div>
        <div style="font-size:12px;color:#9a3412;">Please be ready to receive your patient.</div></div></div>
        <?php endif;
        if($rdvPending_res->num_rows>0): ?>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;font-size:13px;color:#1e40af;font-weight:600;">
            📋 <?= $rdvPending_res->num_rows ?> pending appointment(s) waiting for your confirmation.
        </div>
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
                <a href="chat.php?user=<?= urlencode($patEmail) ?>" class="rdv-name">
                    <?= htmlspecialchars($rdv['fname']." ".$rdv['lname']) ?>
                </a>
                <div class="rdv-meta">
                    <span><?= $rdvToday?'📅 Today':"📅 $rdvDate" ?></span>
                    <span>⏰ <?= $rdvTime ?></span>
                    <?php if(!empty($rdv['reason'])): ?><span>📝 <?= htmlspecialchars(mb_substr($rdv['reason'],0,40)) ?></span><?php endif; ?>
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

<?php else: /* ═══════════ PATIENT VIEW ═══════════ */ ?>

    <div class="tabs">
        <!-- Messages tab -->
        <button class="tab-btn active" onclick="switchTab('messages', this)">
            💬 Doctor Messages
            <?php if($unreadCount > 0): ?>
                <span class="tab-count"><?= $unreadCount ?></span>
            <?php elseif($totalMessages > 0): ?>
                <span class="tab-count" style="background:#6b7280;"><?= $totalMessages ?></span>
            <?php endif; ?>
        </button>

        <!-- Medications tab -->
        <button class="tab-btn" onclick="switchTab('medications', this)">
            💊 Today's Medications
            <?php
            $nowCount = 0;
            [$nh2,$nm2,$ns2] = explode(':', $nowTime);
            $nowSec2 = (int)$nh2*3600 + (int)$nm2*60 + (int)$ns2;
            foreach($allDosesData as $d2){
                $diff2 = $d2['doseSec'] - $nowSec2;
                if($diff2 >= 0 && $diff2 < 60) $nowCount++;
            }
            if($nowCount > 0): ?>
                <span class="tab-count" style="background:#ef4444;">🔴 <?= $nowCount ?></span>
            <?php elseif(count($allDosesData) > 0): ?>
                <span class="tab-count" style="background:#6b7280;"><?= count($allDosesData) ?></span>
            <?php endif; ?>
        </button>

        <!-- Appointments tab -->
        <button class="tab-btn" onclick="switchTab('appointments', this)"
            <?= ($rdvSoon_res->num_rows > 0 || $confirmedCount > 0) ? 'style="color:#1f4e5f;font-weight:800;"' : '' ?>>
            📆 Appointments
            <?php if($rdvSoon_res->num_rows > 0): ?>
                <span class="tab-count">⚠️ <?= $rdvSoon_res->num_rows ?></span>
            <?php elseif($confirmedCount > 0): ?>
                <span class="tab-count" style="background:#10b981;">✅ <?= $confirmedCount ?></span>
            <?php elseif($rdvAll_res->num_rows > 0): ?>
                <span class="tab-count" style="background:#1f4e5f;"><?= $rdvAll_res->num_rows ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- PATIENT: Messages -->
    <div class="tab-content active" id="tab-messages">
        <?php if(!$msg_res || $msg_res->num_rows === 0): ?>
        <div class="empty-state"><div class="icon">💬</div><p>No messages from your doctors yet.</p></div>
        <?php else:
        $msg_res->data_seek(0);
        while($m = $msg_res->fetch_assoc()):
            $dp = explode(" ", ($m['fname']??'')." ".($m['lname']??''));
            $di = strtoupper(substr($dp[0],0,1).(isset($dp[1])?substr($dp[1],0,1):""));
            $ts = date("d M Y, H:i", strtotime($m['created_at']));
            $isUnread = ($m['is_read'] == 0);
        ?>
        <div class="msg-card <?= $isUnread?'unread':'' ?>" id="msg-<?= $m['id'] ?>">
            <div class="msg-header">
                <div class="msg-avatar"><?= $di ?></div>
                <div style="flex:1;min-width:0;">
                    <a href="chat.php?user=<?= urlencode($m['doctor_email']) ?>" class="msg-sender-name">
                        Dr. <?= htmlspecialchars(($m['fname']??'')." ".($m['lname']??'')) ?>
                    </a>
                    <span class="msg-sender-sub">📧 <?= htmlspecialchars($m['doctor_email']) ?></span>
                </div>
                <?php if($isUnread): ?><div class="unread-dot"></div><?php endif; ?>
                <button class="btn-del-msg" onclick="askDelete(<?= $m['id'] ?>)">🗑️ Delete</button>
            </div>
            <div class="msg-body"><div class="msg-text"><?= htmlspecialchars($m['message']) ?></div></div>
            <div class="msg-time"><span>🕒</span><span><?= $ts ?></span></div>
        </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- PATIENT: Medications -->
    <div class="tab-content" id="tab-medications">

        <div class="live-clock">
            <span style="font-size:24px;">🕐</span>
            <div>
                <div class="live-clock-time" id="liveClock">--:--:--</div>
                <div class="live-clock-label">Real-time medication schedule for today</div>
            </div>
        </div>

        <?php if(empty($allDosesData)): ?>
        <div class="empty-state" id="medEmpty">
            <div class="icon">💊</div>
            <p>No medications scheduled for today.</p>
        </div>
        <?php else: ?>

        <div id="sec-now" class="med-section-header" style="display:none;">🔴 Take Now</div>
        <div id="sec-soon" class="med-section-header" style="display:none;">⏳ Coming Up (within 1 hour)</div>
        <div id="sec-future" class="med-section-header" style="display:none;">📅 Later Today</div>
        <div id="sec-past" class="med-section-header" style="display:none;">✅ Earlier Today</div>

        <?php foreach($allDosesData as $d):
            $uid = htmlspecialchars($d['uid']);
        ?>
        <div class="dose-card state-future"
             id="dosecard-<?= $uid ?>"
             data-uid="<?= $uid ?>"
             data-dosesec="<?= $d['doseSec'] ?>">
            <div class="dose-time-col">
                <div class="dose-time"><?= $d['hhmm'] ?></div>
                <span class="dose-badge badge-future" id="badge-<?= $uid ?>">📅</span>
            </div>
            <div class="dose-icon" id="icon-<?= $uid ?>" style="background:#dbeafe;">💊</div>
            <div class="dose-body">
                <div class="dose-name"><?= htmlspecialchars($d['name']) ?></div>
                <div class="dose-meta">
                    <span>💉 <?= htmlspecialchars($d['dosage']) ?></span>
                    <span>⏳ <?= $d['daysLeft'] ?> days left</span>
                </div>
            </div>
            <div class="dose-countdown cd-future" id="cd-<?= $uid ?>">--</div>
        </div>
        <?php endforeach; ?>

        <div class="empty-state dose-hidden" id="medEmpty">
            <div class="icon">💊</div><p>No medications for today.</p>
        </div>

        <?php endif; ?>
    </div>

    <!-- PATIENT: Appointments -->
    <div class="tab-content" id="tab-appointments">
        <?php
        $rdvConfirmed_res->data_seek(0);
        while($rc = $rdvConfirmed_res->fetch_assoc()):
            $rcDate  = date("d M Y", strtotime($rc['date']));
            $rcTime  = date("H:i",   strtotime($rc['time']));
            $rcToday = ($rc['date'] === $today);
        ?>
        <div class="alert-confirmed">
            <span style="font-size:28px;">✅</span>
            <div style="flex:1;">
                <div style="font-weight:bold;color:#065f46;font-size:14px;">
                    Appointment confirmed by Dr. <?= htmlspecialchars($rc['fname']." ".$rc['lname']) ?>!
                </div>
                <div style="font-size:12px;color:#047857;margin-top:3px;">
                    📅 <?= $rcToday ? 'Today' : $rcDate ?> &nbsp;·&nbsp; ⏰ <?= $rcTime ?>
                </div>
            </div>
        </div>
        <?php endwhile;

        if($rdvSoon_res->num_rows > 0): ?>
        <div class="alert-soon">
            <span style="font-size:28px;">⚠️</span>
            <div>
                <div style="font-weight:bold;color:#c2410c;font-size:14px;">Appointment in less than 1 hour!</div>
                <div style="font-size:12px;color:#9a3412;">Please prepare and be on time.</div>
            </div>
        </div>
        <?php endif;

        $rdvAll_res->data_seek(0);
        if($rdvAll_res->num_rows === 0): ?>
        <div class="empty-state"><div class="icon">📆</div><p>No upcoming appointments.</p></div>
        <?php endif;
        while($rdv = $rdvAll_res->fetch_assoc()):
            $dp2      = explode(" ", $rdv['fname']." ".$rdv['lname']);
            $di2      = strtoupper(substr($dp2[0],0,1).(isset($dp2[1])?substr($dp2[1],0,1):""));
            $rdvDate  = date("d M Y", strtotime($rdv['date']));
            $rdvTime  = date("H:i",   strtotime($rdv['time']));
            $rdvToday = ($rdv['date'] === $today);
            $diff     = strtotime($rdv['date']." ".$rdv['time']) - time();
            $isSoon   = $rdvToday && $diff >= 0 && $diff <= 3600;
            $border   = $isSoon ? "#fb923c" : ($rdv['status']==='confirmed' ? "#10b981" : "#2c7a7b");
        ?>
        <div class="rdv-card" style="border-left:4px solid <?= $border ?>;">
            <div class="rdv-avatar"><?= $di2 ?></div>
            <div class="rdv-body">
                <a href="chat.php?user=<?= urlencode($rdv['doctor_email']) ?>" class="rdv-name">
                    Dr. <?= htmlspecialchars($rdv['fname']." ".$rdv['lname']) ?>
                </a>
                <div class="rdv-meta">
                    <span><?= $rdvToday ? '📅 Today' : "📅 $rdvDate" ?></span>
                    <span>⏰ <?= $rdvTime ?></span>
                    <?php if(!empty($rdv['reason'])): ?>
                    <span>📝 <?= htmlspecialchars(mb_substr($rdv['reason'],0,35)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rdv-actions">
                <span class="status-badge <?= $rdv['status']==='confirmed'?'status-confirmed':'status-pending' ?>"><?= $rdv['status']==='confirmed'?'✅ Confirmed':'⏳ Pending' ?></span>
                <?php if($isSoon): ?><span style="background:#fff7ed;color:#c2410c;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:bold;">⚠️ &lt;1h</span><?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

<?php endif; /* end role check */ ?>

</div>

<!-- ══ DELETE MODAL ══ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">🗑️</div>
        <h3>Delete this message?</h3>
        <p>This message will be removed <b>only on your side</b>.<br>The other person can still see it.</p>
        <div class="modal-actions">
            <button class="btn-confirm-del" onclick="confirmDelete()">Yes, delete</button>
            <button class="btn-cancel-del"  onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
const IS_DOCTOR = <?= $isDoctor ? 'true' : 'false' ?>;
<?php if(!$isDoctor): ?>
const DOSES = <?= json_encode(array_values($allDosesData)) ?>;
<?php endif; ?>

/* ══ TABS ══ */
function switchTab(name, btn){
    document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById("tab-"+name).classList.add("active");
    if(name === 'medications') updateAllDoses();
}

/* ══ DELETE MESSAGE ══ */
let pendingId = null;
function askDelete(id){ pendingId=id; document.getElementById("deleteModal").classList.add("open"); }
function closeModal(){ document.getElementById("deleteModal").classList.remove("open"); pendingId=null; }
function confirmDelete(){
    if(!pendingId) return;
    const id = pendingId; closeModal();
    fetch("delete_message.php",{
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
                    const tabId = IS_DOCTOR ? "tab-messages" : "tab-messages";
                    const emptyMsg = IS_DOCTOR
                        ? '<div class="empty-state"><div class="icon">💬</div><p>No messages from your patients yet.</p></div>'
                        : '<div class="empty-state"><div class="icon">💬</div><p>No messages from your doctors yet.</p></div>';
                    if(!document.querySelectorAll("#tab-messages .msg-card").length){
                        document.getElementById("tab-messages").innerHTML = emptyMsg;
                    }
                },350);
            }
        } else { alert("Error: "+(res.error||"Cannot delete.")); }
    });
}

/* ══ MARK ALL / CLEAR ALL ══ */
function clearAllNotif(){
    /* 1. Clear message cards */
    document.getElementById("tab-messages").innerHTML =
        '<div class="empty-state"><div class="icon">💬</div><p>No messages.</p></div>';

    if(IS_DOCTOR){
        /* Doctor: also clear planning and rdv tabs */
        document.getElementById("tab-planning").innerHTML =
            '<div class="empty-state"><div class="icon">📅</div><p>No schedule.</p></div>';
        document.getElementById("tab-rdv").innerHTML =
            '<div class="empty-state"><div class="icon">📆</div><p>No appointments.</p></div>';
    } else {
        /* Patient: hide PAST dose cards only, keep future/now/soon visible */
        document.querySelectorAll('.dose-card.state-past').forEach(c => {
            c.classList.add('dose-hidden');
        });
        checkMedEmpty();
    }

    /* Remove badge counts */
    document.querySelectorAll(".tab-count").forEach(el => el.remove());

    /* Hide sidebar dots immediately */
    ['notifDot','apptDot','msgBadge'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.style.display = 'none';
    });

    /* Call backend */
    fetch("clear_notifications.php", {method:"POST"});
}

document.addEventListener("keydown", e => { if(e.key==="Escape") closeModal(); });

/* ══ MEDICATION REAL-TIME LOGIC (patient only) ══ */
<?php if(!$isDoctor): ?>

function getNowSec(){
    const n = new Date();
    return n.getHours()*3600 + n.getMinutes()*60 + n.getSeconds();
}

function updateClock(){
    const n = new Date();
    const pad = x => String(x).padStart(2,'0');
    const el = document.getElementById('liveClock');
    if(el) el.textContent = `${pad(n.getHours())}:${pad(n.getMinutes())}:${pad(n.getSeconds())}`;

    /* At midnight: hide all past cards */
    if(n.getHours()===0 && n.getMinutes()===0 && n.getSeconds()===0){
        document.querySelectorAll('.dose-card.state-past').forEach(c => c.classList.add('dose-hidden'));
        checkMedEmpty();
    }
}
setInterval(updateClock, 1000);
updateClock();

function updateAllDoses(){
    if(typeof DOSES === 'undefined' || !DOSES.length) return;
    const nowSec = getNowSec();
    const container = document.getElementById('tab-medications');
    if(!container) return;

    let hasNow=false, hasSoon=false, hasFuture=false, hasPast=false;

    DOSES.forEach(d => {
        const uid    = d.uid;
        const card   = document.getElementById('dosecard-'+uid);
        const badge  = document.getElementById('badge-'+uid);
        const cdEl   = document.getElementById('cd-'+uid);
        const iconEl = document.getElementById('icon-'+uid);
        if(!card) return;

        /* Skip manually hidden cards (cleared by "mark all as read") */
        if(card.classList.contains('dose-hidden')) return;

        const diff = d.doseSec - nowSec;

        card.classList.remove('state-now','state-soon','state-future','state-past');

        if(diff >= 0 && diff < 60){
            card.classList.add('state-now');
            if(badge){ badge.className='dose-badge badge-now'; badge.textContent='🔴 NOW'; }
            if(iconEl){ iconEl.style.background='#fee2e2'; }
            if(cdEl){ cdEl.className='dose-countdown cd-now'; cdEl.textContent='⚡ Now!'; }
            hasNow = true;

        } else if(diff > 0 && diff <= 3600){
            card.classList.add('state-soon');
            const mins = Math.ceil(diff/60);
            if(badge){ badge.className='dose-badge badge-soon'; badge.textContent=`⏰ ${mins}min`; }
            if(iconEl){ iconEl.style.background='#fef3c7'; }
            if(cdEl){ cdEl.className='dose-countdown cd-soon'; cdEl.textContent=`In ${mins} min`; }
            hasSoon = true;

        } else if(diff > 3600){
            card.classList.add('state-future');
            const hrs  = Math.floor(diff/3600);
            const mins = Math.floor((diff%3600)/60);
            if(badge){ badge.className='dose-badge badge-future'; badge.textContent=`📅 ${d.hhmm}`; }
            if(iconEl){ iconEl.style.background='#dbeafe'; }
            if(cdEl){ cdEl.className='dose-countdown cd-future'; cdEl.textContent = hrs>0 ? `In ${hrs}h ${mins}m` : `In ${mins} min`; }
            hasFuture = true;

        } else {
            card.classList.add('state-past');
            const ago  = Math.abs(diff);
            const hrs  = Math.floor(ago/3600);
            const mins = Math.floor((ago%3600)/60);
            if(badge){ badge.className='dose-badge badge-past'; badge.textContent='✅ Done'; }
            if(iconEl){ iconEl.style.background='#f3f4f6'; }
            if(cdEl){ cdEl.className='dose-countdown cd-past'; cdEl.textContent = hrs>0 ? `${hrs}h ${mins}m ago` : `${mins} min ago`; }
            hasPast = true;
        }
    });

    /* Section headers */
    const sec = id => document.getElementById(id);
    if(sec('sec-now'))    sec('sec-now').style.display    = hasNow    ? '' : 'none';
    if(sec('sec-soon'))   sec('sec-soon').style.display   = hasSoon   ? '' : 'none';
    if(sec('sec-future')) sec('sec-future').style.display = hasFuture ? '' : 'none';
    if(sec('sec-past'))   sec('sec-past').style.display   = hasPast   ? '' : 'none';

    reorderDoseCards();
}

function reorderDoseCards(){
    const container = document.getElementById('tab-medications');
    if(!container) return;

    const order      = ['sec-now','sec-soon','sec-future','sec-past'];
    const stateOrder = ['state-now','state-soon','state-future','state-past'];

    order.forEach((secId, idx) => {
        const sec = document.getElementById(secId);
        if(!sec) return;
        container.appendChild(sec);
        DOSES.forEach(d => {
            const card = document.getElementById('dosecard-'+d.uid);
            if(card && card.classList.contains(stateOrder[idx]) && !card.classList.contains('dose-hidden')){
                container.appendChild(card);
            }
        });
    });

    const empty = document.getElementById('medEmpty');
    if(empty) container.appendChild(empty);
    checkMedEmpty();
}

function checkMedEmpty(){
    const empty = document.getElementById('medEmpty');
    if(!empty) return;
    const visible = document.querySelectorAll('.dose-card:not(.dose-hidden)');
    if(visible.length === 0){
        empty.classList.remove('dose-hidden');
    } else {
        empty.classList.add('dose-hidden');
    }
}

/* Run immediately and every 10 seconds */
updateAllDoses();
setInterval(updateAllDoses, 10000);

<?php endif; /* end patient JS */ ?>
</script>
</body>
</html>