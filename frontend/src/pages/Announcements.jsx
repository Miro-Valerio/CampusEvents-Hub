import React,{useEffect,useState} from 'react';
import {useSearchParams} from 'react-router-dom';
import {Bell,Pin,CheckCheck} from 'lucide-react';
import {api,localDateTime} from '../lib/api';
import {useToast} from '../context/ToastContext';
import {useLoad,PageTitle,Loading,ErrorState,EmptyState} from '../components/Widgets';

const emoji=['👍','❤️','🎉','🙌'];

export default function Announcements(){
 const {data:departments}=useLoad('/departments');
 const [searchParams,setSearchParams]=useSearchParams();
 const [department,setDepartment]=useState(searchParams.get('department_id')||'');
 const [page,setPage]=useState(Number(searchParams.get('page')||1));
 const targetId=searchParams.get('announcement');
 const searchKey=searchParams.toString();
 const params=new URLSearchParams({
  ...department?{department_id:department}:{},
  ...targetId?{announcement:targetId}:{},
  page,
 });
 const {data,loading,error,reload}=useLoad('/announcements?'+params.toString());
 const {flash}=useToast();

 useEffect(()=>{
  if(!targetId||loading||!data?.data)return;
  const target=document.getElementById(`announcement-${targetId}`);
  if(target){
   target.scrollIntoView({behavior:'smooth',block:'center'});
   target.classList.add('announcement-focus');
  }
 },[targetId,loading,data]);
 useEffect(()=>{setDepartment(searchParams.get('department_id')||'');setPage(Number(searchParams.get('page')||1));},[searchKey]);
 function updateDepartment(value){setDepartment(value);setPage(1);setSearchParams(current=>{const next=new URLSearchParams(current);if(value)next.set('department_id',value);else next.delete('department_id');next.delete('page');return next;},{replace:true});}
 function changePage(value){setPage(value);setSearchParams(current=>{const next=new URLSearchParams(current);next.set('page',String(value));return next;},{replace:true});}

 async function react(id,value){
  try{await api(`/announcements/${id}/react`,{method:'POST',body:{emoji:value}});reload();}
  catch(e){flash(e.message,'error');}
 }
 async function read(id){
  try{await api(`/announcements/${id}/read`,{method:'POST'});reload();}
  catch(e){flash(e.message,'error');}
 }
 const rows=data?.data||[];
 return <div className="container page-section narrow-page">
  <PageTitle eyebrow="CAMPUS BULLETIN" title="Announcements." description="Your source for official SPC updates and department news."/>
  <div className="filter-bar announcement-filters">
   <select aria-label="Filter by department" value={department} onChange={e=>updateDepartment(e.target.value)}>
    <option value="">All departments</option>{departments?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}
   </select>
  </div>
  {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!rows.length?<EmptyState title="No announcements yet" description="No updates match the selected filters."/>:
  <div className="announcement-list">
   {rows.map(a=><article id={`announcement-${a.id}`} className={`announcement-card ${a.is_read?'':'announcement-unread'}`} key={a.id}>
    <div className="announcement-card-top">
     <div className="announcement-author">
      <span className="announcement-avatar"><Bell size={20}/></span>
      <div><strong>{a.department?.name||'SPC Campus'}</strong><small>Posted by {a.creator?.name||'Organizer'} · {localDateTime(a.created_at)}</small></div>
     </div>
     {a.is_pinned&&<span className="pin-tag"><Pin size={13}/> PINNED</span>}
    </div>
    <h2>{a.title}</h2>
    <p className="preserve-lines">{a.body}</p>
    {a.target_course&&<span className="course-tag">For: {a.target_course}</span>}
    <div className="announcement-footer">
     <div className="reactions">{emoji.map(em=><button key={em} className={`reaction ${a.my_reaction===em?'selected':''}`} onClick={()=>react(a.id,a.my_reaction===em?null:em)} title={`React ${em}`}>{em} <span>{a.reactions?.[em]||0}</span></button>)}</div>
     {!a.is_read?<button className="text-link" onClick={()=>read(a.id)}><CheckCheck size={17}/> Mark read</button>:<span className="read-label"><CheckCheck size={16}/> Read</span>}
    </div>
   </article>)}
   {data?.last_page>1&&<div className="pagination">
    <button className="btn btn-secondary" disabled={page===1} onClick={()=>changePage(page-1)}>Previous</button>
    <span>Page {page} of {data.last_page}</span>
    <button className="btn btn-secondary" disabled={page>=data.last_page} onClick={()=>changePage(page+1)}>Next</button>
   </div>}
  </div>}
 </div>;
}
