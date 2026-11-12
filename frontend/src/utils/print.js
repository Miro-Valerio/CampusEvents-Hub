const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

const baseStyles = `
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 32px;
    font-family: "Segoe UI", Arial, sans-serif;
    color: #1f2430;
    background: #fff;
    line-height: 1.45;
  }
  h1, h2, h3 { margin: 0; font-family: Georgia, "Times New Roman", serif; }
  h1 { font-size: 30px; }
  h2 { font-size: 22px; margin-bottom: 10px; }
  p { margin: 0; }
  .sheet-head { border-bottom: 3px solid #b3261e; padding-bottom: 14px; margin-bottom: 20px; }
  .sheet-head .eyebrow { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #b3261e; font-weight: 700; }
  .sheet-meta { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 8px; font-size: 13px; color: #4b5563; }
  table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13px; }
  th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  th { background: #f7f3f2; font-size: 11px; letter-spacing: 0.6px; text-transform: uppercase; color: #6b7280; }
  tr:nth-child(even) td { background: #fcfafa; }
  .muted { color: #6b7280; }
  .totals { margin-top: 16px; display: flex; gap: 24px; font-size: 13px; }
  .totals b { font-size: 18px; display: block; color: #b3261e; }
  .note { margin-top: 22px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
  @page { margin: 14mm; }
`;

const openPrintWindow = (title, body) => {
  const win = window.open("", "_blank", "width=980,height=760");
  if (!win) {
    window.alert("Please allow pop-ups for this site to print.");
    return;
  }
  win.document.open();
  win.document.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)}</title>
    <style>${baseStyles}</style>
  </head>
  <body>${body}</body>
</html>`);
  win.document.close();
  win.focus();
  window.setTimeout(() => {
    win.print();
  }, 450);
};

export function printRsvpSheet({ event, attendees, organizerName, generatedAt }) {
  const going = attendees.filter((attendee) => attendee.status === "going");
  const checkedIn = going.filter((attendee) => attendee.checked_in_at).length;
  const rows = going.length
    ? going
        .map(
          (attendee, index) => `<tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(attendee.name || "Unknown student")}</td>
            <td>${escapeHtml(attendee.email || "")}</td>
            <td>${escapeHtml(attendee.seat_number ?? "—")}</td>
            <td>${attendee.checked_in_at ? "Checked in" : "Not checked in"}</td>
          </tr>`,
        )
        .join("")
    : `<tr><td colspan="5" class="muted">No RSVPs yet.</td></tr>`;

  const start = formatDate(event.starts_at);
  const end = event.ends_at ? ` – ${formatDate(event.ends_at)}` : "";

  openPrintWindow(
    `RSVP sheet — ${event.title}`,
    `<div class="sheet-head">
      <div class="eyebrow">CampusEvents Hub · RSVP sheet</div>
      <h1>${escapeHtml(event.title)}</h1>
      <div class="sheet-meta">
        <span><b>When:</b> ${escapeHtml(start)}${escapeHtml(end)}</span>
        <span><b>Where:</b> ${escapeHtml(event.venue)}</span>
        <span><b>Organizer:</b> ${escapeHtml(organizerName || event.organizer?.name || "SPC Organizer")}</span>
      </div>
    </div>
    <div class="totals">
      <span><b>${going.length}</b>Going RSVPs</span>
      <span><b>${checkedIn}</b>Checked in</span>
      <span><b>${event.capacity}</b>Seat limit</span>
    </div>
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Seat</th><th>Attendance</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
    <p class="note">Generated ${escapeHtml(generatedAt)} by CampusEvents Hub. Only students with an active RSVP are listed.</p>`,
  );
}

export function printEventPoster(event, prettyDate) {
  const start = prettyDate ? prettyDate(event.starts_at) : formatDate(event.starts_at);
  const end = event.ends_at
    ? prettyDate
      ? prettyDate(event.ends_at)
      : formatDate(event.ends_at)
    : "";
  const poster = event.poster_url
    ? `<div class="poster-art" style="background-image:url('${escapeHtml(event.poster_url)}')"></div>`
    : `<div class="poster-art poster-fallback"><span>${escapeHtml(event.category)}</span></div>`;

  openPrintWindow(
    `Event poster — ${event.title}`,
    `<style>
      body { padding: 0; }
      .poster { width: 100%; max-width: 720px; margin: 0 auto; display: grid; gap: 0; }
      .poster-art {
        width: 100%;
        aspect-ratio: 4 / 5;
        background-size: cover;
        background-position: center;
        border-radius: 22px 22px 0 0;
        background-color: #123c76;
      }
      .poster-fallback {
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #123c76, #2f6fb5);
        color: #fff;
        font-size: 30px;
        letter-spacing: 4px;
        text-transform: uppercase;
      }
      .poster-body { border: 1px solid #e5e7eb; border-top: 6px solid #b3261e; border-radius: 0 0 22px 22px; padding: 28px 30px 34px; }
      .poster-category { display: inline-block; background: #fde8e5; color: #b3261e; border-radius: 999px; padding: 6px 14px; font-size: 11px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; }
      .poster-body h1 { font-size: 44px; line-height: 1.05; margin: 16px 0 18px; }
      .poster-details { display: grid; gap: 10px; font-size: 16px; color: #374151; }
      .poster-details b { color: #111827; }
      .poster-organizer { margin-top: 22px; font-size: 13px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 14px; }
      .poster-note { margin-top: 14px; font-size: 12px; color: #9ca3af; text-align: center; }
      @page { margin: 12mm; }
    </style>
    <div class="poster">
      ${poster}
      <div class="poster-body">
        <span class="poster-category">${escapeHtml(event.category)}</span>
        <h1>${escapeHtml(event.title)}</h1>
        <div class="poster-details">
          <p><b>Date &amp; time:</b> ${escapeHtml(start)}${end ? ` – ${escapeHtml(end)}` : ""}</p>
          <p><b>Venue:</b> ${escapeHtml(event.venue)}</p>
          <p><b>Seats:</b> ${escapeHtml(event.capacity)}</p>
        </div>
        <p class="poster-organizer">Presented by ${escapeHtml(event.organizer?.name || "SPC Campus")}</p>
      </div>
    </div>
    <p class="poster-note">CampusEvents Hub · SPC Campus Life</p>`,
  );
}

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString([], { dateStyle: "medium", timeStyle: "short" });
}
