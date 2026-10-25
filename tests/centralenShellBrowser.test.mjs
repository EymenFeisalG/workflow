import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {launchBrowser} from '../../centralen/DEBUG/tests/browser-runtime.mjs';
const root=fileURLToPath(new URL('..',import.meta.url)),origin='http://127.0.0.1',base='/websites/workflow2';
for(const width of [320,390,1280])test('Real Core header and cross-origin chat preserve Workflow at '+width,async()=>{
 const browser=await launchBrowser(),page=await browser.newPage({viewport:{width,height:900}}),errors=[],writes=[];
 page.setDefaultTimeout(8000);page.on('pageerror',error=>errors.push(error.message));
 const html=execFileSync('php',[path.join(root,'tests/centralenShellFixture.php')],{encoding:'utf8'});
 await page.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());
  if(url.origin==='http://localhost'&&url.pathname.startsWith('/websites/centralen/resources/'))return route.continue();
  if(url.origin!==origin)return route.abort();
  if(req.isNavigationRequest()&&url.pathname===base+'/ui/style/css/general.css')return route.continue();
  if(url.pathname.startsWith(base+'/ui/')){const file=path.join(root,url.pathname.slice(base.length));return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':'image/svg+xml'});}
  if(url.pathname===base+'/centralen-shell-api.php'){
   const r=url.searchParams.get('route')||'',reply=data=>route.fulfill({contentType:'application/json',body:JSON.stringify({data})});if(url.searchParams.get('action')==='context')return reply({available:true,actor_linked:true,recipient_linked:true,can_create:true,recipient:{id:'cccccccc-cccc-4ccc-8ccc-cccccccccccc',name:'TEST Bo'}});
   if(r==='/chat/threads')return reply([{id:'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',key:'conversation:bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',kind:'conversation',employee_id:'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',manager_id:'cccccccc-cccc-4ccc-8ccc-cccccccccccc',manager_name:'TEST Bo',can_send:true,updated_at:new Date().toISOString()}]);
   if(r.endsWith('/messages')&&req.method()==='POST'){assert.equal(req.headers()['x-csrf-token'],'WF-SYNTHETIC-CSRF');writes.push(req.postDataJSON());return reply({id:'new',sender_id:'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',sender_name:'TEST Anna',body:writes.at(-1).body,created_at:new Date().toISOString()});}
   if(r.includes('/threads/')&&!r.endsWith('/messages')&&!r.endsWith('/read'))return reply({id:'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',kind:'conversation',employee_id:'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',manager_id:'cccccccc-cccc-4ccc-8ccc-cccccccccccc',manager_name:'TEST Bo',can_send:true});
   return reply([]);
  }
  return route.abort();
 });
 try{
  await page.goto(origin+base+'/ui/style/css/general.css');await page.setContent(html);await page.locator('.chat-launch').waitFor().catch(error=>{console.log(errors);throw error;});
  assert.equal(await page.locator('.site-header').count(),1);assert.equal(await page.locator('.site-header').evaluate(el=>getComputedStyle(el).display),'grid');
  assert.equal(await page.locator('.shell-profile').getAttribute('href'),'http://localhost/websites/centralen/?page=settings');
  const header=await page.locator('.site-header').boundingBox(),title=await page.locator('.shell-app-title').boundingBox();if(width>1100)assert.ok(Math.abs(title.x+title.width/2-width/2)<1);else assert.ok(title.x>=0&&title.x+title.width<=width);assert.ok(header.width<=1280);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  const dock=await page.locator('.macDockWrapper').boundingBox();assert.equal(dock.x,0);assert.equal(dock.width,width);
  await page.locator('#orderModalOverlay').evaluate(el=>{el.style.display='flex';el.style.opacity='1';el.style.visibility='visible'});await page.locator('#orderDesc').fill('Bevarat Workflow-utkast');await page.locator('#orderModalOverlay').evaluate(el=>{el.style.display='none'});
  await page.locator('.chat-launch').click();await page.getByRole('button',{name:'Samtal',exact:true}).click();await page.locator('.chat-row').filter({hasText:'TEST Bo'}).click();
  await page.locator('#chat-message').fill('TEST från Workflow');await page.getByRole('button',{name:'Skicka',exact:true}).click();await page.waitForFunction(()=>document.querySelector('#chat-message').value==='');assert.equal(writes.length,1);
  assert.equal(await page.locator('#orderDesc').inputValue(),'Bevarat Workflow-utkast');
  const panel=await page.locator('.chat-panel').boundingBox();assert.ok(panel.y+panel.height<=dock.y-5);
  fs.mkdirSync(path.join(root,'DEBUG/runtime/centralen-shell'),{recursive:true});await page.screenshot({path:path.join(root,`DEBUG/runtime/centralen-shell/${width}-chat.png`)});await page.locator('[data-chat-workflow]').click();await page.locator('.workflow-task-dialog').waitFor();await page.locator('.workflow-task-dialog input[name=title]').fill('Delegera utan att lämna Workflow');assert.equal(await page.locator('.workflow-task-dialog').evaluate(el=>el.matches(':modal')),true);await page.screenshot({path:path.join(root,`DEBUG/runtime/centralen-shell/${width}-delegate.png`)});await page.locator('[data-workflow-close]').click();
  // Existing Workflow task editor remains above the shared header/chat layer.
  const overlay=page.locator('#orderModalOverlay');if(await overlay.count()){await overlay.evaluate(el=>{el.style.display='flex';el.classList.add('active')});assert.ok(await overlay.evaluate(el=>Number(getComputedStyle(el).zIndex))>11000);const save=await page.locator('#orderSubmitBtn').boundingBox();assert.ok(await page.evaluate(({x,y})=>document.elementFromPoint(x,y)?.closest('#orderSubmitBtn')!==null,{x:save.x+save.width/2,y:save.y+save.height/2}));}
  fs.mkdirSync(path.join(root,'DEBUG/runtime/centralen-shell'),{recursive:true});await page.screenshot({path:path.join(root,`DEBUG/runtime/centralen-shell/${width}.png`)});
  assert.deepEqual(errors,[]);
 }finally{await browser.close();}
});
test('Presentation failure leaves actual Workflow workspace and draft fields available',async()=>{
 const browser=await launchBrowser(),page=await browser.newPage();
 const html=execFileSync('php',[path.join(root,'tests/centralenShellFixture.php'),'--failure'],{encoding:'utf8'});
 await page.route('**/*',route=>route.request().url().endsWith('/fixture')?route.fulfill({contentType:'text/html',body:html}):route.abort());
 try{await page.goto(origin+base+'/fixture');assert.ok(await page.getByRole('status').isVisible());assert.equal(await page.locator('#orderDesc').count(),1);assert.equal(await page.locator('.orders').count(),1);assert.equal(await page.locator('[data-chat-dock]').count(),0);}finally{await browser.close();}
});
for(const mode of ['admin','priority'])test('Shared shell preserves '+mode+' workspace on mobile and desktop',async()=>{
 const browser=await launchBrowser(),page=await browser.newPage(),errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const html=execFileSync('php',[path.join(root,'tests/centralenShellFixture.php'),'--'+mode],{encoding:'utf8'});
 await page.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());
  if(url.origin==='http://localhost'&&url.pathname.startsWith('/websites/centralen/resources/'))return route.continue();
  if(url.origin!==origin)return route.abort();
  if(req.isNavigationRequest()&&url.pathname===base+'/ui/style/css/general.css')return route.continue();
  if(url.pathname.startsWith(base+'/ui/')){const file=path.join(root,url.pathname.slice(base.length));return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':'text/javascript'});}
  if(url.pathname===base+'/centralen-shell-api.php')return route.fulfill({contentType:'application/json',body:'{"data":[]}'});
  return route.abort();
 });
 try{
  await page.goto(origin+base+'/ui/style/css/general.css');await page.setContent(html);await page.locator('.chat-launch').waitFor();
  for(const width of [390,1280]){
   await page.setViewportSize({width,height:900});
   assert.equal(await page.locator('.site-header').count(),1);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   assert.ok(await page.locator(mode==='admin'?'.adminMain':'.orders').isVisible());if(mode==='admin')assert.ok(await page.locator('.adminMain>.topbar a[href="../home.php"]').last().isVisible());
   assert.equal(await page.evaluate(()=>parseFloat(getComputedStyle(document.body).getPropertyValue('--shell-bottom-clearance'))),0);
   await page.screenshot({path:path.join(root,`DEBUG/runtime/centralen-shell/${mode}-${width}.png`)});
  }
  assert.deepEqual(errors,[]);
 }finally{await browser.close();}
});
