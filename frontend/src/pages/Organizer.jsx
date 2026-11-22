import React,{useEffect,useState} from 'react';
import {Link,useSearchParams} from 'react-router-dom';
import {CalendarDays,Users,TicketCheck,Newspaper,Plus,Download,PenSquare,XCircle,BarChart3,ClipboardList,Bell,ArrowRight,Eye,ShieldCheck} from 'lucide-react';
import {QRCodeSVG} from 'qrcode.react';
import {useAuth} from '../context/AuthContext';
import {useToast} from '../context/ToastContext';
import {api,downloadCsv,localDateTime,toInputDate} from '../lib/api';
import {useLoad,PageTitle,Loading,ErrorState,EmptyState,Stat,StatusBadge,Field,FormNotice} from '../components/Widgets';
const tabs=[['overview','Overview'],['events','Manage events'],['attendees','Attendees'],['announcements','Announcements'],['members','Memberships'],['officers','Officer access'],['analytics','Analytics']];
export default function Organizer(){const {user}=useAuth(),{data:departments}=useLoad('/departments');const [params,setParams]=useSearchParams();const visibleTabs=tabs;const tab=visibleTabs.some(([id])=>id===params.get('tab'))?params.get('tab'):'overview';const dept=user?.role==='admin'?Number(params.get('department_id')||departments?.[0]?.id||0):user?.department_id;
 const allowed=a=>user?.role==='admin'||user?.role==='president'||(['officer','faculty'].includes(user?.role)&&user?.officer_permissions?.includes(a));
 const changeTab=t=>setParams(p=>{p.set('tab',t);return p;});
 return <div className="container page-section"><PageTitle eyebrow="DEPARTMENT WORKSPACE" title="Organizer dashboard." description="Manage campus events, announcements, members, and attendance." action={user?.role==='admin'?<select aria-label="Manage department" value={dept||''} onChange={e=>setParams(p=>{p.set('department_id',e.target.value);return p;})}>{departments?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select>:<span className="role-label">{user?.department?.name||'My department'}</span>}/><div className="tab-nav">{visibleTabs.map(([id,label])=><button key={id} onClick={()=>changeTab(id)} className={tab===id?'active':''}>{label}</button>)}</div>
 {!dept?<EmptyState title="Select a department" description="Choose a department to access organizer tools."/>:tab==='overview'?<OrgOverview dept={dept}/>:tab==='events'?allowed('events')?<ManageEvents dept={dept}/>:<AccessDenied/>:tab==='attendees'?allowed('events')?<Attendees dept={dept}/>:<AccessDenied/>:tab==='announcements'?allowed('announcements')?<ManageAnnouncements dept={dept}/>:<AccessDenied/>:tab==='members'?allowed('members')?<ManageMembers dept={dept}/>:<AccessDenied/>:tab==='officers'?(user?.role==='admin'||user?.role==='president')?<ManageOfficers dept={dept}/>:<AccessDenied/>:<OrgAnalytics dept={dept}/>}</div>;
}
function AccessDenied(){return <div className="state-card"><ShieldCheck size={30}/><h3>Permission required</h3><p>Your department president or administrator must grant permission to manage this section.</p></div>}
function OrgOverview({dept}){const {data:events}=useLoad(`/organizer/events?department_id=${dept}`,dept),{data:stats,loading,error,reload}=useLoad(`/organizer/overview?department_id=${dept}`,dept);
 return <>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="dashboard-stats"><Stat icon={CalendarDays} label="Department events" value={stats?.events}/><Stat icon={TicketCheck} label="Active RSVPs" value={stats?.rsvps}/><Stat icon={Users} label="Approved members" value={stats?.members}/><Stat icon={ClipboardList} label="Pending requests" value={stats?.pending}/></div>}<div className="panel spaced-panel"><div className="panel-head"><h2>Recent department events</h2></div>{!events?.length?<EmptyState title="No events yet" description="Create your first department activity in Manage events."/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Start</th><th>Seats</th><th>Status</th></tr></thead><tbody>{events?.slice(0,6).map(e=><tr key={e.id}><td><Link to={`/events/${e.id}`}>{e.title}</Link></td><td>{localDateTime(e.starts_at)}</td><td>{e.attendee_count}/{e.seat_limit}</td><td><StatusBadge value={e.schedule_status||e.status}/></td></tr>)}</tbody></table></div>}</div></>;
}
const emptyEvent={title:'',description:'',venue:'',category:'Campus',starts_at:'',ends_at:'',seat_limit:50,rules:'',requires_check_out:false,check_out_opens_at:'',check_out_closes_at:''};
function offsetDateTime(value,minutes){
 if(!value)return '';
 const date=new Date(`${value}:00Z`);
 if(Number.isNaN(date.getTime()))return '';
 date.setUTCMinutes(date.getUTCMinutes()+minutes);
 return `${date.getUTCFullYear()}-${String(date.getUTCMonth()+1).padStart(2,'0')}-${String(date.getUTCDate()).padStart(2,'0')}T${String(date.getUTCHours()).padStart(2,'0')}:${String(date.getUTCMinutes()).padStart(2,'0')}`;
}
function eventFormValue(event){
 return event?{...event,starts_at:toInputDate(event.starts_at),ends_at:toInputDate(event.ends_at),
  check_out_opens_at:toInputDate(event.check_out_opens_at),check_out_closes_at:toInputDate(event.check_out_closes_at),
  requires_check_out:Boolean(event.requires_check_out)}:emptyEvent;
}
export function EventForm({existing,onSaved,onCancel,dept}){
 const [form,setForm]=useState(()=>eventFormValue(existing)),[poster,setPoster]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false);
 const {flash}=useToast();
 useEffect(()=>{setForm(eventFormValue(existing));},[existing?.id,dept]);
 function set(key,value){
  setForm(current=>{
   const next={...current,[key]:value};
   if(key==='ends_at'&&current.requires_check_out){
    if(current.check_out_opens_at===current.ends_at)next.check_out_opens_at=value;
    if(current.check_out_closes_at===offsetDateTime(current.ends_at,30))next.check_out_closes_at=offsetDateTime(value,30);
   }
   return next;
  });
 }
 function setCheckOutRequired(required){
  setForm(current=>({...current,requires_check_out:required,
   check_out_opens_at:required?(current.check_out_opens_at||current.ends_at):'',
   check_out_closes_at:required?(current.check_out_closes_at||offsetDateTime(current.ends_at,30)):''}));
 }
 async function save(e){
  e.preventDefault();setBusy(true);setError('');
  try{
   const fd=new FormData();
   ['title','description','venue','category','starts_at','ends_at','seat_limit','rules'].forEach(key=>fd.append(key,form[key]??''));
   fd.append('department_id',dept||'');
   fd.append('requires_check_out',form.requires_check_out?'1':'0');
   fd.append('check_out_opens_at',form.requires_check_out?form.check_out_opens_at:'');
   fd.append('check_out_closes_at',form.requires_check_out?form.check_out_closes_at:'');
   if(poster)fd.append('poster',poster);
   const data=await api(existing?`/organizer/events/${existing.id}`:'/organizer/events',{method:'POST',body:fd});
   flash(existing?'Event updated.':'Event published.');onSaved?.(data);
  }catch(e){setError(e.message);}finally{setBusy(false);}
 }
 return <form className="form-stack event-edit-form" onSubmit={save}>
  <h2>{existing?'Edit event':'Create a campus event'}</h2>
  <div className="grid-two"><Field label="Event title"><input value={form.title} onChange={e=>set('title',e.target.value)} required maxLength={200}/></Field><Field label="Category"><input value={form.category} onChange={e=>set('category',e.target.value)} required maxLength={60}/></Field></div>
  <Field label="Description"><textarea rows={4} required value={form.description} onChange={e=>set('description',e.target.value)}/></Field>
  <div className="grid-two"><Field label="Venue"><input value={form.venue} onChange={e=>set('venue',e.target.value)} required/></Field><Field label="Seat capacity"><input type="number" min={1} max={10000} required value={form.seat_limit} onChange={e=>set('seat_limit',e.target.value)}/></Field><Field label="Starts at"><input type="datetime-local" required value={form.starts_at} onChange={e=>set('starts_at',e.target.value)}/></Field><Field label="Ends at"><input type="datetime-local" required value={form.ends_at} onChange={e=>set('ends_at',e.target.value)}/></Field></div>
  <label className="checkbox-label"><input type="checkbox" checked={form.requires_check_out} onChange={e=>setCheckOutRequired(e.target.checked)}/> Require check-out to complete attendance</label>
  {form.requires_check_out&&<div className="grid-two"><Field label="Check-out opens at" hint="Defaults to the event end time."><input type="datetime-local" min={form.starts_at||undefined} max={form.ends_at||undefined} required value={form.check_out_opens_at} onChange={e=>set('check_out_opens_at',e.target.value)}/></Field><Field label="Check-out closes at" hint="The event end plus a 30-minute grace period is the default; maximum is two hours after the event."><input type="datetime-local" min={form.ends_at||undefined} max={offsetDateTime(form.ends_at,120)||undefined} required value={form.check_out_closes_at} onChange={e=>set('check_out_closes_at',e.target.value)}/></Field></div>}
  <Field label="Event guidelines / rules"><textarea rows={3} value={form.rules||''} onChange={e=>set('rules',e.target.value)}/></Field>
  <Field label="Event poster" hint="Upload JPG, PNG, or WEBP (max 5 MB)."><input type="file" accept="image/png,image/jpeg,image/webp" onChange={e=>setPoster(e.target.files?.[0]||null)}/></Field>
  <FormNotice error={error}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={onCancel}>Cancel</button><button disabled={busy} className="btn btn-primary">{busy?'Saving...':existing?'Save changes':'Publish event'}</button></div>
 </form>;
}
function ManageEvents({dept}){
 const {data,loading,error,reload}=useLoad(`/organizer/events?department_id=${dept||''}`,dept),{flash}=useToast();
 const [edit,setEdit]=useState(undefined),[busy,setBusy]=useState(0);
 useEffect(()=>{const timer=setInterval(reload,30000);return()=>clearInterval(timer);},[reload]);
 async function cancel(event){
  if(!confirm(`Cancel "${event.title}"? All RSVP holders will be notified.`))return;
  setBusy(event.id);
  try{const result=await api(`/organizer/events/${event.id}/cancel`,{method:'POST'});flash(result.message);reload();}
  catch(e){flash(e.message,'error');}finally{setBusy(0);}
 }
 return <div className="panel">
  <div className="panel-head"><h2>Department events</h2><button className="btn btn-primary" onClick={()=>setEdit({})}><Plus size={16}/> Create event</button></div>
  {edit!==undefined&&<div className="inline-editor"><EventForm key={edit.id||'create'} dept={dept} existing={edit.id?edit:null} onCancel={()=>setEdit(undefined)} onSaved={()=>{setEdit(undefined);reload();}}/></div>}
  {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.length?<EmptyState title="No events published" description="Create the first event for your department."/>:
   <div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Schedule</th><th>Registered</th><th>Pending</th><th>Verified</th><th>Completed</th><th>Absent</th><th>Status</th><th>Actions</th></tr></thead><tbody>{data.map(e=><tr key={e.id}>
    <td><strong>{e.title}</strong><small>{e.venue}</small></td><td>{localDateTime(e.starts_at)}</td><td>{e.attendee_count}/{e.seat_limit}</td>
    <td>{e.pending_checkin_count||0}</td><td>{e.verified_checkin_count||0}</td><td>{e.attended_count||0}</td>
    <td>{e.attendance_window_closed?e.absent_count||0:'—'}</td><td><StatusBadge value={e.schedule_status||e.status}/></td>
    <td><div className="row-actions"><Link className="btn btn-secondary btn-xs" to={`/events/${e.id}`}>View</Link><button className="btn btn-secondary btn-xs" onClick={()=>{setEdit(e);window.scrollTo({top:230,behavior:'smooth'});}} disabled={e.status==='cancelled'}>Edit</button><button className="btn btn-danger-light btn-xs" disabled={e.status==='cancelled'||busy===e.id} onClick={()=>cancel(e)}>Cancel</button></div></td>
   </tr>)}</tbody></table></div>}
 </div>;
}
function Attendees({dept}){
 const {data:events}=useLoad(`/organizer/events?department_id=${dept}`,dept);
 const [eventId,setEventId]=useState(''),[attendanceFilter,setAttendanceFilter]=useState('Pending Verification'),[busy,setBusy]=useState('');
 const id=eventId||events?.[0]?.id;
 const {data,loading,error,reload}=useLoad(id?`/organizer/events/${id}/attendees`:null,id);
 const {flash}=useToast();
 useEffect(()=>{if(!id)return;const timer=setInterval(reload,30000);return()=>clearInterval(timer);},[id,reload]);
 async function decide(rsvpId,decision){
  setBusy(`${rsvpId}:${decision}`);
  try{
   const route=decision==='approve'?'verify':'reject';
   const result=await api(`/organizer/events/${id}/attendees/${rsvpId}/${route}`,{method:'POST'});
   flash(result.message);reload();
  }catch(e){flash(e.message,'error');}finally{setBusy('');}
 }
 async function exportRows(){try{await downloadCsv(`/organizer/events/${id}/export`,`spc-event-${id}-rsvps.csv`);}catch(e){flash(e.message,'error');}}
 const visibleAttendees=data?.attendees?.filter(attendee=>attendanceFilter==='All'||attendee.attendance_status===attendanceFilter)||[];
 const filters=['Pending Verification','Verified','Completed','Rejected','Absent','Not Checked In','All'];
 return <div className="panel">
  <div className="panel-head"><h2>Event attendees and attendance verification</h2><button className="btn btn-secondary" onClick={exportRows} disabled={!id}><Download size={16}/> Export CSV</button></div>
  <div className="organizer-controls"><select value={id||''} onChange={e=>setEventId(e.target.value)}><option value="" disabled>Choose an event</option>{events?.map(e=><option key={e.id} value={e.id}>{e.title}</option>)}</select>
   {data?.check_in_code&&<div className="checkin-display"><QRCodeSVG value={data.check_in_code} size={118} level="M" includeMargin/><div><span>STUDENT CHECK-IN QR</span><strong>{data.check_in_code}</strong><small>Available during the check-in window</small></div></div>}
   {data?.check_out_code&&<div className="checkin-display"><QRCodeSVG value={data.check_out_code} size={118} level="M" includeMargin/><div><span>STUDENT CHECK-OUT QR</span><strong>{data.check_out_code}</strong><small>Available during the configured check-out window</small></div></div>}
  </div>
  {!id?<EmptyState title="No events yet"/>:loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.attendees?.length?<EmptyState title="No RSVPs for this event"/>:<>
   <div className="tab-nav attendance-filters">{filters.map(filter=><button type="button" key={filter} onClick={()=>setAttendanceFilter(filter)} className={attendanceFilter===filter?'active':''}>{filter}</button>)}</div>
   {!visibleAttendees.length?<EmptyState title={`No ${attendanceFilter==='All'?'attendance':attendanceFilter.toLowerCase()} records`}/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Seat</th><th>Participant</th><th>Course</th><th>Check-in time</th><th>Reviewed at</th><th>Check-out time</th><th>Attendance</th><th>Action</th></tr></thead><tbody>{visibleAttendees.map(a=><tr key={a.id}>
    <td>#{a.seat_no}</td><td><strong>{a.user?.name}</strong><small>{a.user?.email}</small></td><td>{a.user?.course||'—'}</td>
    <td>{a.checkin?.checked_at?localDateTime(a.checkin.checked_at):'—'}</td><td>{a.checkin?.verification_at?localDateTime(a.checkin.verification_at):'—'}</td><td>{a.checkin?.checked_out_at?localDateTime(a.checkin.checked_out_at):'—'}</td>
    <td><StatusBadge value={a.attendance_status}/></td><td>{a.attendance_status==='Pending Verification'&&<div className="row-actions"><button className="btn btn-primary btn-xs" disabled={Boolean(busy)} onClick={()=>decide(a.id,'approve')}>Approve</button><button className="btn btn-danger-light btn-xs" disabled={Boolean(busy)} onClick={()=>decide(a.id,'reject')}>Reject</button></div>}</td>
   </tr>)}</tbody></table></div>}
  </>}
 </div>;
}
function ManageAnnouncements({dept}){const {data,loading,error,reload}=useLoad(`/organizer/announcements?department_id=${dept}`,dept),{flash}=useToast();const [open,setOpen]=useState(false),[editing,setEditing]=useState(null),[errorText,setErrorText]=useState(''),[busy,setBusy]=useState(false);
 function start(a=null){setEditing(a);setOpen(true);setErrorText('');}
 async function save(e){e.preventDefault();setBusy(true);setErrorText('');const f=new FormData(e.currentTarget);const body={title:f.get('title'),body:f.get('body'),is_pinned:f.get('is_pinned')==='on',department_id:dept};try{await api(editing?`/organizer/announcements/${editing.id}`:'/organizer/announcements',{method:editing?'PUT':'POST',body});flash(editing?'Announcement updated.':'Announcement published.');setOpen(false);reload();}catch(e){setErrorText(e.message);}finally{setBusy(false);}}
 async function remove(id){if(!confirm('Delete this announcement?'))return;try{await api(`/organizer/announcements/${id}`,{method:'DELETE'});flash('Announcement deleted.');reload();}catch(e){flash(e.message,'error');}}
 return <div className="panel"><div className="panel-head"><h2>Department announcements</h2><button className="btn btn-primary" onClick={()=>start()}><Plus size={16}/> New announcement</button></div>{open&&<div className="inline-editor"><form key={editing?.id||'new'} onSubmit={save} className="form-stack"><h2>{editing?'Edit announcement':'Publish an announcement'}</h2><Field label="Title"><input name="title" defaultValue={editing?.title||''} required/></Field><Field label="Announcement"><textarea name="body" rows={5} defaultValue={editing?.body||''} required/></Field><label className="checkbox-label"><input name="is_pinned" type="checkbox" defaultChecked={editing?.is_pinned||false}/> Pin to top</label><FormNotice error={errorText}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={()=>setOpen(false)}>Cancel</button><button className="btn btn-primary" disabled={busy}>Publish / Save</button></div></form></div>}{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.length?<EmptyState title="No announcements"/>:<div className="mini-announcements">{data.map(a=><div key={a.id} className="mini-announcement"><span className="announcement-avatar"><Newspaper size={18}/></span><div><strong>{a.title} {a.is_pinned?'📌':''}</strong><small>{localDateTime(a.created_at)} · {a.target_course?`For ${a.target_course}`:'Department-wide'}</small><p>{a.body}</p></div><div className="row-actions"><button className="btn btn-secondary btn-xs" onClick={()=>start(a)}>Edit</button><button className="btn btn-danger-light btn-xs" onClick={()=>remove(a.id)}>Delete</button></div></div>)}</div>}</div>;
}
function ManageMembers({dept}){const {data,loading,error,reload}=useLoad(`/organizer/memberships?department_id=${dept}`,dept),{flash}=useToast();const [busy,setBusy]=useState(0);
 async function review(id,status){setBusy(id);try{await api(`/organizer/memberships/${id}/review`,{method:'POST',body:{status}});flash('Membership '+status+'.');reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 async function remove(member){if(!confirm(`Remove ${member.user?.name} from the department? They will be notified.`))return;setBusy(member.id);try{const result=await api(`/organizer/memberships/${member.id}/remove`,{method:'POST'});flash(result.message);reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 return <div className="panel"><div className="panel-head"><h2>Membership requests</h2><span className="muted-small">Review requests and manage approved department members.</span></div>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.length?<EmptyState title="No membership requests"/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Student</th><th>Course</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody>{data.map(m=><tr key={m.id}><td><strong>{m.user?.name}</strong><small>{m.user?.email}</small></td><td>{m.user?.course}</td><td>{localDateTime(m.created_at)}</td><td><StatusBadge value={m.status}/></td><td>{m.status==='pending'?<div className="row-actions"><button className="btn btn-primary btn-xs" disabled={busy===m.id} onClick={()=>review(m.id,'approved')}>Approve</button><button className="btn btn-danger-light btn-xs" disabled={busy===m.id} onClick={()=>review(m.id,'rejected')}>Reject</button></div>:m.status==='approved'&&<button className="btn btn-danger-light btn-xs" disabled={busy===m.id} onClick={()=>remove(m)}>Remove member</button>}</td></tr>)}</tbody></table></div>}</div>;
}
function OrgAnalytics({dept}){const {data,loading,error}=useLoad(`/organizer/events?department_id=${dept}`,dept);if(loading)return <Loading/>;if(error)return <ErrorState message={error}/>;const rows=data||[];return <div className="panel"><h2>Event analytics</h2><p className="muted">Track how many students view and register for your published events.</p><div className="dashboard-stats"><Stat icon={CalendarDays} label="Total events" value={rows.length}/><Stat icon={Users} label="Total RSVPs" value={rows.reduce((a,b)=>a+(b.attendee_count||0),0)}/><Stat icon={Eye} label="Total event views" value={rows.reduce((a,b)=>a+(b.views||0),0)}/></div><div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Views</th><th>RSVPs</th><th>Capacity used</th></tr></thead><tbody>{rows.map(e=><tr key={e.id}><td>{e.title}</td><td>{e.views}</td><td>{e.attendee_count}</td><td><div className="progress"><span style={{width:`${Math.min(100,Math.round(e.attendee_count/e.seat_limit*100))}%`}}/></div>{Math.round(e.attendee_count/e.seat_limit*100)}%</td></tr>)}</tbody></table></div></div>;
}

function ManageOfficers({dept}){const {data,loading,error,reload}=useLoad(`/organizer/officers?department_id=${dept}`,dept),{flash}=useToast();const [selection,setSelection]=useState({}),[busy,setBusy]=useState(0);
 async function save(officer){setBusy(officer.id);try{await api(`/organizer/officers/${officer.id}/permissions`,{method:'PUT',body:{officer_permissions:selection[officer.id]??officer.officer_permissions??[]}});flash('Officer permissions saved.');reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 function toggle(officer,permission,checked){setSelection(p=>{const current=p[officer.id]??officer.officer_permissions??[];return {...p,[officer.id]:checked?[...new Set([...current,permission])]:current.filter(v=>v!==permission)}});}
 return <div className="panel"><h2>Officer access</h2><p className="muted">Administrators assign officer roles under Users & roles. Department presidents can adjust permissions for existing officers in their own department.</p>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.length?<EmptyState title="No department officers" description="An administrator can appoint officers from the Users & roles page."/>:<div className="officer-list">{data.map(o=><div key={o.id} className="officer-entry"><div><strong>{o.name}</strong><small>{o.email}</small></div><div className="officer-checks">{[['events','Events'],['announcements','Announcements'],['members','Memberships']].map(([id,label])=><label key={id} className="checkbox-label"><input type="checkbox" checked={(selection[o.id]??o.officer_permissions??[]).includes(id)} onChange={e=>toggle(o,id,e.target.checked)}/>{label}</label>)}</div><button disabled={busy===o.id} className="btn btn-primary btn-xs" onClick={()=>save(o)}>Save access</button></div>)}</div>}</div>;
}
