import React, { useEffect, useRef } from "react";
import { Bell, CheckCheck } from "lucide-react";

export default function NotificationBell({
  notifications,
  unreadCount,
  open,
  loading,
  prettyDate,
  onToggle,
  onClose,
  onMarkAllRead,
  onOpenAnnouncements,
}) {
  const wrapRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const handlePointer = (event) => {
      if (wrapRef.current && !wrapRef.current.contains(event.target)) onClose();
    };
    const handleKey = (event) => {
      if (event.key === "Escape") onClose();
    };
    document.addEventListener("mousedown", handlePointer);
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("mousedown", handlePointer);
      document.removeEventListener("keydown", handleKey);
    };
  }, [open, onClose]);

  return (
    <div className="notification-wrap" ref={wrapRef}>
      <button
        type="button"
        className={`notification-bell${open ? " open" : ""}`}
        onClick={onToggle}
        aria-label={
          unreadCount > 0
            ? `Notifications, ${unreadCount} unread`
            : "Notifications"
        }
        aria-expanded={open}
      >
        <Bell size={18} />
        {unreadCount > 0 && (
          <span className="notification-badge">{unreadCount > 9 ? "9+" : unreadCount}</span>
        )}
      </button>

      {open && (
        <div className="notification-panel">
          <div className="notification-panel-head">
            <strong>Notifications</strong>
            {unreadCount > 0 && (
              <button type="button" onClick={onMarkAllRead}>
                <CheckCheck size={14} /> Mark all read
              </button>
            )}
          </div>

          {loading ? (
            <div className="notification-empty">Loading notifications...</div>
          ) : notifications.length === 0 ? (
            <div className="notification-empty">
              <Bell size={20} />
              <span>No notifications yet.</span>
              <small>New announcements will show up here.</small>
            </div>
          ) : (
            <div className="notification-list">
              {notifications.map((notification) => (
                <button
                  type="button"
                  className={`notification-item${notification.read_at ? "" : " unread"}`}
                  key={notification.id}
                  onClick={() => onOpenAnnouncements(notification)}
                >
                  <span className="notification-dot" />
                  <span className="notification-copy">
                    <b>{notification.data?.title || "Campus announcement"}</b>
                    <p>{notification.data?.message}</p>
                    <small>
                      {notification.data?.organizer || "SPC Campus"}
                      {notification.data?.course
                        ? ` · ${notification.data.course}`
                        : " · Campus-wide"}
                      {notification.created_at
                        ? ` · ${prettyDate(notification.created_at)}`
                        : ""}
                    </small>
                  </span>
                </button>
              ))}
            </div>
          )}

          <button
            type="button"
            className="notification-view-all"
            onClick={() => onOpenAnnouncements()}
          >
            View campus announcements
          </button>
        </div>
      )}
    </div>
  );
}
