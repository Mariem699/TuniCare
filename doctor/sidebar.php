<?php
if(session_status() === PHP_SESSION_NONE) session_start();

$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$conn = new mysqli("localhost","root","","tunicare");
$email = $_SESSION['email'] ?? "";

/*  Detect role  */ 
$isDoctor = false;
if($email){
    $chk = $conn->prepare("SELECT 1 FROM doctors WHERE email=?");
    $chk->bind_param("s",$email);
    $chk->execute();
    $chk->store_result();
    $isDoctor = ($chk->num_rows > 0);
}

/*  Unread messages  */ 
$unreadMessages = 0;
if($email){
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_email=? AND is_read=0");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $unreadMessages = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

/*  Pending appointments (doctor side)  */ 
$pendingAppts = 0;
if($isDoctor && $email){
    $stmt2 = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_email=? AND status='pending' AND date>=CURDATE()");
    $stmt2->bind_param("s",$email);
    $stmt2->execute();
    $pendingAppts = (int)($stmt2->get_result()->fetch_assoc()['c'] ?? 0);
}

/*  Confirmed appointments not yet seen (patient side)  */ 
$confirmedAppts = 0;
if(!$isDoctor && $email){
    $stmt3 = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE patient_email=? AND status='confirmed' AND patient_notified=0 AND date>=CURDATE()");
    $stmt3->bind_param("s",$email);
    $stmt3->execute();
    $confirmedAppts = (int)($stmt3->get_result()->fetch_assoc()['c'] ?? 0);
}

/*  Doctor notification dot: unread messages OR pending appointments  */ 
$showDoctorNotifDot = $isDoctor && ($unreadMessages > 0 || $pendingAppts > 0);

/*  Patient notification dot: unread messages OR confirmed appointments not seen  */ 
$showPatientNotifDot = !$isDoctor && ($unreadMessages > 0 || $confirmedAppts > 0);
?>
<style>
.sidebar{
    width:15%;background:#1f4e5f;color:white;padding:20px;
    min-height:100vh;flex-shrink:0;display:flex;flex-direction:column;
}
.sidebar h2{ padding-top:20px;margin-bottom:20px;font-size:30px; }
.sidebar a{
    position:relative;display:flex;align-items:center;gap:6px;
    color:white;text-decoration:none;padding:10px 12px;margin:6px 0;
    border-radius:8px;font-size:18px;transition:background .2s,transform .2s;
}
.sidebar a:hover{ background:#2c7a7b88; }
.sidebar a.active{
    background:#2c7a7b;font-weight:bold;
    transform:translateX(5px);box-shadow:0 3px 8px rgba(0,0,0,.2);
}
.sidebar .logout{ margin-top:auto; }
.notif-dot{
    display:inline-block;width:10px;height:10px;
    background:#ef4444;border-radius:50%;flex-shrink:0;
    box-shadow:0 0 6px rgba(239,68,68,.8);
    animation:pulseDot 1.5s infinite;
}
.msg-badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 5px;
    font-size:11px;font-weight:700;border-radius:50px;
    background:#ef4444;color:#fff;
    box-shadow:0 0 8px rgba(239,68,68,.6);
    animation:pulseDot 1.5s infinite;flex-shrink:0;
}
@keyframes pulseDot{
    0%,100%{transform:scale(1);opacity:1;}
    50%{transform:scale(1.3);opacity:.7;}
}
</style>

<div class="sidebar" id="mainSidebar">
    <h2>🏥 Tunicare</h2>

<?php if($isDoctor): ?>

    <a href="homedoctor.php" class="<?= $currentPage==='homedoctor.php' ?'active':'' ?>">🏠 Home</a>

    <a href="notification.php" class="<?= $currentPage==='notification.php' ?'active':'' ?>">
        🔔 Notifications
        <span class="notif-dot" id="notifDot"
              style="display:<?= $showDoctorNotifDot ? 'inline-block' : 'none' ?>;"></span>
    </a>

    <a href="profild.php"  class="<?= $currentPage==='profild.php'  ?'active':'' ?>">👤 Profile</a>
    <a href="patients.php" class="<?= $currentPage==='patients.php' ?'active':'' ?>">🧑 Patients</a>
    <a href="schedule.php" class="<?= $currentPage==='schedule.php' ?'active':'' ?>">📅 Planning</a>

    <a href="../chat.php" class="<?= $currentPage==='chat.php' ?'active':'' ?>">
        💬 Chat
        <span class="msg-badge" id="msgBadge"
              style="display:<?= ($unreadMessages>0)?'inline-flex':'none' ?>;"><?= $unreadMessages ?></span>
    </a>

<?php else: ?>

    <a href="homepatient.php" class="<?= $currentPage==='homepatient.php' ?'active':'' ?>">🏠 Home</a>

    <a href="notification.php" class="<?= $currentPage==='notification.php' ?'active':'' ?>">
        🔔 Notifications
        <span class="notif-dot" id="notifDot"
              style="display:<?= $showPatientNotifDot ? 'inline-block' : 'none' ?>;"></span>
    </a>

    <a href="profilp.php"            class="<?= $currentPage==='profilp.php'            ?'active':'' ?>">👤 Profile</a>
    <a href="medication_profile.php" class="<?= $currentPage==='medication_profile.php' ?'active':'' ?>">💊 Medication</a>
    <a href="history.php"            class="<?= $currentPage==='history.php'            ?'active':'' ?>">📅 History</a>

    <a href="rendezvous.php" class="<?= $currentPage==='rendezvous.php' ?'active':'' ?>">
        📆 Appointments
        <span class="notif-dot" id="apptDot"
              style="display:<?= ($confirmedAppts>0)?'inline-block':'none' ?>;"></span>
    </a>

    <a href="../chat.php" class="<?= $currentPage==='chat.php' ?'active':'' ?>">
        💬 Chat
        <span class="msg-badge" id="msgBadge"
              style="display:<?= ($unreadMessages>0)?'inline-flex':'none' ?>;"><?= $unreadMessages ?></span>
    </a>

<?php endif; ?>

    <a href="../logout.php" class="logout">🚪 Logout</a>
</div>

<script>
(function(){
    var onChat   = <?= ($currentPage==='chat.php') ? 'true' : 'false' ?>;
    var isDoctor = <?= $isDoctor ? 'true' : 'false' ?>;
    var onNotif  = <?= ($currentPage==='notification.php') ? 'true' : 'false' ?>;

    /*  Refresh message badge (chat icon)  */ 
    function refreshMsgBadge(){
        fetch('../get_unread.php')
        .then(r => r.json())
        .then(d => {
            var badge = document.getElementById('msgBadge');
            if(!badge) return;
            if(d.messages > 0 && !onChat){
                badge.textContent   = d.messages;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(()=>{});
    }

    /*  Refresh doctor notification dot (pending appts + unread messages)  */ 
    function refreshDoctorNotifDot(){
        if(!isDoctor) return;

        Promise.all([
            fetch('../get_unread.php').then(r=>r.json()).catch(()=>({messages:0})),
            fetch('../get_pending_appts.php').then(r=>r.json()).catch(()=>({pending:0}))
        ]).then(([unreadData, pendingData]) => {
            var dot = document.getElementById('notifDot');
            if(!dot) return;
            var hasNotif = (unreadData.messages > 0) || (pendingData.pending > 0);
            /* Don't show dot when user is already on notification page */
            dot.style.display = (hasNotif && !onNotif) ? 'inline-block' : 'none';
        });
    }

    /*  Refresh patient notification dot (unread messages + confirmed appts)  */ 
    function refreshPatientNotifDot(){
        if(isDoctor) return;

        Promise.all([
            fetch('../get_unread.php').then(r=>r.json()).catch(()=>({messages:0})),
            fetch('../get_confirmed_appts.php').then(r=>r.json()).catch(()=>({confirmed:0}))
        ]).then(([unreadData, confirmedData]) => {
            var dot = document.getElementById('notifDot');
            if(dot){
                var hasNotif = (unreadData.messages > 0) || (confirmedData.confirmed > 0);
                dot.style.display = (hasNotif && !onNotif) ? 'inline-block' : 'none';
            }

            /* Appointment dot */
            var apptDot = document.getElementById('apptDot');
            if(apptDot){
                apptDot.style.display = (confirmedData.confirmed > 0) ? 'inline-block' : 'none';
            }
        });
    }

    
    refreshMsgBadge();
    if(isDoctor){
        refreshDoctorNotifDot();
    } else {
        refreshPatientNotifDot();
    }

    /*  Polling  */ 
    setInterval(refreshMsgBadge, 5000);
    if(isDoctor){
        setInterval(refreshDoctorNotifDot, 6000);
    } else {
        setInterval(refreshPatientNotifDot, 6000);
    }
})();
</script>