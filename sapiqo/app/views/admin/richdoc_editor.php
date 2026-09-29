<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $saveUrl */
/** @var string $resetUrl */
/** @var string $backUrl */
/** @var string $backLabel */
/** @var string $bodyHtml */
/** @var bool $isOverride */
?>
<div class="page-head">
  <h1><?= e($title) ?></h1>
  <?php if ($subtitle): ?><p><?= e($subtitle) ?></p><?php endif; ?>
</div>

<div class="card">
  <div class="ed-topbar">
    <div class="ed-topbar__left">
      <span id="rdStatus" class="muted">Loaded</span>
    </div>
    <div class="ed-topbar__right">
      <a class="btn" href="<?= e(url($backUrl)) ?>"><?= e($backLabel) ?></a>
      <?php if ($isOverride): ?>
        <form method="post" action="<?= e(url($resetUrl)) ?>" onsubmit="return confirm('Revert to the shipped default? Your edits on this page will be discarded.');" style="display:inline">
          <?= csrf_field() ?>
          <button class="btn" type="submit">Revert to shipped default</button>
        </form>
      <?php endif; ?>
      <button class="btn btn-gold" type="button" id="rdSave">Save</button>
    </div>
  </div>

  <div id="rdToolbarHost"></div>
  <div id="rdBody" class="ed-rt" contenteditable="true"><?= $bodyHtml ?></div>
</div>

<style>
.ed-topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.ed-topbar__left,.ed-topbar__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ed-rt-toolbar{display:flex;gap:3px;margin-bottom:6px;flex-wrap:wrap;align-items:center;
  background:var(--surface-alt);border:1px solid var(--line);border-radius:8px;padding:5px 6px}
.ed-tb-sep{width:1px;align-self:stretch;background:var(--line-strong);margin:2px 3px}
.ed-tbtn{min-width:29px;height:29px;border:1px solid var(--line-strong);background:#fff;border-radius:6px;
  cursor:pointer;font-size:.85rem;padding:0 7px;line-height:1;color:var(--ink)}
.ed-tbtn:hover{background:var(--navy-800);color:#fff;border-color:var(--navy-800)}
.ed-tbsel{height:29px;border:1px solid var(--line-strong);border-radius:6px;font-size:.8rem;background:#fff;padding:0 4px;max-width:110px}
.ed-tbcolor{width:29px;height:29px;border:1px solid var(--line-strong);border-radius:6px;padding:2px;cursor:pointer;background:#fff}
.ed-rt{min-height:320px;border:1.5px solid var(--line-strong);border-radius:8px;padding:14px 16px;background:#fff}
.ed-rt img{max-width:100%;height:auto}
.ed-rt table{max-width:100%;border-collapse:collapse}
.ed-rt:focus{outline:none;border-color:var(--gold-500)}
</style>

<script>
const el = (t,props={},kids=[])=>{const n=document.createElement(t);for(const k in props){if(k==='class')n.className=props[k];else if(k==='html')n.innerHTML=props[k];else if(k.startsWith('on'))n.addEventListener(k.slice(2),props[k]);else n.setAttribute(k,props[k]);}for(const c of [].concat(kids))if(c!=null)n.append(c);return n;};

function markDirty(){ document.getElementById('rdStatus').textContent='Unsaved changes'; }

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
      if(n.nodeType===8){ n.remove(); return; }
      if(n.nodeType!==1) return;
      const tag=n.nodeName;
      const raw=(n.getAttribute&&(n.getAttribute('style')||''))||'';
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
      const inline=['SPAN','B','I','EM','STRONG','U','FONT'].includes(tag);
      if(inline && !n.getAttribute('style') && (!n.textContent.trim() ? !n.querySelector('img,br') : (tag==='SPAN'))){
        while(n.firstChild)node.insertBefore(n.firstChild,n); n.remove();
      }
    });
  })(tmp);
  return tmp.innerHTML;
}

function buildRTToolbar(ce){
  try{ document.execCommand('styleWithCSS',false,true); }catch(e){}
  let saved=null;
  const save=()=>{ const s=window.getSelection(); if(s.rangeCount && ce.contains(s.anchorNode)) saved=s.getRangeAt(0).cloneRange(); };
  const restore=()=>{ if(saved){ const s=window.getSelection(); s.removeAllRanges(); s.addRange(saved); } };
  ce.addEventListener('keyup',save); ce.addEventListener('mouseup',save); ce.addEventListener('focus',save);
  const exec=(cmd,val)=>{ ce.focus(); restore(); try{document.execCommand(cmd,false,val===undefined?null:val);}catch(e){} markDirty(); save(); };

  ce.addEventListener('paste',e=>{ e.preventDefault();
    const cd=e.clipboardData||window.clipboardData;
    const html=cd&&cd.getData?cd.getData('text/html'):'';
    ce.focus();
    if(html&&html.trim()) document.execCommand('insertHTML',false,cleanPasteHtml(html));
    else document.execCommand('insertText',false,(cd?cd.getData('text/plain'):'')||'');
    markDirty(); });

  const CELL='border:1px solid #cbd6e6;padding:8px;min-width:40px';
  function indentBlock(inc){ ce.focus(); restore();
    let n=window.getSelection().anchorNode;
    while(n&&n!==ce&&!(n.nodeType===1&&/^(P|DIV|LI|H1|H2|H3|H4|BLOCKQUOTE)$/.test(n.nodeName))) n=n.parentNode;
    if(!n||n===ce){ try{document.execCommand(inc?'indent':'outdent');}catch(e){} markDirty(); return; }
    let cur=parseInt(n.style.marginLeft)||0; cur=Math.max(0,cur+(inc?40:-40));
    n.style.marginLeft=cur?cur+'px':''; markDirty(); save();
  }
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
    dd('Paragraph',[['P','Normal'],['H3','Heading'],['H4','Subheading'],['BLOCKQUOTE','Quote']],'formatBlock'),
    sep(),
    btn('B','bold',undefined,'Bold'), btn('I','italic',undefined,'Italic'),
    btn('U','underline',undefined,'Underline'), btn('S','strikeThrough',undefined,'Strikethrough'),
    sep(),
    color('Text color','#16202e','foreColor'), color('Highlight','#ffe066','hiliteColor'),
    sep(),
    btn('⯇','justifyLeft',undefined,'Align left'), btn('≡','justifyCenter',undefined,'Center'),
    btn('⯈','justifyRight',undefined,'Align right'),
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

(function(){
  const body=document.getElementById('rdBody');
  document.getElementById('rdToolbarHost').append(buildRTToolbar(body));
  body.addEventListener('input',markDirty);
  document.getElementById('rdSave').addEventListener('click', async ()=>{
    const status=document.getElementById('rdStatus');
    status.textContent='Saving…';
    try{
      const res=await fetch(<?= json_encode(url($saveUrl)) ?>, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body:'_csrf='+encodeURIComponent(<?= json_encode(csrf_token()) ?>)+'&body_html='+encodeURIComponent(body.innerHTML)
      });
      const data=await res.json();
      if(!res.ok || !data.ok) throw new Error(data.error||'Save failed');
      status.textContent='Saved';
      setTimeout(()=>{ if(status.textContent==='Saved') status.textContent='Loaded'; },2000);
    }catch(e){
      status.textContent='Save failed: '+e.message;
    }
  });
  window.addEventListener('beforeunload', e=>{
    if(document.getElementById('rdStatus').textContent==='Unsaved changes'){ e.preventDefault(); e.returnValue=''; }
  });
})();
</script>
