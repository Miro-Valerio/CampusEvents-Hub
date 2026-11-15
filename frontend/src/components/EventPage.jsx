import React, { useEffect, useState } from "react";
import {
  ArrowLeft,
  Bookmark,
  CalendarDays,
  CalendarPlus,
  Download,
  Flag,
  MapPin,
  Printer,
  Users,
} from "lucide-react";
import { downloadIcs, isPast } from "../utils/ics";
import { printEvent } from "../utils/print";

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
  onReport,
  onLogin,
  onNotice,
  prettyDate,
  canRsvp = true,
  canReport = false,
}) {
  const [attendees, setAttendees] = useState(null);
  const [reportOpen, setReportOpen] = useState(false);
  const [reportReason, setReportReason] = useState("incorrect_information");
  const [reportDetails, setReportDetails] = useState("");
  const [reporting, setReporting] = useState(false);
  const [reportSubmitted, setReportSubmitted] = useState(false);
  const [reportError, setReportError] = useState("");
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
          {event.is_cancelled ? (
            <div className="event-cancelled-notice" role="status">
              This event has been cancelled by the organizer.
            </div>
          ) : past ? (
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
                  {isRsvped
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
          <button
            className="btn btn-outline full-btn event-print-button"
            onClick={() => {
              if (!printEvent(event)) onNotice?.("Allow pop-ups in your browser to print this event page.");
            }}
          >
            <Printer size={16} /> Print event page
          </button>
          {(canReport || onLogin) && (
            <button
              className="btn btn-outline full-btn"
              onClick={() => canReport ? setReportOpen((open) => !open) : onLogin?.()}
            >
              <Flag size={16} /> Report this event
            </button>
          )}
          {reportOpen && canReport && (
            reportSubmitted ? (
              <div className="event-report-success" role="status">
                Thanks. Your report was submitted for staff review.
              </div>
            ) : (
              <form
                className="event-report-form"
                onSubmit={async (submit) => {
                  submit.preventDefault();
                  setReporting(true);
                  setReportError("");
                  try {
                    await onReport(event.id, { reason: reportReason, details: reportDetails });
                    setReportSubmitted(true);
                  } catch (error) {
                    setReportError(error.message);
                  } finally {
                    setReporting(false);
                  }
                }}
              >
                <label>
                  Why are you reporting this event?
                  <select value={reportReason} onChange={(change) => setReportReason(change.target.value)}>
                    <option value="incorrect_information">Incorrect information</option>
                    <option value="inappropriate">Inappropriate content</option>
                    <option value="duplicate">Duplicate event</option>
                    <option value="other">Other concern</option>
                  </select>
                </label>
                <label>
                  Details (optional)
                  <textarea maxLength={1000} rows={3} value={reportDetails} onChange={(change) => setReportDetails(change.target.value)} />
                </label>
                {reportError && <p className="event-report-error" role="alert">{reportError}</p>}
                <button className="btn btn-primary full-btn" type="submit" disabled={reporting}>
                  Submit report
                </button>
              </form>
            )
          )}

          <div className="attendees-box">
            <b>Who’s going</b>
            {attendees && (attendees.failed ? (
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
            ))}
          </div>
        </aside>
      </div>
    </section>
  );
}
