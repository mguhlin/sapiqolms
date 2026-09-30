const {chromium}=require(process.env.SAPIQO_PLAYWRIGHT || 'playwright');
const assert=require('node:assert/strict');
let browser;
(async()=>{
  browser=await chromium.launch({headless:true});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}); const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  for(const width of [1440,375]) {
    await page.setViewportSize({width,height:1000}); await page.goto(process.argv[2],{waitUntil:'networkidle'});
    assert.equal(await page.locator('#roadmap').count(),1);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
    assert.equal(await page.evaluate(()=>[...document.images].some(i=>!i.complete||!i.naturalWidth)),false);
  }
  const app=process.argv[3]; await page.setViewportSize({width:1440,height:1000});
  async function login(email,password) { await page.goto(app+'/login'); await page.locator('#email').fill(email); await page.locator('#password').fill(password); await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:'Sign in',exact:true}).click()]); }
  await login('admin@example.org','Admin-password-123');
  for(const path of ['/admin','/admin/courses','/admin/readiness','/admin/learning-report','/admin/courses/browser-course/checklist','/admin/create?template=cohort','/admin/editor/browser-course']) { assert.equal((await page.goto(app+path,{waitUntil:'networkidle'})).status(),200,path); }
  await page.goto(app+'/admin/assignments/browser-course');
  await page.locator('#rubric-title').fill('Application rubric'); await page.locator('#criteria').fill('Application | 60\nReflection | 40'); await page.getByRole('button',{name:'Save rubric',exact:true}).click();
  await page.locator('#title').fill('Reflection assignment'); await page.locator('#instructions').fill('Explain your application of learning.'); await page.locator('#rubric_id').selectOption({label:'Application rubric'}); await page.locator('input[name=required]').check(); await page.getByRole('button',{name:'Create assignment',exact:true}).click();
  const reviewLink=await page.getByRole('link',{name:/Reflection assignment — review/}).getAttribute('href');
  await page.setViewportSize({width:375,height:900});
  for (const path of ['/admin/readiness','/admin/assignments/browser-course',reviewLink,'/profile/security']) { await page.goto(app+path,{waitUntil:'networkidle'}); assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,path+' mobile overflow'); }
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(app+'/logout'); await login('learner@example.org','Test-password-123');
  await page.goto(app+'/courses/browser-course/',{waitUntil:'networkidle'}); assert.equal(await page.evaluate(()=>window.untrustedShell),undefined);
  await page.getByRole('button',{name:/Start|Continue|Resume/}).first().click(); await page.getByRole('button',{name:'Mark lesson complete',exact:true}).click();
  await page.goto(app+'/assignments/browser-course'); await page.locator('textarea[name=body]').fill('Draft reflection <script>window.assignmentXss=true</script>'); await page.getByRole('button',{name:'Save draft',exact:true}).click();
  await page.getByRole('button',{name:'Submit for review',exact:true}).click(); assert.match(await page.locator('body').innerText(),/submitted/);
  await page.goto(app+'/dashboard'); assert.match(await page.locator('body').innerText(),/50%/);
  assert.equal((await page.goto(app+'/admin/assignments/browser-course')).status(),403);
  await page.goto(app+'/logout'); await login('admin@example.org','Admin-password-123'); await page.goto(app+reviewLink);
  await page.locator('input[name="rubric_scores[0]"]').fill('60'); await page.locator('input[name="rubric_scores[1]"]').fill('40'); await page.locator('textarea[name=feedback]').fill('Excellent application.'); await page.getByRole('button',{name:'Save grade and feedback'}).click(); assert.equal(await page.evaluate(()=>window.assignmentXss),undefined);
  await page.goto(app+'/logout'); await login('learner@example.org','Test-password-123'); await page.goto(app+'/dashboard'); assert.match(await page.locator('body').innerText(),/100%/); assert.equal(await page.getByRole('link',{name:'Certificate (PDF)',exact:true}).count(),1);
  await page.goto(app+'/assignments/browser-course'); assert.match(await page.locator('body').innerText(),/Excellent application/);
  await page.goto(app+'/courses/sandbox/',{waitUntil:'networkidle'});
  const frame=page.frameLocator('#scorm-frame'); await frame.getByRole('button',{name:'Complete course'}).waitFor();
  assert.equal(await page.frames().find(f=>f.url().includes('/scorm/index.html')).evaluate(()=>window.parentBlocked),true);
  assert.equal(await page.evaluate(()=>document.body.dataset.packageEscape),undefined);
  await frame.getByRole('button',{name:'Complete course'}).click(); await page.getByText('Course complete. Your progress was saved.',{exact:true}).waitFor();
  await page.goto(app+'/profile/security'); const secret=await page.locator('#secret').inputValue();
  function totp(secret) { const crypto=require('node:crypto');let bits='',raw=[];for(const c of secret)bits+='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c).toString(2).padStart(5,'0');for(let i=0;i+8<=bits.length;i+=8)raw.push(parseInt(bits.slice(i,i+8),2));const count=Buffer.alloc(8);count.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));const h=crypto.createHmac('sha1',Buffer.from(raw)).update(count).digest();const o=h[19]&15;return String((h.readUInt32BE(o)&0x7fffffff)%1000000).padStart(6,'0'); }
  await page.locator('#setup_code').fill(totp(secret)); await page.locator('#mfa-password').fill('Test-password-123'); await page.getByRole('button',{name:'Enable two-step verification'}).click();
  const recovery=(await page.locator('pre').innerText()).trim().split('\n'); assert.equal(recovery.length,10);
  await page.goto(app+'/logout'); await page.goto(app+'/login'); await page.locator('#email').fill('learner@example.org'); await page.locator('#password').fill('Test-password-123'); await page.getByRole('button',{name:'Sign in',exact:true}).click(); await page.waitForURL('**/mfa/challenge');
  assert.equal((await page.request.get(app+'/api/whoami')).status(),200); assert.equal((await (await page.request.get(app+'/api/whoami')).json()).authenticated,false);
  await page.locator('#code').fill(recovery[0]); await page.getByRole('button',{name:'Verify and sign in'}).click(); await page.waitForURL('**/dashboard');
  await page.goto(app+'/profile/security'); await page.locator('#sessions-code').fill(recovery[1]); await page.getByRole('button',{name:'Sign out all sessions'}).click(); await page.waitForURL('**/login');
  assert.deepEqual(errors,[]); await browser.close(); console.log('Browser journeys passed: responsive roadmap, authoring, assignment/rubric/certificate, sandboxed SCORM, MFA and session revocation.');
})().catch(async e=>{console.error(e);if(browser)await browser.close();process.exitCode=1});
