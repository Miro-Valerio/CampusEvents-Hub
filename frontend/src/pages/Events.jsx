import React,{useEffect,useMemo,useRef,useState} from 'react';
import {Link,useNavigate,useParams,useSearchParams} from 'react-router-dom';
import {Search,SlidersHorizontal,CalendarDays,Users,MapPin,Bookmark,CalendarPlus,TicketCheck,Flag,Printer,ArrowLeft,Building2,CheckCircle2,X} from 'lucide-react';
import {BrowserMultiFormatReader} from '@zxing/browser';
import {useLoad,Loading,ErrorState,EmptyState,PageTitle,FormNotice,StatusBadge,Field} from '../components/Widgets';
import {EventCard} from '../components/EventCard';
import {api,localDateTime,localDate,localTime,campusDateParts} from '../lib/api';
import {useAuth} from '../context/AuthContext';
import {useToast} from '../context/ToastContext';
export function EventDirectory({archive=false}){
 const [searchParams,setSearchParams]=useSearchParams();const defaultSort=archive?'newest':'soonest';const [filters,setFilters]=useState({search:searchParams.get('search')||'',category:searchParams.get('category')||'',department_id:searchParams.get('department_id')||'',date:searchParams.get('date')||'',sort:searchParams.get('sort')||defaultSort}),[page,setPage]=useState(Number(searchParams.get('page')||1));
 const searchKey=searchParams.toString();
 useEffect(()=>{setFilters({search:searchParams.get('search')||'',category:searchParams.get('category')||'',department_id:searchParams.get('department_id')||'',date:searchParams.get('date')||'',sort:searchParams.get('sort')||defaultSort});setPage(Number(searchParams.get('page')||1));},[searchKey,archive]);
 const {data:departments}=useLoad('/departments'),{data:cats}=useLoad('/events/categories');
 const query=useMemo(()=>new URLSearchParams({...Object.fromEntries(Object.entries(filters).filter(([k,v])=>v)),page,archive:archive?1:0}).toString(),[filters,page,archive]);
 const {data,loading,error,reload}=useLoad(`/events?${query}`);
 useEffect(()=>{const timer=setInterval(reload,30000);return()=>clearInterval(timer);},[reload]);
 function update(key,value){setFilters(f=>({...f,[key]:value}));setPage(1);setSearchParams(current=>{const next=new URLSearchParams(current);if(value)next.set(key,value);else next.delete(key);next.delete('page');return next;},{replace:true});}
 function changePage(value){setPage(value);setSearchParams(current=>{const next=new URLSearchParams(current);next.set('page',String(value));return next;},{replace:true});}
 return <div className="container page-section"><PageTitle eyebrow={archive?'THE CAMPUS ARCHIVE':'EXPLORE CAMPUS'} title={archive?'Past campus moments.':'Discover what’s happening.'} description={archive?'Look back at the events that brought SPC together.':'From learning to celebrating, find your next reason to show up.'}/>
 <div className="filter-bar"><div className="search-wrap"><Search size={19}/><input placeholder="Search events, venues, or organizers..." aria-label="Search events" value={filters.search} onChange={e=>update('search',e.target.value)}/></div><select aria-label="Category" value={filters.category} onChange={e=>update('category',e.target.value)}><option value="">All categories</option>{cats?.map(c=><option key={c}>{c}</option>)}</select><select aria-label="Department" value={filters.department_id} onChange={e=>update('department_id',e.target.value)}><option value="">All departments</option>{departments?.map(d=><option key={d.id} value={d.id}>{d.code}</option>)}</select><input aria-label="Event date" type="date" value={filters.date} onChange={e=>update('date',e.target.value)}/><select aria-label="Sort events" value={filters.sort} onChange={e=>update('sort',e.target.value)}><option value="soonest">Soonest</option><option value="newest">Newest</option><option value="popular">Most popular</option></select></div>
 <div className="list-meta"><span><SlidersHorizontal size={16}/> {data?.total??0} {archive?'past':'campus'} events</span><span>Showing {data?.data?.length??0} results</span></div>
 {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.data?.length?<EmptyState title="No matching events" description="Try removing a filter or searching for a different keyword."/>:<><div className="event-grid">{data.data.map(e=><EventCard event={e} key={e.id}/>)}</div>{data.last_page>1&&<div className="pagination"><button className="btn btn-secondary" disabled={page<=1} onClick={()=>changePage(page-1)}>Previous</button><span>Page {page} of {data.last_page}</span><button className="btn btn-secondary" disabled={page>=data.last_page} onClick={()=>changePage(page+1)}>Next</button></div>}</>}
 </div>;
}
export function EventDetails(){
 const {id}=useParams(),{user}=useAuth(),navigate=useNavigate(),{flash}=useToast();
 const {data:event,loading,error,reload}=useLoad(`/events/${id}`);
 const {data:activity,reload:reloadActivity}=useLoad(user?'/me/activity':null,id);
 const {data:preview}=useLoad(`/events/${id}/attendees-preview`);
 const [busy,setBusy]=useState(''),[formError,setFormError]=useState('');
 const [showReport,setShowReport]=useState(false),[reason,setReason]=useState('');
 const [reportCategory,setReportCategory]=useState('other'),[rsvpConfirmation,setRsvpConfirmation]=useState(null);
 const active=activity?.rsvps?.find(item=>item.event_id===Number(id)&&item.status==='active');
 const attendanceStatus=active?.attendance_status||'Not Checked In';
 const saved=activity?.bookmarks?.some(item=>item.id===Number(id));
 const inCal=activity?.calendar?.some(item=>item.id===Number(id));
 useEffect(()=>{const timer=setInterval(()=>{reload();reloadActivity();},30000);return()=>clearInterval(timer);},[reload,reloadActivity]);
 async function act(label,path,body){
  if(busy)return;
  if(!user){navigate(`/login?next=${encodeURIComponent(`/events/${id}`)}`);return;}
  setBusy(label);setFormError('');
  try{
   const result=await api(path,{method:label==='cancel'?'DELETE':'POST',body});
   if(label==='rsvp')setRsvpConfirmation({eventTitle:event.title,seatNo:result.rsvp?.seat_no});
   else flash(result.message||'Updated.');
   reload();reloadActivity();
   if(label==='report'){setShowReport(false);setReason('');setReportCategory('other');}
  }catch(e){setFormError(e.message);flash(e.message,'error');}
  finally{setBusy('');}
 }
 if(loading)return <div className="container page-section"><Loading/></div>;
 if(error||!event)return <div className="container page-section"><ErrorState message={error||'Event not found.'} retry={reload}/></div>;
 const full=(event.attendee_count||0)>=event.seat_limit;
 const started=new Date(event.starts_at)<=new Date();
 const scheduleStatus=event.schedule_status||event.status;
 return <div className="container page-section">
  <Link to="/events" className="back-link"><ArrowLeft size={16}/> Back to events</Link>
  <div className="event-detail-grid">
   <div>
    <div className="detail-poster">{event.poster_url?<img src={event.poster_url} alt={`${event.title} poster`}/>:<div className="poster-fallback"><CalendarDays size={75}/><strong>SPC CAMPUS EVENTS</strong></div>}</div>
    <section className="detail-section"><h2>About this event</h2><p className="preserve-lines">{event.description}</p>{event.rules&&<><h3>Guidelines & rules</h3><p className="preserve-lines">{event.rules}</p></>}</section>
    <section className="detail-section"><h2>Who's joining?</h2><p><Users size={17} className="inline-icon"/> {event.attendee_count} of {event.seat_limit} seats reserved</p>{preview?.names?.length?<div className="attendee-names">{preview.names.map((name,index)=><span key={index}>{name}</span>)}</div>:<p className="muted">Be the first to RSVP for this event.</p>}<p className="helper-text">Attendee names are partially masked for privacy.</p></section>
   </div>
   <div className="detail-info">
    <span className="eyebrow">SPC CAMPUS EVENT</span><h1>{event.title}</h1>
    <div className="detail-badges"><span className="category-chip-static">{event.category}</span><StatusBadge value={scheduleStatus}/></div>
    <div className="detail-facts">
     <div><CalendarDays size={19}/><div><small>DATE & TIME</small><strong>{localDate(event.starts_at)}</strong><span>{localTime(event.starts_at)} – {localTime(event.ends_at)}</span></div></div>
     <div><MapPin size={19}/><div><small>LOCATION</small><strong>{event.venue}</strong></div></div>
     <div><Building2 size={19}/><div><small>ORGANIZED BY</small><Link to={`/departments/${event.department?.id}`}>{event.department?.name}</Link><span>{event.department?.description}</span></div></div>
     <div><Users size={19}/><div><small>SEAT AVAILABILITY</small><strong>{event.attendee_count} / {event.seat_limit} registered</strong><div className="seat-progress"><span style={{width:`${Math.min(100,100*(event.attendee_count||0)/event.seat_limit)}%`}}/></div></div></div>
    </div>
    <div className="ticket-box">
     <p className="ticket-label">YOUR CAMPUS MOMENT</p>
     {active?<>
      <div className="seat-number"><TicketCheck size={22}/><span>Seat #{active.seat_no} reserved</span></div>
      <button className="btn btn-outline btn-block" disabled={Boolean(busy)||started} onClick={()=>act('cancel',`/events/${id}/rsvp`)}>Cancel RSVP</button>
      <div className="attendance-status-card"><strong>Attendance</strong><StatusBadge value={attendanceStatus}/>
       {attendanceStatus==='Pending Verification'&&<p>Your attendance is awaiting organizer review.</p>}
       {attendanceStatus==='Rejected'&&<p>Your check-in was rejected. Contact the event organizers if you believe this is incorrect.</p>}
       {attendanceStatus==='Completed'&&<p>Your attendance is complete.</p>}
       {attendanceStatus==='Verified'&&<p>Your check-in is verified. An organizer will record check-out if required.</p>}
       {attendanceStatus==='Absent'&&<p>The attendance window has closed without a completed attendance record.</p>}
      </div>
      <p className="helper-text">Present your personal QR code from My RSVPs to an authorized event organizer for attendance.</p>
     </>:<>
      <p>{scheduleStatus==='Cancelled'?'This event has been cancelled.':scheduleStatus==='Ended'?'Registration has closed.':full?'All seats are currently taken.':started?'Registration has closed.':`${Math.max(0,event.seat_limit-(event.attendee_count||0))} seats still available.`}</p>
      <button className="btn btn-primary btn-block" disabled={Boolean(busy)||full||started||scheduleStatus==='Cancelled'||scheduleStatus==='Ended'||event.status!=='published'} onClick={()=>act('rsvp',`/events/${id}/rsvp`)}><TicketCheck size={18}/>{busy==='rsvp'?'Reserving...':scheduleStatus==='Cancelled'?'Event cancelled':scheduleStatus==='Ended'?'Event ended':full?'Event is full':started?'Event started':'RSVP — Save my seat'}</button>
     </>}
     <div className="ticket-secondary"><button onClick={()=>act('bookmark',`/events/${id}/bookmark`)} className="btn btn-secondary" disabled={Boolean(busy)}><Bookmark size={16} fill={saved?'currentColor':'none'}/>{saved?'Bookmarked':'Save'}</button><button onClick={()=>act('calendar',`/events/${id}/calendar`)} className="btn btn-secondary" disabled={Boolean(busy)}><CalendarPlus size={16}/>{inCal?'In calendar':'My calendar'}</button></div>
     <FormNotice error={formError}/>
    </div>
    <div className="detail-actions"><button className="text-button" onClick={()=>window.print()}><Printer size={16}/> Print event poster</button><button className="text-button" onClick={()=>setShowReport(value=>!value)}><Flag size={16}/> Report listing</button></div>
    {showReport&&<div className="report-form"><label htmlFor="report-category">Report category</label><select id="report-category" value={reportCategory} onChange={e=>setReportCategory(e.target.value)}><option value="misleading">Misleading or inaccurate</option><option value="spam">Spam or suspicious activity</option><option value="inappropriate">Inappropriate content</option><option value="safety">Safety concern</option><option value="other">Other</option></select><label htmlFor="report-reason">What should administrators review?</label><textarea id="report-reason" rows={4} placeholder="Describe what looks suspicious..." value={reason} onChange={e=>setReason(e.target.value)}/><button className="btn btn-primary" disabled={reason.trim().length<10||Boolean(busy)} onClick={()=>act('report',`/events/${id}/report`,{category:reportCategory,reason})}>Submit report</button></div>}
   </div>
  </div>
  {rsvpConfirmation&&<div className="modal-backdrop" onClick={()=>setRsvpConfirmation(null)}><div className="dialog rsvp-confirmation" role="dialog" aria-modal="true" aria-labelledby="rsvp-confirmation-title" onClick={e=>e.stopPropagation()}><button type="button" className="modal-close" aria-label="Close confirmation" onClick={()=>setRsvpConfirmation(null)}><X size={20}/></button><span className="eyebrow">REGISTRATION CONFIRMED</span><h2 id="rsvp-confirmation-title">{rsvpConfirmation.eventTitle}</h2><p>Your RSVP and personal attendance code have been saved. Open My RSVPs to present your QR code at the event.</p>{rsvpConfirmation.seatNo!==null&&rsvpConfirmation.seatNo!==undefined&&<p className="confirmed-seat">Assigned seat <strong>#{rsvpConfirmation.seatNo}</strong></p>}<Link className="btn btn-primary" to="/my-events" onClick={()=>setRsvpConfirmation(null)}>Open My RSVPs</Link><button type="button" className="btn btn-secondary" onClick={()=>setRsvpConfirmation(null)}>Close</button></div></div>}
 </div>;
}
export function QRScanner({onDetected,onClose}){
 const videoRef=useRef(null),[error,setError]=useState('');
 useEffect(()=>{
  let active=true,controls=null;
  const reader=new BrowserMultiFormatReader();
  try{
   reader.decodeFromVideoDevice(undefined,videoRef.current,(result,_decodeError,scannerControls)=>{
    controls=scannerControls||controls;
    if(result&&active){
     active=false;
     controls?.stop();
     onDetected(result.getText());
    }
   }).then(scannerControls=>{
    controls=scannerControls;
    if(!active)controls.stop();
   }).catch(cameraError=>{
    if(active)setError(cameraError.message||'Unable to access the camera. Enter the QR code value instead.');
   });
  }catch(cameraError){setError(cameraError.message||'Unable to access the camera. Enter the QR code value instead.');}
  return()=>{active=false;controls?.stop();};
 },[onDetected]);
 return <div className="camera-scan"><video ref={videoRef} autoPlay muted playsInline style={{width:'100%',maxWidth:420,borderRadius:10}}/><p className="helper-text">Allow camera access and point it at the event QR code. You can also enter the code manually.</p>{error&&<p className="form-error">{error}</p>}<button type="button" className="btn btn-secondary" onClick={onClose}>Close scanner</button></div>;
}
export function CalendarPage(){const today=campusDateParts(new Date());const [month,setMonth]=useState(today.month-1),[year,setYear]=useState(today.year);const {data:events,loading,error}=useLoad(`/events/calendar?year=${year}&month=${month+1}`,`${year}-${month}`);
 const first=new Date(year,month,1).getDay(),days=new Date(year,month+1,0).getDate();const cells=[...Array(first).fill(null),...Array.from({length:days},(_,i)=>i+1)];
 const byDay=d=>(events||[]).filter(e=>{const dt=campusDateParts(e.starts_at);return dt?.year===year&&dt?.month===month+1&&dt?.day===d;});
 function move(n){const d=new Date(year,month+n,1);setMonth(d.getMonth());setYear(d.getFullYear());}
 return <div className="container page-section"><PageTitle eyebrow="CAMPUS PLANNER" title="Campus calendar." description="See upcoming events at a glance, and plan what you'll attend."/><div className="calendar-panel"><div className="calendar-toolbar"><h2>{new Date(Date.UTC(year,month,15,12)).toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'long',year:'numeric'})}</h2><div className="actions"><button className="btn btn-secondary" onClick={()=>move(-1)}>‹ Previous</button><button className="btn btn-secondary" onClick={()=>{setMonth(today.month-1);setYear(today.year);}}>Today</button><button className="btn btn-secondary" onClick={()=>move(1)}>Next ›</button></div></div>{error?<ErrorState message={error}/>:<div className="calendar-grid">{['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(s=><strong className="calendar-dow" key={s}>{s}</strong>)}{cells.map((d,i)=><div className={`calendar-day ${d?'':'blank'}`} key={i}>{d&&<><strong>{d}</strong>{byDay(d).map(e=><Link key={e.id} to={`/events/${e.id}`} title={e.title}>{e.title}</Link>)}</>}</div>)}</div>}</div><p className="helper-text">Select an event on any date to view the full details. Save an event to My Calendar from its detail page.</p></div>;
}
