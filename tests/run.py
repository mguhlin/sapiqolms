#!/usr/bin/env python3
"""Fresh-install security/HTTP regression tests. Uses only PHP + Python stdlib."""
import contextlib, http.cookiejar, io, json, os, pathlib, re, shutil, socket, struct, subprocess, tarfile, tempfile, time, urllib.error, urllib.request, zipfile
ROOT = pathlib.Path(__file__).resolve().parents[1]

def run(*args, env=None):
    subprocess.run(args, cwd=ROOT, env=env, check=True)

with tempfile.TemporaryDirectory(prefix='sapiqo-tests-') as scratch:
    base=pathlib.Path(scratch); data=base/'data'; courses=base/'content'; archives=base/'archives'
    data.mkdir(); courses.mkdir(); archives.mkdir()
    shutil.copytree(ROOT/'content/assets', courses/'assets')
    sample={'slug':'sample','title':'Sample course','stats':{'lessons':2,'topics':0,'quizzes':1},'modules':[{'id':'m1','title':'Module','lessons':[
        {'id':'lesson-1','title':'First','content':'<p>Hello</p>','topics':[], 'quiz':{'id':'quiz-1','pass':70,'pick':1,'questions':[
          {'qid':'q0','text':'Choose A','options':[{'text':'A','correct':True},{'text':'B','correct':False}]},
          {'qid':'q1','text':'Choose B','options':[{'text':'A','correct':False},{'text':'B','correct':True}]}]}},
        {'id':'lesson-2','title':'Second','content':'<p>Finish</p>','topics':[]}]}]}
    for slug, status in [('sample','published'),('draft','draft'),('assignment','published')]:
        folder=courses/slug; folder.mkdir(); obj=dict(sample, slug=slug, status=status)
        (folder/'course.json').write_text(json.dumps(obj));(folder/'index.html').write_text('<!doctype html><title>Sample</title>')
        (folder/'source.md').write_text('private answer source');(folder/'media').mkdir();(folder/'media/video.mp4').write_bytes(b'0123456789')
    def tar(name, entries):
        with tarfile.open(archives/name, 'w:gz' if name.endswith('.gz') else 'w', format=tarfile.USTAR_FORMAT) as out:
            for path, kind in entries:
                info=tarfile.TarInfo(path)
                if kind=='link': info.type=tarfile.SYMTYPE;info.linkname='/etc/passwd';out.addfile(info)
                else: payload=b'hello';info.size=len(payload);out.addfile(info,io.BytesIO(payload))
    tar('good.tar',[('hello.txt','file')]);tar('good.tar.gz',[('hello.txt','file')])
    tar('traversal.tar',[('../outside','file')]);tar('absolute.tar',[('/outside','file')]);tar('link.tar',[('link','link')])
    for name, path in [('good.zip','hello.txt'),('traversal.zip','../outside')]:
        with zipfile.ZipFile(archives/name,'w') as z: z.writestr(path,'hello')
    with zipfile.ZipFile(archives/'link.zip','w') as z:
        info=zipfile.ZipInfo('link');info.create_system=3;info.external_attr=(0o120777<<16);z.writestr(info,'/etc/passwd')
    raw=bytearray((archives/'good.zip').read_bytes());pos=raw.index(b'PK\x01\x02');struct.pack_into('<I',raw,pos+24,3*1024**3);(archives/'bomb.zip').write_bytes(raw)
    env=dict(os.environ,SAPIQO_DATA=str(data),SAPIQO_COURSES=str(courses),SAPIQO_TEST_ARCHIVES=str(archives))
    run('php',str(ROOT/'tests/security.php'),env=env)
    restore_data=base/'restored';restore_data.mkdir()
    restore_courses=base/'restored-content';restore_courses.mkdir()
    backup=next((data/'backups').glob('sapiqo-backup-*'))
    run('php',str(ROOT/'sapiqo/bin/restore.php'),str(backup),'--force',env=dict(env,SAPIQO_DATA=str(restore_data),SAPIQO_COURSES=str(restore_courses)))
    import sqlite3
    with sqlite3.connect(restore_data/'data/lms.sqlite') as db:
        assert db.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
        assert db.execute('SELECT COUNT(*) FROM users').fetchone()[0]>=3
    assert (restore_courses/'sample/course.json').read_bytes()==(courses/'sample/course.json').read_bytes()
    assert (restore_courses/'sample/media/video.mp4').read_bytes()==b'0123456789'
    print('Complete database/content backup/restore round trip passed',flush=True)
    import importlib.util
    spec=importlib.util.spec_from_file_location('course_builder',ROOT/'sapiqo/creator/build_course.py')
    builder=importlib.util.module_from_spec(spec);spec.loader.exec_module(builder)
    assert 'href=' not in builder._inline('[bad](javascript:alert(1))')
    assert 'src="' not in builder._inline('![bad](data:text/html,test)')
    assert builder.embed_html('javascript:alert(1)')==''
    assert 'src="javascript:' not in builder.md_to_html('![bad](javascript:alert(1))')
    assert 'href="media/guide.pdf"' in builder._inline('[guide](media/guide.pdf)')
    print('Python Markdown URL regression checks passed',flush=True)
    # Default process must also lint all PHP, JS, shell scripts and Python source.
    for file in ROOT.rglob('*.php'):
        result=subprocess.run(['php','-l',str(file)],capture_output=True,text=True)
        if result.returncode: raise RuntimeError(result.stdout+result.stderr)
    for file in ROOT.rglob('*.sh'): run('bash','-n',str(file))
    for file in list((ROOT/'content/assets/js').glob('*.js')) + list((ROOT/'sapiqo/public/assets/js').glob('*.js')): run('node','--check',str(file))
    compile((ROOT/'sapiqo/creator/build_course.py').read_text(), 'build_course.py', 'exec')
    print('PHP, JavaScript, shell, and Python syntax checks passed',flush=True)
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    origin=f'http://127.0.0.1:{port}'
    log=open(base/'server.log','w+')
    server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT/'sapiqo/public'),str(ROOT/'sapiqo/public/router.php')],env=env,stdout=log,stderr=log)
    cookies=http.cookiejar.CookieJar(); browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
    def req(path, payload=None, headers=None, client=browser):
        request=urllib.request.Request(origin+path,data=payload,headers=headers or {})
        try:
            with client.open(request,timeout=10) as res:return res.status,dict(res.headers),res.read().decode()
        except urllib.error.HTTPError as e:return e.code,dict(e.headers),e.read().decode()
    checks=0
    def check(condition,message):
        global checks
        if not condition:raise AssertionError(message)
        checks+=1
    def token(html): return re.search(r'name="_csrf" value="([^"]+)"',html)[1]
    def post(path,obj,csrf):return req(path,json.dumps(obj).encode(),{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'})
    try:
        for _ in range(50):
            try:req('/');break
            except urllib.error.URLError:time.sleep(.1)
        fresh=urllib.request.build_opener()
        check(req('/login',b'email=x',client=fresh)[0]==419,'Empty CSRF cannot pass')
        status,headers,html=req('/login');check(status==200,'Login renders')
        check("script-src 'self' 'nonce-" in headers.get('Content-Security-Policy',''),'Nonce CSP emitted')
        csrf=token(html)
        login=urllib.parse.urlencode({'email':'learner@example.org','password':'Test-password-123','_csrf':csrf}).encode()
        check(req('/login',login)[0]==200,'Learner login succeeds')
        who=json.loads(req('/api/whoami')[2]);csrf=who['csrf']
        check(req('/admin')[0]==403,'Learner denied admin')
        check(req('/courses/draft/course.json')[0]==403,'Direct draft JSON denied')
        check(req('/courses/draft/media/video.mp4')[0]==403,'Direct draft media denied')
        check(req('/courses/sample/source.md')[0]==403,'Course source denied')
        status,headers,body=req('/courses/sample/course.json');check(status==200,'Course JSON renders')
        public=json.loads(body);quiz=public['modules'][0]['lessons'][0]['quiz'];qid=quiz['questions'][0]['qid']
        check(len(quiz['questions'])==1,'Presented quiz bank is server-selected')
        check('correct' not in quiz['questions'][0]['options'][0],'HTTP answer key absent')
        check(post('/api/progress',{'course':'sample','step_id':'quiz-1','done':True},csrf)[0]==403,'Cannot bypass grading')
        result=json.loads(post('/api/progress',{'course':'sample','step_id':'made-up','done':True},csrf)[2])
        check('error' in result,'Fake progress rejected over HTTP')
        answer=0 if qid=='q0' else 1
        result=json.loads(post('/api/quiz',{'course':'sample','quiz_id':'quiz-1','answers':{qid:[answer]},'asked':['not-a-question']},csrf)[2])
        check(result.get('passed') is True and result['total']==1,'Tampered asked list cannot change server grading')
        check(post('/api/scorm',{'course':'sample','status':'passed'},csrf)[0]==422,'Native course cannot spoof SCORM completion')
        result=json.loads(post('/api/progress',{'course':'sample','step_id':'lesson-2','done':True},csrf)[2]);check(result.get('percent')==100,'Legitimate sequential completion succeeds')
        status,headers,body=req('/courses/sample/media/video.mp4',headers={'Range':'bytes=-3'})
        check(status==206 and body=='789' and headers['Content-Range']=='bytes 7-9/10','Suffix byte ranges work')
        check(headers['Cache-Control']=='private, no-store','Protected media is never publicly cached')
        check(req('/.user.ini')[0]==404,'Dotfiles not served by development router')
        check(req('/courses/sample/media/video.mp4',client=fresh)[0]==403,'Anonymous media denied')
        preview=json.loads(req('/courses/sample/course.json',client=fresh)[2]);check(preview['preview'] and 'content' not in preview['modules'][0]['lessons'][0],'Anonymous syllabus strips content')
        # Purchase mode and trusted reverse proxy in an independent PHP request.
        (data/'config.local.php').write_text("<?php return ['catalog_purchase'=>true, 'public_url'=>'https://lms.example.org', 'trusted_proxies'=>['127.0.0.1']];")
        # A new learner has no paid enrollment.
        # Use a fresh session because the learner is already authenticated.
        newcookies=http.cookiejar.CookieJar(); newclient=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(newcookies))
        html=req('/register',client=newclient)[2];registration=urllib.parse.urlencode({'email':'new@example.org','password':'New-password-123','password_confirm':'New-password-123','course':'sample','role':'admin','_csrf':token(html)}).encode()
        check(req('/register',registration,client=newclient)[0]==200,'Public registration succeeds')
        newwho=json.loads(req('/api/whoami',client=newclient)[2]);check(req('/admin',client=newclient)[0]==403,'Registration cannot grant admin')
        check(req('/courses/sample/course.json',client=newclient)[0]==403,'Paid content denied without enrollment')
        check(req('/courses/sample/media/video.mp4',client=newclient)[0]==403,'Paid media denied without enrollment')
        # Exercise newly introduced boundaries through real browser sessions.
        admincookies=http.cookiejar.CookieJar();adminclient=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(admincookies))
        html=req('/login',client=adminclient)[2]
        req('/login',urllib.parse.urlencode({'email':'admin@example.org','password':'Admin-password-123','_csrf':token(html)}).encode(),client=adminclient)
        admincsrf=json.loads(req('/api/whoami',client=adminclient)[2])['csrf']
        check(req('/admin/readiness',client=adminclient)[0]==200,'Operator readiness renders')
        check(req('/admin/learning-report',client=adminclient)[0]==200,'Actionable report renders')
        check(req('/admin/assignments/sample')[0]==403,'Learner cannot author assignments')
        form=urllib.parse.urlencode([('name','Read courses'),('scopes[]','courses:read'),('_csrf',admincsrf)]).encode()
        html=req('/admin/api-keys',form,client=adminclient)[2];key=re.search(r'value="(sk_[a-f0-9]+)"',html)[1]
        check(req('/api/v1/courses',headers={'Authorization':'Bearer '+key},client=fresh)[0]==200,'Scoped API course read works')
        check(req('/api/v1/users',headers={'Authorization':'Bearer '+key},client=fresh)[0]==403,'Scoped API denies learner data')
        check(req('/api/v1/users',json.dumps({'email':'api-admin@example.org','role':'admin'}).encode(),{'Authorization':'Bearer '+key,'Content-Type':'application/json'},client=fresh)[0]==403,'Read-only API cannot create administrators')
        form=urllib.parse.urlencode({'title':'HTTP assignment','instructions':'Apply learning','max_points':'100','pass_percent':'70','resubmit':'1','_csrf':admincsrf}).encode()
        check(req('/admin/assignments/sample',form,client=adminclient)[0]==200,'Author creates submission assignment')
        html=req('/assignments/sample')[2];aid=re.findall(r'action="[^"]*/assignments/sample/(\d+)"',html)[-1]
        boundary='sapiqo-http-fixture'
        parts=[]
        for name,value in [('_csrf',csrf),('revision','0'),('action','submit'),('body','HTTP response')]:parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n')
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="evidence.txt"\r\nContent-Type: text/plain\r\n\r\nProtected evidence\r\n--{boundary}--\r\n')
        html=req('/assignments/sample/'+aid,''.join(parts).encode(),{'Content-Type':'multipart/form-data; boundary='+boundary})[2]
        check('Assignment submitted.' in html,'Authenticated file/text submission works')
        download=re.search(r'href="([^"]*/assignment-file/\d+)"',html)[1]
        check(req(download)[2]=='Protected evidence','Owner downloads private attachment')
        check(req(download,client=newclient)[0]==404,'Other learner cannot download attachment')
        html=req('/admin/assignment/'+aid,client=adminclient)[2]
        check('HTTP response' in html and 'Protected evidence' not in html,'Instructor sees response and attachment link')
        check(req('/profile/security')[0]==200,'Account security page renders')
        secondcookies=http.cookiejar.CookieJar();secondclient=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(secondcookies))
        html=req('/login',client=secondclient)[2]
        req('/login',urllib.parse.urlencode({'email':'learner@example.org','password':'Test-password-123','_csrf':token(html)}).encode(),client=secondclient)
        check(json.loads(req('/api/whoami',client=secondclient)[2])['authenticated'],'Second session signs in')
        form=urllib.parse.urlencode({'action':'revoke','current_password':'Test-password-123','_csrf':csrf}).encode()
        req('/profile/security',form)
        check(not json.loads(req('/api/whoami',client=secondclient)[2])['authenticated'],'Global revocation invalidates another browser')
        print(f'{checks} HTTP regression checks passed',flush=True)
    finally:
        server.terminate();server.wait(timeout=10);log.flush();log.seek(0)
        text=log.read();log.close()
        errors=[line for line in text.splitlines() if any(x in line for x in ('Fatal error','Warning:','Deprecated:'))]
        if errors:raise AssertionError('Server diagnostics: '+'\n'.join(errors))
print('All tests passed.')
