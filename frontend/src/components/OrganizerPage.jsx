import React from "react";
import { ArrowLeft, Users } from "lucide-react";
import EventCard from "./EventCard";

export default function OrganizerPage({ organizer, cardProps, onBack }) {
  return (
    <section className="events-section">
      <button className="back-link" onClick={onBack}>
        <ArrowLeft size={16} /> Back
      </button>
      <div className="organizer-header">
        <span className="organizer-avatar big">
          <Users size={30} />
        </span>
        <div>
          <div className="eyebrow muted">EVENT ORGANIZER</div>
          <h2>{organizer.name}</h2>
          <p>{organizer.description}</p>
        </div>
      </div>

      <h3 className="subhead">Upcoming events</h3>
      {organizer.events?.length ? (
        <div className="event-grid">
          {organizer.events.map((event) => (
            <EventCard key={event.id} event={event} {...cardProps(event)} />
          ))}
        </div>
      ) : (
        <p className="muted-text">No upcoming events from this organizer.</p>
      )}

      {organizer.past_events?.length > 0 && (
        <>
          <h3 className="subhead">Past events</h3>
          <div className="event-grid">
            {organizer.past_events.map((event) => (
              <EventCard key={event.id} event={event} {...cardProps(event)} />
            ))}
          </div>
        </>
      )}
    </section>
  );
}
