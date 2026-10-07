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
const {launch,fixture,courses}=require('./fixture.cjs');
const root=path.resolve(__dirname,'../..');
const configs={
 acessos:{period:'#sel_period',level:'#access_category_',result:'#div_courses',rows:'#courses_body tr',op:'panoramic',export:'#btn_xls_all'},
 cobertura:{period:'#flt_period',level:'#report_category_',result:'#cb_results',rows:'#cb_tbody tr',op:'overview',export:'#btn_xls'},
 risco:{period:'#flt_period',level:'#report_category_',result:'#rsk_results',rows:'#rsk_tbody tr',op:'risk',export:'#btn_xls'},
 docentes:{period:'#flt_period',level:'#report_category_',result:'#doc_results',rows:'#doc_tbody tr',op:'teachers',export:'#btn_xls'},
 presenca:{period:'#flt_period',level:'#report_category_',export:'#btn_xls'}
};
async function settled(page){await page.waitForTimeout(280);}
async function blank(page,selector){
 assert.equal(await page.locator(selector).inputValue(),'');
 assert.equal(await page.locator(selector).evaluate(el=>el.selectedOptions[0].textContent),'');
}
async function quiet(f,cfg){
 await settled(f.page);
 if(cfg.result)assert.equal(await f.page.locator(cfg.result).isVisible(),false);
 else assert(await f.page.locator(cfg.export).isDisabled());
 if(await f.page.locator('#dcs_inp').count()){
  assert(await f.page.locator('#dcs_inp').isDisabled());
  assert.equal(await f.page.locator('#dcs_inp').inputValue(),'');
  assert.equal(await f.page.locator('#dcs_inp').getAttribute('placeholder'),'');
 }
}
async function count(page,cfg,n){
 if(cfg.rows)await page.waitForFunction(({s,n})=>document.querySelectorAll(s).length===n,{s:cfg.rows,n});
 else await page.waitForFunction(()=>!document.querySelector('#btn_xls').disabled);
}
(async()=>{
 const browser=await launch();let passed=0;
 try{
  for(const [tab,cfg] of Object.entries(configs)){
   if(process.env.TEST_TAB&&tab!==process.env.TEST_TAB)continue;
   // Typical installation: courses only in the final categories.
   const f=await fixture(browser,root,{tab,courses:courses.filter(c=>c.id>=104)}),page=f.page;
   page.setDefaultTimeout(6000);
   await blank(page,cfg.period);await blank(page,cfg.level+1);await quiet(f,cfg);
   assert.equal(f.requests.length,0);
   await page.selectOption(cfg.period,'1');await quiet(f,cfg);await blank(page,cfg.level+1);
   await page.selectOption(cfg.level+1,'10');await quiet(f,cfg);await blank(page,cfg.level+2);
   await page.selectOption(cfg.level+2,'20');await quiet(f,cfg);await blank(page,cfg.level+3);
   assert.equal(f.requests.filter(p=>['panoramic','overview','risk','teachers'].includes(p.op)).length,0);
   if(process.env.TEST_ARTIFACT_DIR&&tab==='cobertura'){
    fs.mkdirSync(process.env.TEST_ARTIFACT_DIR,{recursive:true});
    await page.locator('.die-filters').screenshot({path:path.join(process.env.TEST_ARTIFACT_DIR,'blank-categories.png')});
   }
   await page.selectOption(cfg.level+3,'30');await count(page,cfg,1);await blank(page,cfg.level+4);
   if(tab!=='cobertura'){
    await page.locator('#dcs_inp').focus();
    assert.deepEqual(await page.locator('.dcs-drop-name').allTextContents(),['Programação <A & B>']);
    await page.locator('.dcs-drop-name').click();
    if(tab==='acessos')await page.locator('#act_body .ac-ver').first().waitFor();else await count(page,cfg,1);
    assert.equal(await page.evaluate(()=>DieCourseSearch.getSelectedId()),104);
   }
   await page.selectOption(cfg.level+4,'40');await count(page,cfg,1);
   await page.selectOption(cfg.level+3,'');await quiet(f,cfg);
   assert.equal(await page.locator(cfg.level+4).count(),0);
   await page.selectOption(cfg.level+2,'21');await count(page,cfg,1);
   await page.selectOption(cfg.period,'');await quiet(f,cfg);await blank(page,cfg.period);await blank(page,cfg.level+1);
   assert.deepEqual(f.errors,[]);await page.close();
   console.log('PASS '+tab+': blank categories, no premature reports, direct leaf courses and clearing');passed++;

   // Exceptional installation: direct courses at upper levels remain available.
   const g=await fixture(browser,root,{tab}),p=g.page;p.setDefaultTimeout(6000);
   await p.selectOption(cfg.period,'1');await count(p,cfg,1);await blank(p,cfg.level+1);
   if(cfg.op){const request=g.requests.filter(r=>r.op===cfg.op).at(-1);assert.equal(Number(request.descendants),0);}
   await p.selectOption(cfg.level+1,'1');await count(p,cfg,7);
   if(tab==='presenca'){
    const download=p.waitForRequest(r=>r.url().includes('presenca_export.php'));
    await p.route('**/presenca_export.php*',route=>route.fulfill({status:200,headers:{'content-disposition':'attachment; filename="presenca.xlsx"','content-type':'application/octet-stream'},body:'fixture'}));
    await p.locator(cfg.export).click();const url=new URL((await download).url());
    assert.equal(url.searchParams.get('cat'),'1');assert.equal(url.searchParams.get('descendants'),'1');
   }else{
    await p.locator(cfg.export).click();
    const url=new URL(await p.evaluate(()=>window.__exports.at(-1)),'http://learning.test');
    assert.equal(url.searchParams.get('descendants'),'1');
   }
   await p.selectOption(cfg.level+1,'');await count(p,cfg,1);await blank(p,cfg.level+1);
   assert.deepEqual(g.errors,[]);await p.close();
   console.log('PASS '+tab+': direct parent courses, explicit All, export scope and clearing All');passed++;
  }
  console.log('RESULT '+passed+' blank-category workflows passed.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
