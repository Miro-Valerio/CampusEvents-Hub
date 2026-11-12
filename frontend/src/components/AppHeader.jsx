import React from "react";
import { CalendarDays, LogIn, LogOut, Loader2, Settings } from "lucide-react";
import NotificationBell from "./NotificationBell";

const initials = (name) =>
  (name || "?")
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

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
        {link("announcements", "Announcements")}
        {link("organizers", "Organizers")}
        {link("calendar", "Calendar")}
        {link("archive", "Archive")}
        {user?.role === "organizer" && link("dashboard", "Organizer Dashboard")}
        {user?.role === "student" && link("my-rsvps", "My RSVPs")}
        {user?.role === "student" && link("saved", "Bookmarks")}
        {user?.role === "student" && link("my-calendar", "My list")}
        {user?.role === "student" && link("check-ins", "Check-ins")}
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
              onOpenSettings={() => onNavigate("settings", true)}
            />
            <a
              className={`profile-chip${view === "profile" ? " active" : ""}`}
              href="#profile"
              title="View and edit your profile"
              onClick={(e) => {
                e.preventDefault();
                onNavigate("profile", true);
              }}
            >
              <span className="profile-avatar profile-avatar-small" aria-hidden="true">
                <span>{initials(user.name)}</span>
                {user.photo_url && (
                  <img
                    src={user.photo_url}
                    alt=""
                    onError={(e) => {
                      e.currentTarget.style.display = "none";
                    }}
                  />
                )}
              </span>
              <span className="profile-chip-copy">
                <b>{user.name}</b>
                <small>
                  {user.role === "organizer" ? "Organizer" : user.course || "Student"}
                </small>
              </span>
            </a>
            <button
              className={`icon-nav-link${view === "settings" ? " active" : ""}`}
              type="button"
              onClick={() => onNavigate("settings", true)}
              title="Notification settings"
              aria-label="Notification settings"
            >
              <Settings size={17} />
            </button>
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
