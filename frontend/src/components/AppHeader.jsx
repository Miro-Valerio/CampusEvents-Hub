import React, { useEffect, useRef, useState } from "react";
import { CalendarDays, LogIn, LogOut, Loader2, Menu, Settings, ShieldCheck, X } from "lucide-react";
import NotificationBell from "./NotificationBell";

export default function AppHeader({
  user,
  authChecking,
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
  const hasOrganizerDashboard = ["organizer", "faculty"].includes(user?.role)
    && (user?.organizer || user?.organizations?.length);
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
        {user?.role === "admin" && link("admin-dashboard", "Admin Dashboard", true)}
        {hasOrganizerDashboard && link("dashboard", "Organizer Dashboard", true)}
        {user && user.role !== "admin" && !hasOrganizerDashboard && link("user-dashboard", "My Dashboard", true)}
        {link("discover", "Discover")}
        {link("announcements", "Announcements")}
        {link("organizers", "Department")}
        {link("calendar", "Calendar")}
        {link("archive", "Archive")}
      </nav>
      <div className="account" key={user ? `user-${user.id}` : "guest"}>
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
            {user.profile_photo_url && (
              <img className="account-avatar" src={user.profile_photo_url} alt="" />
            )}
            <span className="user-name">Hi, {user.name}</span>
          </>
        ) : (
          authChecking ? (
            <span className="user-name" role="status">Checking session...</span>
          ) : (
            <button className="btn btn-primary" onClick={onLogin}>
              <LogIn size={16} /> Log in
            </button>
          )
        )}
        {user && (
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
                {user.role === "admin" && (
                  <a
                    className={view === "admin-dashboard" ? "active" : ""}
                    href="#admin-dashboard"
                    onClick={(event) => {
                      event.preventDefault();
                      setMenuOpen(false);
                      onNavigate("admin-dashboard", true);
                    }}
                  >
                    <ShieldCheck size={16} /> Admin Dashboard
                  </a>
                )}
                {hasOrganizerDashboard && menuLink("dashboard", "Organizer Dashboard")}
                {user.role !== "admin" && !hasOrganizerDashboard && menuLink("user-dashboard", "My Dashboard")}
                {user.role === "student" && (
                  <>
                    {menuLink("my-rsvps", "My RSVPs")}
                    {menuLink("check-in-history", "Check-in history")}
                    {menuLink("saved", "Bookmarks")}
                    {menuLink("my-calendar", "My list")}
                  </>
                )}
                <a
                  className={view === "settings" ? "active" : ""}
                  href="#settings"
                  onClick={(event) => {
                    event.preventDefault();
                    setMenuOpen(false);
                    onNavigate("settings", true);
                  }}
                >
                  <Settings size={16} /> Profile &amp; settings
                </a>
                <button
                  type="button"
                  onClick={() => {
                    setMenuOpen(false);
                    onLogout();
                  }}
                  disabled={isLoggingOut}
                >
                  {isLoggingOut ? (
                    <Loader2 size={16} className="auth-spinner" aria-hidden="true" />
                  ) : (
                    <LogOut size={16} />
                  )}
                  {isLoggingOut ? "Logging out..." : "Log out"}
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </header>
  );
}
