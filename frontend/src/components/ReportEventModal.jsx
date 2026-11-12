import React, { useEffect, useState } from "react";
import { Flag, Send, X } from "lucide-react";

const REASONS = [
  { value: "wrong_details", label: "Details look wrong", hint: "Wrong venue, description, or capacity." },
  { value: "wrong_date", label: "Date or time is wrong", hint: "The schedule does not match the poster." },
  { value: "spam_or_scam", label: "Spam or scam", hint: "Fake, misleading, or unsafe listing." },
  { value: "inappropriate", label: "Inappropriate content", hint: "Offensive or unsuitable material." },
  { value: "duplicate", label: "Duplicate listing", hint: "The same event was posted twice." },
  { value: "other", label: "Something else", hint: "Tell the organizer what needs fixing." },
];

export default function ReportEventModal({ event, onClose, onSubmit }) {
  const [reason, setReason] = useState("wrong_details");
  const [details, setDetails] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const onKey = (keyEvent) => {
      if (keyEvent.key === "Escape" && !busy) onClose();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [busy, onClose]);

  const submit = async (submitEvent) => {
    submitEvent.preventDefault();
    setBusy(true);
    setError("");
    try {
      await onSubmit({ reason, details: details.trim() || null });
    } catch (err) {
      setError(
        err.response?.data?.message ||
          Object.values(err.response?.data?.errors || {}).flat()[0] ||
          "Could not send your report. Please try again.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(mouseEvent) => {
        if (mouseEvent.target === mouseEvent.currentTarget && !busy) onClose();
      }}
    >
      <div className="modal report-modal" role="dialog" aria-modal="true" aria-labelledby="report-title">
        <button className="modal-close" type="button" onClick={onClose} disabled={busy} aria-label="Close report form">
          <X size={18} />
        </button>
        <div className="report-mark">
          <Flag size={22} />
        </div>
        <h2 id="report-title">Report this listing</h2>
        <p className="report-subtitle">
          Let the organizer know what looks wrong with <b>{event?.title}</b>. Reports are private
          and go straight to the organizer.
        </p>

        {error && (
          <div className="account-message account-message-error" role="alert">
            {error}
          </div>
        )}

        <form onSubmit={submit} className="report-form">
          <div className="report-reasons" role="radiogroup" aria-label="Reason for reporting">
            {REASONS.map((option) => (
              <label
                key={option.value}
                className={`report-option${reason === option.value ? " selected" : ""}`}
              >
                <input
                  type="radio"
                  name="reason"
                  value={option.value}
                  checked={reason === option.value}
                  onChange={() => setReason(option.value)}
                />
                <span>
                  <b>{option.label}</b>
                  <small>{option.hint}</small>
                </span>
              </label>
            ))}
          </div>

          <label className="report-details">
            More details <span className="account-optional">(optional)</span>
            <textarea
              rows="3"
              maxLength="2000"
              value={details}
              onChange={(changeEvent) => setDetails(changeEvent.target.value)}
              placeholder="What exactly looks wrong?"
            />
          </label>

          <div className="report-actions">
            <button className="btn btn-outline" type="button" onClick={onClose} disabled={busy}>
              Cancel
            </button>
            <button className="btn btn-primary" type="submit" disabled={busy}>
              <Send size={15} /> {busy ? "Sending…" : "Send report"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
