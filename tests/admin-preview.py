#!/usr/bin/env python3
"""Author previews bypass lesson ordering without granting learner access."""
from pathlib import Path
import http.cookiejar,json,os,re,shutil,socket,subprocess,tempfile,time,urllib.request,urllib.parse,urllib.error,sqlite3
ROOT=Path(os.environ.get('LMS_TEST_ROOT',Path(__file__).resolve().parents[1]));CODE=os.environ.get('LMS_TEST_CODE','sapiqo');LEGACY=os.environ.get('LEGACY_PREVIEW_FIXTURE')=='1'
assert (ROOT/CODE/'public/assets/js/course-reader.js').read_bytes()==(ROOT/'content/assets/js/reader.js').read_bytes(), 'Code-only update must include the matching runtime reader'
with tempfile.TemporaryDirectory(prefix='lms-preview-') as scratch:
 base=Path(scratch);data=base/'data';content=base/'content';data.mkdir();content.mkdir();shutil.copytree(ROOT/'content/assets',content/'assets')
 sample={'slug':'sample','title':'Preview fixture','status':'published','settings':{'author_preview':True},'stats':{'lessons':2,'modules':2,'topics':0,'quizzes':0},'modules':[{'id':'m1','label':'Module 1','title':'First module','lessons':[{'id':'l1','title':'First lesson','content':'<p>First lesson body for preview testing.</p>','topics':[]}]},{'id':'m2','label':'Module 2','title':'Final module','lessons':[{'id':'l2','title':'Final lesson','content':'<p>Final lesson body for preview testing.</p>','topics':[]}]}]}
 for slug,status in [('sample','published'),('draft','draft')]:
  folder=content/slug;folder.mkdir();(folder/'course.json').write_text(json.dumps(dict(sample,slug=slug,status=status)));(folder/'index.html').write_text('<title>untrusted shell</title>');(folder/'media').mkdir();(folder/'media/video.mp4').write_bytes(b'0123456789')
 (data/'config.local.php').write_text("<?php return ['db_driver'=>'sqlite','catalog_purchase'=>true,'allow_self_registration'=>false];")
 env=dict(os.environ,GOPHER_DATA=str(data),SAPIQO_DATA=str(data),TCEA_LMS_DATA=str(data),GOPHER_COURSES=str(content),SAPIQO_COURSES=str(content),TCEA_LMS_COURSES=str(content))
 command="require $argv[1].'/app/auth.php';require $argv[1].'/app/discovery.php';require $argv[1].'/app/courses.php';require $argv[1].'/app/creator.php';ensure_schema();foreach(['admin','author','learner','outsider'] as $role){[$ok,$uid]=register_local(['email'=>$role.'@example.org','password'=>'Preview-password-123','role'=>$role==='admin'?'admin':'learner']);if(!$ok)throw new Exception('User fixture');if($role==='author')db_run('UPDATE users SET can_edit_content=1 WHERE id=?',[$uid]);if($role==='learner')$learner=$uid;}scan_courses();db_run('UPDATE courses SET sequential=1');$c=course_by_slug('sample');enroll($learner,(int)$c['id']);foreach(['sample','draft'] as $slug)file_put_contents(lms_config()['courses_dir'].'/'.$slug.'/index.html',cr_shell_html('Preview fixture','',false));"
 subprocess.run(['php','-r',command,str(ROOT/CODE)],env=env,check=True,stdout=subprocess.DEVNULL)
 def counts():
  with sqlite3.connect(data/'data/lms.sqlite') as db:return [db.execute('SELECT COUNT(*) FROM '+table).fetchone()[0] for table in ['enrollments','progress','quiz_results','badges']]
 before=counts()
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 origin=f'http://127.0.0.1:{port}'
 with (base/'server.log').open('w+') as log:
  server=subprocess.Popen(['php','-d','session.upload_progress.enabled=0','-S',f'127.0.0.1:{port}','-t',str(ROOT/CODE/'public'),str(ROOT/CODE/'public/router.php')],env=env,stdout=log,stderr=log)
  try:
   def request(client,path,payload=None,headers=None):
    try:
     with client.open(urllib.request.Request(origin+path,data=payload,headers=headers or {}),timeout=15) as r:return r.status,r.read()
    except urllib.error.HTTPError as e:return e.code,e.read()
   def client_for(role):
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));status,html=request(client,'/login');csrf=re.search(rb'name="_csrf" value="([^"]+)"',html)[1].decode();status,body=request(client,'/login',urllib.parse.urlencode({'email':role+'@example.org','password':'Preview-password-123','_csrf':csrf}).encode());assert status==200;(status,body)=request(client,'/api/whoami');return client,json.loads(body)['csrf']
   anon=urllib.request.build_opener()
   for _ in range(50):
    try:request(anon,'/login');break
    except urllib.error.URLError:time.sleep(.1)
   checks=0
   for role in ['admin','author']:
    client,csrf=client_for(role)
    for slug in ['sample','draft']:
     status,body=request(client,f'/courses/{slug}/');assert status==200,(role,slug);assert b'reader.js?v=' in body
     status,body=request(client,f'/courses/{slug}/course.json');course=json.loads(body);assert status==200 and course['settings']['author_preview'] is True and course['settings']['sequential'] is True,(role,slug,course);checks+=1
   learner,csrf=client_for('learner');status,body=request(learner,'/courses/sample/course.json');course=json.loads(body);assert status==200 and course['settings']['author_preview'] is False and course['settings']['sequential'] is True;checks+=1
   if not LEGACY:
    status,body=request(learner,'/api/progress',json.dumps({'course':'sample','step_id':'l2','done':True}).encode(),{'Content-Type':'application/json','X-CSRF-Token':csrf});assert status in [200,403] and json.loads(body).get("error"),(status,body);checks+=1
    assert request(learner,'/courses/draft/course.json')[0]==403;checks+=1
    outsider,_=client_for('outsider');assert request(outsider,'/courses/sample/course.json')[0]==403;checks+=1
   assert request(anon,'/courses/sample/media/video.mp4')[0]==403;checks+=1
   if os.environ.get('PREVIEW_BROWSER')=='1':subprocess.run(['node',str(Path(__file__).with_suffix('.cjs')),origin],env=env,check=True,timeout=90)
   assert counts()==before,(before,counts())
   print(f'{checks} author-preview access/ordering checks passed; enrollment and progress unchanged.',flush=True)
  finally:
   server.terminate();server.wait(timeout=10);log.seek(0);errors=[line for line in log if any(s in line for s in ['Fatal error','Warning:','Deprecated:'])]
   if errors:raise AssertionError(''.join(errors))
