</div><!-- /main-content -->

<?php if (isset($_SESSION["user_id"]) && basename($_SERVER["PHP_SELF"]) !== "chatbot.php"): ?>
<style>.chat-fab{bottom:82px!important;}</style>
<button class="chat-fab" id="chatFab" type="button" aria-label="Open MediConnect Assistant">
  <i class="bi bi-chat-dots-fill"></i>
  <i class="bi bi-x-lg"></i>
</button>
<div class="chat-widget" id="chatWidget" aria-hidden="true">
  <div class="chat-head">
    <div class="chat-avatar"><i class="bi bi-robot"></i></div>
    <div style="flex:1;">
      <div style="font-weight:600;">MediConnect Assistant</div>
      <small style="color:var(--muted);"><span class="online-dot"></span> Online</small>
    </div>
    <a href="chatbot.php" title="Open full chat" style="color:var(--muted);font-size:14px;"><i class="bi bi-arrows-fullscreen"></i></a>
  </div>
  <div class="chat-log" id="wLog"></div>
  <form class="chat-form" id="wForm" autocomplete="off">
    <button type="button" class="chat-attach" title="Attach prescription (JPG, PNG, PDF)"><i class="bi bi-paperclip"></i></button>
    <input type="file" id="wFile" class="chat-file" accept=".jpg,.jpeg,.png,.pdf" hidden>
    <input type="text" id="wInput" class="form-control" placeholder="Ask about hospitals, medicines…">
    <button type="submit" class="chat-send" title="Send"><i class="bi bi-send-fill"></i></button>
  </form>
</div>
<?php endif; ?>

<?php if (isset($_SESSION["user_id"])): ?>
<script src="chatbot.js"></script>
<?php endif; ?>
</body>
</html>