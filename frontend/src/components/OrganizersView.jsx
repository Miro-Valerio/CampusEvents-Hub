import React from "react";
import { ArrowUpRight, Users } from "lucide-react";

export default function OrganizersView({ organizers, loading, onOpen }) {
  return (
    <section className="events-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">WHO’S BEHIND THE EVENTS</div>
          <h2>
            Event <span>organizers.</span>
          </h2>
          <p>Browse the groups hosting events around SPC.</p>
        </div>
        <div className="results-count">
          <b>{organizers.length}</b>
          <span>organizers</span>
        </div>
      </div>
      {loading && (
        <div className="loading-line">
          <span /> Loading organizers...
        </div>
      )}
      <div className="organizer-grid">
        {organizers.map((org) => (
          <button className="organizer-card" key={org.id} onClick={() => onOpen(org.id)}>
            <span className="organizer-avatar">
              <Users size={22} />
            </span>
            <b>{org.name}</b>
            <small>{org.description}</small>
            <span className="organizer-count">
              {org.upcoming_events_count} upcoming event{org.upcoming_events_count === 1 ? "" : "s"}
              <ArrowUpRight size={15} />
            </span>
          </button>
        ))}
      </div>
    </section>
  );
}
