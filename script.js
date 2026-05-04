function updateSidebarBadges() {
    const notifBadge = document.getElementById("notifBadge");
    const msgBadge = document.getElementById("msgBadge");


    const hasNotif = localStorage.getItem("hasNewNotification") === "true";
    if (notifBadge) notifBadge.style.display = hasNotif ? "inline-block" : "none";

    
    const hasMsg = localStorage.getItem("hasUnreadMessages") === "true";
    if (msgBadge) msgBadge.style.display = hasMsg ? "inline-block" : "none";
}

document.addEventListener("DOMContentLoaded", updateSidebarBadges);