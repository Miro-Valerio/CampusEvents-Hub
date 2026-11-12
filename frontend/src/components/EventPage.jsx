import React, { useEffect, useState } from "react";
import {
  ArrowLeft,
  Ban,
  Bookmark,
  CalendarDays,
  CalendarPlus,
  CheckCircle2,
  Download,
  Flag,
  KeyRound,
  MapPin,
  Printer,
  Users,
} from "lucide-react";
import { downloadIcs, isPast } from "../utils/ics";
import { printEventPoster } from "../utils/print";

export default function EventPage({
  event,
  isRsvped,
  isSaved,
  inCalendar,
  isBusy,
  loadAttendees,
  loadCheckInStatus,
  onCheckIn,
  onBack,
  onRsvp,
  onSave,
  onCalendar,
  onOrganizer,
  onReport,
  prettyDate,
  canRsvp = true,
}) {
  const [attendees, setAttendees] = useState(null);
  const [checkInStatus, setCheckInStatus] = useState({
    loading: false,
    checkedInAt: null,
    open: true,
  });
  const [checkInCode, setCheckInCode] = useState("");
  const [checkInBusy, setCheckInBusy] = useState(false);
  const [checkInMessage, setCheckInMessage] = useState("");
  const [checkInError, setCheckInError] = useState("");
  const past = isPast(event);
  const cancelled = Boolean(event.is_cancelled);
  const isFull = !cancelled && !isRsvped && event.registered_count >= event.capacity;

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

  useEffect(() => {
    let alive = true;
    setCheckInMessage("");
    setCheckInError("");
    setCheckInCode("");

    if (!isRsvped || cancelled || typeof loadCheckInStatus !== "function") {
      setCheckInStatus({ loading: false, checkedInAt: null, open: !past && !cancelled });
      return undefined;
    }

    setCheckInStatus((current) => ({ ...current, loading: true }));
    loadCheckInStatus(event.id)
      .then((data) => {
        if (!alive) return;
        setCheckInStatus({
          loading: false,
          checkedInAt: data.checked_in_at,
          open: data.check_in_open !== false,
        });
      })
      .catch(() => {
        if (alive) setCheckInStatus({ loading: false, checkedInAt: null, open: !past });
      });

    return () => {
      alive = false;
    };
  }, [event.id, isRsvped, past, cancelled]);

  const submitCheckIn = async (submit) => {
    submit.preventDefault();
    const code = checkInCode.trim();
    if (!code || checkInBusy || typeof onCheckIn !== "function") return;

    setCheckInBusy(true);
    setCheckInError("");
    setCheckInMessage("");
    try {
      const data = await onCheckIn(event, code);
      setCheckInStatus((current) => ({
        ...current,
        checkedInAt: data.checked_in_at,
        open: true,
      }));
      setCheckInMessage(data.message || "Check-in confirmed.");
      setCheckInCode("");
    } catch (err) {
      setCheckInError(
        err.response?.data?.message || "Could not check in. Please try again.",
      );
    } finally {
      setCheckInBusy(false);
    }
  };

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
      {cancelled && (
        <div className="event-cancelled-banner" role="status">
          <Ban size={20} />
          <div>
            <b>This event was cancelled</b>
            <span>
              {event.cancellation_reason ||
                "The organizer cancelled this event. RSVPs and check-in are closed."}
              {event.cancelled_at ? ` · ${prettyDate(event.cancelled_at)}` : ""}
            </span>
          </div>
        </div>
      )}
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
          {cancelled ? (
            <div className="full-notice cancelled-notice">
              <b>Event cancelled.</b> New RSVPs and door check-in are closed.
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
                  {isBusy
                    ? "Updating…"
                    : isRsvped
                      ? "Cancel RSVP"
                      : isFull
                        ? "Event full"
                        : "RSVP · Going"}
                </button>
              )}
              {isRsvped && (
                <div className="check-in-box">
                  <b>
                    <KeyRound size={15} /> Door check-in
                  </b>
                  {checkInStatus.loading ? (
                    <small>Checking your status…</small>
                  ) : checkInStatus.checkedInAt ? (
                    <div className="check-in-confirmed">
                      <CheckCircle2 size={16} />
                      Checked in {prettyDate(checkInStatus.checkedInAt)}
                    </div>
                  ) : checkInStatus.open ? (
                    <form className="check-in-form" onSubmit={submitCheckIn}>
                      <input
                        value={checkInCode}
                        onChange={(event) => setCheckInCode(event.target.value)}
                        maxLength={12}
                        placeholder="Officer code"
                        aria-label="Check-in code"
                        autoComplete="off"
                      />
                      <button
                        className="btn btn-primary"
                        type="submit"
                        disabled={checkInBusy || !checkInCode.trim()}
                      >
                        {checkInBusy ? "Checking…" : "Check in"}
                      </button>
                    </form>
                  ) : (
                    <small>Check-in is closed for this event.</small>
                  )}
                  {!checkInStatus.checkedInAt && checkInStatus.open && (
                    <small>Ask the event officer for the code at the door.</small>
                  )}
                  {checkInError && <small className="check-in-error">{checkInError}</small>}
                  {checkInMessage && (
                    <small className="check-in-success">{checkInMessage}</small>
                  )}
                </div>
              )}
            </>
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
          <button
            className="btn btn-outline full-btn"
            onClick={() => printEventPoster(event, prettyDate)}
          >
            <Printer size={16} /> Print event poster
          </button>
          {onReport && (
            <button className="btn btn-outline full-btn" onClick={() => onReport(event)}>
              <Flag size={16} /> Report this listing
            </button>
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
