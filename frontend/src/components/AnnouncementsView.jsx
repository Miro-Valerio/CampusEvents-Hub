import React from "react";
import {
  Bell,
  Megaphone,
  Pin,
  RefreshCw,
  Search,
  Users,
} from "lucide-react";
import { COURSES } from "../constants";

const DEFAULT_EMOJIS = ["👍", "❤️", "🎉", "👏", "😮", "😂"];

export default function AnnouncementsView({
  announcements,
  loading,
  course,
  setCourse,
  organizer,
  setOrganizer,
  organizers,
  search,
  setSearch,
  emojis,
  unreadCount,
  user,
  busyId,
  prettyDate,
  onMarkRead,
  onReact,
  onOpenOrganizer,
  onLogin,
  onReset,
  onReload,
}) {
  const reactionEmojis = emojis?.length ? emojis : DEFAULT_EMOJIS;
  const hasFilters = Boolean(course || organizer || search);

  return (
    <section className="events-section announcements-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">CAMPUS FEED</div>
          <h2>
            Announcements <span>that matter.</span>
          </h2>
          <p>
            Official updates from SPC organizations, pinned notices, and everything
            happening around campus.
          </p>
        </div>
        <div className="announcement-heading-side">
          <div className="results-count">
            <b>{announcements.length}</b>
            <span>announcements</span>
          </div>
          <button className="btn btn-outline" onClick={onReload} disabled={loading}>
            <RefreshCw size={15} /> Refresh
          </button>
        </div>
      </div>

      {user && unreadCount > 0 && (
        <div className="announcement-unread-banner">
          <Bell size={16} />
          <span>
            {unreadCount} unread announcement{unreadCount === 1 ? "" : "s"} in your feed
          </span>
        </div>
      )}

      <div className="filter-panel">
        <label className="search-box">
          <Search size={18} />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search announcements, organizations..."
          />
        </label>
        <label className="filter-field">
          Course
          <select value={course} onChange={(event) => setCourse(event.target.value)}>
            <option value="">All courses</option>
            {COURSES.map((name) => (
              <option key={name} value={name}>
                {name}
              </option>
            ))}
          </select>
        </label>
        {user?.course && (
          <button
            type="button"
            className={`reset-btn course-shortcut ${course === user.course ? "active" : ""}`}
            onClick={() => setCourse(course === user.course ? "" : user.course)}
          >
            My course
          </button>
        )}
        <label className="filter-field">
          Organization
          <select value={organizer} onChange={(event) => setOrganizer(event.target.value)}>
            <option value="">All organizations</option>
            {organizers.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        {hasFilters && (
          <button type="button" className="reset-btn" onClick={onReset}>
            Clear
          </button>
        )}
      </div>

      {loading ? (
        <div className="loading-line">
          <span /> Loading announcements...
        </div>
      ) : announcements.length === 0 ? (
        <div className="empty-state">
          <div className="empty-icon">
            <Megaphone size={27} />
          </div>
          <h3>No announcements found</h3>
          <p>
            {hasFilters
              ? "Try a different course or organization filter."
              : "Campus updates will appear here as soon as organizations post them."}
          </p>
          {hasFilters && (
            <button className="btn btn-primary" onClick={onReset}>
              Clear filters
            </button>
          )}
        </div>
      ) : (
        <div className="announcement-list">
          {announcements.map((announcement) => {
            const isUnread = Boolean(user) && !announcement.is_read;
            const isBusy = busyId === announcement.id;
            return (
              <article
                className={`announcement-card${isUnread ? " unread" : ""}${
                  announcement.pinned ? " pinned" : ""
                }`}
                key={announcement.id}
              >
                <div className="announcement-card-top">
                  <div className="announcement-badges">
                    {announcement.pinned && (
                      <span className="announcement-pin">
                        <Pin size={12} /> Pinned
                      </span>
                    )}
                    {isUnread && <span className="announcement-new">New</span>}
                    <span className="announcement-course">
                      {announcement.course || "Campus-wide"}
                    </span>
                  </div>
                  <span className="announcement-time">
                    {prettyDate(announcement.created_at)}
                  </span>
                </div>

                <h3>{announcement.title}</h3>
                <p className="announcement-body">{announcement.body}</p>

                <div className="announcement-footer">
                  <button
                    type="button"
                    className="announcement-org"
                    onClick={() => onOpenOrganizer(announcement.organizer?.id)}
                    disabled={!announcement.organizer?.id}
                  >
                    <Users size={14} />
                    {announcement.organizer?.name || "SPC Campus"}
                  </button>
                  <span className="announcement-author">
                    Posted by {announcement.author?.name || announcement.organizer?.name || "SPC"}
                  </span>
                  {isUnread && (
                    <button
                      type="button"
                      className="announcement-read-btn"
                      onClick={() => onMarkRead(announcement)}
                      disabled={isBusy}
                    >
                      Mark as read
                    </button>
                  )}
                </div>

                <div className="announcement-reactions">
                  {reactionEmojis.map((emoji) => {
                    const reaction = announcement.reactions?.find(
                      (item) => item.emoji === emoji,
                    );
                    return (
                      <button
                        type="button"
                        className={`reaction-btn${reaction?.reacted ? " active" : ""}`}
                        key={emoji}
                        onClick={() =>
                          user ? onReact(announcement, emoji) : onLogin()
                        }
                        disabled={isBusy}
                        title={
                          user
                            ? reaction?.reacted
                              ? `Remove ${emoji} reaction`
                              : `React with ${emoji}`
                            : "Log in to react"
                        }
                      >
                        <span>{emoji}</span>
                        {reaction?.count > 0 && <b>{reaction.count}</b>}
                      </button>
                    );
                  })}
                  {!user && <span className="reaction-hint">Log in to react</span>}
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}
