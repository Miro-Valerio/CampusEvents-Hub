import React from 'react';
import {Link} from 'react-router-dom';
import {CalendarDays,MapPin,Users,ArrowUpRight,BookOpen} from 'lucide-react';
import {localDate,localTime} from '../lib/api';
import {StatusBadge} from './Widgets';
export function EventCard({event}){
 const count=event.attendee_count??0;
 return <article className="event-card">
  <Link className="event-card-media" to={`/events/${event.id}`} aria-label={`View ${event.title}`}>
   {event.poster_url?<img src={event.poster_url} alt={`${event.title} poster`} loading="lazy"/>:<div className="event-art"><BookOpen size={38}/><span>SPC CAMPUS EVENTS</span></div>}
   <span className="category-chip">{event.category}</span>
  </Link>
  <div className="event-card-body"><div className="event-card-date"><CalendarDays size={15}/>{localDate(event.starts_at)} · {localTime(event.starts_at)}</div>
   <h3><Link to={`/events/${event.id}`}>{event.title}</Link></h3>
   <p className="event-dept">{event.department?.name||'SPC Department'}</p>
   <div className="event-card-meta"><span><MapPin size={15}/>{event.venue}</span><span><Users size={15}/>{count} / {event.seat_limit}</span></div>
   <div className="event-card-bottom"><StatusBadge value={event.schedule_status||event.status}/><span className="muted-small">{Math.max(0,event.seat_limit-count)} seats left</span><Link to={`/events/${event.id}`} className="text-link">Details <ArrowUpRight size={16}/></Link></div>
  </div>
 </article>
}
