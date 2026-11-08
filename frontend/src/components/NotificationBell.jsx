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
            <span>You are all caught up.</span>
            <small>No new campus announcements or event updates.</small>
            </div>
          ) : (
          <>
            {unreadCount === 0 && (
              <div className="notification-caught-up" role="status">You are all caught up.</div>
            )}
            <div className="notification-list">
              {notifications.map((notification) => {
                const data = notification.data || {};
                const message =
                  data.kind === "event_time_changed"
                    ? `New start: ${prettyDate(data.starts_at)}`
                    : data.kind === "event_reminder"
                      ? `Starts at ${prettyDate(data.starts_at)}`
                      : data.message;

                return (
                  <button
                    type="button"
                    className={`notification-item${notification.read_at ? "" : " unread"}`}
                    key={notification.id}
                    onClick={() => onOpenAnnouncements(notification)}
                  >
                    <span className="notification-dot" />
                    <span className="notification-copy">
                      <b>{data.title || "Campus announcement"}</b>
                      <p>{message}</p>
                      <small>
                        {data.organizer ||
                          (data.kind?.startsWith("event_") ? "Campus event" : "SPC Campus")}
                        {data.course
                          ? ` · ${data.course}`
                          : data.kind?.startsWith("event_")
                            ? ""
                            : " · Campus-wide"}
                        {notification.created_at
                          ? ` · ${prettyDate(notification.created_at)}`
                          : ""}
                      </small>
                    </span>
                  </button>
                );
              })}
            </div>
          </>
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
