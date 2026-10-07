/*!
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package local_mulima_analytics
 * @copyright 2026 Joaquim Pascoal Mulima Junior
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
const {launch,fixture}=require('./fixture.cjs');
const root=path.resolve(__dirname,'../..');
const artifacts=process.env.TEST_ARTIFACT_DIR||path.join(__dirname,'artifacts');fs.mkdirSync(artifacts,{recursive:true});
const config={cobertura:{op:'overview',body:'#cb_tbody',wrap:'#cb_results',empty:'#cb_empty'},risco:{op:'risk',body:'#rsk_tbody',wrap:'#rsk_results',empty:'#rsk_good'},docentes:{op:'teachers',body:'#doc_tbody',wrap:'#doc_results',empty:'#doc_empty'}};
const level=n=>'#report_category_'+n;
async function rows(page,tab,n){await page.waitForFunction(({selector,n})=>document.querySelectorAll(selector+' tr').length===n,{selector:config[tab].body,n});}
async function hierarchy(page,tab){
 await page.selectOption('#flt_period','1');await page.selectOption(level(1),'10');
 await page.selectOption(level(2),'20');await page.selectOption(level(3),'30');await page.selectOption(level(4),'30');await rows(page,tab,2);
}
(async()=>{
 const browser=await launch();let passed=0;
 async function test(tab,name,fn,respond){
  const f=await fixture(browser,root,{tab,respond});
  try{await fn(f);assert.deepEqual(f.errors,[]);console.log('PASS '+tab+': '+name);passed++;}finally{await f.page.close();}
 }
 try{
  for(const tab of Object.keys(config)){
   const cfg=config[tab];
   await test(tab,'initial state sends no request; category selector is disabled',async({page,requests})=>{
    assert(await page.locator(level(1)).isDisabled());assert.equal(requests.length,0);
   });
   await test(tab,'three category levels, leaf, subtree and All at each level',async({page})=>{
    await hierarchy(page,tab);assert.equal(await page.locator('#flt_cat').inputValue(),'30');
    await page.selectOption(level(4),'40');await rows(page,tab,1);
    await page.selectOption(level(3),'20');await rows(page,tab,4);assert.equal(await page.locator(level(4)).count(),0);
    await page.selectOption(level(2),'10');await rows(page,tab,6);
    await page.selectOption(level(1),'1');await rows(page,tab,7);
   });
   await test(tab,'one report per settled selection; category requests do not duplicate reports',async({page,requests})=>{
    await page.selectOption('#flt_period','1');await rows(page,tab,1);assert.equal(requests.filter(p=>p.op===cfg.op).length,1);
    await page.selectOption(level(1),'10');await rows(page,tab,1);await page.waitForTimeout(230);assert.equal(requests.filter(p=>p.op===cfg.op).length,2);
   });
   await test(tab,'empty category clears previous table and export',async({page})=>{
    await page.selectOption('#flt_period','1');await rows(page,tab,1);await page.selectOption(level(1),'11');
    await page.locator(cfg.wrap).waitFor({state:'hidden'});assert.equal(await page.locator(cfg.body+' tr').count(),0);assert.equal(await page.locator('#btn_xls').isVisible(),false);
   });
   await test(tab,'slow child categories and courses cannot overwrite another branch',async({page})=>{
    await page.selectOption('#flt_period','1');await page.selectOption(level(1),'10');await page.selectOption(level(2),'20');
    await page.selectOption(level(2),'21');await rows(page,tab,1);await page.waitForTimeout(400);
    assert.equal(await page.locator('#flt_cat').inputValue(),'21');assert.equal(await page.locator(level(3)).count(),0);
    assert((await page.locator(cfg.body).innerText()).includes(tab==='cobertura'?'Mecânica':'107'));
   },p=>(p.op==='cats'&&p.parent==='20'||p.op==='courses'&&p.catid==='20')?{delay:350}:undefined);
   await test(tab,'reset cancels an in-flight report and removes stale rows',async({page,requests})=>{
    await page.selectOption('#flt_period','1');await page.waitForTimeout(260);assert(requests.some(p=>p.op===cfg.op));
    await page.selectOption('#flt_period','');await page.waitForTimeout(600);
    assert.equal(await page.locator(cfg.body+' tr').count(),0);assert.equal(await page.locator(cfg.wrap).isVisible(),false);
    assert.equal(await page.locator('.la-filter-error').count(),0);
   },p=>p.op===cfg.op?{delay:500}:undefined);
   let fails=true;
   await test(tab,'HTTP failure shows an actionable error and retry restores report',async({page})=>{
    await page.selectOption('#flt_period','1');await page.locator('.la-filter-error[data-request="report"]').waitFor();
    assert.equal(await page.locator(cfg.empty).isVisible(),false);
    fails=false;await page.locator('.la-filter-error button').click();await rows(page,tab,1);assert.equal(await page.locator('.la-filter-error').count(),0);
   },p=>p.op===cfg.op&&fails?{status:500}:undefined);
   await test(tab,'Excel receives the same category scope',async({page})=>{
    await hierarchy(page,tab);await page.locator('#btn_xls').click();
    const urls=await page.evaluate(()=>window.__exports),u=new URL(urls.at(-1),'http://learning.test');
    assert.equal(u.pathname,'/'+tab+'_export.php');assert.equal(u.searchParams.get('period'),'1');assert.equal(u.searchParams.get('catid'),'30');
   });
   await test(tab,'desktop and mobile controls fit the viewport',async({page})=>{
    await hierarchy(page,tab);await page.locator('.la-report-filters').screenshot({path:path.join(artifacts,tab+'-desktop.png')});
    await page.setViewportSize({width:390,height:900});
    const boxes=await page.locator('.la-report-filters select,.la-report-filters input:not([type=hidden])').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return {left:r.left,right:r.right};}));
    assert(boxes.every(b=>b.left>=0&&b.right<=391));
    const periodBox=await page.locator('#flt_period').boundingBox();assert(periodBox.width>280);
    await page.locator('.la-report-filters').screenshot({path:path.join(artifacts,tab+'-mobile.png')});
   });
  }
  await test('cobertura','changing score target only reclassifies current courses; Excel retains target',async({page,requests})=>{
    await hierarchy(page,'cobertura');assert.equal(await page.locator('#cs_ok').innerText(),'1');const count=requests.length;
    await page.locator('#flt_target').fill('400');await page.locator('#flt_target').press('Tab');
    assert.equal(await page.locator('#cs_ok').innerText(),'1');assert.equal(requests.length,count);
    await page.locator('#btn_xls').click();const u=await page.evaluate(()=>window.__exports.at(-1));assert.equal(new URL(u,'http://learning.test').searchParams.get('target'),'400');
  });
  await test('cobertura','course activities, submissions and return preserve the chosen category',async({page})=>{
    await hierarchy(page,'cobertura');await page.locator('.cb-drill').first().click();await page.locator('.cb-det').first().click();
    await page.locator('#m_tbl').waitFor({state:'visible'});assert((await page.locator('#m_tbody').innerText()).includes('Estudante Teste'));
    await page.keyboard.press('Escape');await page.locator('#btn_back').click();await rows(page,'cobertura',2);assert.equal(await page.locator('#flt_cat').inputValue(),'30');
  });
  for(const tab of ['risco','docentes']){
   await test(tab,'discipline picker and area-specific controls keep selection and export parameters',async({page,requests})=>{
    await hierarchy(page,tab);await page.locator('#dcs_inp').fill('Algoritmos');await page.locator('#dcs_inp').focus();
    await page.locator('.dcs-drop-item').filter({hasText:'Algoritmos'}).click();await rows(page,tab,1);
    if(tab==='risco'){
      await page.selectOption('#flt_scope','courses');await page.locator('#flt_days').fill('30');await page.locator('#flt_days').press('Tab');
    }else await page.selectOption('#flt_range','90');
    await page.waitForTimeout(240);await rows(page,tab,1);
    const p=requests.filter(p=>p.op===config[tab].op).at(-1);assert.equal(String(p.courseid),'105');assert.equal(p.catid,'30');
    assert.equal(tab==='risco'?p.scope:p.range,tab==='risco'?'courses':'90');if(tab==='risco')assert.equal(p.days,30);
    await page.locator('#btn_xls').click();const url=await page.evaluate(()=>window.__exports.at(-1)),u=new URL(url,'http://learning.test');
    assert.equal(u.searchParams.get('courseid'),'105');assert.equal(u.searchParams.get(tab==='risco'?'scope':'range'),tab==='risco'?'courses':'90');
    if(tab==='risco')assert.equal(u.searchParams.get('days'),'30');
   });
  }
  console.log(`RESULT ${passed} additional-tab browser scenarios passed.`);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
