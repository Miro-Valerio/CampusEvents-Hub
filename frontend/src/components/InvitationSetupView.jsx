import React, { useEffect, useState } from "react";
import { KeyRound, LogIn } from "lucide-react";

export default function InvitationSetupView({ http, token, onLogin }) {
  const [account, setAccount] = useState(null);
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [complete, setComplete] = useState(false);

  useEffect(() => {
    let active = true;
    if (!token) {
      setError("The invitation link is missing its setup token.");
      setLoading(false);
      return () => { active = false; };
    }
    http.get(`/account-invitations/${encodeURIComponent(token)}`)
      .then(({ data }) => { if (active) setAccount(data); })
      .catch((requestError) => {
        if (active) setError(requestError.response?.data?.message || "This setup link is invalid or expired.");
      })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [http, token]);

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const { data } = await http.post("/account-invitations/complete", {
        token,
        password,
        password_confirmation: confirmation,
      });
      setComplete(true);
      setError(data.message);
    } catch (requestError) {
      setError(
        Object.values(requestError.response?.data?.errors || {}).flat()[0]
          || requestError.response?.data?.message
          || "Could not set the password. Please retry.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="events-section invitation-setup">
      <form className="settings-card" onSubmit={submit}>
        <div className="settings-card-heading"><KeyRound size={20} /><div><h3>Set up your account</h3><p>Create a private password for CampusEvents Hub.</p></div></div>
        {!loading && account && <p className="invitation-account">{account.name} · {account.email} · {account.role}{account.department ? ` · ${account.department}` : ""}</p>}
        {error && <div className={complete ? "event-report-success" : "event-report-error"} role={complete ? "status" : "alert"}>{error}</div>}
        {account && !complete && (
          <>
            <label>New password (at least 12 characters)<input type="password" minLength={12} autoComplete="new-password" required value={password} onChange={(e) => setPassword(e.target.value)} /></label>
            <label>Confirm password<input type="password" minLength={12} autoComplete="new-password" required value={confirmation} onChange={(e) => setConfirmation(e.target.value)} /></label>
            <button className="btn btn-primary" type="submit" disabled={busy}><KeyRound size={15} />Set password</button>
          </>
        )}
        {complete && <button className="btn btn-primary" type="button" onClick={onLogin}><LogIn size={15} />Continue to login</button>}
      </form>
    </section>
  );
}
