import React, { useEffect, useState } from "react";
import { Bell, Camera, Save, UserRound } from "lucide-react";
import { COURSES, DEPARTMENTS } from "../constants";

const defaultPreferences = {
  event_reminders: true,
  event_changes: true,
  announcements: true,
};

function errorMessage(error) {
  return error.response?.data?.message || "Could not save your settings. Please try again.";
}

export default function UserSettingsView({ http, user, onUserChange, onNotice }) {
  const [name, setName] = useState(user.name || "");
  const [course, setCourse] = useState(user.course || "");
  const [department, setDepartment] = useState(user.department || "");
  const [photo, setPhoto] = useState(null);
  const [preferences, setPreferences] = useState(defaultPreferences);
  const [loadingPreferences, setLoadingPreferences] = useState(true);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [photoPreview, setPhotoPreview] = useState(user.profile_photo_url || "");

  useEffect(() => {
    if (!photo) {
      setPhotoPreview(user.profile_photo_url || "");
      return undefined;
    }
    const previewUrl = URL.createObjectURL(photo);
    setPhotoPreview(previewUrl);
    return () => URL.revokeObjectURL(previewUrl);
  }, [photo, user.profile_photo_url]);

  useEffect(() => {
    let active = true;
    http.get("/notification-preferences")
      .then(({ data }) => {
        if (active) setPreferences({ ...defaultPreferences, ...data.preferences });
      })
      .catch(() => {
        if (active) setError("Could not load notification preferences.");
      })
      .finally(() => {
        if (active) setLoadingPreferences(false);
      });
    return () => {
      active = false;
    };
  }, [http]);

  const saveProfile = async (event) => {
    event.preventDefault();
    setBusy("profile");
    setError("");
    const form = new FormData();
    form.append("name", name);
    form.append("course", user.role === "student" ? course : "");
    form.append("department", user.role === "student" ? department : "");
    if (photo) form.append("profile_photo", photo);
    try {
      const { data } = await http.post("/profile", form);
      onUserChange(data);
      setPhoto(null);
      onNotice("Your profile has been updated.");
    } catch (requestError) {
      setError(errorMessage(requestError));
    } finally {
      setBusy("");
    }
  };

  const savePreferences = async (event) => {
    event.preventDefault();
    setBusy("preferences");
    setError("");
    try {
      const { data } = await http.put("/notification-preferences", preferences);
      setPreferences(data.preferences);
      onNotice("Notification preferences saved.");
    } catch (requestError) {
      setError(errorMessage(requestError));
    } finally {
      setBusy("");
    }
  };

  return (
    <section className="events-section user-settings">
      <div className="section-heading">
        <div>
          <div className="eyebrow muted">YOUR ACCOUNT</div>
          <h2>Profile &amp; <span>settings.</span></h2>
          <p>Update your profile and choose which campus notifications you receive.</p>
        </div>
      </div>
      {error && <div className="error-banner" role="alert">{error}</div>}

      <div className="settings-grid">
        <form className="settings-card" onSubmit={saveProfile}>
          <div className="settings-card-heading">
            <UserRound size={19} />
            <div><h3>Your profile</h3><p>Your name and photo appear with your account.</p></div>
          </div>
          <div className="profile-photo-picker">
            <span className="profile-photo-preview">
              {photoPreview ? <img src={photoPreview} alt="Profile preview" /> : <UserRound size={28} />}
            </span>
            <label className="btn btn-outline">
              <Camera size={15} /> Choose photo
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(event) => setPhoto(event.target.files?.[0] || null)}
              />
            </label>
            <small>JPG, PNG, or WebP, up to 5 MB.</small>
          </div>
          <label>
            Full name
            <input required maxLength="120" value={name} onChange={(event) => setName(event.target.value)} />
          </label>
          {user.role === "student" && (
            <>
              <label>
                Department
                <select value={department} onChange={(event) => setDepartment(event.target.value)}>
                  <option value="">Not set</option>
                  {DEPARTMENTS.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
              </label>
              <label>
                Course
                <select value={course} onChange={(event) => setCourse(event.target.value)}>
                  <option value="">Not set</option>
                  {COURSES.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
              </label>
            </>
          )}
          <button className="btn btn-primary" type="submit" disabled={busy === "profile"}>
            <Save size={15} /> {busy === "profile" ? "Saving..." : "Save profile"}
          </button>
        </form>

        <form className="settings-card" onSubmit={savePreferences}>
          <div className="settings-card-heading">
            <Bell size={19} />
            <div><h3>Notification settings</h3><p>Choose which updates appear in your notification bell.</p></div>
          </div>
          {[
            ["event_reminders", "Event reminders", "A reminder before an event you RSVP’d to."],
            ["event_changes", "Event schedule changes", "Time changes and cancellations for saved or RSVP’d events."],
            ["announcements", "Campus announcements", "Announcements for your course or the campus."],
          ].map(([key, title, description]) => (
            <label className="notification-preference" key={key}>
              <input
                type="checkbox"
                checked={Boolean(preferences[key])}
                disabled={loadingPreferences}
                onChange={(event) => setPreferences((current) => ({ ...current, [key]: event.target.checked }))}
              />
              <span><b>{title}</b><small>{description}</small></span>
            </label>
          ))}
          <button className="btn btn-primary" type="submit" disabled={busy === "preferences" || loadingPreferences}>
            <Save size={15} /> {busy === "preferences" ? "Saving..." : "Save notification settings"}
          </button>
        </form>
      </div>
    </section>
  );
}
