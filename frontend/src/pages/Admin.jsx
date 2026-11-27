import React,{useId,useState} from 'react';
import {Link,useSearchParams} from 'react-router-dom';
import {ShieldCheck,Users,Building2,CalendarDays,TicketCheck,Flag,Newspaper,Activity,Plus,Download,Eye} from 'lucide-react';
import {api,localDateTime,downloadCsv} from '../lib/api';
import {useToast} from '../context/ToastContext';
import {useAuth} from '../context/AuthContext';
import {useLoad,PageTitle,Loading,ErrorState,EmptyState,Stat,StatusBadge,Field,FormNotice} from '../components/Widgets';
import {EventForm} from './Organizer';

const sections=[
 ['overview','Overview'],['departments','Departments'],['users','Users & roles'],['events','All events'],
 ['announcements','Announcements'],['reports','Reports'],['analytics','Analytics'],['audit','Audit logs'],['settings','Settings']
];
const yearLevels=['1st Year','2nd Year','3rd Year','4th Year'];

export default function Admin(){
 const [params,setParams]=useSearchParams();
 const tab=sections.some(x=>x[0]===params.get('tab'))?params.get('tab'):'overview';
 return <div className="container page-section">
  <PageTitle eyebrow="SYSTEM ADMINISTRATION" title="SPC admin console." description="Manage departments, roles, campus events, announcements, and platform oversight." action={<span className="role-label"><ShieldCheck size={16}/> Administrator</span>}/>
  <div className="tab-nav tab-wrap">{sections.map(([key,label])=><button className={tab===key?'active':''} key={key} onClick={()=>setParams({tab:key})}>{label}</button>)}</div>
  {tab==='overview'?<AdminOverview/>:tab==='departments'?<AdminDepartments/>:tab==='users'?<AdminUsers/>:tab==='events'?<AdminEvents/>:tab==='announcements'?<AdminAnnouncements/>:tab==='reports'?<AdminReports/>:tab==='analytics'?<AdminAnalytics/>:tab==='audit'?<AdminAudit/>:<AdminSettings/>}
 </div>;
}

function AdminOverview(){
 const {data,loading,error,reload}=useLoad('/admin/stats');
 return <>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="dashboard-stats admin-stats">
  <Stat icon={Users} value={data?.students} label="Student accounts"/>
  <Stat icon={ShieldCheck} value={data?.officers} label="Officers & presidents"/>
  <Stat icon={Building2} value={data?.departments} label="Departments"/>
  <Stat icon={CalendarDays} value={data?.events} label="All events"/>
  <Stat icon={TicketCheck} value={data?.rsvps} label="Active RSVPs"/>
  <Stat icon={Flag} value={data?.reports} label="Reports awaiting review"/>
  <Stat icon={Newspaper} value={data?.announcements} label="Announcements"/>
  <Stat icon={Activity} value={data?.upcoming} label="Upcoming events"/>
 </div>}<div className="panel spaced-panel"><h2>Administrator responsibilities</h2><div className="admin-summary"><p>Use <strong>Departments</strong> to manage academic units and <strong>Users & roles</strong> to assign administrators, presidents, and officers. Review reports, events, announcements, analytics, and audit activity from the remaining tabs.</p><p className="helper-text">Public sign-up always creates a student account.</p></div></div></>;
}

