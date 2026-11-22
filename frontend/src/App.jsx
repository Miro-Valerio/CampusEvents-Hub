import React from 'react';
import {Navigate,Route,Routes,useLocation} from 'react-router-dom';
import {Layout} from './components/Layout';
import {Loading} from './components/Widgets';
import {useAuth} from './context/AuthContext';
import Landing from './pages/Landing';
import {Login,Register,ForgotPassword,ResetPassword} from './pages/AuthPages';
import {EventDirectory,EventDetails,CalendarPage} from './pages/Events';
import {Departments,DepartmentDetails} from './pages/Departments';
import {StudentDashboard,ActivityPage,Notifications,Profile,Settings} from './pages/Student';
import Announcements from './pages/Announcements';
import Organizer from './pages/Organizer';
import Admin from './pages/Admin';
function Guard({children,roles}){const {user,loading}=useAuth(),location=useLocation();if(loading)return <div className="container page-section"><Loading/></div>;if(!user)return <Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace/>;if(roles&&!roles.includes(user.role))return <Navigate to="/dashboard" replace/>;return children;}
function OrganizerGuard(){const {user,loading}=useAuth(),location=useLocation();if(loading)return <div className="container page-section"><Loading/></div>;if(!user)return <Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace/>;const allowed=['admin','president','officer'].includes(user.role)||(user.role==='faculty'&&user.officer_permissions?.some(permission=>['events','announcements'].includes(permission)));return allowed?<Organizer/>:<Navigate to="/dashboard" replace/>;}
function Guest({children}){const {user,loading}=useAuth();if(loading)return <div className="container page-section"><Loading/></div>;return user?<Navigate to="/dashboard" replace/>:children;}
function NotFound(){return <div className="container page-section"><div className="state-card"><h1>Page not found</h1><p>The page you're looking for doesn't exist.</p><a className="btn btn-primary" href="/">Go home</a></div></div>}
export default function App(){return <Layout><Routes>
 <Route path="/" element={<Landing/>}/>
 <Route path="/login" element={<Guest><Login/></Guest>}/>
 <Route path="/register" element={<Guest><Register/></Guest>}/>
 <Route path="/forgot-password" element={<Guest><ForgotPassword/></Guest>}/>
 <Route path="/reset-password" element={<ResetPassword/>}/>
 <Route path="/events" element={<EventDirectory/>}/>
 <Route path="/events/:id" element={<EventDetails/>}/>
 <Route path="/archive" element={<EventDirectory archive/>}/>
 <Route path="/calendar" element={<CalendarPage/>}/>
 <Route path="/departments" element={<Departments/>}/>
 <Route path="/departments/:id" element={<DepartmentDetails/>}/>
 <Route path="/dashboard" element={<Guard><StudentDashboard/></Guard>}/>
 <Route path="/my-events" element={<Guard><ActivityPage type="rsvps"/></Guard>}/>
 <Route path="/bookmarks" element={<Guard><ActivityPage type="bookmarks"/></Guard>}/>
 <Route path="/my-calendar" element={<Guard><ActivityPage type="calendar"/></Guard>}/>
 <Route path="/attendance" element={<Guard><ActivityPage type="attendance"/></Guard>}/>
 <Route path="/notifications" element={<Guard><Notifications/></Guard>}/>
 <Route path="/announcements" element={<Guard><Announcements/></Guard>}/>
 <Route path="/profile" element={<Guard><Profile/></Guard>}/>
 <Route path="/settings" element={<Guard><Settings/></Guard>}/>
 <Route path="/organizer" element={<OrganizerGuard/>}/>
 <Route path="/admin" element={<Guard roles={['admin']}><Admin/></Guard>}/>
 <Route path="*" element={<NotFound/>}/>
 </Routes></Layout>}
