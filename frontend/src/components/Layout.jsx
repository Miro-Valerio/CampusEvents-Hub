import React,{useEffect,useState} from 'react';
import {Link,NavLink,useNavigate,useLocation} from 'react-router-dom';
import {ArrowRight,Bell,Building2,CalendarDays,ChevronDown,LayoutDashboard,LogIn,LogOut,Menu,Settings2,ShieldCheck,UserRound,X} from 'lucide-react';
import {useAuth} from '../context/AuthContext';
import {useToast} from '../context/ToastContext';
import {api,localDateTime} from '../lib/api';
import {useLoad} from './Widgets';

export function Navbar(){
 const {user,logout}=useAuth(),navigate=useNavigate(),location=useLocation(),{flash}=useToast();
 const [mobile,setMobile]=useState(false),[drop,setDrop]=useState(false),[notificationDrop,setNotificationDrop]=useState(false);
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

 const links=[['/events','Discover'],['/announcements','Announcements'],['/departments','Departments'],['/calendar','Calendar'],['/archive','Archive']];
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
    {drop&&<div className="account-menu"><div className="menu-account"><strong>{user.name}</strong><small>{user.email}</small></div>{user.role!=='admin'&&<Link to="/dashboard" onClick={()=>setDrop(false)}><LayoutDashboard size={16}/> Dashboard</Link>}{(['admin','president','officer'].includes(user.role)||(user.role==='faculty'&&user.officer_permissions?.some(permission=>['events','announcements'].includes(permission))))&&<Link to="/organizer" onClick={()=>setDrop(false)}><Building2 size={16}/> Organizer</Link>}{user.role==='admin'&&<Link to="/admin" onClick={()=>setDrop(false)}><ShieldCheck size={16}/> Admin</Link>}<Link to="/profile" onClick={()=>setDrop(false)}><UserRound size={16}/> Profile</Link><Link to="/settings" onClick={()=>setDrop(false)}><Settings2 size={16}/> Settings</Link><button onClick={async()=>{await logout();setDrop(false);navigate('/');}}><LogOut size={16}/> Log out</button></div>}
   </div></>:<><Link to="/register" className="nav-register">Sign up</Link><Link to="/login" className="btn btn-login"><LogIn size={17}/> Log in</Link></>}
   <button className="mobile-toggle" onClick={()=>setMobile(!mobile)} aria-label="Toggle navigation">{mobile?<X size={24}/>:<Menu size={24}/>}</button>
  </div>
 </div></header>
}

export function Footer(){return <footer className="site-footer"><div className="container footer-grid"><div><div className="footer-brand"><CalendarDays size={23}/> CampusEvents Hub</div><p>One campus. Every connection. Discover events, stay updated, and make every SPC moment count.</p></div><div><strong>Explore</strong><Link to="/events">Upcoming Events</Link><Link to="/departments">Departments</Link><Link to="/calendar">Calendar</Link></div><div><strong>Help</strong><Link to="/">How it works</Link><Link to="/announcements">Announcements</Link><Link to="/departments">Campus communities</Link></div></div><div className="container footer-bottom">© {new Date().getFullYear()} CampusEvents Hub · St. Peter's College <span>Built for SPC students</span></div></footer>}
export function Layout({children}){return <><Navbar/><main>{children}</main><Footer/></>}
