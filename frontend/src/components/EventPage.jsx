import React, { useEffect, useState } from "react";
import {
  ArrowLeft,
  Bookmark,
  CalendarDays,
  CalendarPlus,
  Download,
  MapPin,
  Users,
} from "lucide-react";
import { downloadIcs, isPast } from "../utils/ics";

export default function EventPage({
  event,
  isRsvped,
  isSaved,
  inCalendar,
  isBusy,
  loadAttendees,
  onBack,
  onRsvp,
  onSave,
  onCalendar,
  onOrganizer,
  prettyDate,
  canRsvp = true,
}) {
  const [attendees, setAttendees] = useState(null);
  const past = isPast(event);
  const isFull = !isRsvped && event.registered_count >= event.capacity;

  // Refresh the "who else is going" list whenever the seat count changes.
  useEffect(() => {
    let alive = true;
    setAttendees(null);
    loadAttendees(event.id)
      .then((data) => alive && setAttendees(data))
      .catch(() => alive && setAttendees({ data: [], baseline_count: 0, total: 0, failed: true }));
    return () => {
      alive = false;
    };
  }, [event.id, event.registered_count]);

  const initials = (name) =>
    name
      .split(" ")
      .map((p) => p[0])
      .join("")
      .slice(0, 2)
      .toUpperCase();

  return (
    <section className="events-section event-page">
      <button className="back-link" onClick={onBack}>
        <ArrowLeft size={16} /> Back
      </button>
      <div
        className="event-hero"
        style={{
          backgroundImage: event.poster_url
            ? `linear-gradient(0deg, rgba(12,31,61,.6), transparent), url('${event.poster_url}')`
            : "linear-gradient(135deg, #123c76, #2f6fb5)",
        }}
      >
        <span className="category-tag">{event.category}</span>
      </div>
      <div className="event-page-grid">
        <div className="event-page-main">
          <h2>{event.title}</h2>
          <p className="event-desc">{event.description}</p>
          <div className="detail-row">
            <CalendarDays />
            <span>
              <b>Date &amp; time</b>
              {prettyDate(event.starts_at)}
              {event.ends_at ? ` – ${prettyDate(event.ends_at)}` : ""}
            </span>
          </div>
          <div className="detail-row">
            <MapPin />
            <span>
              <b>Venue</b>
              {event.venue}
            </span>
          </div>
          <div className="detail-row">
            <Users />
            <span>
              <b>Seats</b>
              {event.registered_count} of {event.capacity} {past ? "attended" : "going"}
            </span>
          </div>
          <div className="rules-box">
            <b>Event rules</b>
            <p>
              {event.rules ||
                "Please follow organizer instructions and bring your student ID."}
            </p>
          </div>

          {event.photos?.length > 0 && (
            <div className="photo-section">
              <h3>Event photos</h3>
              <div className="photo-grid">
                {event.photos.map((photo) => (
                  <a key={photo.id} href={photo.url} target="_blank" rel="noreferrer">
                    <img src={photo.url} alt={photo.caption || event.title} loading="lazy" />
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>

        <aside className="event-page-side">
          {past ? (
            <div className="full-notice">This event has ended.</div>
          ) : (
            <>
              {isFull && (
                <div className="full-notice" role="status">
                  This event is full — no seats left.
                </div>
              )}
              {canRsvp && (
                <button
                  className={`btn ${isRsvped ? "btn-outline" : "btn-primary"} full-btn rsvp-button`}
                  disabled={isBusy || isFull}
                  onClick={() => onRsvp(event)}
                >
                  {isBusy
                    ? "Updating…"
                    : isRsvped
                      ? "Cancel RSVP"
                      : isFull
                        ? "Event full"
                        : "RSVP · Going"}
                </button>
              )}
              <button className="btn btn-outline full-btn" onClick={() => onSave(event)}>
                <Bookmark size={16} fill={isSaved ? "currentColor" : "none"} />
                {isSaved ? "Bookmarked" : "Bookmark this event"}
              </button>
              <button className="btn btn-outline full-btn" onClick={() => onCalendar(event)}>
                <CalendarPlus size={16} />
                {inCalendar ? "In my calendar list" : "Add to my calendar list"}
              </button>
              <button
                className="btn btn-outline full-btn"
                onClick={() => downloadIcs([event], `${event.title}.ics`)}
              >
                <Download size={16} /> Download .ics
              </button>
            </>
          )}
          <button className="organizer-link modal-org" onClick={() => onOrganizer(event.organizer_id)}>
            View organizer: {event.organizer?.name}
          </button>

          <div className="attendees-box">
            <b>Who’s going</b>
            {!attendees ? (
              <small>Loading…</small>
            ) : attendees.failed ? (
              <small>Couldn’t load the guest list.</small>
            ) : (
              <>
                <div className="attendee-list">
                  {attendees.data.map((name, i) => (
                    <span className="attendee" key={`${name}-${i}`} title={name}>
                      <i>{initials(name)}</i>
                      {name}
                    </span>
                  ))}
                </div>
                {attendees.baseline_count > 0 && (
                  <small>
                    + {attendees.baseline_count} other students ({attendees.total} total)
                  </small>
                )}
                {attendees.total === 0 && <small>Be the first to RSVP!</small>}
              </>
            )}
          </div>
        </aside>
      </div>
    </section>
  );
}
