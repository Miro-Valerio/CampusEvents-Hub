import React from "react";
import {
  ArrowUpRight,
  CalendarDays,
  CheckCircle2,
  MapPin,
  RefreshCw,
} from "lucide-react";

export default function CheckInHistoryView({
  events,
  loading,
  prettyDate,
  onOpen,
  onRefresh,
  onBrowse,
}) {
  return (
    <section className="events-section check-in-history">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">YOUR ATTENDANCE</div>
          <h2>
            Check-in <span>history.</span>
          </h2>
          <p>Past events you checked in to at the door.</p>
        </div>
        <div className="heading-actions">
          <button className="btn btn-outline" onClick={onRefresh}>
            <RefreshCw size={15} /> Refresh
          </button>
        </div>
      </div>

      {loading ? (
        <div className="loading-line">
          <span /> Loading your check-ins...
        </div>
      ) : events.length === 0 ? (
        <div className="empty-state">
          <div className="empty-icon">
            <CheckCircle2 size={27} />
          </div>
          <h3>No check-ins yet</h3>
          <p>
            When you check in at the door with an officer code, the event will appear
            here after it ends.
          </p>
          <button className="btn btn-primary" onClick={onBrowse}>
            Browse events
          </button>
        </div>
      ) : (
        <div className="check-in-history-grid">
          {events.map((event) => (
            <article className="check-in-history-card" key={event.id}>
              <div className="check-in-history-top">
                <span className="category-tag">{event.category}</span>
                <span className="check-in-history-badge">
                  <CheckCircle2 size={13} /> Checked in
                </span>
              </div>
              <h3>{event.title}</h3>
              <button
                className="organizer-line organizer-line-btn"
                onClick={() => onOpen(event)}
              >
                {event.organizer?.name || "SPC Organizer"}
              </button>
              <div className="check-in-history-meta">
                <span>
                  <CalendarDays size={15} />
                  {prettyDate(event.starts_at)}
                </span>
                <span>
                  <MapPin size={15} />
                  {event.venue}
                </span>
              </div>
              <div className="check-in-history-time">
                <b>Checked in</b>
                {prettyDate(event.checked_in_at)}
              </div>
              <button className="details-link" onClick={() => onOpen(event)}>
                View event <ArrowUpRight size={16} />
              </button>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
