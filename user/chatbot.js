/* ============================================================
   user/chatbot.js — MediConnect Assistant (frontend)
   Shared logic consumed by:
     • user/chatbot.php  (full chat page)
     • floating chat widget injected via user/_footer.php
   Talks to user/chatbot_api.php (JSON) and renders bubbles,
   typing indicator and quick-reply action buttons.
============================================================ */
(function (window) {
  'use strict';

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = (s == null ? '' : String(s));
    return d.innerHTML;
  }

  /* Minimal safe inline markdown-ish rendering.
     Input is ALREADY HTML-escaped (escaping happens first), so we only
     re-map marker characters to tags — no user HTML can pass through. */
  function inline(t) {
    return String(t)
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');
  }

  function renderText(text) {
    var html = '';
    var list = null; /* buffer lines for a bulleted list */

    String(text).split('\n').forEach(function (line) {
      var l = line.trim();
      var isBullet = /^[-•*]\s+/.test(l);
      if (isBullet) {
        if (!list) list = [];
        list.push(inline(esc(l.replace(/^[-•*]\s+/, ''))));
        return;
      }
      if (list) {
        html += '<div class="msg-list"><ul>' +
                list.map(function (li) { return '<li>' + li + '</li>'; }).join('') +
                '</ul></div>';
        list = null;
      }
      var t = inline(esc(line));
      if (t) html += '<div class="msg-line">' + t + '</div>';
    });

    if (list) {
      html += '<div class="msg-list"><ul>' +
              list.map(function (li) { return '<li>' + li + '</li>'; }).join('') +
              '</ul></div>';
    }
    return html;
  }

  /* Build one chat instance bound to { form, input, log } ids. */
  function createChat(cfg) {
    var log   = document.getElementById(cfg.log);
    var input = document.getElementById(cfg.input);
    var form  = document.getElementById(cfg.form);
    if (!log || !input || !form) return null;

    var busy = false;

    function push(el) {
      log.appendChild(el);
      log.scrollTop = log.scrollHeight;
    }

    function say(text, who) {
      var m = document.createElement('div');
      m.className = 'msg ' + who;
      m.innerHTML = renderText(text);
      push(m);
      return m;
    }

    function typing() {
      var t = document.createElement('div');
      t.className = 'typing';
      t.innerHTML = '<span></span><span></span><span></span>';
      push(t);
      return t;
    }

    function quick(items) {
      if (!items || !items.length) return;
      var w = document.createElement('div');
      w.className = 'quick-wrap';
      items.forEach(function (q) {
        var a = document.createElement('a');
        a.className = 'quick-btn';
        a.href = q.url || '#';
        a.innerHTML = (q.icon ? '<i class="bi bi-' + esc(q.icon) + '"></i> ' : '') + esc(q.label);
        if (q.msg) {
          a.addEventListener('click', function (ev) {
            ev.preventDefault();
            send(String(q.msg));
          });
        }
        w.appendChild(a);
      });
      push(w);
    }

    async function send(text, file) {
      if (busy || (!text && !file)) return;
      if (text) say(text, 'user');
      else if (file) say('📎 ' + file.name, 'user');
      input.value = '';
      busy = true;
      var t = typing();
      try {
        var opts = { method: 'POST' };
        if (file) {
          var fd = new FormData();
          if (text) fd.append('message', text);
          fd.append('prescription', file);
          opts.body = fd; /* no Content-Type header — set automatically */
        } else {
          opts.headers = { 'Content-Type': 'application/json' };
          opts.body = JSON.stringify({ message: text });
        }
        var res = await fetch('chatbot_api.php', opts);
        var data = await res.json();
        if (!data || typeof data.reply !== 'string') throw new Error('bad response');
        t.remove();
        say(data.reply, 'bot');
        quick(data.quick);
      } catch (e) {
        t.remove();
        say("Sorry, I can't reach the assistant right now. Please try again in a moment.", 'bot');
      } finally {
        busy = false;
      }
    }

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      send(input.value.trim());
    });

    /* Attach button → pick a prescription file and upload it */
    var attachBtn = form.querySelector('.chat-attach');
    var fileInput = form.querySelector('.chat-file');
    if (attachBtn && fileInput) {
      attachBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        fileInput.click();
      });
      fileInput.addEventListener('change', function () {
        var f = fileInput.files[0];
        if (f) send('', f);
        fileInput.value = '';
      });
    }

    return { send: send };
  }

  window.MediChat = { createChat: createChat };

  /* ---- Full chat page (user/chatbot.php) ---- */
  var page = createChat({ log: 'chatLog', input: 'chatInput', form: 'chatForm' });
  if (page) page.send('hi');

  /* ---- Floating widget (user/_footer.php) ---- */
  var fab    = document.getElementById('chatFab');
  var widget = document.getElementById('chatWidget');
  if (fab && widget) {
    var greeted = false;
    var wchat   = null;
    fab.addEventListener('click', function () {
      var isOpen = widget.classList.toggle('open');
      fab.classList.toggle('open', isOpen);
      widget.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      if (isOpen && !greeted) {
        greeted = true;
        wchat = wchat || createChat({ log: 'wLog', input: 'wInput', form: 'wForm' });
        if (wchat) wchat.send('hi');
      }
    });
  }
})(window);