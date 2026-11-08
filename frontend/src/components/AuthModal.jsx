import React, { useEffect } from "react";
import { CalendarDays, Loader2, X } from "lucide-react";
import { COURSES, DEPARTMENTS } from "../constants";

export default function AuthModal({
  mode,
  form,
  onFormChange,
  onModeChange,
  onSubmit,
  onClose,
  onClearError,
  error,
  isSubmitting,
}) {
  useEffect(() => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, []);

  return (
    <div className="modal-backdrop" onClick={isSubmitting ? undefined : onClose}>
      <div
        className="modal auth-modal"
        onClick={(click) => click.stopPropagation()}
      >
        <button
          className="modal-close"
          onClick={onClose}
          disabled={isSubmitting}
          aria-label="Close"
        >
          <X />
        </button>
        <div className="auth-mark">
          <CalendarDays size={26} />
        </div>
        <h2>
          {mode === "login" ? "Welcome back." : "Join the campus community."}
        </h2>
        <p>
          {mode === "login"
            ? "Log in to save events and keep your campus life close."
            : "Create your CampusEvents Hub account."}
        </p>
        {error && (
          <div className="full-notice" role="alert">
            {error}
          </div>
        )}
        <form onSubmit={onSubmit}>
          {mode === "register" && (
            <label>
              Full name
              <input
                required
                value={form.name}
                onChange={(event) => onFormChange("name", event.target.value)}
                placeholder="Your name"
              />
            </label>
          )}
          {mode === "register" && (
            <label>
              Department
              <select
                required
                value={form.department}
                onChange={(event) => onFormChange("department", event.target.value)}
                disabled={isSubmitting}
              >
                <option value="">Select your department</option>
                {DEPARTMENTS.map((department) => (
                  <option key={department} value={department}>
                    {department}
                  </option>
                ))}
              </select>
            </label>
          )}
          {mode === "register" && (
            <label>
              Course (optional)
              <select
                value={form.course || ""}
                onChange={(event) => onFormChange("course", event.target.value)}
                disabled={isSubmitting}
              >
                <option value="">Not set</option>
                {COURSES.map((name) => (
                  <option key={name} value={name}>
                    {name}
                  </option>
                ))}
              </select>
            </label>
          )}
          <label>
            Email address
            <input
              required
              type="email"
              value={form.email}
              onChange={(event) => onFormChange("email", event.target.value)}
              placeholder="you@spc.edu.ph"
            />
          </label>
          <label>
            Password
            <input
              required
              type="password"
              minLength="8"
              value={form.password}
              onChange={(event) => onFormChange("password", event.target.value)}
              placeholder="At least 8 characters"
            />
          </label>
          {mode === "register" && (
            <label>
              Confirm password
              <input
                required
                type="password"
                value={form.password_confirmation}
                onChange={(event) =>
                  onFormChange("password_confirmation", event.target.value)
                }
                placeholder="Repeat password"
              />
            </label>
          )}
          <button
            className="btn btn-primary full-btn"
            type="submit"
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting && (
              <Loader2 size={16} className="auth-spinner" aria-hidden="true" />
            )}
            {isSubmitting
              ? mode === "login"
                ? "Logging in..."
                : "Creating account..."
              : mode === "login"
                ? "Log in"
                : "Create account"}
          </button>
        </form>
        <div className="auth-switch">
          {mode === "login"
            ? "New to CampusEvents Hub?"
            : "Already have an account?"}{" "}
          <button
            disabled={isSubmitting}
            onClick={() => {
              onModeChange(mode === "login" ? "register" : "login");
              onClearError();
            }}
          >
            {mode === "login" ? "Sign up" : "Log in"}
          </button>
        </div>
      </div>
    </div>
  );
}