function AdminDepartments(){
 const {data,loading,error,reload}=useLoad('/departments'),{flash}=useToast();
 const [editing,setEditing]=useState(undefined),[text,setText]=useState(''),[busy,setBusy]=useState(false);
 async function save(e){e.preventDefault();setBusy(true);setText('');try{const body=Object.fromEntries(new FormData(e.currentTarget));await api(editing?.id?`/admin/departments/${editing.id}`:'/admin/departments',{method:editing?.id?'PUT':'POST',body});flash(editing?.id?'Department updated.':'Department created.');setEditing(undefined);reload();}catch(e){setText(e.message);}finally{setBusy(false);}}
 async function remove(department){if(!confirm(`Delete ${department.name}? The department must have no users or events.`))return;try{await api(`/admin/departments/${department.id}`,{method:'DELETE'});flash('Department deleted.');reload();}catch(e){flash(e.message,'error');}}
 return <div className="panel"><div className="panel-head"><h2>Manage departments</h2><button className="btn btn-primary" onClick={()=>setEditing({})}><Plus size={16}/> New department</button></div>
 {editing!==undefined&&<div className="inline-editor"><form className="form-stack" key={editing?.id||'new'} onSubmit={save}><h2>{editing?.id?'Edit department':'Create department'}</h2><div className="grid-two"><Field label="Department name"><input name="name" defaultValue={editing?.name||''} required/></Field><Field label="Code"><input name="code" defaultValue={editing?.code||''} required placeholder="CCS"/></Field></div><Field label="Description"><textarea name="description" rows={3} defaultValue={editing?.description||''}/></Field><Field label="Contact email"><input name="email" type="email" defaultValue={editing?.email||''}/></Field><FormNotice error={text}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={()=>setEditing(undefined)}>Cancel</button><button className="btn btn-primary" disabled={busy}>Save department</button></div></form></div>}
 {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Department</th><th>Code</th><th>Users</th><th>Events</th><th>Actions</th></tr></thead><tbody>{data?.map(d=><tr key={d.id}><td><strong>{d.name}</strong><small>{d.email||'No contact email'}</small></td><td>{d.code}</td><td>{d.users_count}</td><td>{d.events_count}</td><td><div className="row-actions"><button className="btn btn-secondary btn-xs" onClick={()=>setEditing(d)}>Edit</button><button className="btn btn-danger-light btn-xs" onClick={()=>remove(d)}>Delete</button></div></td></tr>)}</tbody></table></div>}
 </div>;
}

