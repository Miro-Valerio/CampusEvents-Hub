import React, { useEffect, useState } from "react";
import { BellRing, CalendarClock, CheckCircle2, Megaphone, Save } from "lucide-react";

const OPTIONS = [
  {
    key: "announcements",
    label: "Campus announcements",
    description: "New posts from campus organizers appear in your notification bell.",
    icon: Megaphone,
  },
  {
    key: "event_updates",
    label: "Event changes",
    description: "Get notified when an event you care about changes time or is cancelled.",
    icon: BellRing,
  },
  {
    key: "event_reminders",
    label: "Event reminders",
    description: "Receive a reminder the day before an event you RSVPed to.",
    icon: CalendarClock,
  },
];

export default function SettingsView({ http, onNotice }) {
  const [preferences, setPreferences] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    http
      .get("/settings/notifications")
      .then(({ data }) => {
        if (alive) setPreferences(data.data);
      })
      .catch(() => {
        if (alive) setError("Could not load your notification settings. Please refresh.");
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, [http]);

  const toggle = (key) => {
    setSaved(false);
    setPreferences((current) => ({ ...current, [key]: !current?.[key] }));
  };

  const save = async () => {
    setBusy(true);
    setError("");
    setSaved(false);
    try {
      const { data } = await http.put("/settings/notifications", preferences);
      setPreferences(data.data);
      setSaved(true);
      onNotice?.(data.message || "Notification settings saved.");
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Could not save your notification settings. Please try again.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="account-page account-page-narrow">
      <div className="account-heading">
        <div>
          <div className="eyebrow muted">SETTINGS</div>
          <h1>
            Notification <span>settings.</span>
          </h1>
          <p>Choose what you want to hear about. You control the alerts.</p>
        </div>
      </div>

      <div className="account-card settings-card">
        <div className="account-form-heading">
          <h2>Notifications</h2>
          <p>Changes are saved to your account and apply to every device.</p>
        </div>

        {error && (
          <div className="account-message account-message-error" role="alert">
            {error}
          </div>
        )}
        {saved && (
          <div className="account-message account-message-success" role="status">
            <CheckCircle2 size={15} /> Your notification settings were saved.
          </div>
        )}

        {loading || !preferences ? (
          <div className="loading-line">
            <span /> Loading notification settings…
          </div>
        ) : (
          <div className="settings-list">
            {OPTIONS.map(({ key, label, description, icon: Icon }) => (
              <label className="settings-row" key={key}>
                <span className="settings-icon">
                  <Icon size={18} />
                </span>
                <span className="settings-copy">
                  <b>{label}</b>
                  <small>{description}</small>
                </span>
                <span className={`settings-switch${preferences[key] ? " on" : ""}`}>
                  <input
                    type="checkbox"
                    checked={Boolean(preferences[key])}
                    onChange={() => toggle(key)}
                  />
                  <span aria-hidden="true" />
                </span>
              </label>
            ))}
          </div>
        )}

        <div className="settings-footer">
          <button
            className="btn btn-primary"
            type="button"
            onClick={save}
            disabled={busy || loading || !preferences}
          >
            <Save size={15} /> {busy ? "Saving…" : "Save settings"}
          </button>
          <small>
            Turning a notification off hides new alerts of that type. Existing notifications stay
            in your bell.
          </small>
        </div>
      </div>
    </section>
  );
}
