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
const keys={activities:'weight_activities',resources:'weight_resources',graded:'weight_grading',forum_posts:'weight_forums'};
function scoring(counts,rules){
 const score_components=Object.fromEntries(Object.entries(keys).map(([k,w])=>[k,{count:counts[k]||0,weight:rules[w],points:(counts[k]||0)*rules[w]}]));
 const score_raw=Object.values(score_components).reduce((n,c)=>n+c.points,0),score=Math.min(rules.maximum,score_raw);
 return {score_components,score_raw,score,score_max:rules.maximum,score_level:!score?'none':score>=rules.high?'high':score>=rules.moderate?'moderate':'low',has_activity:Object.values(counts).some(n=>n>0)};
}
function teachers(rules){
 return [
  {userid:301,fullname:'Docente A',courses:['Programação <A & B>','Algoritmos'],counts:{activities:10,resources:4,graded:21,forum_posts:4},
   course_scores:[{courseid:104,coursename:'Programação <A & B>',...scoring({activities:10,resources:4,graded:20,forum_posts:4},rules)},
    {courseid:105,coursename:'Algoritmos',...scoring({graded:1},rules)}]},
  {userid:302,fullname:'Docente B',courses:['Algoritmos'],counts:{activities:1},course_scores:[{courseid:105,coursename:'Algoritmos',...scoring({activities:1},rules)}]},
  {userid:303,fullname:'Docente C',courses:['Algoritmos'],counts:{},course_scores:[{courseid:105,coursename:'Algoritmos',...scoring({},rules)}]}
 ].map(t=>({...t,...t.counts,...scoring(t.counts,rules),email:'docente@example.test',assignments:[],assignments_total:0,forums:[],forums_total:0,last_ts:0,last_access:null}));
}
(async()=>{
 const browser=await launch();let passed=0;
 try {
  for(const language of ['pt','en']){
   let rules={weight_activities:5,weight_resources:7,weight_grading:2,weight_forums:3,maximum:150,moderate:40,high:100};
   const {page,errors}=await fixture(browser,root,{tab:'docentes',language,respond:p=>p.op==='teachers'?{data:{ok:true,teachers:teachers(rules),scoring:rules}}:undefined});
   try {
    await page.selectOption('#flt_period','1');
    await page.locator('#doc_tbody tr').first().waitFor();
    const first=page.locator('#doc_tbody tr').first();
    assert.equal(await first.locator('.dc-score').innerText(),'132');
    assert.match(await first.locator('.dc-pill').innerText(),language==='pt'?/Alta/i:/High/i);
    assert.match(await page.locator('#doc_score_formula').innerText(),/150/);
    assert.equal(await page.locator('#ds_active').innerText(),'2');
    await first.locator('.dc-score-open').click();
    const dialog=page.locator('#doc-score-dialog');
    assert(await dialog.isVisible());
    assert.equal(await dialog.locator('tbody tr').count(),2);
    assert.match(await dialog.innerText(),/10 × 5 = 50/);
    assert.match(await dialog.innerText(),/20 × 2 = 40/);
    assert.match(await dialog.innerText(),/0 × 7 = 0/);
    assert.equal(await dialog.locator('tbody tr').first().locator('a').getAttribute('href'),'http://learning.test/course/view.php?id=104');
    assert.equal(await dialog.locator('img').count(),0);
    await dialog.screenshot({path:path.join(artifacts,'score-'+language+'-desktop.png')});
    await page.keyboard.press('Escape');assert.equal(await dialog.isVisible(),false);
    assert(await first.locator('.dc-score-open').evaluate(n=>n===document.activeElement));
    console.log('PASS '+language+': custom weights, score above 99, custom levels and accessible per-course breakdown');passed++;
    await page.setViewportSize({width:390,height:900});
    await first.locator('.dc-score-open').click();
    const rect=await dialog.boundingBox();assert(rect.x>=0&&rect.x+rect.width<=390);
    assert(await dialog.locator('.score-table-scroll').evaluate(n=>n.scrollWidth>n.clientWidth));
    await dialog.screenshot({path:path.join(artifacts,'score-'+language+'-mobile.png')});
    await dialog.locator('.score-close').click();
    assert.equal(await dialog.isVisible(),false);
    rules={...rules,weight_activities:0,weight_resources:0,weight_grading:0,weight_forums:0};
    await page.selectOption('#flt_range','90');
    await page.waitForFunction(()=>document.querySelector('#doc_tbody .dc-score')?.textContent==='0');
    assert.equal(await page.locator('#ds_active').innerText(),'2');
    assert.equal(await page.locator('#ds_idle').innerText(),'1');
    await page.locator('.dc-fchip[data-f=act]').click();assert.equal(await page.locator('#doc_tbody tr').count(),2);
    await page.locator('.dc-score-open').first().click();
    assert.match(await dialog.innerText(),/10 × 0 = 0/);
    assert.match(await dialog.innerText(),/90/);
    await dialog.locator('.score-close').click();
    await page.selectOption('#flt_period','');
    await page.locator('#doc_results').waitFor({state:'hidden'});assert.deepEqual(errors,[]);
    console.log('PASS '+language+': mobile dialog, changed settings, zero weights, real activity filter and clean reset');passed++;
   }finally{await page.close();}
  }
  console.log('RESULT '+passed+' teacher scoring browser scenarios passed.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
