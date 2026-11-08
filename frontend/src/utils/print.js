export function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

export function openPrintPage(title, body) {
  const printWindow = window.open("", "_blank", "width=900,height=700");
  if (!printWindow) return false;

  printWindow.onload = () => {
    window.setTimeout(() => {
      printWindow.focus();
      printWindow.print();
    }, 250);
  };
  printWindow.document.write(`<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>${escapeHtml(title)}</title>
        <style>
          * { box-sizing: border-box; }
          body { margin: 32px; color: #172b45; font: 14px Arial, sans-serif; }
          h1 { margin: 0 0 8px; font-size: 24px; }
          p { line-height: 1.5; }
          .print-muted { color: #5e6f82; }
          .print-poster { display: block; width: 100%; max-height: 55vh; margin: 20px 0; object-fit: contain; }
          .print-details { display: grid; grid-template-columns: max-content 1fr; gap: 8px 14px; }
          .print-details strong { color: #7d1a0d; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          th, td { padding: 9px; border: 1px solid #cbd5df; text-align: left; }
          th { background: #f0f3f6; }
          @media print { body { margin: 14mm; } }
        </style>
      </head>
      <body>${body}</body>
    </html>`);
  printWindow.document.close();
  return true;
}

export function printEvent(event) {
  const posterUrl = event.poster_url ? new URL(event.poster_url, window.location.href) : null;
  const poster = posterUrl && ["http:", "https:"].includes(posterUrl.protocol)
    ? `<img class="print-poster" src="${escapeHtml(posterUrl.href)}" alt="Event poster">`
    : "";
  const body = `
    <h1>${escapeHtml(event.title)}</h1>
    <p class="print-muted">${escapeHtml(event.organizer?.name || "SPC Organizer")}</p>
    ${poster}
    <div class="print-details">
      <strong>Date &amp; time</strong><span>${escapeHtml(new Date(event.starts_at).toLocaleString())}${event.ends_at ? ` – ${escapeHtml(new Date(event.ends_at).toLocaleString())}` : ""}</span>
      <strong>Venue</strong><span>${escapeHtml(event.venue)}</span>
      <strong>Category</strong><span>${escapeHtml(event.category)}</span>
      <strong>Seats</strong><span>${escapeHtml(event.capacity)}</span>
      <strong>Description</strong><span>${escapeHtml(event.description)}</span>
      ${event.rules ? `<strong>Rules</strong><span>${escapeHtml(event.rules)}</span>` : ""}
    </div>`;

  return openPrintPage(event.title, body);
}

export function printRsvpSheet(eventTitle, attendees) {
  const rows = attendees.map((attendee) => `
    <tr>
      <td>${escapeHtml(attendee.name)}</td>
      <td>${escapeHtml(attendee.email)}</td>
      <td>${escapeHtml(attendee.seat_number ?? "—")}</td>
      <td>${escapeHtml(attendee.status)}</td>
      <td>${escapeHtml(attendee.checked_in_at ? new Date(attendee.checked_in_at).toLocaleString() : "Not checked in")}</td>
      <td></td>
    </tr>`).join("");
  const body = `
    <h1>${escapeHtml(eventTitle)} — RSVP list</h1>
    <p class="print-muted">Printed ${escapeHtml(new Date().toLocaleString())} · ${attendees.length} attendee${attendees.length === 1 ? "" : "s"}</p>
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Seat</th><th>Status</th><th>Check-in</th><th>Signature</th></tr></thead>
      <tbody>${rows || '<tr><td colspan="6">No attendees yet.</td></tr>'}</tbody>
    </table>`;

  return openPrintPage(`${eventTitle} RSVP list`, body);
}
