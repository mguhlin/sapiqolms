#!/usr/bin/env python3
"""Relocated custom SQLite paths and tampered backup rejection."""
import os,pathlib,tempfile,subprocess,tarfile,zipfile,io,json,sqlite3
ROOT=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='sapiqo-recovery-') as scratch:
    base=pathlib.Path(scratch);source=base/'source';source.mkdir();courses=base/'content';courses.mkdir();(courses/'fixture.txt').write_text('Recovery evidence')
    custom=base/'custom-source.sqlite';(source/'config.local.php').write_text("<?php return ['sqlite_path'=>"+repr(str(custom))+"];")
    env=dict(os.environ,SAPIQO_DATA=str(source),SAPIQO_COURSES=str(courses))
    subprocess.run(['php',str(ROOT/'sapiqo/bin/setup.php'),'--email','restore@example.org','--password','Recovery-password-123'],env=env,check=True,stdout=subprocess.DEVNULL)
    command="require "+repr(str(ROOT/'sapiqo/app/backup.php'))+"; echo create_backup("+repr(str(base/'backups'))+");"
    backup=pathlib.Path(subprocess.check_output(['php','-r',command],env=env,text=True))
    target=base/'target';target.mkdir();target_courses=base/'target-content';target_courses.mkdir();destination=base/'custom-target.sqlite'
    config="<?php return ['sqlite_path'=>"+repr(str(destination))+"];";(target/'config.local.php').write_text(config)
    targetenv=dict(env,SAPIQO_DATA=str(target),SAPIQO_COURSES=str(target_courses))
    subprocess.run(['php',str(ROOT/'sapiqo/bin/restore.php'),str(backup),'--force','--keep-config'],env=targetenv,check=True)
    with sqlite3.connect(destination) as db:assert db.execute('SELECT email FROM users').fetchone()[0]=='restore@example.org'
    assert (target/'config.local.php').read_text()==config and (target_courses/'fixture.txt').read_text()=='Recovery evidence'
    if backup.suffix=='.zip':
        with zipfile.ZipFile(backup) as z:files={name:z.read(name) for name in z.namelist() if not name.endswith('/')}
    else:
        with tarfile.open(backup) as t:files={m.name:t.extractfile(m).read() for m in t.getmembers() if m.isfile()}
    files['courses/fixture.txt']=b'TAMPERED'
    bad=base/'tampered.tar.gz'
    with tarfile.open(bad,'w:gz',format=tarfile.USTAR_FORMAT) as t:
        for name,payload in files.items():
            entry=tarfile.TarInfo(name);entry.size=len(payload);t.addfile(entry,io.BytesIO(payload))
    rejected=subprocess.run(['php',str(ROOT/'sapiqo/bin/restore.php'),str(bad),'--force','--keep-config'],env=targetenv,capture_output=True,text=True)
    assert rejected.returncode!=0 and 'integrity verification failed' in rejected.stderr
    assert (target_courses/'fixture.txt').read_text()=='Recovery evidence' and (target/'config.local.php').read_text()==config
    print('Custom SQLite relocation, keep-config recovery, and tampered archive rejection before writes passed.')
