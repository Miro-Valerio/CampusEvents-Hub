import React,{useEffect,useState} from 'react';
import {Link} from 'react-router-dom';
import {Bell,CalendarDays,ClipboardCheck,Newspaper,TicketCheck,Users} from 'lucide-react';
import {localDateTime} from '../lib/api';
import {useAuth} from '../context/AuthContext';
import {useLoad,PageTitle,Loading,ErrorState,EmptyState,Stat,StatusBadge} from '../components/Widgets';

const yearLevels=['1st Year','2nd Year','3rd Year','4th Year'];

export default function FacultyDashboard(){
 const {user}=useAuth();
 const [eventPage,setEventPage]=useState(1),[yearLevel,setYearLevel]=useState(''),[eventId,setEventId]=useState('');
 const dashboardUrl=`/faculty/dashboard?events_page=${eventPage}`;
 const {data:dashboard,loading,error,reload}=useLoad(dashboardUrl,dashboardUrl);
 const reportParams=new URLSearchParams();
 if(yearLevel)reportParams.set('year_level',yearLevel);
 if(eventId)reportParams.set('event_id',eventId);
 const reportQuery=reportParams.toString();
 const reportUrl=`/faculty/participation${reportQuery?`?${reportQuery}`:''}`;
 const {data:report,loading:reportLoading,error:reportError,reload:reloadReport}=useLoad(reportUrl,reportUrl);
 const hasEventPermission=user?.officer_permissions?.includes('events');
 const hasAnnouncementPermission=user?.officer_permissions?.includes('announcements');

 useEffect(()=>{
  const timer=setInterval(()=>{reload();reloadReport();},30000);
  return()=>clearInterval(timer);
 },[reload,reloadReport]);

 return <div className="container page-section">
  <PageTitle eyebrow="FACULTY DASHBOARD" title="Department overview." description={`Monitor programs and student participation${dashboard?.department?.name?` for ${dashboard.department.name}`:''}.`}/>
  <nav className="tab-nav" aria-label="Faculty dashboard sections">
   <a href="#faculty-overview">Overview</a><a href="#faculty-events">Department Events</a><a href="#faculty-announcements">Announcements</a><a href="#faculty-participation">Student Participation</a><Link to="/calendar">Calendar</Link><Link to="/notifications">Notifications</Link>
  </nav>

  <section id="faculty-overview" aria-label="Department overview">
   {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<>
    <div className="dashboard-stats">
     <Stat icon={CalendarDays} label="Total department events" value={dashboard?.stats?.total_events??0}/>
     <Stat icon={CalendarDays} label="Upcoming programs" value={dashboard?.stats?.upcoming_programs??0}/>
     <Stat icon={Newspaper} label="Published department announcements" value={dashboard?.stats?.published_announcements??0}/>
     <Stat icon={TicketCheck} label="Student registrations" value={dashboard?.stats?.student_registrations??0}/>
    </div>
    <p className="helper-text">Registrations count RSVP records across department events; {dashboard?.stats?.unique_registered_students??0} unique students are represented.</p>
   </>}
  </section>

  <section id="faculty-events" className="panel spaced-panel" aria-labelledby="faculty-events-heading">
   <div className="panel-head"><h2 id="faculty-events-heading">Department events</h2><Link to={dashboard?.department?.id?`/archive?department_id=${dashboard.department.id}`:'/archive'}>View archive</Link></div>
   {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!dashboard?.events?.data?.length?<EmptyState title="No upcoming or ongoing department events" description="Ended and cancelled events are available in the archive."/>:<>
    <div className="table-scroll"><table className="data-table"><thead><tr><th>Event Title</th><th>Event Type</th><th>Date and Time</th><th>Venue</th><th>Event Status</th><th>Registered Participants</th><th>Details</th></tr></thead><tbody>
     {dashboard.events.data.map(event=><tr key={event.id}><td><strong>{event.title}</strong></td><td>{event.category||'—'}</td><td>{localDateTime(event.starts_at)}</td><td>{event.venue}</td><td><StatusBadge value={event.schedule_status||event.status}/></td><td>{event.registered_participants_count??0}</td><td><Link className="btn btn-secondary btn-xs" to={`/events/${event.id}`}>View Details</Link></td></tr>)}
    </tbody></table></div>
    {dashboard.events.last_page>1&&<div className="pagination"><button className="btn btn-secondary" disabled={eventPage<=1} onClick={()=>setEventPage(page=>page-1)}>Previous</button><span>Page {eventPage} of {dashboard.events.last_page}</span><button className="btn btn-secondary" disabled={eventPage>=dashboard.events.last_page} onClick={()=>setEventPage(page=>page+1)}>Next</button></div>}
   </>}
  </section>

  <section id="faculty-announcements" className="panel spaced-panel" aria-labelledby="faculty-announcements-heading">
   <div className="panel-head"><h2 id="faculty-announcements-heading">Latest department announcements</h2><Link to={`/announcements${dashboard?.department?.id?`?department_id=${dashboard.department.id}`:''}`}>View announcements</Link></div>
   {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!dashboard?.announcements?.length?<EmptyState title="No department announcements"/>:<div className="mini-announcements">
    {dashboard.announcements.map(announcement=><div className="mini-announcement" key={announcement.id}><span className="announcement-avatar"><Newspaper size={18}/></span><div><strong><Link to={`/announcements?announcement=${announcement.id}`}>{announcement.title}</Link></strong><small>Published {localDateTime(announcement.created_at)} · {announcement.creator?.name||'Department'}</small></div></div>)}
   </div>}
  </section>

  <section id="faculty-participation" className="panel spaced-panel" aria-labelledby="faculty-participation-heading">
   <div className="panel-head"><div><h2 id="faculty-participation-heading">Student participation</h2><p className="muted">Attendance counts exclude pending and rejected check-ins. Students without a saved year level appear as Not Set.</p></div></div>
   <div className="grid-two faculty-filters">
    <label className="field"><span>Year Level</span><select value={yearLevel} onChange={event=>setYearLevel(event.target.value)}><option value="">All Year Levels</option>{yearLevels.map(level=><option key={level} value={level}>{level}</option>)}</select></label>
    <label className="field"><span>Department Event</span><select value={eventId} onChange={event=>setEventId(event.target.value)}><option value="">All department events</option>{dashboard?.event_options?.map(event=><option key={event.id} value={event.id}>{event.title}{event.status==='cancelled'?' (Cancelled)':''}</option>)}</select></label>
   </div>
   {reportLoading?<Loading/>:reportError?<ErrorState message={reportError} retry={reloadReport}/>:<>
    <div className="dashboard-stats faculty-report-stats">
     <Stat icon={TicketCheck} label="Registrations" value={report?.summary?.total_registrations??0}/>
     <Stat icon={Users} label="Unique students" value={report?.summary?.unique_students??0}/>
     <Stat icon={ClipboardCheck} label="Verified attendees" value={report?.summary?.verified_attendees??0}/>
     <Stat icon={Users} label="Absent after attendance window" value={report?.summary?.absent_students??0}/>
    </div>
    <div className="table-scroll"><table className="data-table"><thead><tr><th>Year Level</th><th>Total Registered Students</th><th>Unique Students</th><th>Total Verified Attendees</th><th>Total Absent Students</th><th>Attendance Percentage</th></tr></thead><tbody>
     {report?.by_year_level?.map(row=><tr key={row.year_level}><td>{row.year_level}</td><td>{row.total_registrations}</td><td>{row.unique_students}</td><td>{row.verified_attendees}</td><td>{row.absent_students}</td><td>{row.attendance_percentage===null?'—':`${row.attendance_percentage}%`}</td></tr>)}
    </tbody></table></div>
    {!report?.by_year_level?.length&&<EmptyState title="No student registrations for this selection"/>}
    <p className="helper-text">Attendance percentage uses registrations for events whose attendance window has closed. For events requiring check-out, only verified check-ins with a recorded check-out count as attendance.</p>
   </>}
  </section>

  {(hasEventPermission||hasAnnouncementPermission)&&<section className="panel spaced-panel"><div className="panel-head"><h2>Faculty Adviser tools</h2></div><div className="quick-links">
   {hasEventPermission&&<><Link to="/organizer?tab=events"><CalendarDays size={20}/><span>Manage department events</span></Link><Link to="/organizer?tab=attendees"><ClipboardCheck size={20}/><span>Review attendance</span></Link></>}
   {hasAnnouncementPermission&&<Link to="/organizer?tab=announcements"><Bell size={20}/><span>Manage department announcements</span></Link>}
  </div></section>}
 </div>;
}
