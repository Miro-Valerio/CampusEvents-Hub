import React, { useEffect, useMemo, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const monthKey = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
const dayKey = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

export default function CalendarView({ loadMonth, onOpen }) {
  const [cursor, setCursor] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [picked, setPicked] = useState(null);

  const load = () => {
    setLoading(true);
    setFailed(false);
    loadMonth(monthKey(cursor))
      .then(setEvents)
      .catch(() => setFailed(true))
      .finally(() => setLoading(false));
  };
  useEffect(load, [cursor]);

  const byDay = useMemo(() => {
    const map = {};
    events.forEach((e) => {
      const k = dayKey(new Date(e.starts_at));
      (map[k] ||= []).push(e);
    });
    return map;
  }, [events]);

  const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
  const total = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
  const cells = [...Array(first.getDay()).fill(null), ...Array.from({ length: total }, (_, i) => i + 1)];
  while (cells.length % 7) cells.push(null);
  const todayKey = dayKey(new Date());
  const shift = (n) => {
    setPicked(null);
    setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + n, 1));
  };
  const pickedEvents = picked ? byDay[picked] || [] : [];

  return (
    <section className="events-section">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">CAMPUS CALENDAR</div>
          <h2>
            Month <span>view.</span>
          </h2>
          <p>See every campus event laid out by day.</p>
        </div>
        <div className="cal-nav">
          <button className="btn btn-outline" onClick={() => shift(-1)} aria-label="Previous month">
            <ChevronLeft size={16} />
          </button>
          <b>{cursor.toLocaleDateString([], { month: "long", year: "numeric" })}</b>
          <button className="btn btn-outline" onClick={() => shift(1)} aria-label="Next month">
            <ChevronRight size={16} />
          </button>
        </div>
      </div>
      {failed && (
        <div className="error-banner" role="alert">
          <span>Couldn’t load this month’s events.</span>
          <button onClick={load}>Retry</button>
        </div>
      )}
      {loading && (
        <div className="loading-line">
          <span /> Loading calendar...
        </div>
      )}
      <div className="cal-grid">
        {DAYS.map((d) => (
          <div className="cal-head" key={d}>
            {d}
          </div>
        ))}
        {cells.map((n, i) => {
          if (!n) return <div className="cal-cell empty" key={i} />;
          const key = dayKey(new Date(cursor.getFullYear(), cursor.getMonth(), n));
          const list = byDay[key] || [];
          return (
            <div
              key={i}
              className={`cal-cell ${key === todayKey ? "today" : ""} ${picked === key ? "picked" : ""}`}
              onClick={() => list.length && setPicked(key)}
            >
              <span className="cal-day">{n}</span>
              {list.slice(0, 2).map((e) => (
                <button
                  key={e.id}
                  className="cal-chip"
                  title={e.title}
                  onClick={(click) => {
                    click.stopPropagation();
                    onOpen(e);
                  }}
                >
                  {e.title}
                </button>
              ))}
              {list.length > 2 && <small className="cal-more">+{list.length - 2} more</small>}
            </div>
          );
        })}
      </div>
      {picked && (
        <div className="cal-day-list">
          <h3>
            {new Date(picked + "T00:00").toLocaleDateString([], { dateStyle: "full" })}
          </h3>
          {pickedEvents.map((e) => (
            <button className="organizer-event" key={e.id} onClick={() => onOpen(e)}>
              <span>
                <b>{e.title}</b>
                <small>
                  {new Date(e.starts_at).toLocaleTimeString([], { timeStyle: "short" })} · {e.venue}
                </small>
              </span>
            </button>
          ))}
        </div>
      )}
      {!loading && !failed && events.length === 0 && (
        <p className="muted-text">No events scheduled this month.</p>
      )}
    </section>
  );
}
