<?php /** @var string $slug @var string $title */ ?>
<div class="ed-topbar">
  <div class="ed-topbar__left">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/courses')) ?>">&larr; Courses</a>
    <input id="edTitle" class="ed-title" value="<?= e($title) ?>" aria-label="Course title">
  </div>
  <div class="ed-topbar__right">
    <span id="edStatus" class="muted" aria-live="polite"></span>
    <button id="edSettingsBtn" class="btn btn-outline btn-sm" type="button">⚙ Settings</button>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/resources/' . urlencode($slug))) ?>">Resources</a>
    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= e(url('/courses/' . urlencode($slug) . '/')) ?>">Preview ↗</a>
    <button id="edSave" class="btn btn-gold btn-sm" type="button">Save</button>
  </div>
</div>

<div id="edLockBar" class="ed-lockbar" hidden></div>
<style>
.ed-lockbar{position:sticky;top:0;z-index:60;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap;padding:10px 14px;background:#fef3c7;color:#7c2d12;border-bottom:1px solid #f59e0b;font-size:.92rem;font-weight:600}
.ed-lockbar .btn{margin:0}
.ed-wrap.is-readonly .ed-page{opacity:.6}
.ed-wrap.is-readonly .ed-page input,.ed-wrap.is-readonly .ed-page textarea,.ed-wrap.is-readonly .ed-page select,.ed-wrap.is-readonly .ed-page [contenteditable],.ed-wrap.is-readonly .ed-page button{pointer-events:none}
</style>

<div class="ed-wrap">
  <aside class="ed-outline" id="edOutline" aria-label="Course outline"></aside>
  <section class="ed-page" id="edPage" aria-label="Page editor"></section>
</div>

<!-- Resource picker modal -->
<div id="edPicker" class="ed-modal" hidden>
  <div class="ed-modal__box">
    <div class="ed-modal__head"><strong>Choose a file</strong><button type="button" id="edPickerClose" class="btn btn-ghost btn-sm">✕</button></div>
    <div class="ed-modal__body">
      <label class="btn btn-outline btn-sm">Upload new… <input type="file" id="edUpload" hidden></label>
      <span id="edUploadStatus" class="muted"></span>
      <div id="edPickerList" class="ed-picker-list"></div>
    </div>
  </div>
</div>

<!-- Course settings modal -->
<div id="edSettings" class="ed-modal" hidden>
  <div class="ed-modal__box">
    <div class="ed-modal__head"><strong>Course settings</strong><button type="button" id="edSettingsClose" class="btn btn-ghost btn-sm">✕</button></div>
    <div class="ed-modal__body">
      <div class="field"><label for="setTagline">Tagline</label>
        <input id="setTagline" class="ed-inp" placeholder="Short one-line description"></div>
      <div class="ed-row">
        <div class="field" style="flex:1"><label for="setCpe">CPE hours</label>
          <input id="setCpe" class="ed-inp" type="number" min="0" step="0.25" value="0"></div>
        <div class="field" style="flex:1"><label for="setGt">GT hours</label>
          <input id="setGt" class="ed-inp" type="number" min="0" step="0.25" value="0" title="Gifted &amp; Talented credit hours (0 = none)"></div>
        <div class="field" style="flex:1"><label for="setExpiry">Access expires after (days)</label>
          <input id="setExpiry" class="ed-inp" type="number" min="0" value="0" title="0 = no expiry"></div>
      </div>
      <div class="field"><label for="setPrereq">Prerequisite course</label>
        <select id="setPrereq" class="ed-sel"><option value="0">— none —</option></select></div>
      <div class="ed-row">
        <div class="field" style="flex:1"><label for="setSeq">Sequential mode</label>
          <select id="setSeq" class="ed-sel"><option value="inherit">Default</option><option value="on">On</option><option value="off">Off</option></select></div>
        <div class="field" style="flex:1"><label for="setForum">Discussion forum</label>
          <select id="setForum" class="ed-sel"><option value="on">On</option><option value="gated">On + post-first</option><option value="off">Off</option></select></div>
      </div>
      <div class="toolbar" style="margin:6px 0 0"><button id="edSettingsSave" class="btn btn-gold btn-sm" type="button">Save settings</button><button id="edSettingsCancel" class="btn btn-outline btn-sm" type="button">Cancel</button><span id="edSettingsStatus" class="muted"></span></div>
    </div>
  </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
const ED = {
  slug: <?= json_encode($slug) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  base: <?= json_encode(rtrim(url('/'), '/')) ?>,
  course: null, resources: [], sel: {mi:0, li:0}, dirty:false, pick:null, drag:null
};
const $ = (s,r=document)=>r.querySelector(s);
const el = (t,props={},kids=[])=>{const n=document.createElement(t);for(const k in props){if(k==='class')n.className=props[k];else if(k==='html')n.innerHTML=props[k];else if(k.startsWith('on'))n.addEventListener(k.slice(2),props[k]);else n.setAttribute(k,props[k]);}for(const c of [].concat(kids))if(c!=null)n.append(c);return n;};
const esc = s=>(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
function setStatus(t){ $('#edStatus').textContent=t; }
function markDirty(){ ED.dirty=true; setStatus('Unsaved changes'); }

async function load(){
  const r = await fetch(ED.base+'/api/editor/'+ED.slug, {headers:{'Accept':'application/json'}});
  const d = await r.json();
  ED.course = d.course; ED.resources = d.resources||[]; ED.settings = d.settings||{}; ED.otherCourses = d.other_courses||[];
  ED.baseRev = d.rev||0;
  if(!ED.course.modules||!ED.course.modules.length) ED.course.modules=[{title:'Module 1',key:'0',label:'Module 1',lessons:[]}];
  // Forums are added explicitly (＋ Forum); don't auto-create any per module.
  ED.course.modules.forEach(m=>{ if(!Array.isArray(m.forums)) m.forums=[]; m.forums.forEach(f=>{ if(!Array.isArray(f.blocks))f.blocks=[]; }); });
  renderOutline(); renderPage();
  if(location.hash==='#settings') openSettings();
}
const curMod = ()=>ED.course.modules[ED.sel.mi];
const curPage = ()=> (ED.sel.forum||!curMod()) ? null : curMod().lessons[ED.sel.li];
// The item currently open in the right pane — a page OR a forum (both hold blocks).
const curItem = ()=>{ const m=curMod(); if(!m)return null; return ED.sel.forum ? (m.forums||[])[ED.sel.fi]||null : (m.lessons||[])[ED.sel.li]||null; };

/* ---------- Outline ---------- */
function renderOutline(){
  const root = $('#edOutline'); root.innerHTML='';
  ED.course.modules.forEach((m,mi)=>{
    const modEl = el('div',{class:'ed-mod'});
    // Module drag handle (reorders the whole module with its pages).
    const mgrip = el('span',{class:'ed-mgrip', draggable:'true', title:'Drag to reorder module'},'⠿');
    mgrip.addEventListener('dragstart',e=>{ED.drag={type:'module',mi};e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','mod:'+mi);modEl.classList.add('dragging');});
    mgrip.addEventListener('dragend',()=>{ED.drag=null;modEl.classList.remove('dragging');});
    const head = el('div',{class:'ed-mod__head'},[
      mgrip,
      el('input',{class:'ed-mod__title', value:m.title||'', 'aria-label':'Module title',
        oninput:e=>{m.title=e.target.value; markDirty();}}),
      el('button',{class:'ed-icon', title:'Add page', type:'button', onclick:()=>addPage(mi)},'＋'),
      el('button',{class:'ed-icon ed-del', title:'Delete module', type:'button', onclick:()=>delModule(mi)},'✗')
    ]);
    modEl.append(head);
    // Module reorder drop target.
    modEl.addEventListener('dragover',e=>{ if(ED.drag&&ED.drag.type==='module'){e.preventDefault();modEl.classList.add('drop-mod');}});
    modEl.addEventListener('dragleave',()=>modEl.classList.remove('drop-mod'));
    modEl.addEventListener('drop',e=>{ if(!ED.drag||ED.drag.type!=='module')return; e.preventDefault(); modEl.classList.remove('drop-mod'); moveModule(ED.drag.mi, mi, e.clientY, modEl); });
    const list = el('div',{class:'ed-pages', 'data-mi':mi});
    (m.lessons||[]).forEach((l,li)=>{
      const active = (mi===ED.sel.mi && li===ED.sel.li);
      const row = el('div',{class:'ed-pagerow'+(active?' is-active':''), draggable:'true', 'data-mi':mi, 'data-li':li,
        onclick:()=>selectPage(mi,li)},[
        el('span',{class:'ed-grip', title:'Drag to reorder'},'⠿'),
        el('span',{class:'ed-pagerow__t'}, l.title||'Untitled page'),
        (l.quiz&&l.quiz.questions&&l.quiz.questions.length)?el('span',{class:'ed-badge',title:'Has a quiz'},'✓ quiz'):null,
        el('button',{class:'ed-icon ed-pagedel ed-del', title:'Delete page', type:'button',
          onclick:e=>{e.stopPropagation(); delPage(mi,li);}},'🗑')
      ]);
      row.addEventListener('dragstart',e=>{ED.drag={type:'page',mi,li};e.dataTransfer.setData('text/plain',mi+':'+li);e.dataTransfer.effectAllowed='move';row.classList.add('dragging');e.stopPropagation();});
      row.addEventListener('dragend',()=>{ED.drag=null;row.classList.remove('dragging');});
      list.append(row);
    });
    list.addEventListener('dragover',e=>{ if(ED.drag&&ED.drag.type!=='page')return; e.preventDefault();rowAfter(list,e.clientY);list.classList.add('drop');});
    list.addEventListener('dragleave',()=>list.classList.remove('drop'));
    list.addEventListener('drop',e=>{ if(ED.drag&&ED.drag.type!=='page')return; e.preventDefault();e.stopPropagation();list.classList.remove('drop');
      const [fmi,fli]=e.dataTransfer.getData('text/plain').split(':').map(Number);
      const before=rowAfter(list,e.clientY); movePage(fmi,fli,mi,before);});
    modEl.append(list);
    // Forums added to this module (like pages, but discussion boards).
    const forums = Array.isArray(m.forums)?m.forums:[];
    const fwrap = el('div',{class:'ed-forums'});
    forums.forEach((f,fi)=>{
      const activeF = (ED.sel.forum && ED.sel.mi===mi && ED.sel.fi===fi);
      fwrap.append(el('div',{class:'ed-pagerow ed-forumrow'+(activeF?' is-active':''), onclick:()=>selectForum(mi,fi)},[
        el('span',{class:'ed-fic',title:'Discussion forum'},'💬'),
        el('span',{class:'ed-pagerow__t'}, f.name||'Forum'),
        el('button',{class:'ed-icon ed-del ed-pagedel',title:'Delete forum',type:'button',onclick:e=>{e.stopPropagation();delForum(mi,fi);}},'✗')
      ]));
    });
    fwrap.append(el('button',{class:'ed-addforum',type:'button',onclick:()=>addForum(mi)},'＋ Forum'));
    modEl.append(fwrap);
    root.append(modEl);
  });
  root.append(el('button',{class:'btn btn-outline btn-sm ed-addmod', type:'button', onclick:addModule},'＋ Add module'));
}
function rowAfter(list,y){
  const rows=[...list.querySelectorAll('.ed-pagerow:not(.dragging)')];
  for(const r of rows){const b=r.getBoundingClientRect();if(y<b.top+b.height/2)return Number(r.dataset.li);}
  return rows.length;
}
function addModule(){ ED.course.modules.push({title:'New module',key:String(ED.course.modules.length),label:'Module '+(ED.course.modules.length+1),lessons:[]}); markDirty(); renderOutline(); }
function delModule(mi){ if(ED.course.modules.length<=1){alert('Keep at least one module.');return;} const nm=ED.course.modules[mi].title||'this module'; const n=(ED.course.modules[mi].lessons||[]).length; if(!confirm('Delete the module "'+nm+'"'+(n?' and its '+n+' page'+(n===1?'':'s'):'')+'? This cannot be undone once you save.'))return; ED.course.modules.splice(mi,1); ED.sel={mi:0,li:0}; markDirty(); renderOutline(); renderPage(); }
function addPage(mi){ const m=ED.course.modules[mi]; m.lessons=m.lessons||[]; m.lessons.push({title:'Untitled page',blocks:[],topics:[]}); ED.sel={mi,li:m.lessons.length-1}; markDirty(); renderOutline(); renderPage(); }
function delPage(mi,li){ const m=ED.course.modules[mi]; const t=(m.lessons[li]||{}).title||'this page'; if(!confirm('Delete the page "'+t+'"? This cannot be undone once you save.'))return; syncPageFromDom(); m.lessons.splice(li,1); if(ED.sel.mi===mi&&ED.sel.li>=m.lessons.length)ED.sel.li=Math.max(0,m.lessons.length-1); markDirty(); renderOutline(); renderPage(); }
function addForum(mi){ syncPageFromDom(); const m=ED.course.modules[mi]; m.forums=Array.isArray(m.forums)?m.forums:[]; m.forums.push({id:'f-'+Math.random().toString(36).slice(2,9),name:'New forum',blocks:[]}); ED.sel={mi,fi:m.forums.length-1,forum:true}; markDirty(); renderOutline(); renderPage(); }
function delForum(mi,fi){ const f=ED.course.modules[mi].forums[fi]||{}; if(!confirm('Delete the forum "'+(f.name||'this forum')+'"? Any posts in it will no longer appear. This cannot be undone once you save.'))return; syncPageFromDom(); ED.course.modules[mi].forums.splice(fi,1); if(ED.sel.forum&&ED.sel.mi===mi&&ED.sel.fi===fi)ED.sel={mi:0,li:0}; markDirty(); renderOutline(); renderPage(); }
function moveModule(from,to,y,modEl){ if(from===to)return; syncPageFromDom(); const b=modEl.getBoundingClientRect(); let insert=(y<b.top+b.height/2)?to:to+1; const [mod]=ED.course.modules.splice(from,1); if(from<insert)insert--; insert=Math.max(0,Math.min(insert,ED.course.modules.length)); ED.course.modules.splice(insert,0,mod); ED.sel={mi:insert,li:0}; markDirty(); renderOutline(); renderPage(); }
function selectPage(mi,li){ syncPageFromDom(); ED.sel={mi,li}; renderOutline(); renderPage(); }
function selectForum(mi,fi){ syncPageFromDom(); ED.sel={mi,fi,forum:true}; renderOutline(); renderPage(); }
function movePage(fmi,fli,tmi,before){ syncPageFromDom(); const from=ED.course.modules[fmi].lessons; const [pg]=from.splice(fli,1); const to=ED.course.modules[tmi].lessons; if(fmi===tmi&&fli<before)before--; to.splice(before,0,pg); ED.sel={mi:tmi,li:before}; markDirty(); renderOutline(); renderPage(); }

/* ---------- Page + blocks ---------- */
const BLOCK_TYPES=[['richtext','Text'],['markdown','Markdown'],['heading','Heading'],['image','Image'],['video','Video / embed'],['file','File'],['divider','Divider']];
const BLOCK_ICON={richtext:'block-text',markdown:'block-markdown',heading:'block-heading',image:'block-image',video:'block-video',file:'block-file',divider:'block-divider'};
function blockIcon(t){ return el('img',{class:'ui-ic',src:ED.base+'/assets/img/icons/'+(BLOCK_ICON[t]||'block-text')+'.svg',width:16,height:16,alt:'','aria-hidden':'true'}); }
function renderPage(){
  if(window.RZ_hideImg) RZ_hideImg();
  const root=$('#edPage'); root.innerHTML='';
  if(ED.sel.forum){ return renderForum(root); }
  const pg=curPage();
  if(!pg){ root.append(el('p',{class:'muted'},'Select or add a page.')); return; }
  root.append(el('input',{class:'ed-pagetitle', value:pg.title||'', placeholder:'Page title', 'aria-label':'Page title',
    oninput:e=>{pg.title=e.target.value; markDirty(); const r=$('.ed-pagerow.is-active .ed-pagerow__t'); if(r)r.textContent=e.target.value||'Untitled page';}}));
  root.append(blocksArea(pg));
  root.append(renderQuiz(pg));
}
function renderForum(root){
  const f=curItem();
  if(!f){ root.append(el('p',{class:'muted'},'Select a forum.')); return; }
  root.append(el('div',{class:'ed-forumtitle'},[
    el('span',{class:'ed-forumtitle__ic'},'💬'),
    el('input',{class:'ed-pagetitle', value:f.name||'', placeholder:'Forum name', 'aria-label':'Forum name',
      oninput:e=>{f.name=e.target.value; markDirty(); const r=$('.ed-forumrow.is-active .ed-pagerow__t'); if(r)r.textContent=e.target.value||'Forum';}})
  ]));
  root.append(el('p',{class:'muted',style:'margin:-6px 0 12px;font-size:.88rem'},
    'Discussion prompt — add blocks (text, images, video/embeds) shown at the top of this forum to kick off the conversation.'));
  root.append(blocksArea(f));
}
// Shared block-editing area (used by pages and forums).
function blocksArea(item){
  const wrap=el('div',{});
  const blocksWrap=el('div',{class:'ed-blocks'});
  (item.blocks||[]).forEach((b,idx)=>blocksWrap.append(renderBlock(b,idx)));
  wrap.append(blocksWrap);
  const bar=el('div',{class:'ed-addbar'});
  BLOCK_TYPES.forEach(([t,label])=>bar.append(el('button',{class:'btn btn-outline btn-sm ed-addblock',type:'button',onclick:()=>addBlock(t)},[blockIcon(t),' '+label])));
  wrap.append(bar);
  return wrap;
}
function addBlock(type){ syncPageFromDom(); const it=curItem(); if(!it)return; const b={type}; if(type==='heading')b.level=2; if(type==='video')b.mode='embed'; it.blocks=it.blocks||[]; it.blocks.push(b); markDirty(); renderPage(); }
function moveBlock(idx,dir){ syncPageFromDom(); const it=curItem(); const j=idx+dir; if(j<0||j>=it.blocks.length)return; [it.blocks[idx],it.blocks[j]]=[it.blocks[j],it.blocks[idx]]; renderPage(); markDirty(); }
function delBlock(idx){ const it=curItem(); it.blocks.splice(idx,1); markDirty(); renderPage(); }

function renderBlock(b,idx){
  const box=el('div',{class:'ed-block', 'data-idx':idx});
  const tools=el('div',{class:'ed-block__tools'},[
    el('span',{class:'muted', style:'font-size:.75rem;text-transform:uppercase;letter-spacing:.04em'}, b.type),
    el('span',{},[
      el('button',{class:'ed-icon',type:'button',title:'Move up',onclick:()=>moveBlock(idx,-1)},'↑'),
      el('button',{class:'ed-icon',type:'button',title:'Move down',onclick:()=>moveBlock(idx,1)},'↓'),
      el('button',{class:'ed-icon',type:'button',title:'Delete block',onclick:()=>delBlock(idx)},'🗑')
    ])
  ]);
  box.append(tools);
  box.append(blockBody(b,idx));
  return box;
}
function blockBody(b,idx){
  if(b.type==='richtext'||b.type==='callout'){
    const ce=el('div',{class:'ed-rt', contenteditable:'true', 'data-field':'html', html:absHtml(b.html||''), oninput:markDirty});
    return el('div',{},[buildRTToolbar(ce),ce]);
  }
  if(b.type==='markdown'){ return el('textarea',{class:'ed-ta','data-field':'md',rows:'5',placeholder:'Write Markdown…',oninput:markDirty},b.md||''); }
  if(b.type==='heading'){ return el('div',{class:'ed-row'},[
    el('select',{class:'ed-sel','data-field':'level',style:'width:auto;flex:none;min-width:64px',onchange:markDirty}, [2,3,4].map(n=>el('option',{value:n,...(String(b.level||2)===String(n)?{selected:'selected'}:{})},'H'+n))),
    el('input',{class:'ed-inp','data-field':'text',style:'flex:1',value:b.text||'',placeholder:'Heading text…',oninput:markDirty})]); }
  if(b.type==='image'){
    const cur=b.align||'full'; const canWrap=(cur==='left'||cur==='right');
    const aligns=[['full','Full width'],['left','Left'],['center','Center'],['right','Right']];
    const alignRow=el('div',{class:'ed-row',style:'gap:6px;flex-wrap:wrap;align-items:center'},[
      el('span',{class:'muted',style:'font-size:.8rem'},'Align:'),
      ...aligns.map(([v,lbl])=>el('button',{class:'ed-chip'+(cur===v?' is-on':''),type:'button',onclick:()=>{b.align=v;if(v!=='left'&&v!=='right')b.wrap=false;markDirty();renderPage();}},lbl)),
      el('label',{class:'ed-radio',style:'font-size:.82rem'+(canWrap?'':';opacity:.45')},[
        el('input',{type:'checkbox',...(b.wrap&&canWrap?{checked:'checked'}:{}),disabled:!canWrap,onchange:e=>{b.wrap=e.target.checked;markDirty();renderPage();}}),' Wrap text around image'])
    ]);
    const prevStyle=cur==='center'?'margin:6px auto':cur==='right'?'margin:6px 0 6px auto':'margin:6px 0';
    return el('div',{},[
      resourceRow(b,'src','image','Image URL or pick from resources'),
      el('input',{class:'ed-inp','data-field':'alt',value:b.alt||'',placeholder:'Alt text (accessibility)',oninput:markDirty}),
      el('input',{class:'ed-inp','data-field':'caption',value:b.caption||'',placeholder:'Caption (optional)',oninput:markDirty}),
      alignRow,
      b.src?el('img',{src:absUrl(b.src),class:'ed-preview',alt:'',style:'display:block;'+prevStyle}):null ]); }
  if(b.type==='video'){ return el('div',{},[
    el('div',{class:'ed-row'},[
      el('label',{class:'ed-radio'},[el('input',{type:'radio',name:'vm'+idx,...(b.mode!=='file'?{checked:'checked'}:{}),onchange:()=>{b.mode='embed';markDirty();renderPage();}}),' Embed / link']),
      el('label',{class:'ed-radio'},[el('input',{type:'radio',name:'vm'+idx,...(b.mode==='file'?{checked:'checked'}:{}),onchange:()=>{b.mode='file';markDirty();renderPage();}}),' Self-hosted file'])
    ]),
    b.mode==='file'? resourceRow(b,'file','video','Pick a video file')
      : el('input',{class:'ed-inp','data-field':'url',value:b.url||'',placeholder:'YouTube, Vimeo, Google Drive, OneDrive, Dropbox, or .mp4 URL',oninput:markDirty}),
    el('p',{class:'muted',style:'font-size:.8rem'},'For OneDrive/Dropbox, use the “Embed” or share link.') ]); }
  if(b.type==='file'){ return el('div',{},[
    resourceRow(b,'href','file','Pick a file to attach'),
    el('input',{class:'ed-inp','data-field':'name',value:b.name||'',placeholder:'Link text (e.g. Worksheet.pdf)',oninput:markDirty}),
    el('input',{class:'ed-inp','data-field':'desc',value:b.desc||'',placeholder:'Description (optional)',oninput:markDirty}) ]); }
  if(b.type==='divider'){ return el('hr'); }
  return el('div',{});
}
function resourceRow(b,field,kind,ph){
  const inp=el('input',{class:'ed-inp','data-field':field,value:b[field]||'',placeholder:ph,oninput:markDirty});
  const btn=el('button',{class:'btn btn-outline btn-sm',type:'button',onclick:()=>openPicker(kind,url=>{b[field]=url;if(field==='href'&&!b.name)b.name=url.split('/').pop();if(field==='src'||field==='file')0;markDirty();renderPage();})},'Pick / upload');
  return el('div',{class:'ed-row'},[inp,btn]);
}
// Clean pasted HTML (Google Docs / Word): keep meaningful structure + formatting,
// strip classes, ids, junk wrappers, and all but a safe subset of inline styles.
function cleanPasteHtml(html){
  const KEEP=new Set(['P','BR','DIV','SPAN','B','STRONG','I','EM','U','S','STRIKE','SUB','SUP',
    'H1','H2','H3','H4','H5','H6','UL','OL','LI','A','IMG','BLOCKQUOTE','TABLE','THEAD','TBODY',
    'TR','TD','TH','HR','CODE','PRE','FONT']);
  const STYLE=['font-weight','font-style','text-decoration','text-decoration-line','text-align',
    'color','background-color','margin-left','vertical-align'];
  const ATTR={A:['href','title'],IMG:['src','alt'],TD:['colspan','rowspan'],TH:['colspan','rowspan']};
  const tmp=document.createElement('div'); tmp.innerHTML=html;
  tmp.querySelectorAll('style,script,meta,link,title,head').forEach(n=>n.remove());
  (function walk(node){
    [...node.childNodes].forEach(n=>{
      if(n.nodeType===8){ n.remove(); return; }          // comments
      if(n.nodeType!==1) return;                          // text stays
      const tag=n.nodeName;
      const raw=(n.getAttribute&&(n.getAttribute('style')||''))||'';
      // Unwrap unknown tags AND Google's fake-bold/italic containers
      // (<b style="font-weight:normal"> is a container, not bold). Clean the
      // subtree first so the promoted children are already sanitized.
      const unwrap = !KEEP.has(tag)
        || ((tag==='B'||tag==='STRONG') && /font-weight:\s*(normal|400)/i.test(raw))
        || ((tag==='I'||tag==='EM') && /font-style:\s*normal/i.test(raw));
      if(unwrap){ walk(n); while(n.firstChild)node.insertBefore(n.firstChild,n); n.remove(); return; }
      const keep=ATTR[tag]||[];
      [...n.attributes].forEach(a=>{ if(a.name!=='style' && !keep.includes(a.name.toLowerCase())) n.removeAttribute(a.name); });
      const out=[];
      (n.getAttribute('style')||'').split(';').forEach(d=>{ const i=d.indexOf(':'); if(i<0)return;
        const p=d.slice(0,i).trim().toLowerCase(), v=d.slice(i+1).trim(), lv=v.toLowerCase();
        if(!STYLE.includes(p))return;
        if(p==='font-weight'&&(lv==='normal'||lv==='400'))return;
        if(p==='font-style'&&lv==='normal')return;
        if((p==='text-decoration'||p==='text-decoration-line')&&lv==='none')return;
        if(p==='color'&&/^(#000000|#000|rgb\(0, ?0, ?0\)|windowtext)$/.test(lv))return;
        if(p==='background-color'&&/^(transparent|#ffffff|#fff|rgb\(255, ?255, ?255\))$/.test(lv))return;
        out.push(p+':'+v);
      });
      out.length?n.setAttribute('style',out.join(';')):n.removeAttribute('style');
      if(tag==='TABLE'){ n.style.borderCollapse='collapse'; if(!n.style.margin)n.style.margin='1rem 0'; }
      if(tag==='TD'||tag==='TH'){ if(!/border/.test(n.getAttribute('style')||''))n.style.border='1px solid #cbd6e6'; if(!/padding/.test(n.getAttribute('style')||''))n.style.padding='6px'; }
      if(tag==='IMG'){ if(!/max-width/.test(n.getAttribute('style')||''))n.style.maxWidth='100%'; if(!/height/.test(n.getAttribute('style')||''))n.style.height='auto'; }
      walk(n);
      // Drop pointless empty/attribute-less inline wrappers Google loves to add.
      const inline=['SPAN','B','I','EM','STRONG','U','FONT'].includes(tag);
      if(inline && !n.getAttribute('style') && (!n.textContent.trim() ? !n.querySelector('img,br') : (tag==='SPAN'))){
        while(n.firstChild)node.insertBefore(n.firstChild,n); n.remove();
      }
    });
  })(tmp);
  return tmp.innerHTML;
}

// A word-processor-style toolbar bound to one contenteditable (ce). Uses
// document.execCommand (works offline, no libraries) with saved-selection so
// dropdowns and color pickers don't lose the highlighted text.
function buildRTToolbar(ce){
  try{ document.execCommand('styleWithCSS',false,true); }catch(e){}
  let saved=null;
  const save=()=>{ const s=window.getSelection(); if(s.rangeCount && ce.contains(s.anchorNode)) saved=s.getRangeAt(0).cloneRange(); };
  const restore=()=>{ if(saved){ const s=window.getSelection(); s.removeAllRanges(); s.addRange(saved); } };
  ce.addEventListener('keyup',save); ce.addEventListener('mouseup',save); ce.addEventListener('focus',save);
  const exec=(cmd,val)=>{ ce.focus(); restore(); try{document.execCommand(cmd,false,val===undefined?null:val);}catch(e){} markDirty(); save(); };

  // Clean paste: keep formatting (tables, bold, lists, links…) from Google/Word
  // but strip the junk; fall back to plain text when no HTML is on the clipboard.
  ce.addEventListener('paste',e=>{ e.preventDefault();
    const cd=e.clipboardData||window.clipboardData;
    const html=cd&&cd.getData?cd.getData('text/html'):'';
    ce.focus();
    if(html&&html.trim()) document.execCommand('insertHTML',false,cleanPasteHtml(html));
    else document.execCommand('insertText',false,(cd?cd.getData('text/plain'):'')||'');
    markDirty(); });

  // Reliable paragraph indent (adjusts margin-left on the block; execCommand
  // 'indent' inconsistently wraps in blockquote across browsers).
  const CELL='border:1px solid #cbd6e6;padding:8px;min-width:40px';
  function indentBlock(inc){ ce.focus(); restore();
    let n=window.getSelection().anchorNode;
    while(n&&n!==ce&&!(n.nodeType===1&&/^(P|DIV|LI|H1|H2|H3|H4|BLOCKQUOTE)$/.test(n.nodeName))) n=n.parentNode;
    if(!n||n===ce){ try{document.execCommand(inc?'indent':'outdent');}catch(e){} markDirty(); return; }
    let cur=parseInt(n.style.marginLeft)||0; cur=Math.max(0,cur+(inc?40:-40));
    n.style.marginLeft=cur?cur+'px':''; markDirty(); save();
  }
  // --- Tables ---
  function currentCell(){ let n=window.getSelection().anchorNode; while(n&&n!==ce){ if(n.nodeType===1&&(n.nodeName==='TD'||n.nodeName==='TH'))return n; n=n.parentNode; } return null; }
  function newCell(tag){ const c=document.createElement(tag); c.style.cssText=CELL+(tag==='TH'?';background:#eef2f9;font-weight:700':''); c.innerHTML='&nbsp;'; return c; }
  function tableInsert(){ ce.focus(); restore();
    const r=parseInt(prompt('Number of rows:','2'))||0, c=parseInt(prompt('Number of columns:','2'))||0;
    if(r<1||c<1) return; const header=confirm('Add a header row?');
    let h='<table style="border-collapse:collapse;margin:1rem 0">';
    for(let i=0;i<r;i++){ h+='<tr>'; for(let j=0;j<c;j++){ const th=(header&&i===0); const st=CELL+(th?';background:#eef2f9;font-weight:700':''); h+='<'+(th?'th':'td')+' style="'+st+'">&nbsp;</'+(th?'th':'td')+'>'; } h+='</tr>'; }
    h+='</table><p><br></p>'; document.execCommand('insertHTML',false,h); markDirty(); save();
  }
  function tableAddRow(){ restore(); const cell=currentCell(); if(!cell)return alert('Click inside a table first.');
    const tr=cell.parentNode; const nr=document.createElement('tr');
    for(let j=0;j<tr.children.length;j++) nr.append(newCell('TD')); tr.after(nr); markDirty(); }
  function tableAddCol(){ restore(); const cell=currentCell(); if(!cell)return alert('Click inside a table first.');
    const idx=[...cell.parentNode.children].indexOf(cell); const table=cell.closest('table');
    [...table.rows].forEach(row=>{ const ref=row.children[idx]; const c=newCell(ref&&ref.nodeName==='TH'?'TH':'TD'); ref?ref.after(c):row.append(c); }); markDirty(); }
  function tableDelRow(){ restore(); const cell=currentCell(); if(!cell)return; const table=cell.closest('table');
    if(table.rows.length<=1) table.remove(); else cell.parentNode.remove(); markDirty(); }
  function tableDelCol(){ restore(); const cell=currentCell(); if(!cell)return;
    const idx=[...cell.parentNode.children].indexOf(cell); const table=cell.closest('table');
    [...table.rows].forEach(row=>{ if(row.children[idx])row.children[idx].remove(); });
    if(!table.rows[0]||table.rows[0].children.length===0) table.remove(); markDirty(); }
  function tableDelete(){ restore(); const cell=currentCell(); if(cell){ const t=cell.closest('table'); if(t)t.remove(); markDirty(); } }

  const tb=el('div',{class:'ed-rt-toolbar'});
  const sep=()=>el('span',{class:'ed-tb-sep'});
  const btn=(label,cmd,val,title)=>el('button',{class:'ed-tbtn',type:'button',title:title||label,
    onmousedown:e=>{e.preventDefault(); exec(cmd,val);}}, label);
  const dd=(title,opts,cmd)=>{
    const s=el('select',{class:'ed-tbsel',title:title,onmousedown:save,onchange:e=>{const v=e.target.value; e.target.selectedIndex=0; if(v!=='')exec(cmd,v);}},
      [el('option',{value:''},title)].concat(opts.map(([v,l])=>el('option',{value:v},l))));
    return s;
  };
  const color=(title,def,cmd)=>el('input',{type:'color',class:'ed-tbcolor',title:title,value:def,
    onmousedown:save,oninput:e=>exec(cmd,e.target.value)});
  const link=el('button',{class:'ed-tbtn',type:'button',title:'Insert link',
    onmousedown:e=>{e.preventDefault(); save(); const u=prompt('Link URL:','https://'); if(u)exec('createLink',u);}},'🔗');

  tb.append(
    dd('Paragraph',[['P','Normal'],['H2','Heading'],['H3','Subheading'],['BLOCKQUOTE','Quote']],'formatBlock'),
    dd('Size',[['2','Small'],['3','Normal'],['4','Large'],['5','X-Large'],['6','Huge']],'fontSize'),
    sep(),
    btn('B','bold',undefined,'Bold'), btn('I','italic',undefined,'Italic'),
    btn('U','underline',undefined,'Underline'), btn('S','strikeThrough',undefined,'Strikethrough'),
    sep(),
    color('Text color','#16202e','foreColor'), color('Highlight','#ffe066','hiliteColor'),
    sep(),
    btn('⯇','justifyLeft',undefined,'Align left'), btn('≡','justifyCenter',undefined,'Center'),
    btn('⯈','justifyRight',undefined,'Align right'), btn('▤','justifyFull',undefined,'Justify'),
    sep(),
    btn('•','insertUnorderedList',undefined,'Bulleted list'), btn('1.','insertOrderedList',undefined,'Numbered list'),
    el('button',{class:'ed-tbtn',type:'button',title:'Decrease indent',onmousedown:e=>{e.preventDefault();indentBlock(false);}},'⇤'),
    el('button',{class:'ed-tbtn',type:'button',title:'Increase indent',onmousedown:e=>{e.preventDefault();indentBlock(true);}},'⇥'),
    sep(),
    el('select',{class:'ed-tbsel',title:'Table',onmousedown:save,onchange:e=>{const v=e.target.value;e.target.selectedIndex=0;
      ({insert:tableInsert,addrow:tableAddRow,addcol:tableAddCol,delrow:tableDelRow,delcol:tableDelCol,del:tableDelete}[v]||(()=>{}))();}},
      [el('option',{value:''},'⊞ Table'),el('option',{value:'insert'},'Insert table…'),el('option',{value:'addrow'},'Add row'),
       el('option',{value:'addcol'},'Add column'),el('option',{value:'delrow'},'Delete row'),el('option',{value:'delcol'},'Delete column'),el('option',{value:'del'},'Delete table')]),
    sep(),
    link, btn('⛓','unlink',undefined,'Remove link'), btn('⌫','removeFormat',undefined,'Clear formatting'),
    sep(),
    btn('↶','undo',undefined,'Undo'), btn('↷','redo',undefined,'Redo')
  );
  return tb;
}

function doCmd(cmd){ if(cmd.startsWith('formatBlock:')){document.execCommand('formatBlock',false,cmd.split(':')[1]);} else if(cmd==='createLink'){const u=prompt('Link URL:');if(u)document.execCommand('createLink',false,u);} else document.execCommand(cmd,false,null); markDirty(); }
function absUrl(p){ return /^https?:/.test(p)?p:(ED.base+'/courses/'+ED.slug+'/'+p.replace(/^\//,'')); }
// Course-relative media paths (e.g. media/x.png) resolve under /courses/<slug>/ in
// the reader, but not on the editor page — so show them absolute here and store
// them relative again on save.
function courseBase(){ return ED.base+'/courses/'+ED.slug+'/'; }
function absHtml(html){ return (html||'').replace(/(\s(?:src|href))="(?!https?:|\/|data:|#|mailto:)([^"]*)"/gi,'$1="'+courseBase()+'$2"'); }
function relHtml(html){ return (html||'').split('="'+courseBase()).join('="'); }

/* read current page's DOM editors back into the model before nav/save */
function syncPageFromDom(){
  const it=curItem(); if(!it||!Array.isArray(it.blocks))return;
  document.querySelectorAll('#edPage .ed-block').forEach(box=>{
    const idx=+box.dataset.idx; const b=it.blocks[idx]; if(!b)return;
    box.querySelectorAll('[data-field]').forEach(f=>{
      const k=f.dataset.field;
      if(f.classList.contains('ed-rt')) b[k]=relHtml(f.innerHTML);
      else b[k]=f.value;
    });
    if(b.type==='heading')b.level=+(b.level||2);
  });
}

/* ---------- Minimal quiz editor ---------- */
function renderQuiz(pg){
  const wrap=el('div',{class:'ed-quiz'});
  const has=!!(pg.quiz&&pg.quiz.questions);
  wrap.append(el('label',{class:'ed-quiz__toggle'},[
    el('input',{type:'checkbox',...(has?{checked:'checked'}:{}),onchange:e=>{ if(e.target.checked){pg.quiz=pg.quiz||{pass:70,questions:[]};}else{delete pg.quiz;} markDirty(); renderPage(); }}),
    ' Knowledge check (quiz) on this page']));
  if(!has) return wrap;
  wrap.append(el('div',{class:'ed-row'},[el('label',{class:'muted'},'Pass %'),
    el('input',{class:'ed-inp',type:'number',min:'0',max:'100',style:'max-width:90px',value:pg.quiz.pass||70,oninput:e=>{pg.quiz.pass=+e.target.value;markDirty();}})]));
  (pg.quiz.questions||[]).forEach((q,qi)=>{
    const qb=el('div',{class:'ed-qbox'});
    qb.append(el('div',{class:'ed-row'},[
      el('input',{class:'ed-inp',value:q.text||'',placeholder:'Question '+(qi+1),oninput:e=>{q.text=e.target.value;markDirty();}}),
      el('button',{class:'ed-icon',type:'button',title:'Delete question',onclick:()=>{pg.quiz.questions.splice(qi,1);markDirty();renderPage();}},'🗑')]));
    (q.options||[]).forEach((o,oi)=>{
      qb.append(el('div',{class:'ed-row ed-opt'},[
        el('input',{type:'checkbox',title:'Correct',...(o.correct?{checked:'checked'}:{}),onchange:e=>{o.correct=e.target.checked;markDirty();}}),
        el('input',{class:'ed-inp',value:o.text||'',placeholder:'Option '+(oi+1),oninput:e=>{o.text=e.target.value;markDirty();}}),
        el('button',{class:'ed-icon',type:'button',onclick:()=>{q.options.splice(oi,1);markDirty();renderPage();}},'✕')]));
    });
    qb.append(el('button',{class:'btn btn-ghost btn-sm',type:'button',onclick:()=>{q.options=q.options||[];q.options.push({text:'',correct:false});markDirty();renderPage();}},'＋ Option'));
    wrap.append(qb);
  });
  wrap.append(el('button',{class:'btn btn-outline btn-sm',type:'button',onclick:()=>{pg.quiz.questions.push({qid:'q'+Date.now(),type:'single',text:'',options:[{text:'',correct:true},{text:'',correct:false}]});markDirty();renderPage();}},'＋ Add question'));
  return wrap;
}

/* ---------- Resource picker ---------- */
function openPicker(kind,cb){ ED.pick=cb; const list=$('#edPickerList'); list.innerHTML='';
  const items=ED.resources.filter(r=>kind==='file'?true:r.kind===kind);
  if(!items.length) list.append(el('p',{class:'muted'},'No '+kind+' files yet — upload one above.'));
  items.forEach(r=>{ list.append(el('button',{class:'ed-pick',type:'button',onclick:()=>{cb(r.path);closePicker();}},[
    el('span',{class:'ed-pick__k'}, r.kind==='image'?'🖼':r.kind==='video'?'🎬':r.kind==='pdf'?'📄':'📎'),
    el('span',{}, r.label||r.name), el('span',{class:'muted',style:'margin-left:auto;font-size:.78rem'}, Math.max(1,Math.round(r.size/1024))+' KB')])); });
  $('#edPicker').hidden=false;
}
function closePicker(){ $('#edPicker').hidden=true; ED.pick=null; }
$('#edPickerClose').addEventListener('click',closePicker);
$('#edUpload').addEventListener('change',async e=>{
  const f=e.target.files[0]; if(!f)return; $('#edUploadStatus').textContent='Uploading…';
  const fd=new FormData(); fd.append('file',f); fd.append('_csrf',ED.csrf);
  const r=await fetch(ED.base+'/api/editor/'+ED.slug+'/upload',{method:'POST',headers:{'X-CSRF-Token':ED.csrf},body:fd});
  const d=await r.json();
  if(d.ok){ ED.resources.push({path:d.path,name:d.path.split('/').pop(),kind:guessKind(d.path),size:f.size,label:''}); $('#edUploadStatus').textContent='Uploaded.'; if(ED.pick){ED.pick(d.path);closePicker();} }
  else { $('#edUploadStatus').textContent=d.error||'Upload failed'; }
  e.target.value='';
});
function guessKind(p){const x=p.split('.').pop().toLowerCase();if(['png','jpg','jpeg','gif','webp','svg'].includes(x))return'image';if(['mp4','webm','m4v','mov'].includes(x))return'video';if(x==='pdf')return'pdf';return'file';}

/* ---------- Concurrent-edit protection: lock/lease + conflict guard ---------- */
function setReadonly(ro){ ED.readonly=ro; document.querySelector('.ed-wrap').classList.toggle('is-readonly',ro); $('#edSave').disabled=ro; }
function showLockBar(html){ const b=$('#edLockBar'); b.innerHTML=html; b.hidden=false; }
function hideLockBar(){ const b=$('#edLockBar'); b.hidden=true; b.innerHTML=''; }
function lockAPI(path,body){ return fetch(ED.base+'/api/editor/'+ED.slug+'/lock'+path,{method:'POST',
  headers:{'X-CSRF-Token':ED.csrf,'Content-Type':'application/x-www-form-urlencoded'},
  body:new URLSearchParams(Object.assign({_csrf:ED.csrf},body||{}))}).then(r=>r.json()); }
async function acquireLock(force){
  try{ const d=await lockAPI('',{force:force?'1':'0'});
    if(d.ok){ hideLockBar(); setReadonly(false); startHeartbeat(); return true; }
    offerTakeover(d.lockedBy||'Another user'); setReadonly(true); return false;
  }catch(e){ return true; }   // don't hard-block editing if the lock service hiccups
}
function offerTakeover(who){
  showLockBar('🔒 <span>'+esc(who)+' is currently editing this course. To avoid overwriting their work you are in <strong>read-only</strong> mode.</span> '
    +'<button class="btn btn-outline btn-sm" id="edTakeover" type="button">Take over editing</button>');
  const b=$('#edTakeover'); if(b) b.addEventListener('click',()=>{ if(confirm('Take over editing? '+who+' may lose unsaved changes.')) acquireLock(true); });
}
function startHeartbeat(){ if(ED.hb)clearInterval(ED.hb); ED.hb=setInterval(async()=>{
  try{ const d=await lockAPI('/heartbeat'); if(!d.ok){ clearInterval(ED.hb); ED.hb=null; lockLost(); } }catch(e){}
},30000); }
function lockLost(){ setReadonly(true);
  showLockBar('🔒 <span>Someone else has taken over editing this course. You are now in <strong>read-only</strong> mode.</span> '
    +'<button class="btn btn-outline btn-sm" id="edReload" type="button">Reload</button>');
  const b=$('#edReload'); if(b) b.addEventListener('click',()=>location.reload());
}

/* ---------- Save ---------- */
async function save(){
  if(ED.readonly){ alert('You are in read-only mode — you do not hold the edit lock for this course.'); return; }
  syncPageFromDom();
  ED.course.title=$('#edTitle').value;
  setStatus('Saving…'); $('#edSave').disabled=true;
  try{
    const r=await fetch(ED.base+'/api/editor/'+ED.slug,{method:'POST',
      headers:{'X-CSRF-Token':ED.csrf,'Content-Type':'application/json'},
      body:JSON.stringify({title:ED.course.title,tagline:ED.course.tagline||'',modules:ED.course.modules,base_rev:ED.baseRev})});
    const d=await r.json(); $('#edSave').disabled=ED.readonly;
    if(d.ok){ ED.dirty=false; ED.baseRev=d.rev; setStatus('Saved ✓'); return; }
    if(r.status===409){
      setStatus('Not saved — '+(d.error||'conflict'));
      if(d.lockedBy){ setReadonly(true); offerTakeover(d.lockedBy); }
      alert(d.error||'This course changed since you opened it. Reload and re-apply your edits.');
      return;
    }
    setStatus('Error: '+(d.error||'save failed'));
  }catch(e){ $('#edSave').disabled=ED.readonly; setStatus('Error saving'); }
}
/* ---------- Course settings ---------- */
function openSettings(){
  const s=ED.settings||{};
  document.getElementById('setTagline').value=ED.course.tagline||s.tagline||'';
  document.getElementById('setCpe').value=s.cpe_hours||0;
  document.getElementById('setGt').value=s.gt_hours||0;
  document.getElementById('setExpiry').value=s.enroll_days||0;
  document.getElementById('setSeq').value=s.sequential||'inherit';
  document.getElementById('setForum').value=s.forum||'on';
  const pre=document.getElementById('setPrereq'); pre.innerHTML='<option value="0">— none —</option>';
  (ED.otherCourses||[]).forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.title;if(c.id===s.prereq_id)o.selected=true;pre.append(o);});
  document.getElementById('edSettingsStatus').textContent='';
  document.getElementById('edSettings').hidden=false;
}
function closeSettings(){ document.getElementById('edSettings').hidden=true; }
$('#edSettingsBtn').addEventListener('click',openSettings);
$('#edSettingsClose').addEventListener('click',closeSettings);
$('#edSettingsCancel').addEventListener('click',closeSettings);
$('#edSettingsSave').addEventListener('click',async ()=>{
  const st=document.getElementById('edSettingsStatus');
  if(ED.readonly){ st.textContent='Read-only — you do not hold the edit lock.'; return; }
  st.textContent='Saving…';
  const fd=new URLSearchParams({
    _csrf:ED.csrf,
    tagline:document.getElementById('setTagline').value,
    cpe_hours:document.getElementById('setCpe').value,
    gt_hours:document.getElementById('setGt').value,
    enroll_days:document.getElementById('setExpiry').value,
    prereq_id:document.getElementById('setPrereq').value,
    sequential:document.getElementById('setSeq').value,
    forum:document.getElementById('setForum').value
  });
  try{
    const r=await fetch(ED.base+'/api/editor/'+ED.slug+'/settings',{method:'POST',headers:{'X-CSRF-Token':ED.csrf,'Content-Type':'application/x-www-form-urlencoded'},body:fd});
    const d=await r.json();
    if(d.ok){ ED.course.tagline=document.getElementById('setTagline').value; st.textContent='Saved ✓'; setTimeout(closeSettings,700); }
    else st.textContent='Error: '+(d.error||'failed');
  }catch(e){ st.textContent='Error saving'; }
});

$('#edSave').addEventListener('click',save);
$('#edTitle').addEventListener('input',markDirty);
window.addEventListener('beforeunload',e=>{
  try{ if(navigator.sendBeacon){ const fd=new FormData(); fd.append('_csrf',ED.csrf); navigator.sendBeacon(ED.base+'/api/editor/'+ED.slug+'/lock/release',fd); } }catch(_){}
  if(ED.dirty){e.preventDefault();e.returnValue='';}
});
document.addEventListener('keydown',e=>{ if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();save();} });
// --- Resize handles for images + table columns inside rich-text editors ---
window.RZ_hideImg=()=>{};
(function(){
  let handle=null, sel=null, d=null;
  function ensure(){ if(handle)return; handle=document.createElement('div'); handle.className='ed-rz-handle'; handle.style.display='none'; document.body.appendChild(handle);
    handle.addEventListener('mousedown',ev=>{ ev.preventDefault(); if(!sel)return; const r=sel.getBoundingClientRect(); d={x:ev.clientX,w:r.width}; document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up); }); }
  function place(){ if(!sel||!handle)return; const r=sel.getBoundingClientRect(); handle.style.display='block'; handle.style.left=(window.scrollX+r.right-7)+'px'; handle.style.top=(window.scrollY+r.bottom-7)+'px'; }
  function hide(){ if(handle)handle.style.display='none'; if(sel&&sel.classList)sel.classList.remove('ed-rz-sel'); sel=null; }
  window.RZ_hideImg=hide;
  function mv(ev){ if(!sel)return; const w=Math.max(24,d.w+(ev.clientX-d.x)); sel.style.width=Math.round(w)+'px'; sel.style.height='auto'; place(); }
  function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); markDirty(); }
  document.addEventListener('click',ev=>{
    if(ev.target.tagName==='IMG' && ev.target.closest('.ed-rt')){ hide(); sel=ev.target; sel.classList.add('ed-rz-sel'); ensure(); place(); }
    else if(!ev.target.classList||!ev.target.classList.contains('ed-rz-handle')){ hide(); }
  });
  window.addEventListener('scroll',()=>{ if(sel)place(); },true);
  window.addEventListener('resize',()=>{ if(sel)place(); });
  // Table column resize (drag a cell's right edge).
  let col=null;
  document.addEventListener('mousemove',ev=>{ if(col)return; const c=ev.target.closest&&ev.target.closest('.ed-rt td,.ed-rt th'); if(!c)return; const r=c.getBoundingClientRect(); c.style.cursor=(ev.clientX>r.right-7)?'col-resize':''; });
  document.addEventListener('mousedown',ev=>{ const c=ev.target.closest&&ev.target.closest('.ed-rt td,.ed-rt th'); if(!c)return; const r=c.getBoundingClientRect(); if(ev.clientX>r.right-7){ ev.preventDefault(); const table=c.closest('table'); const idx=[...c.parentNode.children].indexOf(c); col={table,idx,x:ev.clientX,w:r.width}; document.addEventListener('mousemove',cmv); document.addEventListener('mouseup',cup); } });
  function cmv(ev){ if(!col)return; const w=Math.max(30,col.w+(ev.clientX-col.x)); [...col.table.rows].forEach(row=>{ const cell=row.children[col.idx]; if(cell)cell.style.width=w+'px'; }); }
  function cup(){ document.removeEventListener('mousemove',cmv); document.removeEventListener('mouseup',cup); col=null; markDirty(); }
})();
load().then(acquireLock);
</script>

<style>
.ed-topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.ed-topbar__left,.ed-topbar__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ed-title{font-size:1.15rem;font-weight:700;border:1.5px solid transparent;border-radius:8px;padding:6px 8px;min-width:200px;background:var(--surface-alt)}
.ed-title:focus{border-color:var(--line-strong);background:#fff}
.ed-wrap{display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start}
.ed-outline{position:sticky;top:12px;background:var(--surface-alt);border-radius:12px;padding:12px;max-height:calc(100vh - 90px);overflow:auto}
.ed-mod{margin-bottom:14px}
.ed-mod__head{display:flex;gap:4px;align-items:center;margin-bottom:6px}
.ed-mod__title{flex:1;font-weight:700;border:1.5px solid transparent;background:transparent;border-radius:6px;padding:4px 6px}
.ed-mod__title:focus{background:#fff;border-color:var(--line-strong)}
.ed-icon{border:0;background:transparent;cursor:pointer;font-size:.95rem;padding:2px 5px;border-radius:6px;color:var(--ink-soft)}
.ed-icon:hover{background:rgba(0,0,0,.07)}
.ed-pages{min-height:8px;display:flex;flex-direction:column;gap:4px;border-radius:8px}
.ed-pages.drop{outline:2px dashed var(--gold-500);outline-offset:2px}
.ed-pagerow{display:flex;align-items:center;gap:7px;padding:7px 8px;background:#fff;border:1.5px solid var(--line);border-radius:8px;cursor:pointer;font-size:.9rem}
.ed-pagerow.is-active{border-color:var(--navy-500);box-shadow:0 0 0 2px rgba(28,58,102,.12)}
.ed-pagerow.dragging{opacity:.4}
.ed-pagerow__t{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ed-grip{cursor:grab;color:var(--ink-soft)}
.ed-mgrip{cursor:grab;color:var(--ink-soft);font-size:.95rem;padding:2px 4px 2px 0;user-select:none;align-self:center}
.ed-del{color:var(--danger)}
.ed-del:hover{background:var(--danger);color:#fff}
.ed-mod.dragging{opacity:.5}
.ed-mod.drop-mod{outline:2px dashed var(--gold-500);outline-offset:3px;border-radius:8px}
.ed-pagedel{opacity:0;margin-left:auto;flex:none}
.ed-pagerow:hover .ed-pagedel,.ed-pagerow.is-active .ed-pagedel{opacity:.65}
.ed-pagedel:hover{opacity:1 !important}
.ed-badge{font-size:.68rem;background:var(--navy-50,#eef);color:var(--navy-700);padding:1px 6px;border-radius:999px}
.ed-addmod{width:100%;margin-top:6px}
.ed-forums{margin:6px 0 2px;padding-left:2px}
.ed-forumrow{display:flex;align-items:center;gap:6px;margin:4px 0}
.ed-fic{font-size:.95rem}
.ed-finp{font-size:.85rem;padding:6px 8px;flex:1}
.ed-addforum{border:1px dashed var(--line-strong);background:transparent;color:var(--ink-soft);border-radius:8px;
  padding:5px 10px;font-size:.82rem;cursor:pointer;margin-top:2px}
.ed-addforum:hover{border-color:var(--navy-500);color:var(--navy-700)}
.ed-page{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;min-height:300px}
.ed-pagetitle{width:100%;font-size:1.3rem;font-weight:700;border:0;border-bottom:2px solid var(--surface-alt);padding:6px 2px;margin-bottom:14px}
.ed-forumtitle{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.ed-forumtitle__ic{font-size:1.4rem}
.ed-forumtitle .ed-pagetitle{margin-bottom:0}
.ed-pagetitle:focus{outline:none;border-color:var(--gold-500)}
.ed-block{border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:12px;background:var(--surface,#fff)}
.ed-block__tools{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.ed-rt-toolbar{display:flex;gap:3px;margin-bottom:6px;flex-wrap:wrap;align-items:center;
  background:var(--surface-alt);border:1px solid var(--line);border-radius:8px;padding:5px 6px}
.ed-tb-sep{width:1px;align-self:stretch;background:var(--line-strong);margin:2px 3px}
.ed-tbtn{min-width:29px;height:29px;border:1px solid var(--line-strong);background:#fff;border-radius:6px;
  cursor:pointer;font-size:.85rem;padding:0 7px;line-height:1;color:var(--ink)}
.ed-tbtn:hover{background:var(--navy-800);color:#fff;border-color:var(--navy-800)}
.ed-tbsel{height:29px;border:1px solid var(--line-strong);border-radius:6px;font-size:.8rem;background:#fff;padding:0 4px;max-width:110px}
.ed-tbcolor{width:29px;height:29px;border:1px solid var(--line-strong);border-radius:6px;padding:2px;cursor:pointer;background:#fff}
.ed-icon.is-on{background:var(--navy-800);color:#fff}
.ed-chip{border:1.5px solid var(--line-strong);background:#fff;border-radius:999px;padding:4px 11px;font-size:.8rem;cursor:pointer}
.ed-chip.is-on{background:var(--navy-800);color:#fff;border-color:var(--navy-800)}
.ed-mforum{background:var(--surface-alt,#eef2f9);border-radius:8px;padding:8px 10px;margin:2px 0 8px}
.ed-rt{min-height:70px;border:1.5px solid var(--line-strong);border-radius:8px;padding:8px 10px;background:#fff}
.ed-rt img{max-width:100%;height:auto}
.ed-rt img.ed-rz-sel{outline:2px solid var(--navy-500);outline-offset:1px}
.ed-rt table{max-width:100%}
.ed-rz-handle{position:absolute;width:14px;height:14px;background:var(--navy-800);border:2px solid #fff;
  border-radius:3px;cursor:nwse-resize;z-index:200;box-shadow:0 1px 3px rgba(0,0,0,.35)}
.ed-rt:focus{outline:none;border-color:var(--gold-500)}
.ed-ta,.ed-inp,.ed-sel{width:100%;border:1.5px solid var(--line-strong);border-radius:8px;padding:8px 10px;font:inherit;margin:3px 0}
.ed-ta{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.9rem}
.ed-row{display:flex;gap:8px;align-items:center}
.ed-row .ed-inp{flex:1}
.ed-radio{display:inline-flex;align-items:center;gap:4px;font-size:.9rem}
.ed-preview{max-width:220px;border-radius:8px;margin-top:6px;display:block}
.ed-addbar{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 4px;padding-top:10px;border-top:1px dashed var(--line)}
.ed-quiz{margin-top:16px;padding-top:12px;border-top:2px solid var(--surface-alt)}
.ed-quiz__toggle{display:flex;align-items:center;gap:8px;font-weight:600}
.ed-qbox{background:var(--surface-alt);border-radius:8px;padding:10px;margin:8px 0}
.ed-opt{margin-left:16px}
.ed-modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:100}
.ed-modal[hidden]{display:none}
.ed-modal__box{background:#fff;border-radius:12px;width:min(520px,92vw);max-height:80vh;display:flex;flex-direction:column}
.ed-modal__head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--line)}
.ed-modal__body{padding:14px 16px;overflow:auto}
.ed-picker-list{display:flex;flex-direction:column;gap:4px;margin-top:12px}
.ed-pick{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;text-align:left}
.ed-pick:hover{background:var(--surface-alt)}
.ed-pick__k{font-size:1.1rem}
@media (max-width:820px){
  .ed-wrap{grid-template-columns:1fr}
  .ed-outline{position:static;max-height:none}
}
</style>
