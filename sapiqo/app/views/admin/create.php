<?php /** @var string $sample @var string $start @var ?string $editing */
$start = $start ?? $sample; ?>
<div class="page-head">
  <h1><?= $editing ? 'Edit course' : 'Create a course' ?></h1>
  <?php if ($editing): ?>
    <p>Editing <strong><?= e($editing) ?></strong>. Publishing rebuilds it in place (keeps the same slug).
       <a href="<?= e(url('/admin/courses')) ?>">&larr; All courses</a></p>
  <?php else: ?>
    <p>Write your course in Markdown on the left and watch it build on the right.
       Click <strong>Publish</strong> to add it to the catalog — no command line needed.
       <a href="<?= e(url('/admin/courses')) ?>">Manage existing courses &rarr;</a></p>
  <?php endif; ?>
</div>

<div class="editor-toolbar card" style="padding:12px 16px">
  <div class="toolbar" style="align-items:center;gap:10px">
    <button type="button" class="btn btn-gold" id="publishBtn"><?= $editing ? 'Save &amp; republish' : 'Publish course' ?></button>
    <button type="button" class="btn btn-outline btn-sm" id="saveDraft">Save draft</button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/drafts')) ?>">Drafts</a>
    <button type="button" class="btn btn-outline btn-sm" id="loadSample">Load example</button>
    <button type="button" class="btn btn-outline btn-sm" id="clearBtn">Clear</button>
    <label class="btn btn-outline btn-sm" style="cursor:pointer;margin:0">Insert image
      <input type="file" id="imgUpload" accept="image/*" hidden></label>
    <label class="btn btn-outline btn-sm" style="cursor:pointer;margin:0">Insert video
      <input type="file" id="vidUpload" accept="video/mp4,video/webm" hidden></label>
    <span class="muted" id="editorStatus" style="margin-left:auto"></span>
  </div>
  <details style="margin-top:10px">
    <summary class="muted">Cheat sheet — headings &amp; directives</summary>
    <div class="cheat">
      <code># Module title</code> starts a module ·
      <code>## Lesson title</code> starts a lesson ·
      <code>### / ####</code> sub-headings ·
      <code>**bold**</code> <code>*italic*</code> <code>`code`</code> · lists, <code>&gt; quotes</code> ·
      <code>@video https://vimeo.com/ID</code> or a YouTube URL ·
      <code>@embed &lt;url&gt;</code> (Vimeo/YouTube/Google Slides/Docs) ·
      <code>@resource [Title](url) note</code> ·
      <code>@image url "alt"</code> ·
      <code>@quiz</code> … <code>@endquiz</code> knowledge check ·
      front matter at the top sets <code>title</code>, <code>slug</code>, <code>tagline</code>, <code>tags</code>, <code>about</code>.
      <br><span class="muted">Quiz block: <code>pass: 70</code>, <code>shuffle: true</code>, <code>pick: 5</code>, <code>attempts: 3</code> ·
      <code>Q: question?</code> (add <code>(multiple)</code>) with <code>- choice</code>/<code>- *correct</code> ·
      <code>TF: statement</code> then <code>= true</code>/<code>= false</code> ·
      <code>SA: question?</code> then <code>= accepted answer</code> ·
      <code>feedback: explanation</code> per question.</span>
      <br><span class="muted">Local video files + captions are a command-line step
      (<code>creator/build_course.py --transcribe</code>); the editor supports Vimeo and image/video URLs.</span>
    </div>
  </details>
</div>

<div class="editor-split">
  <div class="editor-pane">
    <label class="editor-label" for="md">Markdown source</label>
    <textarea id="md" spellcheck="true"><?= e($start) ?></textarea>
  </div>
  <div class="editor-pane">
    <label class="editor-label">Live preview</label>
    <div id="preview" class="course-preview"></div>
  </div>
</div>

<form id="publishForm" method="post" action="<?= e(url('/admin/courses/create')) ?>" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="markdown" id="mdField">
</form>

