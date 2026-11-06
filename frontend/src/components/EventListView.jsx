import React from "react";
import { Download, RefreshCw } from "lucide-react";
import EventCard from "./EventCard";

// Shared page for "My RSVPs", "Bookmarked events" and "My calendar list".
export default function EventListView({
  eyebrow,
  title,
  titleAccent,
  subtitle,
  emptyIcon,
  emptyTitle,
  emptyText,
  events,
  loading,
  cardProps,
  myRsvp = false,
  onRefresh,
  onBrowse,
  onDownloadAll,
}) {
  return (
    <section className="events-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">{eyebrow}</div>
          <h2>
            {title} <span>{titleAccent}</span>
          </h2>
          <p>{subtitle}</p>
        </div>
        <div className="heading-actions">
          {onDownloadAll && events.length > 0 && (
            <button className="btn btn-primary" onClick={onDownloadAll}>
              <Download size={15} /> Download all (.ics)
            </button>
          )}
          <button className="btn btn-outline" onClick={onRefresh}>
            <RefreshCw size={15} /> Refresh
          </button>
        </div>
      </div>
      {loading ? (
        <div className="loading-line">
          <span /> Loading...
        </div>
      ) : events.length === 0 ? (
        <div className="empty-state">
          <div className="empty-icon">{emptyIcon}</div>
          <h3>{emptyTitle}</h3>
          <p>{emptyText}</p>
          <button className="btn btn-primary" onClick={onBrowse}>
            Browse events
          </button>
        </div>
      ) : (
        <div className="event-grid">
          {events.map((event) => (
            <EventCard key={event.id} event={event} myRsvp={myRsvp} {...cardProps(event)} />
          ))}
        </div>
      )}
    </section>
  );
}
