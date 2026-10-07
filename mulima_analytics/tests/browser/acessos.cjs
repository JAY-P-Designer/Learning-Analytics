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
const {launch,fixture}=require('./fixture.cjs');
const path=require('node:path');
const fs=require('node:fs');
const root=path.resolve(__dirname,'../..');
const artifacts=process.env.TEST_ARTIFACT_DIR||path.join(__dirname,'artifacts');
fs.mkdirSync(artifacts,{recursive:true});
const waitRows=(page,number)=>page.waitForFunction(n=>document.querySelectorAll('#courses_body tr').length===n,number);
const select=async(page,id,value)=>{await page.locator(id).selectOption(String(value));};
const level=(n)=>'#access_category_'+n;
async function hierarchy(page){
 await select(page,'#sel_period',1);
 await select(page,level(1),10);
 await select(page,level(2),20);
 await select(page,level(3),30);
 await select(page,level(4),30);
 await waitRows(page,2);
}
(async()=>{
 const browser=await launch();let passed=0;
 async function test(name,callback,options={}){
  const f=await fixture(browser,root,options);
  try{await callback(f);assert.deepEqual(f.errors,[]);passed++;console.log('PASS '+name);}finally{await f.page.close();}
 }
 try{
  await test('Initial state: no requests without period; inputs correctly disabled',async({page,requests})=>{
    assert(await page.locator('#dcs_inp').isDisabled());assert(await page.locator(level(1)).isDisabled());assert.equal(requests.length,0);
  });
  await test('Period and three category levels scope the list and report; deeper levels work',async({page})=>{
    await hierarchy(page);
    assert.equal(await page.locator('#sel_cat').inputValue(),'30');
    assert.deepEqual(await page.locator('.crs-ver').evaluateAll(es=>es.map(e=>e.dataset.cid).sort()),['104','105']);
    assert((await page.locator('#courses_body').innerText()).includes('Programação <A & B>'));
    await select(page,level(4),40);await waitRows(page,1);
    assert.equal(await page.locator('.crs-ver').getAttribute('data-cid'),'105');
    await select(page,level(3),20);await waitRows(page,4);
    assert.equal(await page.locator(level(4)).count(),0);
    await select(page,level(2),10);await waitRows(page,6);
    await select(page,level(1),1);await waitRows(page,7);
  });
  await test('No duplicated panoramic request after category children load',async({page,requests})=>{
    await select(page,'#sel_period',1);await waitRows(page,1);
    assert.equal(requests.filter(r=>r.op==='panoramic').length,1);
    await select(page,level(1),10);await waitRows(page,1);
    await page.waitForTimeout(250);
    assert.equal(requests.filter(r=>r.op==='panoramic').length,2);
  });
  await test('Empty category stays blank and sends no panoramic query',async({page,requests})=>{
    await select(page,'#sel_period',1);await select(page,level(1),11);
    await page.waitForFunction(()=>document.getElementById('dcs_inp').disabled&&document.getElementById('dcs_spin').style.display==='none');
    assert.equal(await page.locator('#div_courses').isVisible(),false);
    assert.equal(requests.filter(r=>r.op==='panoramic'&&r.catid==='11').length,0);
    assert.equal(await page.locator(level(2)).count(),0);
  });
  await test('Rapid branch change discards slow course and category responses',async({page})=>{
    await select(page,'#sel_period',1);await select(page,level(1),10);
    await select(page,level(2),20);await select(page,level(2),21);await waitRows(page,1);
    await page.waitForTimeout(450);
    assert.equal(await page.locator('#sel_cat').inputValue(),'21');
    assert.equal(await page.locator(level(3)).count(),0);
    assert.equal(await page.locator('.crs-ver').getAttribute('data-cid'),'107');
  },{respond:p=>(p.op==='cats'&&p.parent==='20'||p.op==='courses'&&p.catid==='20')?{delay:400}:undefined});
  await test('Clearing the period while requests run leaves no stale results',async({page})=>{
    await select(page,'#sel_period',1);await select(page,'#sel_period','');await page.waitForTimeout(450);
    assert.equal(await page.locator('#sel_cat').inputValue(),'');assert(await page.locator('#dcs_inp').isDisabled());
    assert.equal(await page.locator('#courses_body tr').count(),0);assert.equal(await page.locator('#access_error').isVisible(),false);
  },{respond:p=>p.op==='courses'||p.op==='cats'?{delay:350}:undefined});
  await test('Changing periods while a report runs cannot display previous-period data',async({page,requests})=>{
    await select(page,'#sel_period',1);await page.waitForFunction(()=>document.querySelector('#dcs_badge').textContent==='1');
    await page.waitForTimeout(220);assert(requests.some(r=>r.op==='panoramic'&&r.period==='1'));
    await select(page,'#sel_period',2);await waitRows(page,1);await page.waitForTimeout(450);
    assert.equal(await page.locator('.crs-ver').getAttribute('data-cid'),'108');
    assert(await page.locator('#div_courses_tbl').isVisible());
  },{respond:p=>p.op==='panoramic'&&p.period==='1'?{delay:550}:undefined});
  let fail=true;
  await test('Failed AJAX response shows a retry action instead of an empty report',async({page})=>{
    await select(page,'#sel_period',1);await page.locator('#access_error').waitFor({state:'visible'});
    assert(await page.locator('#dcs_inp').isDisabled());assert.equal(await page.locator('#div_courses_empty').isVisible(),false);
    fail=false;await page.locator('#access_retry').click();await waitRows(page,1);
    assert.equal(await page.locator('#access_error').isVisible(),false);
  },{respond:p=>p.op==='courses'&&fail?{status:500}:undefined});
  await test('Invalid JSON/session HTML is surfaced and stops the loading state',async({page})=>{
    await select(page,'#sel_period',1);await page.locator('#access_error').waitFor({state:'visible'});
    assert((await page.locator('#access_error_text').innerText()).includes('sessão'));
    assert.equal(await page.locator('#dcs_spin').isVisible(),false);
  },{respond:p=>p.op==='courses'?{status:200,body:'<html>Login</html>'}:undefined});
  let categoryFail=true;
  await test('A category error remains visible while the independent course request completes',async({page})=>{
    await select(page,'#sel_period',1);await waitRows(page,1);
    assert(await page.locator('#access_error').isVisible());
    assert(await page.locator(level(1)).isDisabled());
    categoryFail=false;await page.locator('#access_retry').click();await select(page,level(1),10);await waitRows(page,1);
  },{respond:p=>p.op==='cats'&&categoryFail?{data:{ok:false,error:'Category lookup failed'}}:undefined});
  await test('Drill-down, detail, back and XLSX parameters use the current selection',async({page})=>{
    await hierarchy(page);await page.locator('#btn_xls_all').click();
    let urls=await page.evaluate(()=>window.__exports);assert.equal(new URL(urls[0],'http://learning.test').searchParams.get('catid'),'30');
    await page.locator('.crs-ver[data-cid="104"]').click();await page.locator('#act_body .ac-ver').first().waitFor();
    assert.equal(await page.locator('#st_ev').innerText(),'10');
    await page.locator('#btn_xls').click();
    await page.locator('#act_body .ac-ver').first().click();await page.locator('#m_content').waitFor({state:'visible'});
    await page.evaluate(()=>exportDtl());urls=await page.evaluate(()=>window.__exports);
    assert.equal(new URL(urls[1],'http://learning.test').searchParams.get('courseid'),'104');
    assert.equal(new URL(urls[2],'http://learning.test').searchParams.get('cmid'),'501');
    assert.equal(new URL(urls[2],'http://learning.test').searchParams.get('courseid'),'104');
    await page.keyboard.press('Escape');await page.locator('#btn_back').click();await waitRows(page,2);
  });
  await test('Course search selects only a course in the chosen subtree',async({page})=>{
    await hierarchy(page);await page.locator('#dcs_inp').fill('Algoritmos');await page.locator('#dcs_inp').focus();
    await page.locator('.dcs-drop-item').filter({hasText:'Algoritmos'}).click();
    await page.locator('#act_body .ac-ver').first().waitFor();assert((await page.locator('#cur_course_name').innerText()).includes('Algoritmos'));
  });
  let detailFail=true;
  await test('Detail failures can be retried inside the modal',async({page})=>{
    await hierarchy(page);await page.locator('.crs-ver[data-cid="104"]').click();
    await page.locator('#act_body .ac-ver').first().click();await page.locator('#m_error').waitFor({state:'visible'});
    assert.equal(await page.locator('#m_empty').isVisible(),false);
    detailFail=false;await page.locator('#m_retry').click();await page.locator('#m_content').waitFor({state:'visible'});
    await page.evaluate(()=>exportDtl());const urls=await page.evaluate(()=>window.__exports);
    assert.equal(new URL(urls[0],'http://learning.test').searchParams.get('cmid'),'501');
  },{respond:p=>p.op==='detail'&&detailFail?{status:500}:undefined});
  await test('A slow activity response cannot replace a newly selected period',async({page,requests})=>{
    await hierarchy(page);await page.locator('.crs-ver[data-cid="104"]').click();
    await page.waitForTimeout(30);assert(requests.some(r=>r.op==='activities'));
    await select(page,'#sel_period',2);await waitRows(page,1);await page.waitForTimeout(450);
    assert.equal(await page.locator('.crs-ver').getAttribute('data-cid'),'108');
    assert.equal(await page.locator('#div_table').isVisible(),false);
  },{respond:p=>p.op==='activities'?{delay:500}:undefined});
  await test('Desktop and mobile filter controls fit the viewport',async({page})=>{
    await hierarchy(page);
    await page.locator('#access_filters').screenshot({path:path.join(artifacts,'filters-desktop.png')});
    await page.setViewportSize({width:390,height:850});
    const boxes=await page.locator('#access_filters select,#dcs_inp').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return {left:r.left,right:r.right};}));
    assert(boxes.every(b=>b.left>=0&&b.right<=391));
    await page.locator('#access_filters').screenshot({path:path.join(artifacts,'filters-mobile.png')});
  });
  console.log(`RESULT ${passed} browser scenarios passed.`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