function AdminUsers(){
 const {user:currentUser}=useAuth();
 const [search,setSearch]=useState(''),[query,setQuery]=useState('');
 const [roleTab,setRoleTab]=useState('student'),[yearLevelFilter,setYearLevelFilter]=useState('');
 const params=new URLSearchParams({role:roleTab});
 if(query)params.set('search',query);
 if(roleTab==='student'&&yearLevelFilter)params.set('year_level',yearLevelFilter);
 const usersUrl=`/admin/users?${params.toString()}`;
 const {data,loading,error,reload}=useLoad(usersUrl,usersUrl);
 const {data:depts}=useLoad('/departments'),{flash}=useToast();
 const [editing,setEditing]=useState(null),[editRole,setEditRole]=useState(''),[editDepartmentId,setEditDepartmentId]=useState(''),[err,setErr]=useState(''),[busy,setBusy]=useState(false);
 const [creatingUser,setCreatingUser]=useState(false),[createRole,setCreateRole]=useState('student'),[createDepartmentId,setCreateDepartmentId]=useState(''),[passwordSetup,setPasswordSetup]=useState('temporary_password'),[createErr,setCreateErr]=useState(''),[createBusy,setCreateBusy]=useState(false);
 const tabs=[['student','Students'],['faculty','Faculty'],['leadership','Department Leadership'],['admin','Admins']];
 async function save(e){
  e.preventDefault();const f=new FormData(e.currentTarget);
  const body={
   name:f.get('name'),email:f.get('email'),role:f.get('role')||editing.role,
   department_id:f.get('department_id')||null,is_active:f.has('is_active')?f.get('is_active')==='on':editing.is_active,
   ...( ['student','president','officer'].includes(editRole)?{course:f.get('course')||null}:{}),
   ...(editRole==='student'?{year_level:f.get('year_level')||null}:{}),
   ...(editRole==='faculty'?{faculty_position:f.get('faculty_position')||null}:{}),
   ...( ['faculty','officer'].includes(editRole)?{officer_permissions:f.getAll('permission')}:{}),
  };
  setBusy(true);setErr('');
  try{await api(`/admin/users/${editing.id}`,{method:'PUT',body});flash('User account updated.');setEditing(null);reload();}
  catch(e){setErr(e.message);}finally{setBusy(false);}
 }
 async function createUser(e){
  e.preventDefault();const f=new FormData(e.currentTarget),role=f.get('role');
  const body={
   name:f.get('name'),email:f.get('email'),role,department_id:f.get('department_id'),
   password_setup:f.get('password_setup'),
   ...(f.get('password_setup')==='temporary_password'?{password:f.get('password'),password_confirmation:f.get('password_confirmation')}:{}),
   ...(role==='student'?{course:f.get('course'),year_level:f.get('year_level')}:{}),
   ...( ['president','officer'].includes(role)?{course:f.get('course')||null}:{}),
   ...(role==='faculty'?{faculty_position:f.get('faculty_position')||null,officer_permissions:f.getAll('officer_permissions')}:{}),
   ...(role==='officer'?{officer_permissions:f.getAll('officer_permissions')}:{}),
  };
  setCreateBusy(true);setCreateErr('');
  try{const result=await api('/admin/users',{method:'POST',body});flash(result.message);setCreatingUser(false);reload();}
  catch(e){setCreateErr(e.message);}finally{setCreateBusy(false);}
 }
 async function toggleActive(user){const active=!user.is_active;if(!confirm(`${active?'Reactivate':'Deactivate'} ${user.name}'s account?`))return;try{await api(`/admin/users/${user.id}`,{method:'PUT',body:{is_active:active}});flash(`User account ${active?'reactivated':'deactivated'}.`);reload();}catch(e){flash(e.message,'error');}}
 async function remove(user){if(!confirm(`Permanently delete ${user.name}'s account and related records?`))return;try{await api(`/admin/users/${user.id}`,{method:'DELETE'});flash('User deleted.');reload();}catch(e){flash(e.message,'error');}}
 const protectedAdminEditing=editing?.role==='admin'&&(editing.is_protected_admin||editing.id===currentUser?.id);
 return <div className="panel">
  <div className="panel-head"><h2>Users and role assignments</h2><div className="row-actions"><button className="btn btn-primary" onClick={()=>{setCreatingUser(value=>!value);setCreateRole('student');setCreateDepartmentId('');setPasswordSetup('temporary_password');setCreateErr('');}}><Plus size={16}/> Create User</button><form className="search-inline" onSubmit={e=>{e.preventDefault();setQuery(search);}}><input value={search} placeholder="Search users..." onChange={e=>setSearch(e.target.value)}/><button className="btn btn-secondary">Search</button></form></div></div>
  <div className="tab-nav tab-wrap">{tabs.map(([role,label])=><button type="button" key={role} onClick={()=>{setRoleTab(role);setEditing(null);}} className={roleTab===role?'active':''}>{label}</button>)}</div>
  {creatingUser&&<div className="inline-editor"><form className="form-stack" onSubmit={createUser}>
   <h2>Create User</h2>
   <Field label="Account role"><select name="role" value={createRole} onChange={e=>setCreateRole(e.target.value)}><option value="student">Student</option><option value="faculty">Faculty</option><option value="president">Department President</option><option value="officer">Department Officer</option></select></Field>
   <div className="grid-two"><Field label="Full name"><input name="name" required maxLength="120"/></Field><Field label="Email address"><input name="email" type="email" required maxLength="255"/></Field></div>
   <div className="grid-two"><Field label="Department"><select name="department_id" value={createDepartmentId} onChange={e=>setCreateDepartmentId(e.target.value)} required><option value="" disabled>Select department</option>{depts?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select></Field>
    {['student','president','officer'].includes(createRole)&&<CourseField departmentId={createDepartmentId} required={createRole==='student'}/>}
    {createRole==='student'&&<Field label="Year Level"><select name="year_level" required defaultValue=""><option value="" disabled>Select year level</option>{yearLevels.map(level=><option key={level} value={level}>{level}</option>)}</select></Field>}
    {createRole==='faculty'&&<Field label="Faculty position (optional)"><input name="faculty_position" maxLength="120"/></Field>}
   </div>
   {['faculty','officer'].includes(createRole)&&<fieldset className="permissions-fieldset"><legend>{createRole==='faculty'?'Faculty Adviser permissions':'Officer permissions'}</legend>{[['events','Manage events'],['announcements','Publish announcements'],['attendance','Manage attendance'],...(createRole==='officer'?[['members','Review memberships']]:[])].map(([permission,label])=><label className="checkbox-label" key={permission}><input type="checkbox" name="officer_permissions" value={permission}/>{label}</label>)}</fieldset>}
   <fieldset className="permissions-fieldset"><legend>Account Password Setup</legend>
    <label className="checkbox-label"><input type="radio" name="password_setup" value="temporary_password" checked={passwordSetup==='temporary_password'} onChange={()=>setPasswordSetup('temporary_password')}/>Set Temporary Password</label>
    <label className="checkbox-label"><input type="radio" name="password_setup" value="email_invitation" checked={passwordSetup==='email_invitation'} onChange={()=>setPasswordSetup('email_invitation')}/>Send Email Invitation</label>
   </fieldset>
   {passwordSetup==='temporary_password'?<><div className="grid-two"><Field label="Password"><input name="password" type="password" autoComplete="new-password" minLength="8" required/></Field><Field label="Confirm password"><input name="password_confirmation" type="password" autoComplete="new-password" minLength="8" required/></Field></div><p className="helper-text">Use at least 8 characters. The user must change this password at first login.</p></>:<p className="helper-text">A secure, single-use password setup link will be sent to the account email. The account remains inactive until setup is complete.</p>}
   <FormNotice error={createErr}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={()=>setCreatingUser(false)}>Cancel</button><button className="btn btn-primary" disabled={createBusy}>{createBusy?'Creating account...':passwordSetup==='temporary_password'?'Create user with temporary password':'Create user and send setup link'}</button></div>
  </form></div>}
  {editing&&<div className="inline-editor"><form className="form-stack" onSubmit={save} key={editing.id}>
   <h2>Edit {editing.name}</h2>
   <div className="grid-two"><Field label="Full name"><input name="name" defaultValue={editing.name} required/></Field><Field label="Email address"><input name="email" defaultValue={editing.email} type="email" required/></Field>
    <Field label="Department"><select name="department_id" value={editDepartmentId} onChange={e=>setEditDepartmentId(e.target.value)}><option value="">Unassigned</option>{depts?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select></Field>
    <Field label="Account role"><select name="role" value={editRole} onChange={e=>setEditRole(e.target.value)} disabled={protectedAdminEditing}><option value="student">Student</option><option value="faculty">Faculty</option><option value="officer">Department officer</option><option value="president">Department president</option><option value="admin">Administrator</option></select></Field>
    {['student','president','officer'].includes(editRole)&&<CourseField departmentId={editDepartmentId} defaultValue={editing.course||''} required={editRole==='student'}/>}
    {editRole==='student'&&<Field label="Year Level"><select name="year_level" defaultValue={editing.year_level||''}><option value="">Not Set</option>{yearLevels.map(level=><option key={level} value={level}>{level}</option>)}</select></Field>}
    {editRole==='faculty'&&<Field label="Faculty position (optional)"><input name="faculty_position" defaultValue={editing.faculty_position||''} maxLength="120"/></Field>}
   </div>
   <label className="checkbox-label"><input type="checkbox" name="is_active" defaultChecked={editing.is_active!==false} disabled={protectedAdminEditing&&editing.is_active}/> Account active</label>
   {protectedAdminEditing&&<p className="helper-text">This Admin account is protected and cannot be demoted or deactivated.</p>}
   {['faculty','officer'].includes(editRole)&&<fieldset className="permissions-fieldset"><legend>{editRole==='faculty'?'Faculty Adviser permissions':'Officer permissions'}</legend>{[['events','Manage events'],['announcements','Publish announcements'],['attendance','Manage attendance'],...(editRole==='officer'?[['members','Review memberships']]:[])].map(([permission,label])=><label className="checkbox-label" key={permission}><input type="checkbox" name="permission" value={permission} defaultChecked={editing.officer_permissions?.includes(permission)}/>{label}</label>)}</fieldset>}
   <FormNotice error={err}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={()=>setEditing(null)}>Cancel</button><button className="btn btn-primary" disabled={busy}>Save user</button></div>
  </form></div>}
  {roleTab==='student'&&<Field label="Filter by Year Level"><select value={yearLevelFilter} onChange={e=>setYearLevelFilter(e.target.value)}><option value="">All year levels</option>{yearLevels.map(level=><option key={level} value={level}>{level}</option>)}</select></Field>}
  {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="table-scroll"><table className="data-table">
   <thead><tr><th>Full Name / Email</th>{roleTab==='student'&&<><th>Department</th><th>Course / Program</th><th>Year Level</th></>}{roleTab==='faculty'&&<><th>Department</th><th>Faculty Position</th></>}{roleTab==='leadership'&&<><th>Department</th><th>Course / Program</th><th>Role</th></>}{roleTab==='admin'&&<><th>Department</th><th>Role</th></>}<th>Account Status</th><th>Actions</th></tr></thead>
   <tbody>{data?.data?.map(user=><tr key={user.id}>
    <td><strong>{user.name}</strong><small>{user.email}</small></td>
    {roleTab==='student'?<><td>{user.department?.name||'—'}</td><td>{user.course||'—'}</td><td>{user.year_level||'Not Set'}</td></>:roleTab==='faculty'?<><td>{user.department?.name||'—'}</td><td>{user.faculty_position||'—'}</td></>:roleTab==='leadership'?<><td>{user.department?.name||'—'}</td><td>{user.course||'—'}</td><td>{user.role==='president'?'President':'Officer'}</td></>:<><td>{user.department?.name||'—'}</td><td>Administrator</td></>}
    <td><StatusBadge value={user.is_active?'Active':'Deactivated'}/>{user.is_primary_admin&&<small>Primary Admin</small>}{user.role==='admin'&&user.is_protected_admin&&!user.is_primary_admin&&<small>Last active Admin</small>}</td><td><div className="row-actions"><button className="btn btn-secondary btn-xs" onClick={()=>{setEditing(user);setEditRole(user.role);setEditDepartmentId(String(user.department_id||''));setErr('');}}>Edit / Assign</button>{!(user.role==='admin'&&user.is_active&&(user.is_protected_admin||user.id===currentUser?.id))&&<button className="btn btn-secondary btn-xs" onClick={()=>toggleActive(user)}>{user.is_active?'Deactivate':'Reactivate'}</button>}{!(user.role==='admin'&&(user.is_protected_admin||user.id===currentUser?.id))&&<button className="btn btn-danger-light btn-xs" onClick={()=>remove(user)}>Delete</button>}</div></td>
   </tr>)}</tbody></table>{!data?.data?.length&&<EmptyState title={`No ${tabs.find(([role])=>role===roleTab)?.[1].toLowerCase()} found`}/>}<p className="helper-text">Showing {data?.data?.length||0} of {data?.total||0} accounts.</p></div>}
 </div>;
}

function CourseField({departmentId,defaultValue='',required=false}){
 const {data:courses}=useLoad(departmentId?`/admin/courses?department_id=${departmentId}`:null,departmentId);
 const listId=`course-options-${useId().replace(/:/g,'')}`;
 return <Field label="Course / Program"><input name="course" list={listId} defaultValue={defaultValue} maxLength="140" required={required}/><datalist id={listId}>{courses?.map(course=><option key={course} value={course}/>)}</datalist></Field>;
}

function AdminEvents(){
 const {data,loading,error,reload}=useLoad('/admin/events'),{data:depts}=useLoad('/departments'),{flash}=useToast();
 const [editing,setEditing]=useState(undefined),[dept,setDept]=useState(''),[busy,setBusy]=useState(0);
 async function cancel(e){if(!confirm('Cancel event and notify RSVP holders?'))return;setBusy(e.id);try{await api(`/organizer/events/${e.id}/cancel`,{method:'POST'});flash('Event cancelled.');reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 async function remove(e){if(!confirm(`Delete "${e.title}" from listings? This is separate from cancelling. RSVP and attendance history will be preserved.`))return;setBusy(e.id);try{const result=await api(`/admin/events/${e.id}`,{method:'DELETE'});flash(result.message);reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 return <div className="panel"><div className="panel-head"><h2>All campus events</h2><button className="btn btn-primary" onClick={()=>setEditing({})}><Plus size={16}/> Publish event</button></div>
 {editing!==undefined&&<div className="inline-editor"><Field label="Department"><select value={editing.id?editing.department_id:dept} disabled={Boolean(editing.id)} onChange={e=>setDept(e.target.value)}><option value="">Select department</option>{depts?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select></Field>{(editing.id||dept)&&<EventForm key={editing.id||'create'} existing={editing.id?editing:null} dept={editing.id?editing.department_id:Number(dept)} onCancel={()=>setEditing(undefined)} onSaved={()=>{setEditing(undefined);reload();}}/>}</div>}
 {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Department</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead><tbody>{data?.map(e=><tr key={e.id}><td><strong>{e.title}</strong><small>{e.venue}</small></td><td>{e.department?.code}</td><td>{localDateTime(e.starts_at)}</td><td><StatusBadge value={e.schedule_status||e.status}/></td><td><div className="row-actions"><button className="btn btn-secondary btn-xs" disabled={e.status==='cancelled'||busy===e.id} onClick={()=>setEditing(e)}>Edit</button><Link className="btn btn-secondary btn-xs" to={`/events/${e.id}`}>View</Link><button className="btn btn-danger-light btn-xs" disabled={e.status==='cancelled'||busy===e.id} onClick={()=>cancel(e)}>Cancel</button><button className="btn btn-danger-light btn-xs" disabled={busy===e.id} onClick={()=>remove(e)}>Delete</button></div></td></tr>)}</tbody></table></div>}
 </div>;
}

function AdminAnnouncements(){
 const {data,loading,error,reload}=useLoad('/admin/announcements'),{data:depts}=useLoad('/departments'),{flash}=useToast();
 const [editing,setEditing]=useState(undefined),[busy,setBusy]=useState(false),[err,setErr]=useState('');
 async function save(e){e.preventDefault();const f=new FormData(e.currentTarget);const body={title:f.get('title'),body:f.get('body'),department_id:f.get('department_id')||null,is_pinned:f.get('is_pinned')==='on'};setBusy(true);setErr('');try{await api(editing.id?`/organizer/announcements/${editing.id}`:'/organizer/announcements',{method:editing.id?'PUT':'POST',body});flash('Announcement saved.');setEditing(undefined);reload();}catch(e){setErr(e.message);}finally{setBusy(false);}}
 async function remove(a){if(!confirm(`Delete announcement ${a.title}?`))return;try{await api(`/organizer/announcements/${a.id}`,{method:'DELETE'});flash('Announcement removed.');reload();}catch(e){flash(e.message,'error');}}
 return <div className="panel"><div className="panel-head"><h2>Campus and department announcements</h2><button className="btn btn-primary" onClick={()=>setEditing({})}><Plus size={16}/> Publish announcement</button></div>
 {editing!==undefined&&<div className="inline-editor"><form onSubmit={save} key={editing.id||'new'} className="form-stack"><h2>{editing.id?'Edit announcement':'New announcement'}</h2><Field label="Title"><input name="title" defaultValue={editing.title||''} required/></Field><Field label="Message"><textarea name="body" rows={4} defaultValue={editing.body||''} required/></Field>{!editing.id&&<Field label="Department"><select name="department_id"><option value="">All campus</option>{depts?.map(d=><option key={d.id} value={d.id}>{d.name}</option>)}</select></Field>}<label className="checkbox-label"><input type="checkbox" name="is_pinned" defaultChecked={editing.is_pinned||false}/> Pin this announcement</label><FormNotice error={err}/><div className="actions"><button type="button" className="btn btn-secondary" onClick={()=>setEditing(undefined)}>Cancel</button><button className="btn btn-primary" disabled={busy}>Save announcement</button></div></form></div>}
 {loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="mini-announcements">{data?.map(a=><div key={a.id} className="mini-announcement"><span className="announcement-avatar"><Newspaper size={18}/></span><div><strong>{a.title}</strong><small>{a.department?.name||'Campus-wide'} · {localDateTime(a.created_at)}</small><p>{a.body}</p></div><div className="row-actions"><button className="btn btn-secondary btn-xs" onClick={()=>setEditing(a)}>Edit</button><button className="btn btn-danger-light btn-xs" onClick={()=>remove(a)}>Delete</button></div></div>)}</div>}
 </div>;
}

function AdminReports(){
 const {data,loading,error,reload}=useLoad('/admin/reports'),{flash}=useToast();
 const [resolutions,setResolutions]=useState({}),[busy,setBusy]=useState(0);
 async function updateStatus(id,status){setBusy(id);try{await api(`/admin/reports/${id}`,{method:'PUT',body:{status,resolution:resolutions[id]||''}});flash('Report '+status.replace('_',' ')+'.');reload();}catch(e){flash(e.message,'error');}finally{setBusy(0);}}
 return <div className="panel"><h2>Suspicious event reports</h2>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:!data?.data?.length?<EmptyState title="No reports submitted"/>:<div className="report-list">{data.data.map(r=><div className="report-card" key={r.id}><div className="report-title"><div><strong>{r.event_title}</strong><small>Reference #{r.id} · Reported by {r.reporter} · {localDateTime(r.created_at)}</small></div><StatusBadge value={r.status}/></div><p>{r.reason}</p>{['pending','under_review'].includes(r.status)&&<><textarea rows={2} value={resolutions[r.id]||''} onChange={e=>setResolutions(p=>({...p,[r.id]:e.target.value}))}/><div className="actions"><Link className="btn btn-secondary btn-xs" to={`/events/${r.event_id}`}>Inspect event</Link>{r.status==='pending'&&<button className="btn btn-secondary btn-xs" disabled={busy===r.id} onClick={()=>updateStatus(r.id,'under_review')}>Mark under review</button>}<button className="btn btn-primary btn-xs" disabled={busy===r.id} onClick={()=>updateStatus(r.id,'resolved')}>Resolve</button><button className="btn btn-danger-light btn-xs" disabled={busy===r.id} onClick={()=>updateStatus(r.id,'dismissed')}>Dismiss</button></div></>}</div>)}</div>}</div>;
}

function AdminAnalytics(){
 const {data,loading,error,reload}=useLoad('/admin/reporting'),{flash}=useToast();
 async function exportData(){try{await downloadCsv('/admin/reporting/export','spc-campus-report.csv')}catch(e){flash(e.message,'error')}}
 return <div className="panel"><div className="panel-head"><h2>Campus activity analytics</h2><button className="btn btn-secondary" onClick={exportData}><Download size={16}/> Export campus report</button></div>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<><h3>Most viewed events</h3><div className="table-scroll"><table className="data-table"><thead><tr><th>Event</th><th>Department</th><th>Views</th><th>RSVPs</th></tr></thead><tbody>{data?.top_events?.map(e=><tr key={e.id}><td>{e.title}</td><td>{e.department?.name}</td><td>{e.views}</td><td>{e.attendee_count}</td></tr>)}</tbody></table></div></>}</div>;
}

function AdminAudit(){const {data,loading,error,reload}=useLoad('/admin/audits');return <div className="panel"><h2>System audit logs</h2>{loading?<Loading/>:error?<ErrorState message={error} retry={reload}/>:<div className="table-scroll"><table className="data-table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Record</th></tr></thead><tbody>{data?.data?.map(a=><tr key={a.id}><td>{localDateTime(a.created_at)}</td><td>{a.actor_name||'System / deleted user'}</td><td>{a.action}</td><td>{a.subject_type||'—'} {a.subject_id||''}</td></tr>)}</tbody></table></div>}</div>}

function AdminSettings(){
 const {data,loading,error,reload}=useLoad('/admin/settings'),{flash}=useToast();
 const [busy,setBusy]=useState(false),[text,setText]=useState('');
 async function save(e){e.preventDefault();const f=new FormData(e.currentTarget);setBusy(true);setText('');try{await api('/admin/settings',{method:'PUT',body:{campus_name:f.get('campus_name'),contact_email:f.get('contact_email')||null,registration_open:f.get('registration_open')==='on'}});flash('System settings updated.');reload();}catch(e){setText(e.message);}finally{setBusy(false);}}
 if(loading)return <Loading/>;if(error)return <ErrorState message={error} retry={reload}/>;
 return <div className="panel"><h2>System settings</h2><form className="form-stack" key={JSON.stringify(data)} onSubmit={save}><Field label="Campus name"><input name="campus_name" defaultValue={data?.campus_name||"St. Peter's College"} required/></Field><Field label="Contact email"><input name="contact_email" type="email" defaultValue={data?.contact_email||''}/></Field><label className="checkbox-label"><input name="registration_open" type="checkbox" defaultChecked={data?.registration_open!=='0'}/> Allow new student registrations</label><FormNotice error={text}/><button className="btn btn-primary" disabled={busy}>Save system settings</button></form></div>;
}
