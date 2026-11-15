import React from "react";
import { CalendarDays, MapPin } from "lucide-react";
import useDelayedLoading from "../utils/useDelayedLoading";

export default function CheckInHistoryView({ checkIns, loading, prettyDate, onRefresh }) {
  const showLoading = useDelayedLoading(loading);

  return (
    <section className="events-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">YOUR CAMPUS MEMORIES</div>
          <h2>
            Check-in <span>history.</span>
          </h2>
          <p>Past events you attended and checked in to.</p>
        </div>
        <button className="btn btn-outline" onClick={onRefresh}>
          Refresh
        </button>
      </div>
      {showLoading && checkIns.length === 0 ? (
        <div className="loading-line"><span /> Loading check-in history...</div>
      ) : loading && checkIns.length === 0 ? null : checkIns.length === 0 ? (
        <div className="empty-state">
          <div className="empty-icon"><CalendarDays size={27} /></div>
          <h3>No check-ins yet</h3>
          <p>Events you check in to will appear here after they end.</p>
        </div>
      ) : (
        <div className="event-grid">
          {checkIns.map(({ event, checked_in_at, seat_number }) => (
            <article className="event-card" key={`${event.id}-${checked_in_at}`}>
              <div className="event-card-body">
                <span className="eyebrow muted">{event.organizer?.name || "SPC Organizer"}</span>
                <h3>{event.title}</h3>
                <div className="event-meta">
                  <span><CalendarDays size={15} /> Event: {prettyDate(event.starts_at)}</span>
                  <span><MapPin size={15} /> {event.venue}</span>
                </div>
                <p className="rsvp-seat-number">
                  Checked in: <strong>{prettyDate(checked_in_at)}</strong>
                  {seat_number ? ` · Seat #${seat_number}` : ""}
                </p>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
