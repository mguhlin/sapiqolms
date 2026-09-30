#!/usr/bin/env python3
"""Eight independent PHP processes contest each single-use action."""
import os,pathlib,subprocess,tempfile,json
ROOT=pathlib.Path(__file__).resolve().parents[1]
def exercise(env):
    php="""foreach (['helpers','auth','courses','discovery','quiz','account_security'] as $n) require %s.'/sapiqo/app/'.$n.'.php'; ensure_schema(); [$ok,$uid]=register_local(['email'=>'race@example.org','password'=>'Race-password-123']); $cid=db_insert("INSERT INTO courses (slug,title,path,total_units,active,status,created_at) VALUES (?,?,?,?,?,?,?)",['race','Race','/courses/race/',1,1,'published',now_utc()]); $secret=mfa_base32_encode(random_bytes(20)); db_run('UPDATE users SET mfa_secret=? WHERE id=?',[mfa_encrypt($secret),$uid]); $aid=gb_add_assessment($cid,'Concurrent assignment','Assignment',100,null);db_run('UPDATE assessments SET submission_enabled=1 WHERE id=?',[$aid]); echo json_encode(['aid'=>$aid,'uid'=>$uid,'cid'=>$cid,'token'=>create_reset_token($uid),'code'=>mfa_totp($secret,intdiv(time(),30))]);""" % repr(str(ROOT))
    fixture=json.loads(subprocess.check_output(['php','-r',php],env=env,text=True))
    with tempfile.TemporaryDirectory(prefix='sapiqo-barrier-') as scratch:
        for action,args in [('reset',[fixture['token']]),('quiz',[str(fixture['uid']),str(fixture['cid'])]),('mfa',[str(fixture['uid']),fixture['code']]),('assignment',[str(fixture['aid']),str(fixture['uid'])])]:
            barrier=pathlib.Path(scratch)/action
            workers=[subprocess.Popen(['php',str(ROOT/'tests/concurrency-worker.php'),action,str(barrier),*args],env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for _ in range(8)]
            barrier.touch(); results=[]
            for worker in workers:
                out,err=worker.communicate(timeout=20)
                if worker.returncode or err:raise AssertionError(action+': '+err)
                results.append(out.strip())
            assert results.count('1')==1 and results.count('0')==7,(action,results)
            print(f'{action}: exactly one success across 8 concurrent workers',flush=True)
if __name__=='__main__':
    with tempfile.TemporaryDirectory(prefix='sapiqo-race-') as scratch:
        base=pathlib.Path(scratch);(base/'data').mkdir();(base/'content').mkdir()
        exercise(dict(os.environ,SAPIQO_DATA=str(base/'data'),SAPIQO_COURSES=str(base/'content')))
