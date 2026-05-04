<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error: " . $conn->connect_error);

$me = $_SESSION['email'] ?? "";
if(!$me){ header("Location: ../login.html"); exit(); }

$stmtRole = $conn->prepare("SELECT 1 FROM doctors WHERE email=?");
$stmtRole->bind_param("s",$me);
$stmtRole->execute();
$stmtRole->store_result();
$role = ($stmtRole->num_rows > 0) ? "doctor" : "patient";

$other = $_GET['user'] ?? "";
$conv  = null;

function normalizePair($a,$b){ return [min($a,$b), max($a,$b)]; }

if($other){
    [$u1,$u2] = normalizePair($me, $other);

    $stmt = $conn->prepare("SELECT id, user1, user2, deleted_by_user1, deleted_by_user2
                            FROM conversations WHERE user1=? AND user2=? LIMIT 1");
    $stmt->bind_param("ss",$u1,$u2);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if($row){
        $conv = $row['id'];
        if($u1 === $me && $row['deleted_by_user1'])
            $conn->query("UPDATE conversations SET deleted_by_user1=0 WHERE id=$conv");
        elseif($u2 === $me && $row['deleted_by_user2'])
            $conn->query("UPDATE conversations SET deleted_by_user2=0 WHERE id=$conv");
    } else {
        $stmt2 = $conn->prepare("INSERT INTO conversations (user1,user2,deleted_by_user1,deleted_by_user2) VALUES(?,?,0,0)");
        $stmt2->bind_param("ss",$u1,$u2);
        $stmt2->execute();
        $conv = $stmt2->insert_id;
    }
}

/*  OTHER USER NAME  */ 
$otherName = $other;
if($other){
    $s = $conn->prepare("SELECT fname,lname FROM patients WHERE email=?");
    $s->bind_param("s",$other); $s->execute();
    $r = $s->get_result();
    if($r->num_rows > 0){ $u=$r->fetch_assoc(); $otherName=$u['fname']." ".$u['lname']; }
    else {
        $s = $conn->prepare("SELECT fname,lname FROM doctors WHERE email=?");
        $s->bind_param("s",$other); $s->execute();
        $r = $s->get_result();
        if($r->num_rows > 0){ $u=$r->fetch_assoc(); $otherName=$u['fname']." ".$u['lname']; }
    }
}

/*  UNREAD COUNT (sidebar badge)  */ 
$stmtU = $conn->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_email=? AND is_read=0");
$stmtU->bind_param("s",$me);
$stmtU->execute();
$unread = $stmtU->get_result()->fetch_assoc()['c'] ?? 0;

/*  ALL USERS (search)  */ 
$allUsersArr = [];
$rAll = $conn->query("SELECT fname,lname,email,'patient' as role FROM patients
                      UNION SELECT fname,lname,email,'doctor' as role FROM doctors");
while($u = $rAll->fetch_assoc()){
    if($u['email'] === $me) continue;
    $allUsersArr[] = $u;
}

/*  CONVERSATIONS LIST  */ 
$meEsc = mysqli_real_escape_string($conn,$me);
$rConvs = $conn->query("
    SELECT c.id, c.user1, c.user2,
        COALESCE(
            (SELECT message FROM messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1),
            'New conversation'
        ) AS last_msg,
        COALESCE(
            (SELECT created_at FROM messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1),
            c.last_time
        ) AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE conversation_id=c.id AND receiver_email='$meEsc' AND is_read=0) AS uc
    FROM conversations c
    WHERE
        (c.user1='$meEsc' AND c.deleted_by_user1=0)
        OR
        (c.user2='$meEsc' AND c.deleted_by_user2=0)
    ORDER BY last_time DESC
");

$currentPage = "chat.php"; /* forcé car ce fichier est à la racine */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat – Tunicare</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#eef2f7;display:flex;min-height:100vh;overflow:hidden}

/*  SIDEBAR  */ 
.sidebar{
    width:15%;background:#1f4e5f;color:white;padding:20px;
    min-height:100vh;flex-shrink:0;display:flex;flex-direction:column;
}
.sidebar h2{padding-top:20px;margin-bottom:20px;font-size:30px;}
.sidebar a{
    position:relative;display:flex;align-items:center;gap:6px;
    color:white;text-decoration:none;padding:10px 12px;margin:6px 0;
    border-radius:8px;font-size:18px;transition:background .2s,transform .2s;
}
.sidebar a:hover{background:#2c7a7b88;}
.sidebar a.active{
    background:#2c7a7b;font-weight:bold;
    transform:translateX(5px);box-shadow:0 3px 8px rgba(0,0,0,.2);
}
.sidebar .logout{margin-top:auto}

/* Sidebar badges */
.notif-dot{
    display:inline-block;width:10px;height:10px;
    background:#ef4444;border-radius:50%;flex-shrink:0;
    box-shadow:0 0 6px rgba(239,68,68,.8);
    animation:pulseDot 1.5s infinite;
}
.unread-badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:18px;height:18px;padding:0 5px;font-size:10px;font-weight:700;
    border-radius:50%;background:#ef4444;color:#fff;
    box-shadow:0 0 8px rgba(239,68,68,.6);animation:pulseDot 1.5s infinite;
}
@keyframes pulseDot{
    0%,100%{transform:scale(1);opacity:1;}
    50%{transform:scale(1.3);opacity:.7;}
}

/*  USERS PANEL  */ 
.users-panel{width:18%;background:#fff;height:100vh;display:flex;flex-direction:column;border-right:1px solid #e2e8f0;flex-shrink:0;}
.users-header{padding:16px 14px 12px;border-bottom:1px solid #e2e8f0;}
.users-header h3{font-size:30px;color:#1f4e5f;font-weight:700;margin-bottom:10px}
.btn-new-chat{background:#1f4e5f;color:white;border:none;padding:9px 14px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;width:100%;text-align:left;display:flex;align-items:center;gap:6px;transition:background .2s;}
.btn-new-chat:hover{background:#29b8aa;}
.search-wrap{display:none;flex-direction:column;gap:6px;background:#f8fafc;border-radius:10px;padding:10px;margin-top:8px;border:1px solid #e2e8f0;}
.search-wrap.open{display:flex}
.search-wrap input{width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;color:#111;background:#fff;outline:none;}
.search-wrap input:focus{border-color:#1f4e5f;box-shadow:0 0 0 3px rgba(31,78,95,.12)}
#searchResults{max-height:220px;overflow-y:auto}
.search-result-item{display:flex;align-items:center;gap:8px;padding:8px 6px;border-radius:8px;cursor:pointer;color:#1f4e5f;font-size:13px;text-decoration:none;transition:background .15s;}
.search-result-item:hover{background:#e8f4f8}
.s-avatar{width:32px;height:32px;border-radius:50%;background:#1f4e5f;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.s-info{flex:1;min-width:0}
.s-name{font-weight:600;font-size:13px;color:#1a202c}
.s-email{font-size:10px;color:#718096;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.badge{font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;flex-shrink:0}
.badge-patient{background:#d1fae5;color:#065f46}
.badge-doctor{background:#dbeafe;color:#1e40af}
.not-found-msg{text-align:center;padding:14px;color:#999;font-size:12px}
.convs-scroll{flex:1;overflow-y:auto;padding:6px 8px}
.divider-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;padding:10px 6px 4px;}
.conv-item{display:flex;align-items:center;gap:10px;text-decoration:none;padding:10px;border-radius:12px;color:#1a202c;transition:background .15s;margin-bottom:2px;}
.conv-item:hover{background:#f1f5f9}
.conv-item.active{background:#e8f4f8}
.conv-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#1f4e5f,#2c7a7b);color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;border:2px solid #e2e8f0;}
.conv-info{min-width:0;flex:1}
.conv-name{font-size:13.5px;font-weight:600;color:#1a202c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-preview{font-size:11.5px;color:#718096;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}

/*  Badge bleu par conversation non lue  */ 
.conv-unread{
    background:#3b82f6;color:white;border-radius:50%;font-size:10px;font-weight:700;
    min-width:18px;height:18px;display:flex;align-items:center;justify-content:center;
    padding:0 4px;flex-shrink:0;
    box-shadow:0 0 6px rgba(59,130,246,.5);
    animation:pulseDot 1.5s infinite;
}

/*  CHAT AREA  */ 
.chat{flex:1;display:flex;flex-direction:column;height:100vh;background:#eef2f7}
.chat-header{background:#fff;padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.hdr-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#1f4e5f,#2c7a7b);color:white;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;flex-shrink:0;}
.hdr-info{flex:1}
.hdr-name{font-size:15px;font-weight:700;color:#1f4e5f}
.btn-delete-conv{display:flex;align-items:center;gap:5px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:.2s;flex-shrink:0;}
.btn-delete-conv:hover{background:#ef4444;color:white;border-color:#ef4444}
.box{flex:1;overflow-y:auto;padding:20px 16px;display:flex;flex-direction:column;gap:6px;}
.box::-webkit-scrollbar{width:4px}
.box::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:4px}
.msg-wrap{display:flex;width:100%}
.msg-wrap.me{justify-content:flex-end}
.msg-wrap.other{justify-content:flex-start}
.msg{max-width:60%;padding:10px 14px 6px;border-radius:18px;font-size:14px;line-height:1.5;word-break:break-word;position:relative;}
.msg.me{background:#1f4e5f;color:white;border-bottom-right-radius:4px;box-shadow:0 2px 8px rgba(31,78,95,.25);}
.msg.other{background:#fff;color:#1a202c;border-bottom-left-radius:4px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.07);}
.msg-time{font-size:10px;opacity:.65;margin-top:4px;display:block;text-align:right;}
.msg.other .msg-time{text-align:left}
.date-sep{text-align:center;margin:12px 0 4px;font-size:11px;color:#94a3b8;font-weight:600;letter-spacing:.4px;}
.date-sep span{background:#e2e8f0;padding:3px 12px;border-radius:20px;}
.chat-form{padding:12px 16px;background:#fff;border-top:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;flex-shrink:0;}
.chat-form input{flex:1;padding:12px 16px;border:2px solid #e2e8f0;border-radius:24px;outline:none;font-size:14px;color:#1a202c;background:#f8fafc;transition:border-color .2s,background .2s;}
.chat-form input:focus{border-color:#1f4e5f;background:#fff;box-shadow:0 0 0 3px rgba(31,78,95,.1);}
.btn-send{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#2c7a7b,#1f4e5f);color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;transition:.2s;flex-shrink:0;box-shadow:0 2px 8px rgba(31,78,95,.3);}
.btn-send:hover{transform:scale(1.08);opacity:.9}
.empty-chat{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;gap:14px;}
.empty-chat .ec-icon{width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:36px;}
.empty-chat h3{font-size:16px;color:#64748b;font-weight:600}
.empty-chat p{font-size:13px;text-align:center;max-width:220px;line-height:1.6}

/*  DELETE MODAL  */ 
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:20px;padding:32px 28px;width:360px;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center;animation:modalIn .2s ease;}
@keyframes modalIn{from{transform:scale(.9);opacity:0;}to{transform:scale(1);opacity:1;}}
.modal-icon{font-size:44px;margin-bottom:14px}
.modal-box h3{font-size:17px;color:#1a202c;margin-bottom:8px;font-weight:700}
.modal-box p{font-size:13px;color:#718096;margin-bottom:24px;line-height:1.7}
.modal-actions{display:flex;gap:10px;justify-content:center}
.btn-confirm-del{padding:10px 24px;background:#ef4444;color:white;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;}
.btn-confirm-del:hover{background:#dc2626}
.btn-cancel-del{padding:10px 24px;background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;}
.btn-cancel-del:hover{background:#e2e8f0}
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
    <h2>🏥 Tunicare</h2>
    <?php if($role === "doctor"): ?>
        <a href="doctor/homedoctor.php">🏠 Home</a>
        <a href="doctor/notification.php">
            🔔 Notifications
            <span class="notif-dot" id="notifDot" style="display:<?= ($unread>0)?'inline-block':'none' ?>;"></span>
        </a>
        <a href="doctor/profild.php">👤 Profile</a>
        <a href="doctor/patients.php">🧑 Patients</a>
        <a href="doctor/schedule.php">📅 Schedule</a>
        <a href="chat.php" class="active">💬 Chat</a>
    <?php else: ?>
        <a href="patient/homepatient.php">🏠 Home</a>
        <a href="patient/notification.php">
            🔔 Notifications
            <span class="notif-dot" id="notifDot" style="display:<?= ($unread>0)?'inline-block':'none' ?>;"></span>
        </a>
        <a href="patient/profilp.php">👤 Profile</a>
        <a href="patient/medication_profile.php">💊 Medication</a>
        <a href="patient/history.php">📅 History</a>
        <a href="patient/rendezvous.php">📆 Appointments</a>
        <a href="chat.php" class="active">💬 Chat</a>
    <?php endif; ?>
    <a href="logout.php" class="logout">🚪 Logout</a>
</div>

<!-- ── USERS PANEL ── -->
<div class="users-panel">
    <div class="users-header">
        <h3>💬 Messages</h3>
        <button class="btn-new-chat" onclick="toggleSearch()">✏️ New conversation</button>
        <div class="search-wrap" id="searchWrap">
            <input type="text" id="searchInput" placeholder="🔍 Search name or email..."
                   oninput="searchUsers(this.value)" autocomplete="off">
            <div id="searchResults"></div>
        </div>
    </div>

    <div class="convs-scroll">
        <div class="divider-label">Recent</div>
        <?php if($rConvs && $rConvs->num_rows > 0):
            while($c = $rConvs->fetch_assoc()):
                $peer = ($c['user1'] === $me) ? $c['user2'] : $c['user1'];
                if(!$peer) continue;

                $pn = $peer;
                $rp = $conn->query("SELECT fname,lname FROM patients WHERE email='".mysqli_real_escape_string($conn,$peer)."'");
                if($rp && $rp->num_rows > 0){ $pp=$rp->fetch_assoc(); $pn=$pp['fname']." ".$pp['lname']; }
                else {
                    $rd = $conn->query("SELECT fname,lname FROM doctors WHERE email='".mysqli_real_escape_string($conn,$peer)."'");
                    if($rd && $rd->num_rows > 0){ $pd=$rd->fetch_assoc(); $pn=$pd['fname']." ".$pd['lname']; }
                }

                $pts  = explode(" ", $pn);
                $init = strtoupper(substr($pts[0],0,1).(isset($pts[1])?substr($pts[1],0,1):""));
                $active = ($peer === $other);
                $prev = htmlspecialchars(mb_substr($c['last_msg'],0,32)).(mb_strlen($c['last_msg'])>32?"…":"");
                $uc   = (int)$c['uc'];
        ?>
        <a href="chat.php?user=<?= urlencode($peer) ?>" class="conv-item <?= $active?'active':'' ?>">
            <div class="conv-avatar"><?= $init ?></div>
            <div class="conv-info">
                <div class="conv-name"><?= htmlspecialchars($pn) ?></div>
                <div class="conv-preview"><?= $prev ?></div>
            </div>
            <?php if($uc > 0): ?>
            <div class="conv-unread"><?= $uc ?></div>
            <?php endif; ?>
        </a>
        <?php endwhile;
        else: ?>
        <div style="color:#94a3b8;font-size:12px;padding:12px 6px;">No conversations yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── CHAT AREA ── -->
<div class="chat">
<?php if($other && $conv):
    $parts = explode(" ", trim($otherName));
    $init2 = strtoupper(substr($parts[0]??'',0,1).substr($parts[1]??'',0,1));
?>
    <div class="chat-header">
        <div class="hdr-avatar"><?= $init2 ?></div>
        <div class="hdr-info">
            <div class="hdr-name"><?= htmlspecialchars($otherName) ?></div>
        </div>
        <button class="btn-delete-conv" onclick="openDeleteModal()">🗑️ Delete</button>
    </div>

    <div id="box" class="box"></div>

    <div class="chat-form">
        <input type="text" id="m" placeholder="Write a message..." autocomplete="off">
        <button class="btn-send" id="sendBtn">➤</button>
    </div>

<?php else: ?>
    <div class="empty-chat">
        <div class="ec-icon">💬</div>
        <h3>Your messages</h3>
        <p>Select a conversation or start a new one.</p>
    </div>
<?php endif; ?>
</div>

<!-- ── DELETE MODAL ── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">🗑️</div>
        <h3>Delete conversation?</h3>
        <p>This conversation will be removed <b>only on your side</b>.<br>The other person can still see it.</p>
        <div class="modal-actions">
            <button class="btn-confirm-del" onclick="deleteConv()">Yes, delete</button>
            <button class="btn-cancel-del"  onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
const conv     = <?= $conv ? (int)$conv : 'null' ?>;
const allUsers = <?= json_encode($allUsersArr) ?>;

/*  LOAD MESSAGES  */ 
function load(){
    if(!conv) return;
    fetch("get_messages.php?conv=" + conv)
    .then(r => r.text())
    .then(html => {
        const box = document.getElementById("box");
        if(!box) return;
        const atBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 60;
        box.innerHTML = html;
        if(atBottom) box.scrollTop = box.scrollHeight;
        /* Rafraîchir les badges après lecture */
        refreshSidebarBadge();
        refreshConvBadges();
    });
}

/*  SEND  */ 
function sendMsg(){
    const input = document.getElementById("m");
    const msg   = input.value.trim();
    if(!msg || !conv) return;
    fetch("send.php", {
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"msg="+encodeURIComponent(msg)+"&conv="+conv
    }).then(()=>{ input.value=""; load(); setTimeout(()=>{ const b=document.getElementById("box"); if(b) b.scrollTop=b.scrollHeight; },100); });
}

/*  DELETE CONV  */ 
function openDeleteModal(){ document.getElementById("deleteModal").classList.add("open"); }
function closeDeleteModal(){ document.getElementById("deleteModal").classList.remove("open"); }
function deleteConv(){
    if(!conv) return;
    fetch("delete_conv.php",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"conv="+conv
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success) window.location.href = "chat.php";
        else{ alert("Error: "+(res.error||"Cannot delete.")); closeDeleteModal(); }
    });
}

/*  SEARCH  */ 
function toggleSearch(){
    const w = document.getElementById("searchWrap");
    w.classList.toggle("open");
    if(w.classList.contains("open")){
        document.getElementById("searchInput").focus();
        renderResults(allUsers);
    } else {
        document.getElementById("searchInput").value="";
        document.getElementById("searchResults").innerHTML="";
    }
}
function searchUsers(q){
    q = q.toLowerCase().trim();
    renderResults(!q ? allUsers : allUsers.filter(u=>(u.fname+" "+u.lname+" "+u.email).toLowerCase().includes(q)));
}
function renderResults(users){
    const c = document.getElementById("searchResults");
    if(!users.length){ c.innerHTML='<div class="not-found-msg">❌ No results</div>'; return; }
    c.innerHTML = users.map(u=>{
        const init = (u.fname[0]+(u.lname[0]||"")).toUpperCase();
        const bc   = u.role==="doctor"?"badge-doctor":"badge-patient";
        const bl   = u.role==="doctor"?"Doctor":"Patient";
        return `<a class="search-result-item" href="chat.php?user=${encodeURIComponent(u.email)}">
            <div class="s-avatar">${init}</div>
            <div class="s-info">
                <div class="s-name">${esc(u.fname+' '+u.lname)}</div>
                <div class="s-email">${esc(u.email)}</div>
            </div>
            <span class="badge ${bc}">${bl}</span>
        </a>`;
    }).join("");
}
function esc(s){ return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }

/*  SIDEBAR BADGE (point rouge Notifications)  */ 
function refreshSidebarBadge(){
    fetch("get_unread.php")
    .then(r => r.json())
    .then(d => {
        const dot = document.getElementById("notifDot");
        if(dot) dot.style.display = (d.messages > 0) ? 'inline-block' : 'none';
    })
    .catch(()=>{});
}

/*  CONV BADGES (bulles bleues par conversation)  */ 
function refreshConvBadges(){
    fetch("get_conv_unread.php")
    .then(r => r.json())
    .then(data => {
        document.querySelectorAll(".conv-item").forEach(item => {
            const href = item.getAttribute("href") || "";
            const match = href.match(/user=([^&]+)/);
            if(!match) return;
            const peer = decodeURIComponent(match[1]);
            const badge = item.querySelector(".conv-unread");
            const count = data[peer] || 0;
            if(count > 0){
                if(badge){ badge.textContent = count; badge.style.display = 'flex'; }
                else {
                    const div = document.createElement("div");
                    div.className = "conv-unread";
                    div.textContent = count;
                    item.appendChild(div);
                }
            } else {
                if(badge) badge.style.display = 'none';
            }
        });
    })
    .catch(()=>{});
}

/*  INIT  */ 
document.addEventListener("DOMContentLoaded",()=>{
    const input = document.getElementById("m");
    const btn   = document.getElementById("sendBtn");
    if(btn)   btn.addEventListener("click", sendMsg);
    if(input) input.addEventListener("keydown", e=>{ if(e.key==="Enter") sendMsg(); });
    if(conv){ load(); setInterval(load, 2000); }
    refreshSidebarBadge();
    refreshConvBadges();
    setInterval(refreshSidebarBadge, 5000);
    setInterval(refreshConvBadges, 3000);
});
document.addEventListener("keydown", e=>{ if(e.key==="Escape") closeDeleteModal(); });
</script>
</body>
</html>