import React,{useEffect,useRef,useState} from 'react';
import {Link,NavLink,useNavigate,useLocation} from 'react-router-dom';
import {ArrowRight,Bell,Building2,CalendarDays,ChevronDown,LayoutDashboard,LogIn,LogOut,Menu,Settings2,ShieldCheck,UserRound,X} from 'lucide-react';
import {useAuth} from '../context/AuthContext';
import {useToast} from '../context/ToastContext';
import {api,localDateTime} from '../lib/api';
import {useLoad} from './Widgets';
import '../availability.css';

function getNavigationRoles(user){
 const roleValues=[user?.role,user?.base_role,user?.department_role];
 if(Array.isArray(user?.roles))roleValues.push(...user.roles.map(role=>typeof role==='string'?role:role?.name||role?.slug));
 else if(typeof user?.roles==='string')roleValues.push(user.roles);
 return new Set(roleValues.filter(role=>typeof role==='string')
  .flatMap(role=>role.split(/[|,]/))
  .map(role=>role.trim().toLowerCase().replace(/[\s-]+/g,'_').replace(/^(department|dept)_/,''))
  .filter(Boolean));
}

export function Navbar(){
 const {user,logout,refresh}=useAuth(),navigate=useNavigate(),location=useLocation(),{flash}=useToast();
 const [mobile,setMobile]=useState(false),[drop,setDrop]=useState(false),[notificationDrop,setNotificationDrop]=useState(false),[availabilitySaving,setAvailabilitySaving]=useState(false);
 const lastHeartbeat=useRef(0);
 const {data:count,reload:reloadCount}=useLoad(user?'/notifications/count':null,user?.id);
 const {data:recent,loading:recentLoading,reload:reloadRecent}=useLoad(user&&notificationDrop?'/notifications?per_page=5':null,user?.id);

 useEffect(()=>{
  setMobile(false);setDrop(false);setNotificationDrop(false);reloadCount();
 },[user?.id,location.pathname]);

 useEffect(()=>{
  if(!user)return;
  const refresh=()=>{reloadCount();if(notificationDrop)reloadRecent();};
  const timer=setInterval(refresh,30000);
  window.addEventListener('spc-notifications-changed',refresh);
  return()=>{clearInterval(timer);window.removeEventListener('spc-notifications-changed',refresh);};
 },[user?.id,notificationDrop,reloadCount,reloadRecent]);

 useEffect(()=>{
  if(user?.role!=='admin')return;
  let heartbeatErrorShown=false;
  const heartbeat=async(force=false)=>{
   if(document.visibilityState==='hidden')return;
   const now=Date.now();
   if(!force&&now-lastHeartbeat.current<25000)return;
   lastHeartbeat.current=now;
   try{await api('/admin/presence/heartbeat',{method:'POST'});heartbeatErrorShown=false;}
   catch(e){if(!heartbeatErrorShown){flash(`Availability heartbeat failed: ${e.message}`,'error');heartbeatErrorShown=true;}}
  };
  const onActivity=()=>heartbeat();
  const onVisibility=()=>{if(document.visibilityState==='visible')heartbeat(true);};
  heartbeat(true);
  window.addEventListener('pointerdown',onActivity,{passive:true});
  window.addEventListener('pointermove',onActivity,{passive:true});
  window.addEventListener('keydown',onActivity);
  window.addEventListener('touchstart',onActivity,{passive:true});
  window.addEventListener('focus',onActivity);
  document.addEventListener('visibilitychange',onVisibility);
  return()=>{
   window.removeEventListener('pointerdown',onActivity);
   window.removeEventListener('pointermove',onActivity);
   window.removeEventListener('keydown',onActivity);
   window.removeEventListener('touchstart',onActivity);
   window.removeEventListener('focus',onActivity);
   document.removeEventListener('visibilitychange',onVisibility);
  };
 },[user?.id,user?.role,flash]);

 const links=[['/events','Discover'],['/announcements','Announcements'],['/departments','Departments'],['/calendar','Calendar'],['/archive','Archive']];
 const roles=getNavigationRoles(user);
 const isAdmin=roles.has('admin'),isPresident=roles.has('president'),isOfficer=roles.has('officer'),isFaculty=roles.has('faculty');
 const hasOrganizerPermission=user?.officer_permissions?.some(permission=>['events','announcements','attendance'].includes(permission));
 const canAccessOrganizer=isPresident||(isOfficer||isFaculty)&&hasOrganizerPermission;
 const isRegularStudent=roles.has('student')&&!isAdmin&&!isPresident&&!isOfficer&&!isFaculty;
 const dashboardMenuItem=isAdmin
  ?<><Link to="/admin" onClick={()=>setDrop(false)}><ShieldCheck size={16}/> Admin Dashboard</Link><div className="availability-menu"><strong>Availability Status</strong><small>Current: {user?.availability_preference==='busy'?'Busy':'Online'}</small><div className="availability-options"><button type="button" aria-pressed={user?.availability_preference==='online'} disabled={availabilitySaving||user?.availability_preference==='online'} onClick={()=>setAvailability('online')}><span className="presence-dot online"/>Online</button><button type="button" aria-pressed={user?.availability_preference==='busy'} disabled={availabilitySaving||user?.availability_preference==='busy'} onClick={()=>setAvailability('busy')}><span className="presence-dot busy"/>Busy</button></div></div></>
  :isPresident
   ?<Link to="/organizer" onClick={()=>setDrop(false)}><Building2 size={16}/> Department Dashboard</Link>
   :isOfficer
    ?canAccessOrganizer&&<Link to="/organizer" onClick={()=>setDrop(false)}><Building2 size={16}/> Department Dashboard</Link>
    :isFaculty
     ?<><Link to="/dashboard" onClick={()=>setDrop(false)}><LayoutDashboard size={16}/> Faculty Dashboard</Link>{canAccessOrganizer&&<Link to="/organizer" onClick={()=>setDrop(false)}><Building2 size={16}/> Organizer</Link>}</>
     :isRegularStudent
      ?<Link to="/dashboard" onClick={()=>setDrop(false)}><LayoutDashboard size={16}/> Student Dashboard</Link>
      :null;
 async function setAvailability(status){
  if(availabilitySaving)return;
  setAvailabilitySaving(true);
  try{
   await api('/admin/availability',{method:'PUT',body:{status}});
   await refresh();
  }catch(e){flash(e.message,'error');}
  finally{setAvailabilitySaving(false);}
 }
 async function openNotification(notification){
  setNotificationDrop(false);
  if(!notification.read_at){
   try{await api(`/notifications/${notification.id}/read`,{method:'POST'});window.dispatchEvent(new Event('spc-notifications-changed'));}
   catch(e){flash(e.message,'error');return;}
  }
  const target=notification.link?.startsWith('/')?notification.link:'/notifications';
  navigate(target);
 }
 async function markAll(){
  try{await api('/notifications/read-all',{method:'POST'});window.dispatchEvent(new Event('spc-notifications-changed'));reloadRecent();flash("You're all caught up!");}
  catch(e){flash(e.message,'error');}
 }

 return <header className="site-header"><div className="nav-container">
  <Link to="/" className="brand"><div className="brand-icon"><CalendarDays size={22}/></div><span><strong>CampusEvents Hub</strong><small>SPC CAMPUS LIFE</small></span></Link>
  <nav className={`top-nav ${mobile?'open':''}`} aria-label="Main navigation">{links.map(([to,name])=><NavLink key={to} to={to} className={({isActive})=>isActive?'active':''} onClick={()=>setMobile(false)}>{name}</NavLink>)}</nav>
  <div className="nav-actions">{user?<>
   <div className="notification-bell">
    <button className="nav-icon notification-toggle" aria-label="Notifications" aria-expanded={notificationDrop} aria-controls="notification-menu" onClick={()=>setNotificationDrop(value=>!value)}>
     <Bell size={19}/>{count?.unread>0&&<em>{count.unread>9?'9+':count.unread}</em>}
    </button>
    {notificationDrop&&<div className="notification-menu" id="notification-menu">
     <div className="notification-menu-head"><strong>Notifications</strong><Link to="/notifications" onClick={()=>setNotificationDrop(false)}>View all <ArrowRight size={14}/></Link></div>
     {recentLoading?<p className="notification-menu-state">Loading notifications...</p>:recent?.data?.length?<div className="notification-menu-list">{recent.data.map(item=><button type="button" className={`notification-menu-item ${item.read_at?'':'unread'}`} key={item.id} onClick={()=>openNotification(item)}>
      <strong>{item.title}</strong><span>{item.message}</span><small>{localDateTime(item.created_at)}</small>
     </button>)}</div>:<p className="notification-menu-state">{count?.unread?'No notifications to display.':"You're all caught up!"}</p>}
     {!recentLoading&&recent?.data?.length&&!count?.unread&&<p className="notification-menu-state">You're all caught up!</p>}
     <div className="notification-menu-footer"><button type="button" className="text-button" disabled={!count?.unread} onClick={markAll}>Mark all read</button><Link to="/notifications" onClick={()=>setNotificationDrop(false)}>Open notification center</Link></div>
    </div>}
   </div>
   <div className="account"><button className="account-toggle" onClick={()=>setDrop(!drop)}><span className="avatar-small">{user.photo_url?<img src={user.photo_url} alt=""/>:user.name?.[0]}</span><span className="account-name">{user.name?.split(' ')[0]}</span><ChevronDown size={14}/></button>
    {drop&&<div className="account-menu"><div className="menu-account"><strong>{user.name}</strong><small>{user.email}</small></div>{dashboardMenuItem}<Link to="/profile" onClick={()=>setDrop(false)}><UserRound size={16}/> Profile</Link><Link to="/settings" onClick={()=>setDrop(false)}><Settings2 size={16}/> Settings</Link><button onClick={async()=>{await logout();setDrop(false);navigate('/');}}><LogOut size={16}/> Log out</button></div>}
   </div></>:<><Link to="/register" className="nav-register">Sign up</Link><Link to="/login" className="btn btn-login"><LogIn size={17}/> Log in</Link></>}
   <button className="mobile-toggle" onClick={()=>setMobile(!mobile)} aria-label="Toggle navigation">{mobile?<X size={24}/>:<Menu size={24}/>}</button>
  </div>
 </div></header>
}

export function Footer(){return <footer className="site-footer"><div className="container footer-grid"><div><div className="footer-brand"><CalendarDays size={23}/> CampusEvents Hub</div><p>One campus. Every connection. Discover events, stay updated, and make every SPC moment count.</p></div><div><strong>Explore</strong><Link to="/events">Upcoming Events</Link><Link to="/departments">Departments</Link><Link to="/calendar">Calendar</Link></div><div><strong>Help</strong><Link to="/">How it works</Link><Link to="/announcements">Announcements</Link><Link to="/departments">Campus communities</Link></div></div><div className="container footer-bottom">© {new Date().getFullYear()} CampusEvents Hub · St. Peter's College <span>Built for SPC students</span></div></footer>}
export function Layout({children}){return <><Navbar/><main>{children}</main><Footer/></>}
