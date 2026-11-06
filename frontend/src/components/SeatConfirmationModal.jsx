import React from "react";
import { CalendarDays, X } from "lucide-react";

export default function SeatConfirmationModal({ confirmation, onClose }) {
  if (!confirmation) return null;

  return (
    <div className="modal-backdrop" role="presentation" onClick={onClose}>
      <div
        className="modal rsvp-confirmation"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rsvp-confirmation-title"
        onClick={(event) => event.stopPropagation()}
      >
        <button
          className="modal-close"
          onClick={onClose}
          aria-label="Close confirmation"
        >
          <X />
        </button>
        <div className="auth-mark">
          <CalendarDays size={26} />
        </div>
        <div className="eyebrow muted">RSVP CONFIRMED</div>
        <h2 id="rsvp-confirmation-title">You’re going!</h2>
        <p>{confirmation.event.title}</p>
        <div className="rsvp-seat-number confirmation-seat">
          Your seat number <strong>#{confirmation.seatNumber}</strong>
        </div>
        <button className="btn btn-primary full-btn" onClick={onClose}>
          Done
        </button>
      </div>
    </div>
  );
}
