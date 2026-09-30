#!/usr/bin/env python3
"""Upgrade/rollback real v1.12.1 code in a disposable installation."""
import pathlib,subprocess,tempfile,os,io,tarfile,json,shutil
ROOT=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='sapiqo-upgrade-') as scratch:
    base=pathlib.Path(scratch);data=base/'data';data.mkdir();courses=base/'content';courses.mkdir()
    env=dict(os.environ,SAPIQO_DATA=str(data),SAPIQO_COURSES=str(courses))
    source=subprocess.check_output(['git','archive','8f43afd7a7f4b74088afe3a2a70f0baaf941093b','sapiqo'],cwd=ROOT)
    with tarfile.open(fileobj=io.BytesIO(source)) as archive:archive.extractall(base,filter='data')
    code=base/'sapiqo';config=data/'config.local.php';config.write_text("<?php return ['org_name'=>'Upgrade fixture'];")
    subprocess.run(['php',str(code/'bin/setup.php'),'--email','upgrade@example.org','--password','Upgrade-password-123'],env=env,check=True,stdout=subprocess.DEVNULL)
    config_before=config.read_bytes();sentinel=courses/'keep.txt';sentinel.write_text('Keep course content')
    command="require "+repr(str(ROOT/'sapiqo/app/updater.php'))+"; echo build_update_package("+repr(str(base/'packages'))+");"
    package=subprocess.check_output(['php','-r',command],env=env,text=True)
    command="require "+repr(str(code/'app/updater.php'))+"; echo json_encode(apply_update_package("+repr(package)+",basename("+repr(package)+")));"
    result=json.loads(subprocess.check_output(['php','-r',command],env=env,text=True));assert result[0],result
    command="require "+repr(str(code/'app/db.php'))+"; ensure_schema(); echo json_encode([APP_VERSION,DB_SCHEMA_VERSION,db_one('SELECT email FROM users')['email']]);"
    current=json.loads(subprocess.check_output(['php','-r',command],env=env,text=True));assert current[0]=='1.13.0' and current[1]==22 and current[2]=='upgrade@example.org',current
    assert config.read_bytes()==config_before and sentinel.read_text()=='Keep course content'
    command="require "+repr(str(code/'app/updater.php'))+"; echo json_encode(rollback_update());"
    result=json.loads(subprocess.check_output(['php','-r',command],env=env,text=True));assert result[0],result
    command="require "+repr(str(code/'app/db.php'))+"; ensure_schema(); echo APP_VERSION;"
    assert subprocess.check_output(['php','-r',command],env=env,text=True)=='1.12.1'
    assert config.read_bytes()==config_before and sentinel.read_text()=='Keep course content'
    print('v1.12.1 → v1.13.0 upgrade, additive schema migration and code rollback preserve users, config and course files.')
