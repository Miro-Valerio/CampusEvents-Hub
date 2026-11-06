import React from "react";
import { CalendarDays, LogIn, LogOut, Loader2 } from "lucide-react";

export default function AppHeader({
  user,
  isLoggingOut,
  view,
  onNavigate,
  onLogin,
  onLogout,
}) {
  const link = (target, label, requiresLogin = false) => (
    <a
      className={view === target ? "active" : ""}
      href={`#${target}`}
      onClick={(e) => {
        e.preventDefault();
        onNavigate(target, requiresLogin);
      }}
    >
      {label}
    </a>
  );
  return (
    <header className="topbar">
      <a
        className="brand"
        href="#top"
        onClick={(e) => {
          e.preventDefault();
          onNavigate("discover");
        }}
      >
        <span className="brand-icon">
          <CalendarDays size={22} />
        </span>
        <span>
          CampusEvents<span className="brand-accent"> Hub</span>
          <small>SPC CAMPUS LIFE</small>
        </span>
      </a>
      <nav>
        {link("discover", "Discover")}
        {link("organizers", "Organizers")}
        {link("calendar", "Calendar")}
        {link("archive", "Archive")}
        {user?.role === "organizer" && link("dashboard", "Organizer Dashboard")}
        {user?.role === "student" && link("my-rsvps", "My RSVPs")}
        {user?.role === "student" && link("saved", "Bookmarks")}
        {user?.role === "student" && link("my-calendar", "My list")}
      </nav>
      <div className="account">
        {user ? (
          <>
            <span className="user-name">Hi, {user.name}</span>
            <button
              className="btn btn-outline"
              type="button"
              onClick={onLogout}
              disabled={isLoggingOut}
              aria-busy={isLoggingOut}
            >
              {isLoggingOut ? (
                <Loader2 size={16} className="auth-spinner" aria-hidden="true" />
              ) : (
                <LogOut size={16} />
              )}
              {isLoggingOut ? "Logging out..." : "Log out"}
            </button>
          </>
        ) : (
          <button className="btn btn-primary" onClick={onLogin}>
            <LogIn size={16} /> Log in
          </button>
        )}
      </div>
    </header>
  );
}
