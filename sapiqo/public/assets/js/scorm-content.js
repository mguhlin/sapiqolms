/* Sandboxed SCORM API shim. Contains no LMS credentials or parent DOM access. */
(function () {
  'use strict';
  const cmi = Object.create(null);
  let channel = null;
  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || !event.data || event.data.type !== 'sapiqo-scorm-init' || typeof event.data.channel !== 'string') return;
    channel = event.data.channel;
  });
  function report() {
    if (channel) window.parent.postMessage({type: 'sapiqo-scorm-commit', channel, status: cmi['cmi.core.lesson_status'] || cmi['cmi.completion_status'] || cmi['cmi.success_status'] || '', score: cmi['cmi.core.score.raw'] || '', scaled: cmi['cmi.score.scaled'] || ''}, '*');
    return 'true';
  }
  function set(key, value) { if (typeof key === 'string' && key.length < 150 && String(value).length <= 8192) cmi[key] = String(value); return 'true'; }
  function get(key) { return cmi[key] || ''; }
  window.API = {LMSInitialize: ()=>'true', LMSFinish: report, LMSGetValue: get, LMSSetValue: set, LMSCommit: report, LMSGetLastError: ()=>'0', LMSGetErrorString: ()=>'', LMSGetDiagnostic: ()=>''};
  window.API_1484_11 = {Initialize: ()=>'true', Terminate: report, GetValue: get, SetValue: set, Commit: report, GetLastError: ()=>'0', GetErrorString: ()=>'', GetDiagnostic: ()=>''};
  window.parent.postMessage({type: 'sapiqo-scorm-ready'}, '*');
})();
