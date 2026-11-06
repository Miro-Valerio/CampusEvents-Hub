import React, { useEffect, useRef, useState } from "react";
import { CalendarDays, LogIn, LogOut, Loader2, Menu, X } from "lucide-react";
import NotificationBell from "./NotificationBell";

export default function AppHeader({
  user,
  isLoggingOut,
  view,
  notifications,
  notificationsUnread,
  notificationsOpen,
  notificationsLoading,
  prettyDate,
  onNavigate,
  onLogin,
  onLogout,
  onToggleNotifications,
  onCloseNotifications,
  onMarkAllNotificationsRead,
  onOpenNotifications,
}) {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    if (!menuOpen) return undefined;
    const closeOnOutsideClick = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) setMenuOpen(false);
    };
    const closeOnEscape = (event) => {
      if (event.key === "Escape") setMenuOpen(false);
    };
    document.addEventListener("mousedown", closeOnOutsideClick);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("mousedown", closeOnOutsideClick);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [menuOpen]);

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
  const menuLink = (target, label, requiresLogin = true) => (
    <a
      className={view === target ? "active" : ""}
      href={`#${target}`}
      onClick={(event) => {
        event.preventDefault();
        setMenuOpen(false);
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
        {link("announcements", "Announcements")}
        {link("organizers", "Organizers")}
        {link("calendar", "Calendar")}
      </nav>
      <div className="account">
        {user ? (
          <>
            <NotificationBell
              notifications={notifications}
              unreadCount={notificationsUnread}
              open={notificationsOpen}
              loading={notificationsLoading}
              prettyDate={prettyDate}
              onToggle={onToggleNotifications}
              onClose={onCloseNotifications}
              onMarkAllRead={onMarkAllNotificationsRead}
              onOpenAnnouncements={onOpenNotifications}
            />
            <span className="user-name">Hi, {user.name}</span>
          </>
        ) : (
          <button className="btn btn-primary" onClick={onLogin}>
            <LogIn size={16} /> Log in
          </button>
        )}
        <div className="mobile-menu-wrap" ref={menuRef}>
          <button
            className="mobile-menu-toggle"
            type="button"
            aria-label={menuOpen ? "Close navigation menu" : "Open navigation menu"}
            aria-expanded={menuOpen}
            aria-controls="mobile-navigation-menu"
            onClick={() => setMenuOpen((open) => !open)}
          >
            {menuOpen ? <X size={21} /> : <Menu size={21} />}
          </button>
          {menuOpen && (
            <div className="mobile-navigation-menu" id="mobile-navigation-menu">
              <div className="mobile-menu-primary">
                {menuLink("discover", "Discover", false)}
                {menuLink("announcements", "Announcements", false)}
                {menuLink("organizers", "Organizers", false)}
                {menuLink("calendar", "Calendar", false)}
              </div>
              {menuLink("archive", "Archive", false)}
              {user?.role === "student" && (
                <>
                  {menuLink("my-rsvps", "My RSVPs")}
                  {menuLink("check-in-history", "Check-in history")}
                  {menuLink("saved", "Bookmarks")}
                  {menuLink("my-calendar", "My list")}
                </>
              )}
              {user?.role === "organizer" && menuLink("dashboard", "Organizer Dashboard")}
              {user && (
                <button type="button" onClick={() => {
                  setMenuOpen(false);
                  onLogout();
                }} disabled={isLoggingOut}>
                  {isLoggingOut ? (
                    <Loader2 size={16} className="auth-spinner" aria-hidden="true" />
                  ) : (
                    <LogOut size={16} />
                  )}
                  {isLoggingOut ? "Logging out..." : "Log out"}
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
