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
const forums=[
  {id:11,cmid:601,courseid:101,coursename:'Programação <A & B>',name:'Fórum "sem tópicos" <img src=x>',visible:true,topics:0,posts:0,discussions:0,replies:0,last_post:null},
  {id:12,cmid:602,courseid:101,coursename:'Programação <A & B>',name:'Fórum de debate',visible:true,topics:2,posts:2,discussions:1,replies:1,last_post:'18/09/2026 10:30'},
  {id:13,cmid:603,courseid:101,coursename:'Programação <A & B>',name:'Dúvidas',visible:false,topics:1,posts:3,discussions:0,replies:3,last_post:'18/09/2026 10:30'},
  {id:14,cmid:604,courseid:101,coursename:'Programação <A & B>',name:'Avisos',visible:true,topics:1,posts:0,discussions:0,replies:0,last_post:null}
];
const base={email:'teacher@example.test',courses:['Programação <A & B>'],course_details:[{id:101,name:'Programação <A & B>'}],activities:0,resources:0,graded:0,
  last_ts:0,last_access:null,forums_total:4,forums_participated:0,forum_posts:0,forum_discussions:0,
  forum_replies:0,forum_last_ts:0,forum_last_post:null,score:0,
  forums:forums.map(f=>({...f,posts:0,discussions:0,replies:0,last_post:null}))};
const teachers=[
  {...base,userid:301,fullname:'Docente com participação',forums_participated:2,forum_posts:5,forum_discussions:1,
    forum_replies:4,forum_last_ts:1800000000,forum_last_post:'18/09/2026 10:30',score:5,forums},
  {...base,userid:302,fullname:'Docente sem publicações'},
  {...base,userid:303,fullname:'Docente sem fóruns',forums_total:0,forums:[]}
];
(async()=>{
 const browser=await launch();let passed=0;
 try {
  for(const language of ['pt','en']) {
   const {page,requests,errors}=await fixture(browser,root,{tab:'docentes',language,
    respond:p=>p.op==='teachers'?{data:{ok:true,teachers}}:undefined});
   try {
    assert.equal(await page.locator('#doc_results').isVisible(),false);
    assert.equal(requests.length,0);
    await page.selectOption('#flt_period','1');
    await page.waitForFunction(()=>document.querySelectorAll('#doc_tbody tr').length===3);
    const first=page.locator('#doc_tbody tr').first();
    const forum=first.locator('.dc-forum');
    assert.equal(await forum.locator('.dc-m').innerText(),'5');
    assert.match(await forum.innerText(),language==='pt'?/2 de 4 fóruns/:/2 of 4 forums/);
    assert.match(await forum.innerText(),language==='pt'?/Tópicos iniciados: 1 · Respostas: 4/:/Discussions started: 1 · Replies: 4/);
    await forum.locator('summary').first().click();assert.match(await forum.innerText(),/18\/09\/2026 10:30/);
    await forum.locator('.dc-forum-details > summary').click();
    const items=forum.locator('.dc-forum-list li');
    assert.equal(await items.count(),4);
    assert.match(await items.first().innerText(),language==='pt'?/Sem tópicos/:/No discussions/);
    assert.match(await items.first().innerText(),language==='pt'?/Sem participação no período/:/No participation in the period/);
    assert.equal(await items.first().locator('img').count(),0);
    const link=items.first().locator('strong a');
    assert.equal(await link.getAttribute('href'),'http://learning.test/mod/forum/view.php?id=601');
    assert.equal(await link.getAttribute('target'),'_blank');
    assert.equal(await link.getAttribute('rel'),'noopener noreferrer');
    assert.equal(await items.first().locator('.dc-forum-meta a').getAttribute('href'),'http://learning.test/course/view.php?id=101');
    assert.match(await items.nth(1).locator('.dc-forum-status').innerText(),language==='pt'?/Com participação no período/:/Participated in the period/);
    assert.match(await items.nth(2).innerText(),language==='pt'?/Actividade oculta/:/Hidden activity/);
    assert.match(await items.last().innerText(),language==='pt'?/Tópicos existentes: 1/:/Existing discussions: 1/);
    assert.match(await items.last().locator('.dc-forum-status').innerText(),language==='pt'?/Sem participação no período/:/No participation in the period/);
    assert.equal(await page.locator('#ds_active').innerText(),'1');
    assert.equal(await page.locator('#ds_idle').innerText(),'2');
    console.log('PASS '+language+': full forum list, empty discussions, own interaction, safe forum/course links, hidden activity and last post');passed++;
    const idle=page.locator('#doc_tbody tr').nth(1).locator('.dc-forum');
    assert.match(await idle.innerText(),language==='pt'?/0 de 4 fóruns/:/0 of 4 forums/);
    assert.match(await idle.innerText(),language==='pt'?/Sem publicações no período/:/No posts in the period/);
    assert.match(await page.locator('#doc_tbody tr').nth(2).innerText(),language==='pt'?/Sem fóruns nestas disciplinas/:/No forums in these courses/);
    await page.locator('[data-k="forum_posts"]').click();
    await page.locator('[data-k="forum_posts"]').click();
    assert.match(await page.locator('#doc_tbody tr').last().innerText(),/Docente com participação/);
    console.log('PASS '+language+': no-forum and no-post states differ; forum sorting works');passed++;
    await page.selectOption('#flt_range','90');
    await page.waitForFunction(()=>document.querySelector('#doc_forum_window').textContent.includes('90'));
    assert.equal(await page.locator('#doc th.sortable.act').getAttribute('data-k'),'score');
    assert.equal(requests.filter(p=>p.op==='teachers').at(-1).range,'90');
    await page.locator('#btn_xls').click();
    const url=await page.evaluate(()=>window.__exports.at(-1));
    assert.equal(new URL(url,'http://learning.test').searchParams.get('range'),'90');
    await page.locator('.dc-forum-details > summary').first().click();
    await page.locator('#doc_results').screenshot({path:path.join(artifacts,'forums-'+language+'-desktop.png')});
    await page.setViewportSize({width:390,height:900});
    await page.locator('.dc-forum').first().scrollIntoViewIfNeeded();
    assert(await page.locator('.dc-forum').first().isVisible());
    const rect=await page.locator('#doc_results').boundingBox();assert(rect.x+rect.width<=391);
    await page.locator('#doc_results').screenshot({path:path.join(artifacts,'forums-'+language+'-mobile.png')});
    await page.selectOption('#flt_period','');
    await page.locator('#doc_results').waitFor({state:'hidden'});
    assert.equal(await page.locator('#doc_tbody tr').count(),0);
    assert.deepEqual(errors,[]);
    console.log('PASS '+language+': date filter, export, mobile visibility and clearing stale results');passed++;
   } finally {await page.close();}
  }
  console.log('RESULT '+passed+' forum browser scenarios passed.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
