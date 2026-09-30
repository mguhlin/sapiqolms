#!/usr/bin/env python3
"""Isolated browser journeys. npm ci + npx playwright install chromium first."""
import os,pathlib,shutil,socket,subprocess,tempfile,time,json
ROOT=pathlib.Path(__file__).resolve().parents[1]
def free_port():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
with tempfile.TemporaryDirectory(prefix='sapiqo-browser-') as scratch:
    base=pathlib.Path(scratch);(base/'data').mkdir();(base/'content').mkdir();shutil.copytree(ROOT/'content/assets',base/'content/assets')
    course={'slug':'browser-course','title':'Browser Course','stats':{'modules':1,'lessons':1,'topics':0,'quizzes':0},'modules':[{'id':'m1','title':'Learning','label':'Module 1','lessons':[{'id':'lesson-1','title':'Practice','content':'<p>Practice activity</p>','topics':[]}]}]}
    for slug,obj in [('browser-course',course),('sandbox',{'slug':'sandbox','title':'Sandbox course','type':'scorm','stats':{'units':1},'scorm':{'entry':'scorm/index.html','version':'1.2'},'modules':[]})]:
        folder=base/'content'/slug;folder.mkdir();(folder/'course.json').write_text(json.dumps(obj));(folder/'index.html').write_text('<script>window.untrustedShell=true</script>')
    folder=base/'content/sandbox/scorm';folder.mkdir()
    (folder/'index.html').write_text('''<!doctype html><title>SCORM fixture</title><p>Sandbox fixture</p><button id="finish" onclick="API.LMSSetValue('cmi.core.lesson_status','completed');API.LMSSetValue('cmi.core.score.raw','88');API.LMSCommit()">Complete course</button><script>window.parentBlocked=false;try { window.parent.document.body.dataset.packageEscape='bad'; } catch(e) { window.parentBlocked=true; }</script>''')
    env=dict(os.environ,SAPIQO_DATA=str(base/'data'),SAPIQO_COURSES=str(base/'content'))
    subprocess.run(['php',str(ROOT/'sapiqo/bin/setup.php'),'--email','admin@example.org','--password','Admin-password-123'],env=env,check=True,stdout=subprocess.DEVNULL)
    php="require "+repr(str(ROOT/'sapiqo/app/auth.php'))+"; require "+repr(str(ROOT/'sapiqo/app/discovery.php'))+"; ensure_schema(); register_local(['email'=>'learner@example.org','password'=>'Test-password-123']); scan_courses();"
    subprocess.run(['php','-r',php],env=env,check=True)
    appport,siteport=free_port(),free_port();processes=[]
    with open(base/'server.log','w+') as log:
        try:
            processes.append(subprocess.Popen(['php','-S',f'127.0.0.1:{appport}','-t',str(ROOT/'sapiqo/public'),str(ROOT/'sapiqo/public/router.php')],env=env,stdout=log,stderr=log))
            processes.append(subprocess.Popen(['python3','-m','http.server',str(siteport),'--bind','127.0.0.1','--directory',str(ROOT/'site')],stdout=log,stderr=log))
            time.sleep(.5)
            subprocess.run(['node',str(ROOT/'tests/browser.cjs'),f'http://127.0.0.1:{siteport}',f'http://127.0.0.1:{appport}'],env=env,check=True,timeout=180)
        finally:
            for p in processes:p.terminate();p.wait(timeout=10)
            log.flush();log.seek(0)
            errors=[line for line in log.read().splitlines() if any(s in line for s in ['Fatal error','Warning:','Deprecated:'])]
            if errors:raise RuntimeError('\n'.join(errors))
