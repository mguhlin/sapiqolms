<?php
declare(strict_types=1);
foreach (['helpers','db','auth','account_security','courses','quiz'] as $name) require_once __DIR__.'/../sapiqo/app/'.$name.'.php';
// Wait on a shared barrier so workers contend on the same DB rows.
while (!is_file($argv[2])) usleep(1000);
if ($argv[1]==='reset') echo reset_password_once($argv[3],'Concurrent-password-123')?'1':'0';
elseif ($argv[1]==='quiz') echo quiz_record_limited((int)$argv[3],(int)$argv[4],'race-quiz',['score'=>0,'total'=>1,'passed'=>false],1)?'1':'0';
elseif ($argv[1]==='mfa') echo mfa_verify((int)$argv[3],$argv[4])?'1':'0';

elseif ($argv[1]==='assignment') { try { assignment_save(gb_assessment((int)$argv[3]),(int)$argv[4],'Concurrent submission',true,0); echo '1'; } catch (InvalidArgumentException $e) { echo '0'; } }
