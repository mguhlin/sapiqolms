/* Privileged shell: accept only bounded completion messages from its own opaque frame. */
(async function () {
  'use strict';
  const config = JSON.parse(document.getElementById('scorm-player').dataset.config);
  const frame = document.getElementById('scorm-frame');
  const status = document.getElementById('scorm-status');
  const api = new URL('../../api/', location.href);
  const channel = Array.from(crypto.getRandomValues(new Uint8Array(24)), b=>b.toString(16).padStart(2,'0')).join('');
  let csrf, saving = false, done = false;
  window.addEventListener('message', async function (event) {
    if (event.source !== frame.contentWindow || event.origin !== 'null' || !event.data || typeof event.data !== 'object') return;
    const message = event.data;
    if (message.type === 'sapiqo-scorm-ready') { frame.contentWindow.postMessage({type:'sapiqo-scorm-init',channel}, '*'); return; }
    if (message.type !== 'sapiqo-scorm-commit' || message.channel !== channel || typeof message.status !== 'string' || !['passed','completed'].includes(message.status.toLowerCase()) || !csrf || saving || done) return;
    let score = Number.parseFloat(message.score);
    if (!Number.isFinite(score)) score = Number.parseFloat(message.scaled) * 100;
    score = Number.isFinite(score) ? Math.min(100, Math.max(0, score)) : 100;
    saving = true; status.textContent = 'Saving completion…';
    try {
      const response = await fetch(new URL('scorm', api), {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({course:config.course,status:'completed',score})});
      const result = await response.json();
      if (!response.ok || result.error) throw new Error('Save failed');
      done = true; status.textContent = 'Course complete. Your progress was saved.';
    } catch (_) { status.textContent = 'Completion could not be saved. Commit again to retry.'; }
    finally { saving = false; }
  });
  try {
    const response = await fetch(new URL('whoami',api),{credentials:'same-origin'});
    const identity = await response.json();
    if (!identity.authenticated) throw new Error('Sign in required');
    csrf = identity.csrf; frame.src = config.entry; status.textContent = 'Course ready';
  } catch (_) { status.textContent = 'Unable to start this course. Return to the dashboard and sign in again.'; }
})();
