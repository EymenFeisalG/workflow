import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {launchBrowser} from '../../centralen/DEBUG/tests/browser-runtime.mjs';

// CGI-generated synthetic fixtures only; no app handler, account or database is reached.
const root=fileURLToPath(new URL('..',import.meta.url));
const browser=await launchBrowser();let checks=0;
try{
 for(const kind of ['login','link']){
  const html=await fs.readFile(path.join(root,`DEBUG/centralen-${kind}-fixture.html`),'utf8');
  const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url());if(url.origin!=='https://workflow.test')return route.abort();
   if(url.pathname==='/fixture')return route.fulfill({contentType:'text/html',body:html});
   if(!url.pathname.startsWith('/ui/'))return route.abort();
   const file=path.resolve(root,'.'+url.pathname);assert.ok(file.startsWith(path.join(root,'ui')+path.sep));
   try{return route.fulfill({body:await fs.readFile(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.png')?'image/png':'image/svg+xml'});}catch{return route.abort();}
  });
  await page.goto('https://workflow.test/fixture');
  for(const width of [320,390,1280]){
   await page.setViewportSize({width,height:844});
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,kind+' document overflow at '+width);
   const form=await page.locator('.loginForm').boundingBox();assert.ok(form.x>=0&&form.x+form.width<=width+1);
   if(kind==='link'){
    await page.locator('[name=username]').fill('Synthetic account');await page.locator('[name=password]').fill('Synthetic only');await page.locator('button.login').focus();await page.keyboard.press('Shift+Tab');assert.equal(await page.evaluate(()=>document.activeElement.name),'password');
    const buttons=await page.locator('button.login').boundingBox();assert.ok(buttons.height>=44);assert.ok((await page.locator('[name=username]').boundingBox()).height>=44);assert.ok((await page.locator('[name=password]').boundingBox()).height>=44);assert.ok((await page.locator('.centralen-new-profile').boundingBox()).height>=44);
   }else{
    assert.equal(await page.locator('a.centralen-login-link').getAttribute('href'),'centralen-login.php?action=start');assert.equal(await page.locator('input[type=password]').count(),0);
   }
   if([390,1280].includes(width))await page.screenshot({path:path.join(root,`DEBUG/centralen-${kind}-${width}.png`),fullPage:true});checks++;
  }
  assert.deepEqual(errors,[]);await page.close();
 }
 const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.addInitScript(()=>{localStorage.setItem('workflow_last_delegated_worker',JSON.stringify({id:999,username:'Legacy-only colleague'}));localStorage.setItem('workflow_order_draft_v1',JSON.stringify({orderTitle:'Retained draft',desc:'Keep this description',worker:999,workerName:'Legacy-only colleague'}));});
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url());if(url.origin!=='https://workflow.test')return route.abort();
  if(url.pathname==='/picker')return route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><meta charset="utf-8"></head><body><div id="delegationList"></div><button id="delegationPill"></button><span id="delegationPillName"></span><span id="delegationPillAvatar"></span><span id="delegationPillRole"></span><div id="delegationDropdown"></div><button id="lastDelegatedChip"><span id="lastDelegatedName"></span></button><div id="orderModalOverlay"><form id="orderModalForm"><input id="orderTitle"><textarea id="orderDesc"></textarea><input id="orderCompanyName"><input id="orderCompanyDomain"><input id="orderOrg"><input id="orderContact"><input id="orderAdminUser"><input id="orderAdminPass"><input id="orderWorkerSelect"><button id="orderSubmitBtn" type="submit">Save</button></form></div><script>window.WorkflowSharedUsers=true;window.CurrentUser={id:1,username:'Me'};window.TeamWorkers=[{id:1,username:'Me'},{id:2,username:'Current Core colleague'}];window.notices=[];window.pageAlert=(title,body)=>notices.push({title,body});</script><script src="/ui/js/jquery.js"></script><script src="/ui/js/orderModal.js"></script></body></html>`});
  if(url.pathname.startsWith('/php/')){assert.equal(route.request().method(),'GET','stale draft never posts');return route.fulfill({json:{success:true,orders:[],workers:[{id:1,username:'Me'},{id:2,username:'Current Core colleague'}]}});}
  const file=path.resolve(root,'.'+url.pathname);return route.fulfill({body:await fs.readFile(file),contentType:'text/javascript'});
 });
 await page.goto('https://workflow.test/picker');await page.locator('[data-worker-id="2"]').waitFor();assert.equal(await page.locator('[data-worker-id="999"]').count(),0);assert.equal(await page.locator('#lastDelegatedChip').isVisible(),false);checks++;
 await page.evaluate(()=>openOrderModal());assert.equal(await page.locator('#orderTitle').inputValue(),'Retained draft');assert.equal(await page.locator('#orderDesc').inputValue(),'Keep this description');await page.locator('#orderSubmitBtn').click();assert.equal(await page.evaluate(()=>notices[0]?.title),'Välj mottagare');assert.deepEqual(errors,[]);checks++;await page.close();
 console.log(`Workflow shared login browser: ${checks} viewport/state checks PASS; screenshots in DEBUG, synthetic fixtures.`);
}finally{await browser.close();}
