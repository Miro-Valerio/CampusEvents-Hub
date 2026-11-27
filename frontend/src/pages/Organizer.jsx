import React,{useCallback,useEffect,useState} from 'react';
import {Link,useSearchParams} from 'react-router-dom';
import {CalendarDays,Users,TicketCheck,Newspaper,Plus,Download,PenSquare,XCircle,BarChart3,ClipboardList,Bell,ArrowRight,Eye,ShieldCheck,ScanLine} from 'lucide-react';
import {QRScanner} from './Events';
import '../attendance.css';
import {useAuth} from '../context/AuthContext';
import {useToast} from '../context/ToastContext';
import {api,downloadCsv,localDateTime,toInputDate} from '../lib/api';
import {useLoad,PageTitle,Loading,ErrorState,EmptyState,Stat,StatusBadge,Field,FormNotice} from '../components/Widgets';
const tabs=[['overview','Overview'],['events','Manage events'],['attendees','Attendees'],['announcements','Announcements'],['members','Memberships'],['officers','Officer access'],['analytics','Analytics']];
const yearLevels=['1st Year','2nd Year','3rd Year','4th Year'];
export default function Organizer(){const {user}=useAuth(),{data:departments}=useLoad('/departments');const [params,setParams]=useSearchParams(),[adminAvailability,setAdminAvailability]=useState(null);const facultyCanManageEvents=user?.role==='faculty'&&user.officer_permissions?.includes('events'),facultyCanManageAnnouncements=user?.role==='faculty'&&user.officer_permissions?.includes('announcements'),facultyCanManageAttendance=user?.role==='faculty'&&user.officer_permissions?.includes('attendance');const visibleTabs=user?.role==='faculty'?tabs.filter(([id])=>id==='overview'||(['events','analytics'].includes(id)&&facultyCanManageEvents)||(id==='attendees'&&facultyCanManageAttendance)||(id==='announcements'&&facultyCanManageAnnouncements)):user?.role==='officer'?tabs.filter(([id])=>id!=='attendees'||user.officer_permissions?.includes('attendance')):tabs;const tab=visibleTabs.some(([id])=>id===params.get('tab'))?params.get('tab'):visibleTabs[0]?.[0]||'overview';const dept=user?.role==='admin'?Number(params.get('department_id')||departments?.[0]?.id||0):user?.department_id;
 useEffect(()=>{
  if(!['president','officer'].includes(user?.role)){setAdminAvailability(null);return;}
  let active=true;
  const refreshAvailability=async()=>{
   try{const result=await api('/department/admin-availability');if(active)setAdminAvailability(result.status);}
   catch{if(active)setAdminAvailability('Unavailable');}
  };
  refreshAvailability();
  const timer=setInterval(refreshAvailability,15000);
  return()=>{active=false;clearInterval(timer);};
 },[user?.role]);
 const allowed=a=>user?.role==='admin'||user?.role==='president'||(['officer','faculty'].includes(user?.role)&&user?.officer_permissions?.includes(a));
 const changeTab=t=>setParams(p=>{p.set('tab',t);return p;});
 return <div className="container page-section">{['president','officer'].includes(user?.role)&&<div className="admin-availability-indicator" role="status" aria-live="polite"><span className={`presence-dot ${adminAvailability?.toLowerCase()||'offline'}`}/><span>{adminAvailability==='Unavailable'?'Admin availability unavailable':adminAvailability?`Admin is ${adminAvailability}`:'Loading Admin availability...'}</span></div>}<PageTitle eyebrow="DEPARTMENT WORKSPACE" title="Organizer dashboard." description="Manage campus events, announcements, members, and attendance." action={user?.role==='admin'?<select aria-label="Manage department" value={dept||''} onChange={e=>setParams(p=>{p.set('department_id',e.target.value);return p;})}>{departments?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select>:<span className="role-label">{user?.department?.name||'My department'}</span>}/><div className="tab-nav">{visibleTabs.map(([id,label])=><button key={id} onClick={()=>changeTab(id)} className={tab===id?'active':''}>{label}</button>)}</div>
 {!dept?<EmptyState title="Select a department" description="Choose a department to access organizer tools."/>:tab==='overview'?<OrgOverview dept={dept} showEvents={user?.role!=='faculty'||allowed('events')}/>:tab==='events'?allowed('events')?<ManageEvents dept={dept}/>:<AccessDenied/>:tab==='attendees'?allowed('attendance')?<Attendees dept={dept} showStudentEmail={user?.role!=='faculty'}/>:<AccessDenied/>:tab==='announcements'?allowed('announcements')?<ManageAnnouncements dept={dept}/>:<AccessDenied/>:tab==='members'?allowed('members')?<ManageMembers dept={dept}/>:<AccessDenied/>:tab==='officers'?(user?.role==='admin'||user?.role==='president')?<ManageOfficers dept={dept}/>:<AccessDenied/>:<OrgAnalytics dept={dept}/>}</div>;
}
function AccessDenied(){return <div className="state-card"><ShieldCheck size={30}/><h3>Permission required</h3><p>Your department president or administrator must grant permission to manage this section.</p></div>}
function OrgOverview({dept,showEvents=true}){const {data:events}=useLoad(showEvents?`/organizer/events?department_id=${dept}`:null,dept),{data:stats,loading,error,reload}=useLoad(`/organizer/overview?department_id=${dept}`,dept);
 return <>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="dashboard-stats"><Stat icon={CalendarDays} label="Department events" value={stats?.events}/><Stat icon={TicketCheck} label="Active RSVPs" value={stats?.rsvps}/><Stat icon={Users} label="Approved members" value={stats?.members}/><Stat icon={ClipboardList} label="Pending requests" value={stats?.pending}/></div>}{showEvents&&<div className="panel spaced-panel"><div className="panel-head"><h2>Recent department events</h2></div>{!events?.length?<EmptyState title="No events yet" description="Create your first department activity in Manage events."/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Start</th><th>Seats</th><th>Status</th></tr></thead><tbody>{events?.slice(0,6).map(e=><tr key={e.id}><td><Link to={`/events/${e.id}`}>{e.title}</Link></td><td>{localDateTime(e.starts_at)}</td><td>{e.attendee_count}/{e.seat_limit}</td><td><StatusBadge value={e.schedule_status||e.status}/></td></tr>)}</tbody></table></div>}</div>}</>;
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
 async function remove(event){
  if(!confirm(`Delete "${event.title}" from listings? This is separate from cancelling. RSVP and attendance history will be preserved.`))return;
  setBusy(event.id);
  try{const result=await api(`/organizer/events/${event.id}`,{method:'DELETE'});flash(result.message);reload();}
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
    <td><div className="row-actions"><Link className="btn btn-secondary btn-xs" to={`/events/${e.id}`}>View</Link><button className="btn btn-secondary btn-xs" onClick={()=>{setEdit(e);window.scrollTo({top:230,behavior:'smooth'});}} disabled={e.status==='cancelled'||busy===e.id}>Edit</button><button className="btn btn-danger-light btn-xs" disabled={e.status==='cancelled'||busy===e.id} onClick={()=>cancel(e)}>Cancel</button><button className="btn btn-danger-light btn-xs" disabled={busy===e.id} onClick={()=>remove(e)}>Delete</button></div></td>
   </tr>)}</tbody></table></div>}
 </div>;
}
function Attendees({dept,showStudentEmail}){
 const {data:events}=useLoad(`/organizer/attendance/events?department_id=${dept}`,dept);
 const [eventId,setEventId]=useState(''),[attendanceFilter,setAttendanceFilter]=useState('All');
 const [yearLevelFilter,setYearLevelFilter]=useState(''),[courseFilter,setCourseFilter]=useState('');
 const [search,setSearch]=useState(''),[sortBy,setSortBy]=useState('seat'),[busy,setBusy]=useState('');
 const [attendanceMode,setAttendanceMode]=useState('check_in'),[code,setCode]=useState('');
 const [scannerOpen,setScannerOpen]=useState(false),[candidate,setCandidate]=useState(null),[lookupError,setLookupError]=useState('');
 const id=eventId||events?.[0]?.id;
 const filterParams=new URLSearchParams();
 if(courseFilter)filterParams.set('course',courseFilter);
 if(yearLevelFilter)filterParams.set('year_level',yearLevelFilter);
 const filterQuery=filterParams.toString();
 const attendeesUrl=id?`/organizer/events/${id}/attendees${filterQuery?`?${filterQuery}`:''}`:null;
 const {data,loading,error,reload}=useLoad(attendeesUrl,`${id||''}:${filterQuery}`);
 const {flash}=useToast();
 useEffect(()=>{if(!id)return;const timer=setInterval(reload,30000);return()=>clearInterval(timer);},[id,reload]);
 async function lookup(rawCode=code){
  if(!id||!rawCode.trim())return;
  setCode(rawCode.trim().toUpperCase());setCandidate(null);setLookupError('');setBusy('lookup');
  try{
   const result=await api(`/organizer/events/${id}/attendance/lookup`,{method:'POST',body:{code:rawCode.trim(),mode:attendanceMode}});
   setCandidate(result);
  }catch(e){setLookupError(e.message);}
  finally{setBusy('');}
 }
 const detected=useCallback(value=>{setScannerOpen(false);lookup(value);},[id,attendanceMode]);
 async function confirmAttendance(){
  if(!candidate||!id)return;
  setBusy('confirm');setLookupError('');
  try{
   const route=attendanceMode==='check_in'?'check-in':'check-out';
   const result=await api(`/organizer/events/${id}/attendance/${route}`,{method:'POST',body:{code}});
   flash(result.message);setCandidate(null);setCode('');reload();
  }catch(e){setLookupError(e.message);}
  finally{setBusy('');}
 }
 async function decide(rsvpId,decision){
  setBusy(`${rsvpId}:${decision}`);
  try{
   const route=decision==='approve'?'verify':'reject';
   const result=await api(`/organizer/events/${id}/attendees/${rsvpId}/${route}`,{method:'POST'});
   flash(result.message);reload();
  }catch(e){flash(e.message,'error');}finally{setBusy('');}
 }
 async function exportRows(){
  const params=new URLSearchParams();
  if(courseFilter)params.set('course',courseFilter);
  if(yearLevelFilter)params.set('year_level',yearLevelFilter);
  const query=params.toString();
  try{await downloadCsv(`/organizer/events/${id}/export${query?`?${query}`:''}`,`spc-event-${id}-rsvps.csv`);}
  catch(e){flash(e.message,'error');}
 }
 const searchText=search.trim().toLocaleLowerCase();
 const visibleAttendees=data?.attendees?.filter(attendee=>(attendanceFilter==='All'||attendee.attendance_status===attendanceFilter)
  &&(!searchText||attendee.user?.name?.toLocaleLowerCase().includes(searchText)))||[];
 const sortedAttendees=[...visibleAttendees].sort((a,b)=>{
  if(sortBy==='name')return (a.user?.name||'').localeCompare(b.user?.name||'');
  if(sortBy==='course')return (a.user?.course||'').localeCompare(b.user?.course||'');
  if(sortBy==='year')return (a.user?.year_level||'Not Set').localeCompare(b.user?.year_level||'Not Set');
  return (a.seat_no||0)-(b.seat_no||0);
 });
 const attendanceFilters=['Not Checked In','Verified','Completed','Absent','Pending Verification','Rejected','All'];
 return <div className="panel">
  <div className="panel-head"><h2>Attendance management</h2><button className="btn btn-secondary" onClick={exportRows} disabled={!id}><Download size={16}/> Export CSV</button></div>
  <div className="organizer-controls attendance-controls"><select className="attendance-event-select" aria-label="Filter by event" value={id||''} onChange={e=>{setEventId(e.target.value);setCourseFilter('');setYearLevelFilter('');setSearch('');setAttendanceMode('check_in');setCandidate(null);setCode('');setLookupError('');}}><option value="" disabled>Choose an event</option>{events?.map(e=><option key={e.id} value={e.id}>{e.title}</option>)}</select>
   <Field label="Attendance action"><select className="attendance-action-select" aria-label="Attendance action" value={attendanceMode} onChange={e=>{setAttendanceMode(e.target.value);setCandidate(null);setLookupError('');}}><option value="check_in">Check-in</option><option value="check_out" disabled={!events?.find(event=>Number(event.id)===Number(id))?.requires_check_out}>Check-out</option></select></Field>
   <div className="attendance-code-entry"><Field label="Student Attendance Code"><input value={code} onChange={e=>{setCode(e.target.value.toUpperCase());setCandidate(null);setLookupError('');}} maxLength={32} placeholder="Enter the 32-character code"/></Field><button type="button" className="btn btn-secondary" onClick={()=>setScannerOpen(true)} disabled={!id}><ScanLine size={16}/> Scan student QR</button><button type="button" className="btn btn-primary" onClick={()=>lookup()} disabled={!id||!code.trim()||Boolean(busy)}>{busy==='lookup'?'Checking...':'Look up code'}</button></div>
   {scannerOpen&&<QRScanner onDetected={detected} onClose={()=>setScannerOpen(false)}/>}
   {lookupError&&<FormNotice error={lookupError}/>}
   {candidate&&<div className="attendance-candidate"><h3>Confirm {candidate.mode==='check_in'?'check-in':'check-out'}</h3><p><strong>Event:</strong> {candidate.event.title}</p><p><strong>Student:</strong> {candidate.student.name}</p><p><strong>Course / Program:</strong> {candidate.student.course||'—'}</p><p><strong>Year Level:</strong> {candidate.student.year_level||'Not Set'}</p><p><strong>Current attendance:</strong> {candidate.attendance_status}</p><button type="button" className="btn btn-primary" onClick={confirmAttendance} disabled={Boolean(busy)}>{busy==='confirm'?'Saving...':attendanceMode==='check_in'?'Confirm Attendance':'Confirm Check-Out'}</button></div>}
  </div>
  {!id?<EmptyState title="No events yet"/>:loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<>
   <div className="dashboard-stats attendance-summary">
    <Stat icon={TicketCheck} label="Total Confirmed RSVPs" value={data?.summary?.confirmed_rsvps??0}/>
    <Stat icon={Users} label="Total Verified Attendees" value={data?.summary?.verified_attendees??0}/>
    <Stat icon={ClipboardList} label="Total Pending Verification" value={data?.summary?.pending_verification??0}/>
    <Stat icon={XCircle} label="Total Absent Students" value={data?.summary?.absent_students??0}/>
    <Stat icon={BarChart3} label="Attendance Percentage" value={data?.summary?.attendance_percentage===null?'—':`${data?.summary?.attendance_percentage??0}%`}/>
   </div>
   <div className="grid-two attendance-report-filters">
    <Field label="Course / Program"><select value={courseFilter} onChange={e=>setCourseFilter(e.target.value)}><option value="">All Courses / Programs</option>{data?.courses?.map(course=><option key={course} value={course}>{course}</option>)}</select></Field>
    <Field label="Year Level"><select value={yearLevelFilter} onChange={e=>setYearLevelFilter(e.target.value)}><option value="">All Year Levels</option>{yearLevels.map(level=><option key={level} value={level}>{level}</option>)}</select></Field>
    <Field label="Search student name"><input value={search} onChange={e=>setSearch(e.target.value)} placeholder="Search students..."/></Field>
    <Field label="Sort by"><select value={sortBy} onChange={e=>setSortBy(e.target.value)}><option value="seat">Seat number</option><option value="name">Student name</option><option value="course">Course / Program</option><option value="year">Year Level</option></select></Field>
   </div>
   {!data?.summary?.attendance_window_closed&&<p className="helper-text">Absent students are counted only after the event's attendance window has closed.</p>}
   <div className="tab-nav attendance-filters">{attendanceFilters.map(filter=><button type="button" key={filter} onClick={()=>setAttendanceFilter(filter)} className={attendanceFilter===filter?'active':''}>{filter}</button>)}</div>
   {!sortedAttendees.length?<EmptyState title={!data?.attendees?.length?'No student RSVPs for this event':`No ${attendanceFilter==='All'?'attendance':attendanceFilter.toLowerCase()} records match these filters`}/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Seat</th><th>Student Name</th><th>Course / Program</th><th>Year Level</th><th>RSVP Status</th><th>Check-In Time</th><th>Reviewed At</th><th>Check-Out Time</th><th>Attendance Status</th><th>Actions</th></tr></thead><tbody>{sortedAttendees.map(a=><tr key={a.id}>
    <td>#{a.seat_no}</td><td><strong>{a.user?.name}</strong>{showStudentEmail&&<small>{a.user?.email}</small>}</td><td>{a.user?.course||'—'}</td><td>{a.user?.year_level||'Not Set'}</td><td><StatusBadge value={a.status}/></td>
    <td>{a.checkin?.checked_at?localDateTime(a.checkin.checked_at):'—'}</td><td>{a.checkin?.verification_at?localDateTime(a.checkin.verification_at):'—'}</td><td>{a.checkin?.checked_out_at?localDateTime(a.checkin.checked_out_at):'—'}</td>
    <td><StatusBadge value={a.attendance_status}/></td><td>{a.attendance_status==='Pending Verification'&&<div className="row-actions"><button className="btn btn-primary btn-xs" disabled={Boolean(busy)} onClick={()=>decide(a.id,'approve')}>Approve legacy check-in</button><button className="btn btn-danger-light btn-xs" disabled={Boolean(busy)} onClick={()=>decide(a.id,'reject')}>Reject legacy check-in</button></div>}</td>
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

function ManageOfficers({dept}){const {data,loading,error,reload}=useLoad(`/organizer/officers?department_id=${dept}`,dept),{flash}=useToast();const [selection,setSelection]=useState({}),[savedSelection,setSavedSelection]=useState({}),[busy,setBusy]=useState(0);
 function samePermissions(first,second){return JSON.stringify([...new Set(first)].sort())===JSON.stringify([...new Set(second)].sort());}
 function hasChanges(officer){const current=selection[officer.id]??officer.officer_permissions??[];const saved=savedSelection[officer.id]??officer.officer_permissions??[];return !samePermissions(current,saved);}
 async function save(officer){if(!hasChanges(officer))return;const permissions=[...new Set(selection[officer.id]??officer.officer_permissions??[])];setBusy(officer.id);try{await api(`/organizer/officers/${officer.id}/permissions`,{method:'PUT',body:{officer_permissions:permissions}});setSelection(previous=>({...previous,[officer.id]:permissions}));setSavedSelection(previous=>({...previous,[officer.id]:permissions}));flash('Officer permissions saved.');reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 function toggle(officer,permission,checked){setSelection(p=>{const current=p[officer.id]??officer.officer_permissions??[];return {...p,[officer.id]:checked?[...new Set([...current,permission])]:current.filter(v=>v!==permission)}});}
 return <div className="panel"><h2>Officer access</h2><p className="muted">Administrators assign officer roles under Users & roles. Department presidents can adjust permissions for existing officers in their own department.</p>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.length?<EmptyState title="No department officers" description="An administrator can appoint officers from the Users & roles page."/>:<div className="officer-list">{data.map(o=>{const saved=savedSelection[o.id]!==undefined&&!hasChanges(o);return <div key={o.id} className="officer-entry"><div><strong>{o.name}</strong><small>{o.email}</small></div><div className="officer-checks">{[['events','Events'],['announcements','Announcements'],['attendance','Attendance'],['members','Memberships']].map(([id,label])=><label key={id} className="checkbox-label"><input type="checkbox" checked={(selection[o.id]??o.officer_permissions??[]).includes(id)} onChange={e=>toggle(o,id,e.target.checked)}/>{label}</label>)}</div><button disabled={busy===o.id||!hasChanges(o)} className="btn btn-primary btn-xs" onClick={()=>save(o)}>{busy===o.id?'Saving...':saved?'Saved':'Save access'}</button></div>})}</div>}</div>;
}
