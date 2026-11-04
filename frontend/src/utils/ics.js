// Build and download an .ics calendar file so events can go into Google/Apple/Outlook calendar.
const stamp = (value) => new Date(value).toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");
const esc = (text = "") =>
  String(text).replace(/\\/g, "\\\\").replace(/;/g, "\\;").replace(/,/g, "\\,").replace(/\r?\n/g, "\\n");

export function buildIcs(events) {
  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//CampusEvents Hub//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
  ];
  events.forEach((event) => {
    const start = new Date(event.starts_at);
    const end = event.ends_at
      ? new Date(event.ends_at)
      : new Date(start.getTime() + 2 * 3600 * 1000);
    lines.push(
      "BEGIN:VEVENT",
      `UID:event-${event.id}@campusevents-hub`,
      `DTSTAMP:${stamp(new Date())}`,
      `DTSTART:${stamp(start)}`,
      `DTEND:${stamp(end)}`,
      `SUMMARY:${esc(event.title)}`,
      `LOCATION:${esc(event.venue)}`,
      `DESCRIPTION:${esc(`${event.description || ""}\n${event.category ? `Category: ${event.category}` : ""}`.trim())}`,
      "STATUS:CONFIRMED",
      "END:VEVENT",
    );
  });
  lines.push("END:VCALENDAR");
  return `${lines.join("\r\n")}\r\n`;
}

export function downloadIcs(events, filename = "campus-events.ics") {
  const blob = new Blob([buildIcs(events)], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export const isPast = (event) => new Date(event.ends_at || event.starts_at) < new Date();
