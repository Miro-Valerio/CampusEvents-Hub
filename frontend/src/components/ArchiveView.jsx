import React from "react";
import { Images, Search } from "lucide-react";
import EventCard from "./EventCard";

export default function ArchiveView({ events, pagination, loading, search, setSearch, cardProps, onPage }) {
  return (
    <section className="events-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">LOOK BACK</div>
          <h2>
            Past events <span>archive.</span>
          </h2>
          <p>Relive previous campus events with photos.</p>
        </div>
        <div className="results-count">
          <b>{pagination.total ?? events.length}</b>
          <span>past events</span>
        </div>
      </div>
      <div className="filter-panel">
        <label className="search-box">
          <Search size={18} />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search past events..."
          />
        </label>
      </div>
      {loading && (
        <div className="loading-line">
          <span /> Loading archive...
        </div>
      )}
      {!loading && events.length === 0 && (
        <div className="empty-state">
          <div className="empty-icon">
            <Images size={27} />
          </div>
          <h3>No past events yet</h3>
          <p>Finished events and their photos will show up here.</p>
        </div>
      )}
      <div className="event-grid">
        {events.map((event) => (
          <div className="archive-item" key={event.id}>
            <EventCard event={event} {...cardProps(event)} />
            {event.photos?.length > 0 && (
              <div className="photo-strip">
                {event.photos.slice(0, 4).map((p) => (
                  <img key={p.id} src={p.url} alt={p.caption || event.title} loading="lazy" />
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
      {pagination.last_page > 1 && (
        <div className="pagination">
          <button disabled={!pagination.prev_page_url} onClick={() => onPage(pagination.current_page - 1)}>
            Previous
          </button>
          <span>
            Page {pagination.current_page} of {pagination.last_page}
          </span>
          <button disabled={!pagination.next_page_url} onClick={() => onPage(pagination.current_page + 1)}>
            Next
          </button>
        </div>
      )}
    </section>
  );
}
