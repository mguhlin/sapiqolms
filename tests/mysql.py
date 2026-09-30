#!/usr/bin/env python3
"""Disposable MariaDB server: no installed databases or services are modified."""
import os,pathlib,subprocess,tempfile,time,socket,json,shutil,sys
ROOT=pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tests'));from concurrency import exercise
with tempfile.TemporaryDirectory(prefix='sapiqo-mysql-') as scratch:
    base=pathlib.Path(scratch);datadir=base/'mysql';datadir.mkdir();data=base/'data';data.mkdir();courses=base/'content';courses.mkdir()
    subprocess.run(['mariadb-install-db','--no-defaults',f'--datadir={datadir}','--auth-root-authentication-method=normal','--skip-test-db'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    sock=base/'mysql.sock';log=open(base/'mysql.log','w+')
    server=subprocess.Popen(['/usr/sbin/mariadbd','--no-defaults',f'--datadir={datadir}',f'--socket={sock}',f'--port={port}','--bind-address=127.0.0.1',f'--pid-file={base}/mysql.pid','--skip-log-bin'],stdout=log,stderr=log)
    def sql(query, database=None):
        cmd=['mysql','--no-defaults',f'--socket={sock}','--user=root','--batch','--skip-column-names']
        if database:cmd.append(database)
        return subprocess.check_output(cmd+['-e',query],text=True,stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:sql('SELECT 1');break
            except subprocess.CalledProcessError:time.sleep(.1)
        else:raise RuntimeError('Disposable MariaDB did not start')
        for name in ['sapiqo_test','sapiqo_restored']:sql('CREATE DATABASE '+name+' CHARACTER SET utf8mb4')
        config={'host':'127.0.0.1','port':port,'dbname':'sapiqo_test','user':'root','pass':'','charset':'utf8mb4'}
        (data/'config.local.php').write_text("<?php return ['db_driver'=>'mysql','mysql'=>json_decode('"+json.dumps(config)+"',true)];")
        env=dict(os.environ,SAPIQO_DATA=str(data),SAPIQO_COURSES=str(courses))
        exercise(env)
        # Assignment lifecycle and full DB/content recovery on the actual MySQL driver.
        sample={'slug':'mysql-course','title':'MySQL course','stats':{'lessons':1,'topics':0,'quizzes':0},'modules':[{'id':'m','title':'Module','lessons':[{'id':'lesson','title':'Apply','content':'<p>Apply</p>','topics':[]}]}]}
        folder=courses/'mysql-course';folder.mkdir();(folder/'course.json').write_text(json.dumps(sample));(folder/'index.html').write_text('<title>Course</title>')
        php="""foreach (['helpers','auth','courses','discovery','notifications','backup'] as $n) require %s.'/sapiqo/app/'.$n.'.php'; scan_courses(); $u=db_one('SELECT * FROM users WHERE email=?',['race@example.org']);$c=course_by_slug('mysql-course');$a=gb_add_assessment((int)$c['id'],'Apply','Assignment',100,null);db_run('UPDATE assessments SET submission_enabled=1,required_completion=1 WHERE id=?',[$a]);$a=gb_assessment($a);record_progress((int)$u['id'],(int)$c['id'],'lesson',true);if(course_percent((int)$u['id'],$c)!==50)throw new Exception('Completion gate');assignment_save($a,(int)$u['id'],'Application evidence',true,0);assignment_grade($a,(int)$u['id'],1,[],90,'Good');if(course_percent((int)$u['id'],$c)!==100)throw new Exception('Grade completion');echo create_backup(%s);""" % (repr(str(ROOT)),repr(str(base/'backups')))
        backup=subprocess.check_output(['php','-r',php],env=env,text=True)
        restored=base/'restored';restored.mkdir();restore_courses=base/'restored-content';restore_courses.mkdir();target=dict(config,dbname='sapiqo_restored')
        (restored/'config.local.php').write_text("<?php return ['db_driver'=>'mysql','mysql'=>json_decode('"+json.dumps(target)+"',true)];")
        subprocess.run(['php',str(ROOT/'sapiqo/bin/restore.php'),backup,'--force'],env=dict(env,SAPIQO_DATA=str(restored),SAPIQO_COURSES=str(restore_courses)),check=True)
        assert sql('SELECT COUNT(*) FROM assignment_submissions','sapiqo_restored').strip()=='2'
        assert sql('SELECT points FROM assessment_scores','sapiqo_restored').strip()=='90.00'
        assert (restore_courses/'mysql-course/course.json').read_bytes()==(folder/'course.json').read_bytes()
        print('MariaDB schema, concurrent reset/quiz/MFA, assignment grading, and complete recovery passed.',flush=True)
    finally:
        server.terminate();server.wait(timeout=20);log.close()
