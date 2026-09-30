#!/usr/bin/env python3
"""Local SMTP protocol fixture; does not contact a real mail provider."""
import socket,threading,subprocess,json,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
messages=[]; failures=[]
with socket.socket() as server:
    server.bind(('127.0.0.1',0));server.listen(1);port=server.getsockname()[1]
    def receive():
        try:
            client,_=server.accept()
            with client:
                client.settimeout(10);stream=client.makefile('rwb',buffering=0);stream.write(b'220 local fixture\r\n')
                while True:
                    line=stream.readline()
                    if line.startswith(b'EHLO'):stream.write(b'250 fixture\r\n')
                    elif line.startswith((b'MAIL FROM:',b'RCPT TO:')):stream.write(b'250 accepted\r\n')
                    elif line==b'DATA\r\n':
                        stream.write(b'354 send data\r\n');body=b''
                        while True:
                            part=stream.readline()
                            if part==b'.\r\n':break
                            if not part:raise RuntimeError('Truncated message')
                            body+=part
                        messages.append(body);stream.write(b'250 queued\r\n')
                    elif line==b'QUIT\r\n':stream.write(b'221 bye\r\n');break
                    else:raise RuntimeError('Unexpected SMTP command')
        except Exception as e:failures.append(e)
    thread=threading.Thread(target=receive,daemon=True);thread.start()
    cfg={'from':'lms@example.org','from_name':'Test LMS','smtp':{'host':'127.0.0.1','port':port,'secure':'','user':'','pass':''}}
    php="require "+repr(str(ROOT/'sapiqo/app/mailer.php'))+"; echo json_encode(smtp_send(json_decode('"+json.dumps(cfg)+"',true),'learner@example.org','Protocol test',\"Hello\\n.leading dot\"));"
    result=json.loads(subprocess.check_output(['php','-r',php],text=True));thread.join(timeout=10)
    assert result[0] is True and len(messages)==1 and not failures
    assert b'\r\n..leading dot\r\n' in messages[0] and b'Message-ID:' in messages[0] and b'Date:' in messages[0]
print('Local SMTP greeting, recipient, DATA, headers and dot-stuffing passed. Real TLS/provider delivery remains unverified.')
