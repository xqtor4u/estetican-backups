/**
 * Widget de chat con IA para el sitio público de WordPress (BL-042) — variante
 * "DOM ya presente" (el HTML del botón/panel viene pre-pegado en la página,
 * este script solo agrega el comportamiento).
 *
 * Se carga con un <script> de body vacío y la config en atributos data-*,
 * a propósito: el editor de WordPress usado en este sitio le aplica el
 * filtro de párrafos (wpautop) al contenido del campo JS ANTES de envolverlo
 * en <script>, así que cualquier JS inline con texto termina con <p>/</p>
 * insertados adentro y deja de ejecutar. Un <script src="..." data-x="..">
 * sin cuerpo no tiene texto que corromper — ver docs/tecnico/NOTAS_TECNICAS.md.
 *
 * Uso en el HTML de WordPress:
 *   <script src="https://app.estetican.org/assistant-widget-wp.js"
 *           data-api-base="https://app.estetican.org"
 *           data-token="EL_TOKEN_DEL_WIDGET" async></script>
 */
(function () {
  'use strict';
  var currentScript = document.currentScript;
  function init() {
    var apiBase = (currentScript.dataset.apiBase || '').replace(/\/$/, '');
    var token = currentScript.dataset.token || '';
    if (!apiBase || !token) {
      console.warn('[EsteticanAssistant] Falta data-api-base o data-token en el <script>.');
      return;
    }
    var SESSION_KEY = 'estetican_assistant_session';
    function getSessionUuid() {
      var existing = window.localStorage.getItem(SESSION_KEY);
      if (existing) return existing;
      var uuid = (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() :
        'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
          var r = (Math.random() * 16) | 0, v = c === 'x' ? r : (r & 0x3) | 0x8;
          return v.toString(16);
        });
      window.localStorage.setItem(SESSION_KEY, uuid);
      return uuid;
    }
    function apiFetch(path, options) {
      options = options || {};
      options.headers = Object.assign({ 'X-Widget-Token': token, 'Content-Type': 'application/json' }, options.headers || {});
      return fetch(apiBase + path, options).then(function (r) {
        return r.json().then(function (body) { return { ok: r.ok, body: body }; });
      });
    }
    function appendMessage(container, role, text) {
      var el = document.createElement('div');
      el.className = 'estetican-ai-msg ' + role;
      el.textContent = text;
      container.appendChild(el);
      container.scrollTop = container.scrollHeight;
    }
    var toggle = document.getElementById('estetican-ai-toggle');
    var panel = document.getElementById('estetican-ai-panel');
    var closeBtn = document.getElementById('estetican-ai-close');
    var messagesEl = document.getElementById('estetican-ai-messages');
    var form = document.getElementById('estetican-ai-form');
    var input = document.getElementById('estetican-ai-input');
    if (!toggle || !panel || !form || !input) {
      console.warn('[EsteticanAssistant] No se encontró el HTML del widget en la página.');
      return;
    }
    var submitBtn = form.querySelector('button[type="submit"]');
    var ctaBox = document.getElementById('estetican-ai-cta');
    var ctaLink = document.getElementById('estetican-ai-cta-link');
    var opened = false;
    apiFetch('/api/assistant/config').then(function (result) {
      if (!result.ok || !result.body.enabled) return;
      toggle.style.display = 'flex';
      if (result.body.cta_url) {
        ctaLink.href = result.body.cta_url;
        ctaLink.textContent = result.body.cta_label || 'Agenda tu cita';
        ctaBox.style.display = 'block';
      }
      appendMessage(messagesEl, 'assistant', '¡Hola! ¿En qué te puedo ayudar sobre nuestros servicios?');
    });
    toggle.addEventListener('click', function () {
      opened = !opened;
      panel.classList.toggle('estetican-ai-hidden', !opened);
      if (opened) input.focus();
    });
    closeBtn.addEventListener('click', function () {
      opened = false;
      panel.classList.add('estetican-ai-hidden');
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var message = input.value.trim();
      if (!message) return;
      appendMessage(messagesEl, 'user', message);
      input.value = '';
      input.disabled = true;
      submitBtn.disabled = true;
      apiFetch('/api/assistant/chat', {
        method: 'POST',
        body: JSON.stringify({ session_uuid: getSessionUuid(), message: message }),
      }).then(function (result) {
        if (result.ok) {
          appendMessage(messagesEl, 'assistant', result.body.reply);
          if (!result.body.limit_reached) {
            input.disabled = false;
            submitBtn.disabled = false;
            input.focus();
          }
        } else {
          appendMessage(messagesEl, 'system', result.body.message || 'No pudimos procesar tu mensaje.');
          input.disabled = false;
          submitBtn.disabled = false;
        }
      }).catch(function () {
        appendMessage(messagesEl, 'system', 'Sin conexión, intenta de nuevo.');
        input.disabled = false;
        submitBtn.disabled = false;
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
