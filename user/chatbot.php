<?php
require '_init.php';
require_login();

$pageTitle = 'Health Assistant';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>🤖 Health Assistant</h1>
    <p class="subtitle">Powered by generative AI — ask me anything about your health, and I'll handle bookings, medicines &amp; more.</p>
  </div>
  <a class="btn" href="dashboard.php">← Dashboard</a>
</div>

<div style="max-width:820px;">

  <div class="card chat-card">
    <div class="chat-head">
      <div class="chat-avatar"><i class="bi bi-robot"></i></div>
      <div style="flex:1;">
        <div style="font-weight:600;">MediConnect Assistant</div>
        <small style="color:var(--muted);"><span class="online-dot"></span> Online · AI-assisted</small>
      </div>
    </div>

    <div class="chat-log" id="chatLog"></div>

    <form class="chat-form" id="chatForm" autocomplete="off">
      <button type="button" class="chat-attach" title="Attach prescription (JPG, PNG, PDF)"><i class="bi bi-paperclip"></i></button>
      <input type="file" id="chatFile" class="chat-file" accept=".jpg,.jpeg,.png,.pdf" hidden>
      <input type="text" id="chatInput" class="form-control" placeholder="Type a message… e.g. find hospitals in Lagos">
      <button type="submit" class="chat-send" title="Send"><i class="bi bi-send-fill"></i></button>
    </form>
  </div>

</div>

<?php include '_footer.php'; ?>