<style>
  .editor-split{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;align-items:start}
  .editor-pane{display:flex;flex-direction:column;min-width:0}
  .editor-label{font-weight:600;font-size:.85rem;color:var(--ink-soft,#5a6b82);margin-bottom:6px}
  #md{width:100%;height:70vh;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
      font-size:13.5px;line-height:1.55;padding:14px;border:1px solid var(--line,#d7deea);border-radius:10px;
      background:#fff;color:#1a2233;tab-size:2}
  #md.drag{border:2px dashed var(--navy-600,#2b62aa);background:#f0f6ff}
  .course-preview{height:70vh;overflow:auto;padding:20px 24px;border:1px solid var(--line,#d7deea);
      border-radius:10px;background:#fff}
  .course-preview .mod{border-top:2px solid var(--gold-400,#f4b41a);margin:22px 0 4px;padding-top:6px}
  .course-preview .mod:first-child{border-top:0;margin-top:0}
  .course-preview .mod-label{font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
      color:var(--navy-600,#2b62aa)}
  .course-preview h2{font-size:1.5rem;margin:2px 0 10px}
  .course-preview .lesson{border:1px solid var(--line,#eef1f6);border-radius:10px;padding:14px 16px;margin:12px 0}
  .course-preview .lesson-title{font-size:1.1rem;font-weight:700;margin:0 0 8px;color:#17233a}
  .course-preview h3{font-size:1.05rem;margin:14px 0 6px}
  .course-preview h4{font-size:.95rem;margin:12px 0 6px}
  .course-preview img{max-width:100%;border-radius:8px}
  .course-preview blockquote{border-left:3px solid var(--gold-400,#f4b41a);margin:10px 0;padding:2px 14px;color:#3a475c}
  .course-preview .vid{aspect-ratio:16/9;background:#0d1b30;color:#cfe0f6;border-radius:8px;display:grid;
      place-items:center;font-size:.85rem;margin:10px 0}
  .course-preview .resources{background:var(--surface-alt,#f6f8fc);border-radius:8px;padding:10px 14px 10px 30px}
  .course-preview .course-meta{background:linear-gradient(135deg,var(--navy-800,#183a63),var(--navy-900,#0d1b30));
      color:#fff;border-radius:10px;padding:18px 20px;margin-bottom:10px}
  .course-preview .course-meta h1{color:#fff;margin:0 0 6px;font-size:1.6rem}
  .course-preview .course-meta p{color:#cfe0f6;margin:0}
  .course-preview .pill{display:inline-block;font-size:.7rem;font-weight:600;background:rgba(255,255,255,.15);
      color:#fff;border-radius:999px;padding:2px 10px;margin:2px 4px 0 0}
  .course-preview .quizbox{border:1px solid var(--line,#d7deea);background:var(--surface-alt,#f6f8fc);border-radius:10px;padding:14px 16px;margin:12px 0}
  .course-preview .quizbox__title{font-weight:700;color:var(--navy-900,#0d1b30);margin-bottom:8px}
  .course-preview .quizbox__q{margin:8px 0}
  .course-preview .quizbox__q ul{list-style:none;padding-left:6px;margin:4px 0}
  .course-preview .quizbox__q li{padding:2px 0}
  .course-preview .quizbox .ok{color:#1f8b4c;font-size:.8em;font-weight:600}
  .cheat{margin-top:8px;font-size:.85rem;line-height:1.9;color:var(--ink-soft,#5a6b82)}
  .cheat code{background:var(--surface-alt,#f1f4fa);padding:1px 6px;border-radius:5px;font-size:.82em}
  @media (max-width:900px){.editor-split{grid-template-columns:1fr}#md,.course-preview{height:50vh}}
</style>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
  var SAMPLE = <?= json_encode($sample, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var md = document.getElementById("md");
  var preview = document.getElementById("preview");
  var status = document.getElementById("editorStatus");

  var UPLOAD_BASE = <?= json_encode(url('/uploads/')) ?>;
  function esc(s){return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
  function resolveSrc(u){ return u.indexOf("upload:")===0 ? UPLOAD_BASE + u.slice(7) : u; }
  function inline(t){
    t = esc(t);
    t = t.replace(/!\[([^\]]*)\]\(([^)]+)\)/g,function(_,a,u){return '<img src="'+resolveSrc(u)+'" alt="'+a+'">';});
    t = t.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
    t = t.replace(/\*\*([^*]+)\*\*/g,"<strong>$1</strong>");
    t = t.replace(/(^|[^*])\*([^*]+)\*(?!\*)/g,"$1<em>$2</em>");
    t = t.replace(/`([^`]+)`/g,"<code>$1</code>");
    return t;
  }

  // Render a block of plain Markdown lines (no directives/headings-1-2) to HTML.
  function renderBody(lines){
    var out=[], para=[], i=0;
    function flush(){ if(para.length){ out.push("<p>"+inline(para.join(" "))+"</p>"); para=[]; } }
    while(i<lines.length){
      var s=lines[i].trim();
      if(s===""){ flush(); i++; continue; }
      if(/^(---|\*\*\*|___)$/.test(s)){ flush(); out.push("<hr>"); i++; continue; }
      var h=s.match(/^(#{3,6})\s+(.*)$/);
      if(h){ flush(); var l=h[1].length; out.push("<h"+l+">"+inline(h[2])+"</h"+l+">"); i++; continue; }
      if(/^>\s?/.test(s)){ flush(); var b=[]; while(i<lines.length&&/^>\s?/.test(lines[i].trim())){b.push(lines[i].trim().replace(/^>\s?/,""));i++;} out.push("<blockquote>"+renderBody(b)+"</blockquote>"); continue; }
      if(/^[-*]\s+/.test(s)){ flush(); var items=[]; while(i<lines.length&&/^[-*]\s+/.test(lines[i].trim())){items.push(lines[i].trim().replace(/^[-*]\s+/,""));i++;} out.push("<ul>"+items.map(function(x){return "<li>"+inline(x)+"</li>";}).join("")+"</ul>"); continue; }
      if(/^\d+\.\s+/.test(s)){ flush(); var it2=[]; while(i<lines.length&&/^\d+\.\s+/.test(lines[i].trim())){it2.push(lines[i].trim().replace(/^\d+\.\s+/,""));i++;} out.push("<ol>"+it2.map(function(x){return "<li>"+inline(x)+"</li>";}).join("")+"</ol>"); continue; }
      var img=s.match(/^!\[([^\]]*)\]\(([^)]+)\)$/);
      if(img){ flush(); out.push('<figure><img src="'+esc(resolveSrc(img[2]))+'" alt="'+esc(img[1])+'"></figure>'); i++; continue; }
      para.push(s); i++;
    }
    flush();
    return out.join("\n");
  }

  function renderQuizPreview(lines){
    var pass=70, shuffle=false, pick=0, attempts=0, questions=[], cur=null;
    function flush(){ if(cur) questions.push(cur); cur=null; }
    lines.forEach(function(raw){
      var t=raw.trim(); if(t==="") return;
      var pm;
      if((pm=t.match(/^pass:\s*(\d+)/i))){ pass=Math.max(1,Math.min(100,parseInt(pm[1],10))); return; }
      if(/^shuffle:\s*(true|yes|1)/i.test(t)){ shuffle=true; return; }
      if((pm=t.match(/^pick:\s*(\d+)/i))){ pick=parseInt(pm[1],10); return; }
      if((pm=t.match(/^attempts:\s*(\d+)/i))){ attempts=parseInt(pm[1],10); return; }
      if((pm=t.match(/^feedback:\s*(.*)$/i))){ if(cur)cur.feedback=pm[1].trim(); return; }
      var qm=t.match(/^Q[:.]\s*(.*)$/i);
      if(qm){ flush(); var txt=qm[1],mult=false;
        if(/\(multiple\)\s*$/i.test(txt)){mult=true;txt=txt.replace(/\(multiple\)\s*$/i,"").trim();}
        cur={text:txt,type:mult?"multiple":"single",options:[]}; return; }
      var tf=t.match(/^TF[:.]\s*(.*)$/i);
      if(tf){ flush(); cur={text:tf[1].trim(),type:"single",options:[{text:"True",correct:false},{text:"False",correct:false}]}; return; }
      var sa=t.match(/^SA[:.]\s*(.*)$/i);
      if(sa){ flush(); cur={text:sa[1].trim(),type:"short",answers:[]}; return; }
      var eq=t.match(/^=\s*(.*)$/);
      if(eq&&cur){ var v=eq[1].trim();
        if(cur.type==="short"){ if(v)cur.answers.push(v); }
        else { cur.options.forEach(function(o){ if(o.text.toLowerCase()===v.toLowerCase())o.correct=true; }); }
        return; }
      var om=t.match(/^[-*+]\s+(.*)$/);
      if(om&&cur&&cur.type!=="short"){ var o=om[1].trim(),correct=false; if(o.charAt(0)==="*"){correct=true;o=o.slice(1).trim();}
        if(o)cur.options.push({text:o,correct:correct}); }
    });
    flush();
    if(!questions.length) return '<div class="quizbox muted">Empty quiz — add <code>Q:</code>, <code>TF:</code>, or <code>SA:</code> questions.</div>';
    var meta=[pass+'% to pass'];
    if(pick>0)meta.push('pick '+pick);
    if(shuffle)meta.push('shuffled');
    if(attempts>0)meta.push(attempts+' attempts');
    var h='<div class="quizbox"><div class="quizbox__title">✔ Knowledge check <span class="muted">('+meta.join(' · ')+')</span></div>';
    questions.forEach(function(q,i){
      if(q.type!=="short"){ var nc=q.options.filter(function(o){return o.correct;}).length; if(nc>1)q.type="multiple"; }
      var tag=q.type==="short"?" — short answer":(q.type==="multiple"?' <span class="muted">(choose all)</span>':"");
      h+='<div class="quizbox__q"><strong>'+(i+1)+". "+esc(q.text)+"</strong>"+tag+"<ul>";
      if(q.type==="short"){ (q.answers||[]).forEach(function(a){ h+='<li>✎ <span class="ok">'+esc(a)+"</span></li>"; }); }
      else { q.options.forEach(function(o){ h+="<li>"+(q.type==="multiple"?"▢":"◯")+" "+esc(o.text)+(o.correct?' <span class="ok">✓ correct</span>':"")+"</li>"; }); }
      if(q.feedback) h+='<li class="muted">💬 '+esc(q.feedback)+"</li>";
      h+="</ul></div>";
    });
    return h+"</div>";
  }

  function moduleLabel(title){
    var low=title.toLowerCase();
    if(/welcome/.test(low)||/^start/.test(low)) return "Start Here";
    if(/badge|certificate/.test(low)) return "Finish";
    var m=title.match(/(?:module\s+)?(\d+)/i);
    if(m && /^\s*(module\s+)?\d+\b/i.test(title)) return "Module "+m[1];
    return title;
  }

  function render(){
    var text=md.value;
    var meta={}, body=text;
    var fm=text.match(/^---\s*\n([\s\S]*?)\n---\s*\n?([\s\S]*)$/);
    if(fm){ fm[1].split("\n").forEach(function(line){ var idx=line.indexOf(":"); if(idx>0){ meta[line.slice(0,idx).trim().toLowerCase()]=line.slice(idx+1).trim(); } }); body=fm[2]; }

    var html="";
    if(meta.title){
      html+='<div class="course-meta"><h1>'+esc(meta.title)+"</h1>";
      if(meta.tagline) html+="<p>"+esc(meta.tagline)+"</p>";
      if(meta.tags) html+="<div>"+meta.tags.split(",").map(function(t){t=t.trim();return t?'<span class="pill">'+esc(t)+"</span>":"";}).join("")+"</div>";
      html+="</div>";
      if(meta.about) html+='<p class="muted">'+inline(meta.about)+"</p>";
    }

    var lines=body.split("\n");
    var buf=[], resources=[], inLesson=false, modCount=0, lessonCount=0, quizCount=0;
    var inQuiz=false, quizLines=[];
    function flushBuf(){ if(buf.length){ html+=renderBody(buf); buf=[]; } }
    function flushResources(){ if(resources.length){ html+='<h3>Resources</h3><ul class="resources">'+resources.join("")+"</ul>"; resources=[]; } }
    function closeLesson(){ if(inLesson){ flushBuf(); flushResources(); html+="</div>"; inLesson=false; } }

    lines.forEach(function(raw){
      var s=raw.trim();
      if(inQuiz){
        if(/^@endquiz\b/i.test(s)){ flushBuf(); html+=renderQuizPreview(quizLines); quizCount++; inQuiz=false; quizLines=[]; }
        else quizLines.push(raw);
        return;
      }
      if(/^@quiz\b/i.test(s)){ flushBuf(); inQuiz=true; quizLines=[]; return; }
      var m1=s.match(/^#\s+(.*)$/);
      if(m1){ closeLesson(); modCount++; html+='<div class="mod"><div class="mod-label">'+esc(moduleLabel(m1[1]))+'</div><h2>'+esc(m1[1])+"</h2></div>"; return; }
      var m2=s.match(/^##\s+(.*)$/);
      if(m2){ closeLesson(); lessonCount++; html+='<div class="lesson"><div class="lesson-title">'+esc(m2[1])+"</div>"; inLesson=true; return; }
      var dir=s.match(/^@(video|resource|image|embed)\s+(.*)$/);
      if(dir){
        flushBuf();
        if(dir[1]==="video"||dir[1]==="embed"){
          var yt=dir[2].match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
          var vm=dir[2].match(/vimeo\.com\/(?:video\/)?(\d+)/);
          if(yt) html+='<div class="vid">▶ YouTube video '+yt[1]+"</div>";
          else if(vm) html+='<div class="vid">▶ Vimeo video '+vm[1]+"</div>";
          else if(dir[2].indexOf("upload:")===0) html+='<video controls preload="metadata" style="max-width:100%;border-radius:8px;margin:10px 0" src="'+esc(resolveSrc(dir[2]))+'"></video>';
          else if(/^https?:\/\/(docs|drive)\.google\.com/.test(dir[2])) html+='<div class="vid">▤ Google embed</div>';
          else if(/^https?:\/\/.*\.(mp4|webm|m4v)/i.test(dir[2])) html+='<video controls preload="metadata" style="max-width:100%;border-radius:8px;margin:10px 0" src="'+esc(dir[2])+'"></video>';
          else if(dir[1]==="embed") html+='<div class="vid">⧉ Embed: '+esc(dir[2])+"</div>";
          else html+='<p class="muted"><em>Video "'+esc(dir[2])+'" — add local files with the command-line builder.</em></p>';
        } else if(dir[1]==="resource"){
          var rm=dir[2].match(/\[([^\]]+)\]\(([^)]+)\)\s*(.*)$/);
          if(rm) resources.push('<li><a href="'+esc(rm[2])+'" target="_blank" rel="noopener"><strong>'+esc(rm[1])+"</strong></a>"+(rm[3]?" — "+esc(rm[3]):"")+"</li>");
        } else if(dir[1]==="image"){
          var im=dir[2].match(/(\S+)(?:\s+"([^"]*)")?/);
          if(im) buf.push("!["+(im[2]||"")+"]("+im[1]+")");
        }
        return;
      }
      buf.push(raw);
    });
    closeLesson();

    if(!modCount) html+='<p class="muted">Add a <code>#&nbsp;Module</code> and <code>##&nbsp;Lesson</code> to see your course take shape.</p>';
    preview.innerHTML=html;
    status.textContent=modCount+" module"+(modCount!==1?"s":"")+" · "+lessonCount+" lesson"+(lessonCount!==1?"s":"")+
      (quizCount?" · "+quizCount+" quiz"+(quizCount!==1?"zes":""):"");
  }

  // --- Uploads (image/video) ---
  function csrfToken(){ var f=document.getElementById("publishForm"); var i=f&&f.querySelector('input[name="_csrf"]'); return i?i.value:""; }
  function insertAtCursor(text){
    var start=md.selectionStart||0, end=md.selectionEnd||0;
    md.value=md.value.slice(0,start)+text+md.value.slice(end);
    var pos=start+text.length; md.selectionStart=md.selectionEnd=pos; md.focus(); render();
  }
  function uploadFile(file){
    if(!file) return;
    status.textContent="Uploading "+file.name+"…";
    var fd=new FormData(); fd.append("file",file); fd.append("_csrf",csrfToken());
    fetch(<?= json_encode(url('/admin/uploads')) ?>,{method:"POST",body:fd,credentials:"same-origin",headers:{"Accept":"application/json"}})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok){ insertAtCursor("\n"+d.markdown+"\n"); status.textContent="Inserted "+d.name; }
        else status.textContent="⚠ "+(d.error||"Upload failed");
      }).catch(function(){ status.textContent="⚠ Upload error"; });
  }
  document.getElementById("imgUpload").addEventListener("change", function(e){ uploadFile(e.target.files[0]); e.target.value=""; });
  document.getElementById("vidUpload").addEventListener("change", function(e){ uploadFile(e.target.files[0]); e.target.value=""; });
  // Drag-drop onto the textarea.
  md.addEventListener("dragover", function(e){ e.preventDefault(); md.classList.add("drag"); });
  md.addEventListener("dragleave", function(){ md.classList.remove("drag"); });
  md.addEventListener("drop", function(e){
    e.preventDefault(); md.classList.remove("drag");
    if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) uploadFile(e.dataTransfer.files[0]);
  });

  md.addEventListener("input", render);
  document.getElementById("loadSample").addEventListener("click", function(){ md.value=SAMPLE; render(); });
  document.getElementById("clearBtn").addEventListener("click", function(){ if(confirm("Clear the editor?")){ md.value=""; render(); } });
  document.getElementById("saveDraft").addEventListener("click", function(){
    status.textContent="Saving draft…";
    var fd=new FormData(); fd.append("markdown",md.value); fd.append("_csrf",csrfToken());
    fetch(<?= json_encode(url('/admin/drafts')) ?>,{method:"POST",body:fd,credentials:"same-origin",headers:{"Accept":"application/json"}})
      .then(function(r){return r.json();}).then(function(d){ status.textContent=d.ok?"Draft saved.":"⚠ "+(d.error||"Could not save draft"); })
      .catch(function(){ status.textContent="⚠ Error saving draft"; });
  });

  document.getElementById("publishBtn").addEventListener("click", function(){
    var btn=this; btn.disabled=true; status.textContent="Publishing…";
    var fd=new FormData(document.getElementById("publishForm"));
    fd.set("markdown", md.value);
    fetch(document.getElementById("publishForm").action, {
      method:"POST", body:fd, headers:{"Accept":"application/json"}, credentials:"same-origin"
    }).then(function(r){return r.json();}).then(function(d){
      btn.disabled=false;
      if(d.ok){ status.textContent="Published! Opening course…"; window.location.href=d.url; }
      else { status.textContent="⚠ "+(d.error||"Could not publish."); }
    }).catch(function(){ btn.disabled=false; status.textContent="⚠ Network error while publishing."; });
  });

  render();
})();
</script>
