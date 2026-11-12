import React from "react";
import {
  ArrowUpRight,
  Bookmark,
  CalendarDays,
  CalendarPlus,
  Flag,
  Images,
  MapPin,
  Users,
} from "lucide-react";
import { isPast } from "../utils/ics";

export default function EventCard({
  event,
  prettyDate,
  isRsvped,
  isSaved = false,
  inCalendar = false,
  isBusy,
  onRsvp,
  onSave,
  onCalendar,
  onOpen,
  onOrganizer,
  onReport,
  myRsvp = false,
  canRsvp = true,
}) {
  const past = isPast(event);
  const cancelled = Boolean(event.is_cancelled);
  const isFull = !cancelled && !isRsvped && event.registered_count >= event.capacity;
  const poster = event.poster_url
    ? `linear-gradient(180deg, transparent 30%, rgba(11,28,55,.72)), url('${event.poster_url}')`
    : "linear-gradient(135deg, #123c76, #2f6fb5)";

  return (
    <article className={`event-card${cancelled ? " is-cancelled" : ""}`}>
      <div className="poster" style={{ backgroundImage: poster }}>
        <span className="category-tag">{event.category}</span>
        {!myRsvp && !past && (
          <div className="poster-actions">
            <button
              className={`save-btn ${isSaved ? "is-on" : ""}`}
              title={isSaved ? "Remove bookmark" : "Bookmark event"}
              aria-pressed={isSaved}
              onClick={() => onSave(event)}
            >
              <Bookmark size={17} fill={isSaved ? "currentColor" : "none"} />
            </button>
            <button
              className={`save-btn ${inCalendar ? "is-on" : ""}`}
              title={inCalendar ? "Remove from calendar list" : "Add to calendar list"}
              aria-pressed={inCalendar}
              onClick={() => onCalendar(event)}
            >
              <CalendarPlus size={17} />
            </button>
            {onReport && (
              <button
                className="save-btn"
                title="Report this listing"
                aria-label={`Report ${event.title}`}
                onClick={() => onReport(event)}
              >
                <Flag size={16} />
              </button>
            )}
          </div>
        )}
        <span className="poster-date">
          <CalendarDays size={14} />
          {new Date(event.starts_at).toLocaleDateString([], {
            month: "short",
            day: "numeric",
          })}
        </span>
        {cancelled ? (
          <span className="cancelled-badge">Cancelled</span>
        ) : (
          isFull && !past && <span className="full-badge">Full</span>
        )}
      </div>
      <div className="event-card-body">
        <button
          className="organizer-line organizer-line-btn"
          onClick={() => onOrganizer?.(event.organizer_id)}
        >
          {event.organizer?.name || "SPC Organizer"}
        </button>
        <h3>{event.title}</h3>
        <div className="event-meta">
          <span>
            <CalendarDays size={15} />
            {prettyDate(event.starts_at)}
          </span>
          <span>
            <MapPin size={15} />
            {event.venue}
          </span>
        </div>
        {myRsvp ? (
          <div className="rsvp-seat-number">
            Your seat: <strong>#{event.seat_number}</strong>
          </div>
        ) : null}
        <div className="seat-row">
          <div className="seat-copy">
            <Users size={15} />
            <span>
              <b>{event.registered_count}</b> of {event.capacity} {past ? "attended" : "going"}
            </span>
          </div>
          <div className="seat-track">
            <span
              style={{
                width: `${Math.min(100, (event.registered_count / event.capacity) * 100)}%`,
              }}
            />
          </div>
        </div>
        {cancelled && (
          <div className="cancelled-notice" role="status">
            This event was cancelled by the organizer.
          </div>
        )}
        {isFull && !past && !cancelled && (
          <div className="full-notice" role="status">
            This event is full — no seats left.
          </div>
        )}
        <button className="details-link" onClick={() => onOpen(event)}>
          {past ? (
            <>
              View photos <Images size={16} />
            </>
          ) : (
            <>
              View event details <ArrowUpRight size={16} />
            </>
          )}
        </button>
        {!past && !cancelled && canRsvp && (
          <button
            className={`btn ${myRsvp || isRsvped ? "btn-outline" : "btn-primary"} full-btn rsvp-button`}
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
      </div>
    </article>
  );
}
