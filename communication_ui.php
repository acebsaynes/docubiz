<link rel="stylesheet" href="communication.css?v=<?= filemtime(__DIR__.'/communication.css') ?>">
<div class="comm" data-page="<?= htmlspecialchars($communicationPage,ENT_QUOTES,'UTF-8') ?>">
<?php if($communicationPage!=='documents'): ?>
<div class="comm-composer">
 <nav class="comm-tabs" aria-label="Create an update"><button type="button" data-tab="message" class="active">MESSAGE</button><button type="button" data-tab="event">EVENT</button><button type="button" data-tab="poll">POLL</button><button type="button" data-tab="file">FILE</button></nav>
 <form class="comm-form"><div class="comm-message"><textarea data-draft-text placeholder="Type a message..." aria-label="Message" maxlength="10000"></textarea><div class="comm-tools"><button type="button" data-emoji>☺ Emoji</button><button type="button" data-media>▧ Media</button><button type="button" data-attach>♧ File</button></div><p class="comm-draft-note">Draft only — messages are not sent or saved. Use the File tab to upload documents.</p><input type="file" data-draft-files multiple hidden><div class="comm-draft-file-list"></div></div>
 <div class="comm-poll" hidden><label>Question<input name="question" placeholder="What's your poll about?" maxlength="500"></label><label>Options</label><div class="comm-options"></div><button type="button" data-add-option>+ Add option</button></div>
 <div class="comm-drop" hidden><button type="button" data-choose>⇧<br>Choose File or Drag &amp; Drop to Upload</button></div><input type="file" multiple class="comm-files" hidden><div class="comm-file-list"></div>
 <div class="comm-recipients"><span>To:</span><div class="comm-tags"><div class="comm-selected"></div><button type="button" data-recipients>+ Add more</button><div class="comm-picker" hidden></div></div></div>
 <div class="comm-actions"><button type="button" data-cancel>Cancel</button><button type="submit" class="comm-primary">Send</button></div></form>
</div>
<?php else: ?>
<div class="comm-composer"><form class="comm-form"><div class="comm-drop"><button type="button" data-choose>⇧<br>Choose File or Drag &amp; Drop to Upload</button></div><input type="file" multiple class="comm-files" hidden><div class="comm-file-list"></div><div class="comm-recipients"><span>To:</span><div class="comm-tags"><div class="comm-selected"></div><button type="button" data-recipients>+ Add more</button><div class="comm-picker" hidden></div></div></div><div class="comm-actions"><button type="button" data-cancel>Cancel</button><button type="submit" class="comm-primary">Send</button></div></form></div>
<?php endif; ?>
<p class="comm-status" role="status"></p>
<?php if($communicationPage==='messages'): ?><input class="comm-search" type="search" placeholder="Search polls and shared files..." aria-label="Search updates"><?php endif; ?>
<section class="comm-feed" aria-label="<?= $communicationPage==='documents'?'Uploaded documents':'Shared updates' ?>"></section>
</div>
<script src="emoji_keyboard.js?v=<?= filemtime(__DIR__.'/emoji_keyboard.js') ?>" defer></script>
<script src="communication.js?v=<?= filemtime(__DIR__.'/communication.js') ?>" defer></script>
