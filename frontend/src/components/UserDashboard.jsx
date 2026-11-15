import React from "react";
import {
  ArrowUpRight,
  Bookmark,
  CalendarCheck,
  CalendarDays,
  Megaphone,
} from "lucide-react";

const shortcuts = [
  {
    view: "my-rsvps",
    title: "My RSVPs",
    description: "See the events you’re going to.",
    icon: CalendarCheck,
  },
  {
    view: "saved",
    title: "Saved events",
    description: "Pick up where you left off.",
    icon: Bookmark,
  },
  {
    view: "my-calendar",
    title: "My calendar",
    description: "Keep your campus plans together.",
    icon: CalendarDays,
  },
  {
    view: "announcements",
    title: "Announcements",
    description: "Catch up on campus updates.",
    icon: Megaphone,
  },
];

export default function UserDashboard({
  user,
  rsvpCount,
  savedCount,
  calendarCount,
  onNavigate,
}) {
  return (
    <section className="user-dashboard">
      <header className="user-dashboard-welcome">
        <div>
          <div className="eyebrow muted">YOUR CAMPUS DASHBOARD</div>
          <h1>
            Welcome back, <span>{user.name}</span>.
          </h1>
          <p>Your SPC plans, saved events, and campus updates—all in one place.</p>
        </div>
        <button className="btn btn-primary" onClick={() => onNavigate("discover")}>
          Explore events <ArrowUpRight size={17} />
        </button>
      </header>

      <div className="user-dashboard-stats" aria-label="Your campus activity">
        <article className="user-dashboard-stat">
          <span><CalendarCheck size={19} /></span>
          <div><strong>{rsvpCount}</strong><small>Upcoming RSVPs</small></div>
        </article>
        <article className="user-dashboard-stat">
          <span><Bookmark size={19} /></span>
          <div><strong>{savedCount}</strong><small>Saved events</small></div>
        </article>
        <article className="user-dashboard-stat">
          <span><CalendarDays size={19} /></span>
          <div><strong>{calendarCount}</strong><small>On your calendar</small></div>
        </article>
      </div>

      <div className="user-dashboard-section-heading">
        <div>
          <div className="eyebrow muted">MAKE IT YOURS</div>
          <h2>Your campus, <span>your way.</span></h2>
        </div>
      </div>
      <div className="user-dashboard-shortcuts">
        {shortcuts.map(({ view, title, description, icon: Icon }) => (
          <button
            className="user-dashboard-shortcut"
            key={view}
            onClick={() => onNavigate(view, true)}
          >
            <span className="user-dashboard-shortcut-icon"><Icon size={20} /></span>
            <span className="user-dashboard-shortcut-copy">
              <strong>{title}</strong>
              <small>{description}</small>
            </span>
            <ArrowUpRight className="user-dashboard-shortcut-arrow" size={17} />
          </button>
        ))}
      </div>
    </section>
  );
}
