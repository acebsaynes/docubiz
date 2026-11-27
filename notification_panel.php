<link rel="stylesheet" href="notification_panel.css?v=<?= filemtime(__DIR__.'/notification_panel.css') ?>">
<section id="dbNotifications" class="db-notifications" hidden aria-label="Notifications" role="region">
 <header class="nt-header"><h2>Notification</h2><button type="button" id="ntClose" aria-label="Close notifications">×</button></header>
 <div class="nt-toolbar"><div role="group" aria-label="Notification filter"><button type="button" id="ntAll" aria-pressed="true">All Notifications</button><button type="button" id="ntUnread" aria-pressed="false">Unread (0)</button></div><button type="button" id="ntMarkAll">Mark all as read</button></div>
 <div id="ntStatus" role="status" aria-live="polite"></div>
 <div id="ntList" class="nt-list"></div>
</section>
<script src="notification_panel.js?v=<?= filemtime(__DIR__.'/notification_panel.js') ?>" defer></script>
