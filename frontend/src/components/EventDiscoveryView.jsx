import React from "react";
import { Search } from "lucide-react";
import EventCard from "./EventCard";

export default function EventDiscoveryView({
  events,
  pagination,
  search,
  setSearch,
  date,
  setDate,
  category,
  setCategory,
  organizer,
  setOrganizer,
  organizers,
  sort,
  setSort,
  loading,
  cardProps,
  onResetFilters,
  onPage,
}) {
  return (
    <section className="events-section" id="events">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">WHAT’S HAPPENING</div>
          <h2>
            Find your next <span>moment.</span>
          </h2>
          <p>From workshops to campus celebrations, there’s a place for you.</p>
        </div>
        <div className="results-count">
          <b>{pagination.total ?? events.length}</b>
          <span>events found</span>
        </div>
      </div>
      <div className="filter-panel">
        <label className="search-box">
          <Search size={18} />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search events, venues, organizations..."
          />
        </label>
        <label className="filter-field">
          <span>Date</span>
          <input
            type="date"
            value={date}
            onChange={(event) => setDate(event.target.value)}
          />
        </label>
        <label className="filter-field">
          <span>Category</span>
          <select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
          >
            <option value="">All categories</option>
            <option>Campus</option>
            <option>Workshop</option>
            <option>Student Life</option>
            <option>Sports</option>
            <option>Arts &amp; Culture</option>
          </select>
        </label>
        <label className="filter-field">
          <span>Organizer</span>
          <select
            value={organizer}
            onChange={(event) => setOrganizer(event.target.value)}
          >
            <option value="">All organizers</option>
            {organizers.map((org) => (
              <option key={org.id} value={org.id}>
                {org.name}
              </option>
            ))}
          </select>
        </label>
        <label className="filter-field sort-field">
          <span>Sort by</span>
          <select
            value={sort}
            onChange={(event) => setSort(event.target.value)}
          >
            <option value="soonest">Soonest</option>
            <option value="newest">Newest</option>
            <option value="popular">Most popular</option>
          </select>
        </label>
        <button className="reset-btn" onClick={onResetFilters}>
          Clear
        </button>
      </div>
      {loading && (
        <div className="loading-line">
          <span /> Finding campus moments...
        </div>
      )}
      {!loading && events.length === 0 && (
        <div className="empty-state">
          <div className="empty-icon">
            <Search size={27} />
          </div>
          <h3>No events found just yet</h3>
          <p>
            Try another keyword or clear your filters. Your next campus moment
            might be one click away.
          </p>
          <button className="btn btn-primary" onClick={onResetFilters}>
            Clear all filters
          </button>
        </div>
      )}
      <div className="event-grid">
        {events.map((event) => (
          <EventCard key={event.id} event={event} {...cardProps(event)} />
        ))}
      </div>
      {pagination.last_page > 1 && (
        <div className="pagination">
          <button
            disabled={!pagination.prev_page_url}
            onClick={() => onPage(pagination.current_page - 1)}
          >
            Previous
          </button>
          <span>
            Page {pagination.current_page} of {pagination.last_page}
          </span>
          <button
            disabled={!pagination.next_page_url}
            onClick={() => onPage(pagination.current_page + 1)}
          >
            Next
          </button>
        </div>
      )}
    </section>
  );
}
