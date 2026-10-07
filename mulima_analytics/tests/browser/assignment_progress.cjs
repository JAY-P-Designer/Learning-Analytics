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
const artifacts=process.env.TEST_ARTIFACT_DIR||path.join(__dirname,'artifacts');
fs.mkdirSync(artifacts,{recursive:true});
const assignment={id:501,name:'Trabalho <img src=x> & análise',coursename:'Disciplina de teste',courseid:101,
 submitted:40,corrected:20,pending:20,percent:50,level:'in_progress',team:false,offline:false};
const empty={...assignment,id:502,name:'Trabalho sem entregas',submitted:0,corrected:0,pending:0,percent:null,level:'no_submissions'};
const base={email:'teacher@example.test',courses:['Disciplina de teste'],activities:0,resources:0,graded:0,
  last_ts:0,last_access:null,forums_total:0,forums_participated:0,forum_posts:0,forum_discussions:0,
  forum_replies:0,forum_last_ts:0,forum_last_post:null,score:0,
  assignments:[assignment,empty],assignments_total:2,submissions_total:40,submissions_graded:20,
  submissions_pending:20,grading_percent:50,grading_level:'in_progress'};
function data(range){
 return [
  {...base,userid:301,fullname:'Docente A',graded:range==='7'?2:10,score:range==='7'?8:40},
  {...base,userid:302,fullname:'Docente B',graded:0,score:0},
  {...base,userid:303,fullname:'Docente C',assignments:[empty],assignments_total:1,
    submissions_total:0,submissions_graded:0,submissions_pending:0,grading_percent:null,grading_level:'no_submissions'},
  {...base,userid:304,fullname:'Docente D',assignments:[],assignments_total:0,
    submissions_total:0,submissions_graded:0,submissions_pending:0,grading_percent:null,grading_level:'no_assignments'}
 ];
}
(async()=>{
 const browser=await launch();let passed=0;
 try {
  for(const language of ['pt','en']) {
   const {page,requests,errors}=await fixture(browser,root,{tab:'docentes',language,
    respond:p=>p.op==='teachers'?{data:{ok:true,teachers:data(p.range)}}:undefined});
   try {
    assert.equal(await page.locator('#doc_results').isVisible(),false);assert.equal(requests.length,0);
    await page.selectOption('#flt_period','1');
    await page.waitForFunction(()=>document.querySelectorAll('#doc_tbody tr').length===4);
    assert.equal(await page.locator('#ds_graded').innerText(),'20 / 40');
    assert.match(await page.locator('#ds_grading_summary').innerText(),/50%/);
    assert.equal(await page.locator('#ds_active').innerText(),'1');
    const first=page.locator('#doc_tbody tr').filter({hasText:'Docente A'}).locator('.dc-assign');
    assert.equal(await first.locator('[role=progressbar]').getAttribute('aria-valuenow'),'50');
    assert.match(await first.innerText(),language==='pt'?/20 de 40 submissões corrigidas/:/20 of 40 submissions graded/);
    assert.match(await first.innerText(),language==='pt'?/Avaliações pelo docente \(30 dias\): 10/:/Teacher assessments \(30 days\): 10/);
    await first.locator('summary').click();assert.equal(await first.locator('li').count(),2);
    assert.match(await first.locator('li').first().innerText(),/Trabalho <img src=x> & análise/);
    assert.equal(await first.locator('img').count(),0);
    assert.match(await first.locator('li').last().innerText(),language==='pt'?/Sem submissões/:/No submissions/);
    console.log('PASS '+language+': progress, teacher contribution, shared-course deduplication and full escaped assignment list');passed++;
    const nosub=page.locator('#doc_tbody tr').filter({hasText:'Docente C'}).locator('.dc-assign');
    const noassign=page.locator('#doc_tbody tr').filter({hasText:'Docente D'}).locator('.dc-assign');
    assert.match(await nosub.innerText(),language==='pt'?/Sem submissões/:/No submissions/);
    assert.equal(await nosub.locator('[role=progressbar]').count(),0);
    assert.match(await noassign.innerText(),language==='pt'?/Sem trabalhos nestas disciplinas/:/No assignments in these courses/);
    await page.locator('[data-k=grading_percent]').click();await page.locator('[data-k=grading_percent]').click();
    assert.deepEqual(errors,[]);
    console.log('PASS '+language+': empty states and sorting have no division-by-zero or JavaScript errors');passed++;
    await page.selectOption('#flt_range','7');
    await page.waitForFunction(()=>document.querySelector('#doc_assign_window').textContent.includes('7'));
    assert.equal(requests.filter(p=>p.op==='teachers').at(-1).range,'7');
    assert.equal(await page.locator('#ds_graded').innerText(),'20 / 40');
    assert.match(await first.innerText(),language==='pt'?/Avaliações pelo docente \(7 dias\): 2/:/Teacher assessments \(7 days\): 2/);
    await page.locator('#btn_xls').click();
    assert.equal(new URL(await page.evaluate(()=>window.__exports.at(-1)),'http://learning.test').searchParams.get('range'),'7');
    await first.locator('summary').click();
    await page.locator('#doc_results').screenshot({path:path.join(artifacts,'assignments-'+language+'-desktop.png')});
    await page.setViewportSize({width:390,height:900});
    const rect=await page.locator('#doc_results').boundingBox();assert(rect.x+rect.width<=391);
    await first.scrollIntoViewIfNeeded();assert(await first.isVisible());
    await page.locator('#doc_results').screenshot({path:path.join(artifacts,'assignments-'+language+'-mobile.png')});
    await page.selectOption('#flt_period','');await page.locator('#doc_results').waitFor({state:'hidden'});
    assert.equal(await page.locator('#doc_tbody tr').count(),0);assert.deepEqual(errors,[]);
    console.log('PASS '+language+': interval, export, mobile layout and reset preserve intended scope');passed++;
   } finally {await page.close();}
  }
  console.log('RESULT '+passed+' assignment browser scenarios passed.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
