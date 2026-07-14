/**
 * Widget de chat con IA para el sitio público de WordPress (BL-042).
 * Autocontenido, sin dependencias — se inserta con un <script> en WordPress.
 *
 * Uso en WordPress (pegar antes de </body>, ej. vía "Insert Headers and Footers"):
 *
 *   <script>
 *     window.EsteticanAssistantConfig = {
 *       apiBase: 'https://app.estetican.org',
 *       token: 'EL_TOKEN_DEL_WIDGET_CONFIGURADO_EN_EL_BACKOFFICE'
 *     };
 *   </script>
 *   <script src="https://app.estetican.org/assistant-widget.js" async></script>
 */
(function () {
    'use strict';

    var config = window.EsteticanAssistantConfig || {};
    var apiBase = (config.apiBase || '').replace(/\/$/, '');
    var token = config.token || '';

    if (!apiBase || !token) {
        console.warn('[EsteticanAssistant] Falta apiBase o token en window.EsteticanAssistantConfig — widget no cargado.');
        return;
    }

    var SESSION_KEY = 'estetican_assistant_session';

    function getSessionUuid() {
        var existing = window.localStorage.getItem(SESSION_KEY);
        if (existing) return existing;

        var uuid = (window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                var v = c === 'x' ? r : (r & 0x3) | 0x8;
                return v.toString(16);
            });

        window.localStorage.setItem(SESSION_KEY, uuid);
        return uuid;
    }

    function apiFetch(path, options) {
        options = options || {};
        options.headers = Object.assign({
            'X-Widget-Token': token,
            'Content-Type': 'application/json',
        }, options.headers || {});

        return fetch(apiBase + path, options).then(function (response) {
            return response.json().then(function (body) {
                return { ok: response.ok, status: response.status, body: body };
            });
        });
    }

    function injectStyles() {
        var style = document.createElement('style');
        style.textContent = [
            '#estetican-ai-toggle{position:fixed;bottom:20px;right:20px;width:56px;height:56px;border-radius:50%;',
            'background:#2f6f4f;color:#fff;border:none;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer;',
            'font-size:26px;z-index:999999;display:flex;align-items:center;justify-content:center;}',
            '#estetican-ai-panel{position:fixed;bottom:88px;right:20px;width:320px;max-width:92vw;height:440px;',
            'max-height:75vh;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.3);',
            'display:flex;flex-direction:column;overflow:hidden;font-family:system-ui,-apple-system,sans-serif;',
            'font-size:14px;z-index:999999;}',
            '#estetican-ai-panel.hidden{display:none;}',
            '.estetican-ai-header{background:#2f6f4f;color:#fff;padding:10px 14px;display:flex;',
            'justify-content:space-between;align-items:center;}',
            '.estetican-ai-header button{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;}',
            '.estetican-ai-messages{flex:1;overflow-y:auto;padding:10px;display:flex;flex-direction:column;gap:8px;}',
            '.estetican-ai-msg{padding:8px 10px;border-radius:10px;max-width:85%;line-height:1.35;white-space:pre-wrap;}',
            '.estetican-ai-msg.user{align-self:flex-end;background:#2f6f4f;color:#fff;}',
            '.estetican-ai-msg.assistant{align-self:flex-start;background:#f0f0f0;color:#222;}',
            '.estetican-ai-msg.system{align-self:center;background:transparent;color:#888;font-size:12px;}',
            '.estetican-ai-cta{padding:8px 10px;border-top:1px solid #eee;text-align:center;}',
            '.estetican-ai-cta a{display:block;background:#e8a33d;color:#222;text-decoration:none;',
            'padding:8px;border-radius:8px;font-weight:600;}',
            '.estetican-ai-form{display:flex;border-top:1px solid #eee;padding:8px;gap:6px;}',
            '.estetican-ai-form input{flex:1;border:1px solid #ddd;border-radius:8px;padding:8px;font-size:14px;}',
            '.estetican-ai-form button{background:#2f6f4f;color:#fff;border:none;border-radius:8px;',
            'padding:0 14px;cursor:pointer;}',
            '.estetican-ai-form button:disabled{opacity:.5;cursor:default;}',
        ].join('');
        document.head.appendChild(style);
    }

    function buildWidget(settings) {
        injectStyles();

        var toggle = document.createElement('button');
        toggle.id = 'estetican-ai-toggle';
        toggle.setAttribute('aria-label', 'Abrir chat de ayuda');
        toggle.textContent = '💬';

        var panel = document.createElement('div');
        panel.id = 'estetican-ai-panel';
        panel.className = 'hidden';
        panel.innerHTML =
            '<div class="estetican-ai-header"><span>Asistente virtual</span>' +
            '<button type="button" class="estetican-ai-close" aria-label="Cerrar">×</button></div>' +
            '<div class="estetican-ai-messages"></div>' +
            (settings.cta_url
                ? '<div class="estetican-ai-cta"><a href="' + encodeAttr(settings.cta_url) + '" target="_blank" rel="noopener">' +
                  escapeHtml(settings.cta_label || 'Agenda tu cita') + '</a></div>'
                : '') +
            '<form class="estetican-ai-form">' +
            '<input type="text" maxlength="500" placeholder="Escribe tu pregunta..." autocomplete="off" />' +
            '<button type="submit">Enviar</button>' +
            '</form>';

        document.body.appendChild(toggle);
        document.body.appendChild(panel);

        var messagesEl = panel.querySelector('.estetican-ai-messages');
        var form = panel.querySelector('.estetican-ai-form');
        var input = panel.querySelector('input');
        var submitBtn = panel.querySelector('button[type="submit"]');
        var opened = false;

        appendMessage(messagesEl, 'assistant', '¡Hola! ¿En qué te puedo ayudar sobre nuestros servicios?');

        toggle.addEventListener('click', function () {
            opened = !opened;
            panel.classList.toggle('hidden', !opened);
            if (opened) input.focus();
        });

        panel.querySelector('.estetican-ai-close').addEventListener('click', function () {
            opened = false;
            panel.classList.add('hidden');
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
                    if (result.body.limit_reached) {
                        input.disabled = true;
                        submitBtn.disabled = true;
                    } else {
                        input.disabled = false;
                        submitBtn.disabled = false;
                        input.focus();
                    }
                } else {
                    appendMessage(messagesEl, 'system', result.body.message || 'No pudimos procesar tu mensaje, intenta de nuevo.');
                    input.disabled = false;
                    submitBtn.disabled = false;
                }
            }).catch(function () {
                appendMessage(messagesEl, 'system', 'Sin conexión, intenta de nuevo en un momento.');
                input.disabled = false;
                submitBtn.disabled = false;
            });
        });
    }

    function appendMessage(container, role, text) {
        var el = document.createElement('div');
        el.className = 'estetican-ai-msg ' + role;
        el.textContent = text;
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }

    function encodeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function init() {
        apiFetch('/api/assistant/config').then(function (result) {
            if (result.ok && result.body.enabled) {
                buildWidget(result.body);
            }
        }).catch(function () {
            // Silencioso: si el asistente no responde, el sitio sigue funcionando igual.
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
