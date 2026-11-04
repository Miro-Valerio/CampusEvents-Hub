import React, { useEffect, useState } from "react";
import {
  CalendarDays,
  Download,
  ImagePlus,
  Megaphone,
  Pencil,
  Pin,
  PinOff,
  Plus,
  RefreshCw,
  Save,
  Trash2,
  Users,
  X,
} from "lucide-react";
import { COURSES } from "../constants";

const emptyEvent = () => {
  const start = new Date(Date.now() + 24 * 60 * 60 * 1000);
  start.setMinutes(0, 0, 0);
  const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);
  return {
    title: "",
    description: "",
    poster_url: "",
    venue: "",
    starts_at: toLocalInput(start),
    ends_at: toLocalInput(end),
    category: "",
    rules: "",
    capacity: 100,
  };
};

function toLocalInput(value) {
  const date = value instanceof Date ? value : new Date(value);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

function eventToForm(event) {
  return {
    title: event.title,
    description: event.description,
    poster_url: event.poster_url || "",
    venue: event.venue,
    starts_at: toLocalInput(event.starts_at),
    ends_at: event.ends_at ? toLocalInput(event.ends_at) : "",
    category: event.category,
    rules: event.rules || "",
    capacity: event.capacity,
  };
}

const emptyAnnouncement = () => ({
  title: "",
  body: "",
  course: "",
  pinned: false,
});

function errorMessage(error) {
  return (
    error.response?.data?.message ||
    Object.values(error.response?.data?.errors || {}).flat()[0] ||
    "The request could not be completed. Please try again."
  );
}

export default function OrganizerDashboard({
  http,
  organizer,
  onProfileChange,
  onNotice,
}) {
  const [events, setEvents] = useState([]);
  const [announcements, setAnnouncements] = useState([]);
  const [announcementForm, setAnnouncementForm] = useState(emptyAnnouncement);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [dashboardError, setDashboardError] = useState(null);
  const [validationMessages, setValidationMessages] = useState([]);
  const [busy, setBusy] = useState("");
  const [editingId, setEditingId] = useState(null);
  const [eventForm, setEventForm] = useState(emptyEvent);
  const [profileForm, setProfileForm] = useState({
    name: organizer?.name || "",
    description: organizer?.description || "",
  });
  const [attendees, setAttendees] = useState({});
  const [photoEventId, setPhotoEventId] = useState(null);
  const [photoForm, setPhotoForm] = useState({ url: "", caption: "" });

  const loadDashboard = async () => {
    setLoading(true);
    setLoadFailed(false);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      const [eventResponse, statsResponse, announcementResponse] = await Promise.all([
        http.get("/organizer/events"),
        http.get("/organizer/stats"),
        http.get("/organizer/announcements"),
      ]);
      setEvents(eventResponse.data.data || []);
      setStats(statsResponse.data);
      setAnnouncements(announcementResponse.data.data || []);
    } catch (error) {
      setLoadFailed(true);
      setDashboardError({ message: errorMessage(error), retry: loadDashboard });
    } finally {
      setLoading(false);
    }
  };

  const reportError = (error, retry) => {
    setValidationMessages(Object.values(error.response?.data?.errors || {}).flat());
    setDashboardError({ message: errorMessage(error), retry });
  };

  useEffect(() => {
    loadDashboard();
  }, []);

  const startEdit = (event) => {
    setEditingId(event.id);
    setEventForm(eventToForm(event));
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const saveEvent = async (event) => {
    event?.preventDefault();
    setBusy("event");
    setDashboardError(null);
    setValidationMessages([]);
    const payload = {
      ...eventForm,
      capacity: Number(eventForm.capacity),
      ends_at: eventForm.ends_at || null,
    };
    try {
      if (editingId) {
        await http.put(`/organizer/events/${editingId}`, payload);
        onNotice("Event updated.");
      } else {
        await http.post("/organizer/events", payload);
        onNotice("Event created.");
      }
      setEditingId(null);
      setEventForm(emptyEvent());
      await loadDashboard();
    } catch (error) {
      reportError(error, () => saveEvent());
    } finally {
      setBusy("");
    }
  };

  const deleteEvent = async (event) => {
    if (!window.confirm(`Delete "${event.title}" and its related RSVPs?`)) return;
    setBusy(`delete-${event.id}`);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      await http.delete(`/organizer/events/${event.id}`);
      onNotice("Event deleted.");
      await loadDashboard();
    } catch (error) {
      reportError(error, () => deleteEvent(event));
    } finally {
      setBusy("");
    }
  };

  const saveAnnouncement = async (event) => {
    event?.preventDefault();
    setBusy("announcement");
    setDashboardError(null);
    setValidationMessages([]);
    try {
      await http.post("/organizer/announcements", {
        ...announcementForm,
        course: announcementForm.course || null,
        pinned: Boolean(announcementForm.pinned),
      });
      setAnnouncementForm(emptyAnnouncement());
      onNotice("Announcement posted to the campus feed.");
      await loadDashboard();
    } catch (error) {
      reportError(error, () => saveAnnouncement());
    } finally {
      setBusy("");
    }
  };

  const toggleAnnouncementPin = async (announcement) => {
    setBusy(`announcement-pin-${announcement.id}`);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      const { data } = await http.post(
        `/organizer/announcements/${announcement.id}/pin`,
      );
      onNotice(data.message);
      await loadDashboard();
    } catch (error) {
      reportError(error, () => toggleAnnouncementPin(announcement));
    } finally {
      setBusy("");
    }
  };

  const deleteAnnouncement = async (announcement) => {
    if (!window.confirm(`Delete "${announcement.title}" from the campus feed?`)) return;
    setBusy(`announcement-delete-${announcement.id}`);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      await http.delete(`/organizer/announcements/${announcement.id}`);
      onNotice("Announcement deleted.");
      await loadDashboard();
    } catch (error) {
      reportError(error, () => deleteAnnouncement(announcement));
    } finally {
      setBusy("");
    }
  };

  const saveProfile = async (event) => {
    event?.preventDefault();
    setBusy("profile");
    setDashboardError(null);
    setValidationMessages([]);
    try {
      const { data } = await http.put("/organizer/profile", profileForm);
      onProfileChange(data);
      onNotice("Organization profile updated.");
    } catch (error) {
      reportError(error, () => saveProfile());
    } finally {
      setBusy("");
    }
  };

  const toggleAttendees = async (eventId) => {
    if (attendees[eventId]) {
      setAttendees((current) => {
        const next = { ...current };
        delete next[eventId];
        return next;
      });
      return;
    }
    setBusy(`attendees-${eventId}`);
    try {
      const { data } = await http.get(`/organizer/events/${eventId}/attendees`);
      setAttendees((current) => ({ ...current, [eventId]: data.data || [] }));
    } catch (error) {
      reportError(error, () => toggleAttendees(eventId));
    } finally {
      setBusy("");
    }
  };

  const addPhoto = async (event) => {
    setBusy(`photo-${event.id}`);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      await http.post(`/organizer/events/${event.id}/photos`, photoForm);
      setPhotoForm({ url: "", caption: "" });
      setPhotoEventId(null);
      onNotice("Event photo added.");
      await loadDashboard();
    } catch (error) {
      reportError(error, () => addPhoto(event));
    } finally {
      setBusy("");
    }
  };

  const deletePhoto = async (photoId) => {
    setBusy(`photo-delete-${photoId}`);
    setDashboardError(null);
    setValidationMessages([]);
    try {
      await http.delete(`/organizer/photos/${photoId}`);
      onNotice("Event photo deleted.");
      await loadDashboard();
    } catch (error) {
      reportError(error, () => deletePhoto(photoId));
    } finally {
      setBusy("");
    }
  };

  const downloadAttendees = (eventId, eventTitle) => {
    const rows = [
      ["Name", "Email", "Seat number", "Status"],
      ...attendees[eventId].map((attendee) => [
        attendee.name,
        attendee.email,
        attendee.seat_number,
        attendee.status,
      ]),
    ];
    const csv = rows
      .map((row) =>
        row
          .map((value) => {
            const text = String(value ?? "").replace(/^[\s]*[=+\-@]/, "'$&");
            return `"${text.replaceAll('"', '""')}"`;
          })
          .join(","),
      )
      .join("\r\n");
    const url = URL.createObjectURL(new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `${eventTitle.replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "")}-attendees.csv`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
  };

  return (
    <section className="organizer-dashboard">
      {dashboardError && (
        <div className="dashboard-error" role="alert">
          <div>
            <span>{dashboardError.message}</span>
            {validationMessages.length > 0 && (
              <ul className="dashboard-validation-errors">
                {validationMessages.map((message, index) => (
                  <li key={`${message}-${index}`}>{message}</li>
                ))}
              </ul>
            )}
          </div>
          {dashboardError.retry && (
            <button className="btn btn-outline" onClick={dashboardError.retry}>
              <RefreshCw size={15} /> Retry
            </button>
          )}
        </div>
      )}
      <div className="dashboard-heading">
        <div>
          <div className="eyebrow muted">ORGANIZER TOOLS</div>
          <h1>
            Your event <span>dashboard.</span>
          </h1>
          <p>Manage {organizer?.name || "your organization"} events and attendees.</p>
        </div>
        <button className="btn btn-outline" onClick={loadDashboard} disabled={loading}>
          <RefreshCw size={16} /> Refresh
        </button>
      </div>

      <div className="dashboard-stats">
        {[
          ["Events", stats?.total_events, <CalendarDays size={19} />],
          ["Upcoming", stats?.upcoming_events, <CalendarDays size={19} />],
          ["Going RSVPs", stats?.total_rsvps, <Users size={19} />],
          ["Seats filled", stats?.seats_filled, <Users size={19} />],
        ].map(([label, value, icon]) => (
          <article className="dashboard-stat" key={label}>
            <span className="dashboard-stat-icon">{icon}</span>
            <div>
              <strong>{value ?? "—"}</strong>
              <small>{label}</small>
            </div>
          </article>
        ))}
      </div>

      <div className="dashboard-columns">
        <form className="dashboard-panel dashboard-profile" onSubmit={saveProfile}>
          <div className="dashboard-panel-heading">
            <div>
              <h2>Organization profile</h2>
              <p>Shown on your public organizer page.</p>
            </div>
          </div>
          <label>
            Organization name
            <input
              required
              maxLength="190"
              value={profileForm.name}
              onChange={(event) =>
                setProfileForm((current) => ({ ...current, name: event.target.value }))
              }
            />
          </label>
          <label>
            Description
            <textarea
              required
              maxLength="5000"
              rows="4"
              value={profileForm.description || ""}
              onChange={(event) =>
                setProfileForm((current) => ({
                  ...current,
                  description: event.target.value,
                }))
              }
            />
          </label>
          <button className="btn btn-primary" type="submit" disabled={busy === "profile"}>
            <Save size={15} /> {busy === "profile" ? "Saving..." : "Save profile"}
          </button>
        </form>

        <form className="dashboard-panel dashboard-event-form" onSubmit={saveEvent}>
          <div className="dashboard-panel-heading">
            <div>
              <h2>{editingId ? "Edit event" : "Create an event"}</h2>
              <p>Publish an event for students to discover and RSVP.</p>
            </div>
            {editingId && (
              <button
                className="icon-button"
                type="button"
                aria-label="Cancel editing"
                onClick={() => {
                  setEditingId(null);
                  setEventForm(emptyEvent());
                }}
              >
                <X size={18} />
              </button>
            )}
          </div>
          <div className="dashboard-form-grid">
            <label className="dashboard-wide">
              Event title
              <input
                required
                maxLength="190"
                value={eventForm.title}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, title: event.target.value }))
                }
              />
            </label>
            <label>
              Category
              <input
                required
                maxLength="100"
                value={eventForm.category}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, category: event.target.value }))
                }
              />
            </label>
            <label>
              Venue
              <input
                required
                maxLength="190"
                value={eventForm.venue}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, venue: event.target.value }))
                }
              />
            </label>
            <label>
              Starts
              <input
                required
                type="datetime-local"
                value={eventForm.starts_at}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, starts_at: event.target.value }))
                }
              />
            </label>
            <label>
              Ends
              <input
                type="datetime-local"
                value={eventForm.ends_at}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, ends_at: event.target.value }))
                }
              />
            </label>
            <label>
              Capacity
              <input
                required
                type="number"
                min="1"
                value={eventForm.capacity}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, capacity: event.target.value }))
                }
              />
            </label>
            <label>
              Poster URL
              <input
                type="url"
                value={eventForm.poster_url}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, poster_url: event.target.value }))
                }
              />
            </label>
            <label className="dashboard-wide">
              Description
              <textarea
                required
                rows="3"
                value={eventForm.description}
                onChange={(event) =>
                  setEventForm((current) => ({
                    ...current,
                    description: event.target.value,
                  }))
                }
              />
            </label>
            <label className="dashboard-wide">
              Rules (optional)
              <textarea
                rows="2"
                value={eventForm.rules}
                onChange={(event) =>
                  setEventForm((current) => ({ ...current, rules: event.target.value }))
                }
              />
            </label>
          </div>
          <button className="btn btn-primary" type="submit" disabled={busy === "event"}>
            <Plus size={16} />
            {busy === "event" ? "Saving event..." : editingId ? "Update event" : "Create event"}
          </button>
        </form>
      </div>

      <div className="dashboard-columns dashboard-announcement-columns">
        <form
          className="dashboard-panel dashboard-announcement-form"
          onSubmit={saveAnnouncement}
        >
          <div className="dashboard-panel-heading">
            <div>
              <h2>Post an announcement</h2>
              <p>Publish an update to the campus feed.</p>
            </div>
          </div>
          <label>
            Title
            <input
              required
              maxLength="190"
              value={announcementForm.title}
              onChange={(event) =>
                setAnnouncementForm((current) => ({ ...current, title: event.target.value }))
              }
              placeholder="What do students need to know?"
            />
          </label>
          <label>
            Message
            <textarea
              required
              rows="4"
              maxLength="10000"
              value={announcementForm.body}
              onChange={(event) =>
                setAnnouncementForm((current) => ({ ...current, body: event.target.value }))
              }
              placeholder="Share the details, schedule, or next steps..."
            />
          </label>
          <label>
            Audience
            <select
              value={announcementForm.course}
              onChange={(event) =>
                setAnnouncementForm((current) => ({ ...current, course: event.target.value }))
              }
            >
              <option value="">Campus-wide</option>
              {COURSES.map((name) => (
                <option key={name} value={name}>
                  {name}
                </option>
              ))}
            </select>
          </label>
          <label className="dashboard-pin-toggle">
            <input
              type="checkbox"
              checked={announcementForm.pinned}
              onChange={(event) =>
                setAnnouncementForm((current) => ({
                  ...current,
                  pinned: event.target.checked,
                }))
              }
            />
            Pin to the top of the feed
          </label>
          <button
            className="btn btn-primary"
            type="submit"
            disabled={busy === "announcement"}
          >
            <Megaphone size={16} />
            {busy === "announcement" ? "Posting..." : "Post announcement"}
          </button>
        </form>

        <section className="dashboard-panel dashboard-announcements">
          <div className="dashboard-panel-heading">
            <div>
              <h2>Your announcements</h2>
              <p>Pin important notices or remove outdated updates.</p>
            </div>
            <span className="dashboard-count">{announcements.length}</span>
          </div>
          {loading ? (
            <div className="loading-line"><span /> Loading announcements...</div>
          ) : announcements.length === 0 ? (
            <div className="dashboard-empty">
              You haven’t posted any announcements yet.
            </div>
          ) : (
            <div className="dashboard-announcement-list">
              {announcements.map((announcement) => (
                <article className="dashboard-announcement" key={announcement.id}>
                  <div className="dashboard-announcement-top">
                    <span className="dashboard-announcement-badges">
                      {announcement.pinned && (
                        <span className="dashboard-announcement-pinned">
                          <Pin size={11} /> Pinned
                        </span>
                      )}
                      <span className="dashboard-announcement-course">
                        {announcement.course || "Campus-wide"}
                      </span>
                    </span>
                    <small>
                      {new Date(announcement.created_at).toLocaleString()}
                    </small>
                  </div>
                  <h3>{announcement.title}</h3>
                  <p>{announcement.body}</p>
                  <div className="dashboard-announcement-footer">
                    <span className="dashboard-announcement-metrics">
                      {announcement.reads_count ?? 0} read ·{" "}
                      {announcement.reactions_count ?? 0} reaction
                      {(announcement.reactions_count ?? 0) === 1 ? "" : "s"}
                    </span>
                    <span className="dashboard-announcement-actions">
                      <button
                        className="btn btn-outline"
                        type="button"
                        onClick={() => toggleAnnouncementPin(announcement)}
                        disabled={busy === `announcement-pin-${announcement.id}`}
                      >
                        {announcement.pinned ? <PinOff size={14} /> : <Pin size={14} />}
                        {announcement.pinned ? "Unpin" : "Pin"}
                      </button>
                      <button
                        className="btn btn-outline dashboard-delete"
                        type="button"
                        onClick={() => deleteAnnouncement(announcement)}
                        disabled={busy === `announcement-delete-${announcement.id}`}
                      >
                        <Trash2 size={14} /> Delete
                      </button>
                    </span>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      </div>

      <section className="dashboard-panel dashboard-events">
        <div className="dashboard-panel-heading">
          <div>
            <h2>Your events</h2>
            <p>Review RSVPs, update event details, and manage past-event photos.</p>
          </div>
          <span className="dashboard-count">{events.length}</span>
        </div>
        {loading ? (
          <div className="loading-line"><span /> Loading organizer events...</div>
        ) : loadFailed ? (
          <div className="dashboard-empty">Couldn’t load your events. Use Retry above.</div>
        ) : events.length === 0 ? (
          <div className="dashboard-empty">You haven’t created any events yet.</div>
        ) : (
          <div className="dashboard-event-list">
            {events.map((event) => {
              const isPast = new Date(event.ends_at || event.starts_at) < new Date();
              const going = event.active_rsvps_count || 0;
              return (
                <article className="dashboard-event" key={event.id}>
                  <div className="dashboard-event-main">
                    {event.poster_url ? (
                      <img src={event.poster_url} alt="" />
                    ) : (
                      <div className="dashboard-event-placeholder">
                        <CalendarDays size={22} />
                      </div>
                    )}
                    <div className="dashboard-event-details">
                      <span className={`dashboard-event-status ${isPast ? "past" : ""}`}>
                        {isPast ? "Past event" : "Upcoming"}
                      </span>
                      <h3>{event.title}</h3>
                      <p>{event.venue} · {new Date(event.starts_at).toLocaleString()}</p>
                      <small>{going} going RSVP{going === 1 ? "" : "s"} / {event.capacity} seats</small>
                    </div>
                  </div>
                  <div className="dashboard-event-actions">
                    <button
                      className="btn btn-outline"
                      onClick={() => toggleAttendees(event.id)}
                      disabled={busy === `attendees-${event.id}`}
                    >
                      <Users size={15} />
                      {attendees[event.id] ? "Hide attendees" : "View attendees"}
                    </button>
                    <button className="btn btn-outline" onClick={() => startEdit(event)}>
                      <Pencil size={15} /> Edit
                    </button>
                    <button
                      className="btn btn-outline dashboard-delete"
                      onClick={() => deleteEvent(event)}
                      disabled={busy === `delete-${event.id}`}
                    >
                      <Trash2 size={15} /> Delete
                    </button>
                    {isPast && (
                      <button
                        className="btn btn-outline"
                        onClick={() => setPhotoEventId(photoEventId === event.id ? null : event.id)}
                      >
                        <ImagePlus size={15} /> Add photo
                      </button>
                    )}
                  </div>
                  {attendees[event.id] && (
                    <div className="dashboard-attendees">
                      <strong>Attendees</strong>
                      {attendees[event.id].length ? (
                        <>
                          <div className="dashboard-attendee-actions">
                            <button
                              className="btn btn-outline"
                              onClick={() => downloadAttendees(event.id, event.title)}
                            >
                              <Download size={15} /> Download CSV
                            </button>
                          </div>
                          <div className="dashboard-attendee-list">
                            {attendees[event.id].map((attendee, index) => (
                              <div key={`${event.id}-${attendee.seat_number}-${index}`}>
                                <span>{attendee.name} <small>{attendee.email}</small></span>
                                <span>Seat {attendee.seat_number ?? "—"} · {attendee.status}</span>
                              </div>
                            ))}
                          </div>
                        </>
                      ) : (
                        <>
                          <p>No attendees yet.</p>
                          <button
                            className="btn btn-outline dashboard-csv-empty"
                            onClick={() => downloadAttendees(event.id, event.title)}
                          >
                            <Download size={15} /> Download CSV
                          </button>
                        </>
                      )}
                    </div>
                  )}
                  {photoEventId === event.id && (
                    <form
                      className="dashboard-photo-form"
                      onSubmit={(submit) => {
                        submit.preventDefault();
                        addPhoto(event);
                      }}
                    >
                      <label>
                        Photo URL
                        <input
                          required
                          type="url"
                          value={photoForm.url}
                          onChange={(change) =>
                            setPhotoForm((current) => ({ ...current, url: change.target.value }))
                          }
                        />
                      </label>
                      <label>
                        Caption
                        <input
                          value={photoForm.caption}
                          onChange={(change) =>
                            setPhotoForm((current) => ({
                              ...current,
                              caption: change.target.value,
                            }))
                          }
                        />
                      </label>
                      <button className="btn btn-primary" type="submit" disabled={busy === `photo-${event.id}`}>
                        <ImagePlus size={15} /> Add photo
                      </button>
                    </form>
                  )}
                  {event.photos?.length > 0 && (
                    <div className="dashboard-photos">
                      {event.photos.map((photo) => (
                        <figure key={photo.id}>
                          <img src={photo.url} alt={photo.caption || "Event photo"} />
                          <figcaption>
                            {photo.caption || "Event photo"}
                            <button
                              className="icon-button dashboard-photo-delete"
                              onClick={() => deletePhoto(photo.id)}
                              disabled={busy === `photo-delete-${photo.id}`}
                              aria-label="Delete event photo"
                            >
                              <Trash2 size={14} />
                            </button>
                          </figcaption>
                        </figure>
                      ))}
                    </div>
                  )}
                </article>
              );
            })}
          </div>
        )}
      </section>
    </section>
  );
}
