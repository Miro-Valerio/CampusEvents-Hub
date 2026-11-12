import React, { useEffect, useRef, useState } from "react";
import { Camera, CheckCircle2, Mail, Save, UserRound } from "lucide-react";
import { COURSES } from "../constants";

function errorMessage(error) {
  return (
    error.response?.data?.message ||
    Object.values(error.response?.data?.errors || {}).flat()[0] ||
    "Could not save your profile. Please try again."
  );
}

function initials(name) {
  return (name || "?")
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

export default function ProfileView({ http, user, onSaved, onNotice }) {
  const [form, setForm] = useState({
    name: user?.name || "",
    course: user?.course || "",
    photo_url: user?.photo_url || "",
  });
  const [busy, setBusy] = useState("");
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");
  const [photoFailed, setPhotoFailed] = useState(false);
  const fileRef = useRef(null);

  useEffect(() => {
    setForm({
      name: user?.name || "",
      course: user?.course || "",
      photo_url: user?.photo_url || "",
    });
    setPhotoFailed(false);
  }, [user?.id, user?.name, user?.course, user?.photo_url]);

  const save = async (event) => {
    event.preventDefault();
    setBusy("profile");
    setError("");
    try {
      const { data } = await http.put("/profile", {
        name: form.name.trim(),
        course: form.course || null,
        photo_url: form.photo_url.trim() || null,
      });
      onSaved?.(data.data);
      onNotice?.(data.message || "Profile updated.");
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy("");
    }
  };

  const upload = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;

    setUploading(true);
    setError("");
    const payload = new FormData();
    payload.append("photo", file);
    try {
      const { data } = await http.post("/profile/photo", payload, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setPhotoFailed(false);
      setForm((current) => ({ ...current, photo_url: data.data.photo_url || "" }));
      onSaved?.(data.data);
      onNotice?.(data.message || "Profile photo updated.");
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setUploading(false);
    }
  };

  const removePhoto = () => {
    setPhotoFailed(false);
    setForm((current) => ({ ...current, photo_url: "" }));
  };

  const showPhoto = form.photo_url && !photoFailed;

  return (
    <section className="account-page">
      <div className="account-heading">
        <div>
          <div className="eyebrow muted">YOUR ACCOUNT</div>
          <h1>
            Your <span>profile.</span>
          </h1>
          <p>Update the name, course, and photo other students see.</p>
        </div>
      </div>

      <div className="account-layout">
        <aside className="account-card account-preview">
          <div className="profile-avatar profile-avatar-large">
            {showPhoto ? (
              <img
                src={form.photo_url}
                alt={form.name || "Profile photo"}
                onError={() => setPhotoFailed(true)}
              />
            ) : (
              <span>{initials(form.name)}</span>
            )}
          </div>
          <h2>{form.name || "Your name"}</h2>
          <span className="profile-role">
            <UserRound size={14} /> {user?.role === "organizer" ? "Organizer" : "Student"}
          </span>
          <p className="profile-course">{form.course || "Course not set yet"}</p>
          <p className="profile-email">
            <Mail size={14} /> {user?.email}
          </p>

          <div className="profile-photo-actions">
            <input
              ref={fileRef}
              type="file"
              accept="image/png,image/jpeg,image/webp"
              onChange={upload}
              hidden
            />
            <button
              type="button"
              className="btn btn-outline"
              onClick={() => fileRef.current?.click()}
              disabled={uploading}
            >
              <Camera size={15} /> {uploading ? "Uploading…" : "Upload photo"}
            </button>
            {form.photo_url && (
              <button type="button" className="btn btn-outline" onClick={removePhoto}>
                Remove
              </button>
            )}
          </div>
          <small className="profile-hint">
            JPG, PNG, or WebP up to 2 MB. You can also paste an image URL.
          </small>
        </aside>

        <form className="account-card account-form" onSubmit={save}>
          <div className="account-form-heading">
            <h2>Profile details</h2>
            <p>Changes appear on your RSVPs, check-ins, and campus activity.</p>
          </div>

          {error && (
            <div className="account-message account-message-error" role="alert">
              {error}
            </div>
          )}

          <label>
            Full name
            <input
              required
              maxLength="120"
              value={form.name}
              onChange={(event) =>
                setForm((current) => ({ ...current, name: event.target.value }))
              }
            />
          </label>

          <label>
            Course
            <select
              value={form.course}
              onChange={(event) =>
                setForm((current) => ({ ...current, course: event.target.value }))
              }
            >
              <option value="">Select your course</option>
              {COURSES.map((course) => (
                <option key={course} value={course}>
                  {course}
                </option>
              ))}
              {form.course && !COURSES.includes(form.course) && (
                <option value={form.course}>{form.course}</option>
              )}
            </select>
          </label>

          <label>
            Profile photo URL <span className="account-optional">(optional)</span>
            <input
              type="url"
              maxLength="2048"
              placeholder="https://example.com/your-photo.jpg"
              value={form.photo_url}
              onChange={(event) => {
                setPhotoFailed(false);
                setForm((current) => ({ ...current, photo_url: event.target.value }));
              }}
            />
          </label>

          <label>
            Email address
            <input value={user?.email || ""} readOnly disabled />
          </label>

          <div className="account-form-footer">
            <button className="btn btn-primary" type="submit" disabled={busy === "profile"}>
              {busy === "profile" ? (
                "Saving…"
              ) : (
                <>
                  <Save size={15} /> Save profile
                </>
              )}
            </button>
            <span className="account-safe">
              <CheckCircle2 size={14} /> Your email cannot be changed here.
            </span>
          </div>
        </form>
      </div>
    </section>
  );
}
