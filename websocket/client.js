/**
 * websocket/client.js
 *
 * Sertakan file ini secara inline di halaman PHP yang butuh WebSocket.
 * Cara embed:
 *   <script>
 *     const WS_URL  = 'ws://localhost:8080';
 *     const WS_ROLE = '<?= $_SESSION["user_role"] ?? "guest" ?>';
 *     const WS_USER = <?= $_SESSION["user_id"]   ?? 0 ?>;
 *   </script>
 *   <script src="/padud-coffee/websocket/client.js"></script>
 *
 * Events yang bisa didengarkan:
 *   window.addEventListener('ws:new_order',          e => { ... e.detail ... });
 *   window.addEventListener('ws:order_status',       e => { ... e.detail ... });
 *   window.addEventListener('ws:payment_confirmed',  e => { ... e.detail ... });
 *   window.addEventListener('ws:alert_15min',        e => { ... e.detail ... });
 */

(function () {
  'use strict';

  const WS_RECONNECT_DELAY = 3000; // ms
  const PING_INTERVAL      = 25000; // ms

  let ws          = null;
  let pingTimer   = null;
  let reconnTimer = null;
  let dead        = false;

  function connect() {
    if (dead) return;

    const url  = typeof WS_URL  !== 'undefined' ? WS_URL  : 'ws://localhost:8080';
    const role = typeof WS_ROLE !== 'undefined' ? WS_ROLE : 'guest';
    const uid  = typeof WS_USER !== 'undefined' ? WS_USER : 0;

    console.log('[WS] Connecting to', url);
    ws = new WebSocket(url);

    ws.onopen = function () {
      console.log('[WS] Connected');
      clearTimeout(reconnTimer);

      // Register role ke server
      ws.send(JSON.stringify({ event: 'register', role: role, user_id: uid }));

      // Ping periodik untuk keep-alive
      pingTimer = setInterval(function () {
        if (ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({ event: 'ping' }));
        }
      }, PING_INTERVAL);

      dispatchWsEvent('connected', {});
    };

    ws.onmessage = function (e) {
      let msg;
      try { msg = JSON.parse(e.data); } catch (_) { return; }

      const event = msg.event || 'unknown';
      console.log('[WS] Message:', event, msg.data);

      dispatchWsEvent(event, msg.data || msg);

      // Tampilkan toast jika ada title + message
      if (msg.data && msg.data.title && typeof window.wsShowToast === 'function') {
        window.wsShowToast(msg.data.title, msg.data.message || '');
      }
    };

    ws.onerror = function (err) {
      console.warn('[WS] Error — WebSocket server mungkin belum berjalan.', err);
    };

    ws.onclose = function () {
      console.warn('[WS] Disconnected. Reconnect dalam', WS_RECONNECT_DELAY / 1000, 'detik...');
      clearInterval(pingTimer);
      dispatchWsEvent('disconnected', {});

      if (!dead) {
        reconnTimer = setTimeout(connect, WS_RECONNECT_DELAY);
      }
    };
  }

  function dispatchWsEvent(name, detail) {
    window.dispatchEvent(new CustomEvent('ws:' + name, { detail: detail }));
  }

  // Public API
  window.wsClient = {
    connect: connect,
    disconnect: function () { dead = true; if (ws) ws.close(); },
    send: function (event, data) {
      if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ event: event, data: data }));
      }
    },
  };

  // Auto connect
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', connect);
  } else {
    connect();
  }

})();

/**
 * Default toast handler — override dengan mendefinisikan window.wsShowToast(title, msg)
 * di halaman masing-masing sebelum include file ini.
 */
if (typeof window.wsShowToast === 'undefined') {
  window.wsShowToast = function (title, message) {
    const toast = document.createElement('div');
    toast.style.cssText = [
      'position:fixed', 'bottom:24px', 'right:24px', 'z-index:99999',
      'background:#0B1F3B', 'color:#fff', 'padding:14px 20px', 'border-radius:12px',
      'max-width:320px', 'box-shadow:0 8px 24px rgba(0,0,0,.3)',
      'font-family:Poppins,sans-serif', 'font-size:.85rem',
      'animation:wsToastIn .3s ease',
    ].join(';');

    toast.innerHTML = '<strong style="display:block;margin-bottom:4px">' + title + '</strong>' + message;

    const style = document.createElement('style');
    style.textContent = '@keyframes wsToastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}';
    if (!document.getElementById('ws-toast-style')) {
      style.id = 'ws-toast-style';
      document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(function () {
      toast.style.transition = 'opacity .4s ease';
      toast.style.opacity = '0';
      setTimeout(function () { toast.remove(); }, 400);
    }, 4000);
  };
}
