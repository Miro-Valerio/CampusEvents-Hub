import React, { useEffect, useState } from "react";
import { ClipboardCheck, ShieldCheck, UserPlus, Users } from "lucide-react";
import { DEPARTMENTS } from "../constants";

const departmentName = (department) => {
  const names = {
    CCS: "College of Computer Studies (CCS)",
    BSCRIM: "College of Criminology (BSCRIM)",
    COE: "College of Engineering (COE)",
    CED: "College of Education (CED)",
    CAD: "College of Arts and Design (CAD)",
    CBA: "College of Business Administration (CBA)",
  };
  return names[department] || department;
};

const newOrganizer = () => ({
  name: "",
  email: "",
  password: "",
  department: "CCS",
  organization_name: departmentName("CCS"),
  description: "Department events and announcements.",
});

export default function AdminDashboard({ http, onNotice }) {
  const [students, setStudents] = useState([]);
  const [organizers, setOrganizers] = useState([]);
  const [reports, setReports] = useState([]);
  const [organizerForm, setOrganizerForm] = useState(newOrganizer);
  const [selectedStudentId, setSelectedStudentId] = useState("");
  const [promotion, setPromotion] = useState({
    department: "CCS",
    organization_name: departmentName("CCS"),
    description: "Department events and announcements.",
  });
  const [reportNotes, setReportNotes] = useState({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");

  const loadAdminData = async () => {
    setLoading(true);
    setError("");
    try {
      const [studentResponse, organizerResponse, reportResponse] = await Promise.all([
        http.get("/admin/users", { params: { role: "student" } }),
        http.get("/admin/users", { params: { role: "organizer" } }),
        http.get("/admin/reports"),
      ]);
      setStudents(studentResponse.data.data || []);
      setOrganizers(organizerResponse.data.data || []);
      setReports(reportResponse.data.data || []);
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ||
          "Could not load admin data. Check your connection and retry.",
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAdminData();
  }, []);

  const createOrganizer = async (event) => {
    event.preventDefault();
    setBusy("create");
    setError("");
    try {
      await http.post("/admin/organizers", organizerForm);
      setOrganizerForm(newOrganizer());
      onNotice("Department organizer account created. Give the user their password securely and ask them to change it.");
      await loadAdminData();
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ||
          Object.values(requestError.response?.data?.errors || {}).flat()[0] ||
          "Could not create the organizer account.",
      );
    } finally {
      setBusy("");
    }
  };

  const promoteStudent = async (event) => {
    event.preventDefault();
    if (!selectedStudentId) return;
    setBusy("promote");
    setError("");
    try {
      await http.put(`/admin/users/${selectedStudentId}/promote`, promotion);
      setSelectedStudentId("");
      onNotice("Student account promoted to department organizer.");
      await loadAdminData();
    } catch (requestError) {
      setError(
        requestError.response?.data?.message ||
          Object.values(requestError.response?.data?.errors || {}).flat()[0] ||
          "Could not promote the student account.",
      );
    } finally {
      setBusy("");
    }
  };

  const reviewReport = async (report, status) => {
    setBusy(`report-${report.id}`);
    setError("");
    try {
      await http.put(`/admin/reports/${report.id}`, {
        status,
        review_notes: reportNotes[report.id] || "",
      });
      onNotice(status === "reviewed" ? "Report marked as reviewed." : "Report dismissed.");
      await loadAdminData();
    } catch (requestError) {
      setError(requestError.response?.data?.message || "Could not update this report.");
    } finally {
      setBusy("");
    }
  };

  const updateDepartment = (setForm) => (event) => {
    const department = event.target.value;
    setForm((current) => ({
      ...current,
      department,
      organization_name: departmentName(department),
    }));
  };

  return (
    <section className="organizer-dashboard admin-dashboard">
      <div className="dashboard-heading">
        <div>
          <div className="eyebrow muted">SYSTEM ADMINISTRATION</div>
          <h1>Admin <span>dashboard.</span></h1>
          <p>Manage department organizers and review event reports.</p>
        </div>
        <button className="btn btn-outline" type="button" onClick={loadAdminData} disabled={loading}>
          Refresh
        </button>
      </div>

      {error && <div className="dashboard-error" role="alert">{error}</div>}

      {loading ? (
        <div className="loading-line"><span /> Loading admin dashboard...</div>
      ) : (
        <>
          <div className="dashboard-stats">
            {[
              ["Department organizers", organizers.length, <Users size={19} />],
              ["Student accounts", students.length, <Users size={19} />],
              ["Pending reports", reports.filter((report) => report.status === "pending").length, <ClipboardCheck size={19} />],
            ].map(([label, count, icon]) => (
              <article className="dashboard-stat" key={label}>
                <span className="dashboard-stat-icon">{icon}</span>
                <div><strong>{count}</strong><small>{label}</small></div>
              </article>
            ))}
          </div>

          <div className="settings-grid admin-management-grid">
            <form className="settings-card" onSubmit={createOrganizer}>
              <div className="settings-card-heading">
                <UserPlus size={19} />
                <div><h3>Create department account</h3><p>Create an officer or faculty organizer login.</p></div>
              </div>
              <label>Account holder name
                <input required maxLength={120} value={organizerForm.name} onChange={(event) => setOrganizerForm((current) => ({ ...current, name: event.target.value }))} />
              </label>
              <label>Email
                <input required type="email" maxLength={190} value={organizerForm.email} onChange={(event) => setOrganizerForm((current) => ({ ...current, email: event.target.value }))} />
              </label>
              <label>Temporary password (at least 12 characters)
                <input required type="password" minLength={12} value={organizerForm.password} onChange={(event) => setOrganizerForm((current) => ({ ...current, password: event.target.value }))} />
              </label>
              <label>Department
                <select value={organizerForm.department} onChange={updateDepartment(setOrganizerForm)}>
                  {DEPARTMENTS.map((department) => <option key={department} value={department}>{department}</option>)}
                </select>
              </label>
              <label>Organizer display name
                <input required maxLength={190} value={organizerForm.organization_name} onChange={(event) => setOrganizerForm((current) => ({ ...current, organization_name: event.target.value }))} />
              </label>
              <label>Description
                <textarea required maxLength={5000} rows={3} value={organizerForm.description} onChange={(event) => setOrganizerForm((current) => ({ ...current, description: event.target.value }))} />
              </label>
              <button className="btn btn-primary" type="submit" disabled={busy === "create"}>
                <UserPlus size={15} /> {busy === "create" ? "Creating..." : "Create organizer account"}
              </button>
            </form>

            <form className="settings-card" onSubmit={promoteStudent}>
              <div className="settings-card-heading">
                <ShieldCheck size={19} />
                <div><h3>Promote a student officer</h3><p>Give an existing student account organizer access for their department.</p></div>
              </div>
              <label>Student account
                <select required value={selectedStudentId} onChange={(event) => {
                  const selected = students.find((student) => String(student.id) === event.target.value);
                  setSelectedStudentId(event.target.value);
                  if (selected?.department && DEPARTMENTS.includes(selected.department)) {
                    setPromotion((current) => ({
                      ...current,
                      department: selected.department,
                      organization_name: departmentName(selected.department),
                    }));
                  }
                }}>
                  <option value="">Select a registered student</option>
                  {students.map((student) => (
                    <option key={student.id} value={student.id}>
                      {student.name} · {student.department || "No department"} · {student.email}
                    </option>
                  ))}
                </select>
              </label>
              <label>Department
                <select value={promotion.department} onChange={updateDepartment(setPromotion)}>
                  {DEPARTMENTS.map((department) => <option key={department} value={department}>{department}</option>)}
                </select>
              </label>
              <label>Organizer display name
                <input required maxLength={190} value={promotion.organization_name} onChange={(event) => setPromotion((current) => ({ ...current, organization_name: event.target.value }))} />
              </label>
              <label>Description
                <textarea required maxLength={5000} rows={3} value={promotion.description} onChange={(event) => setPromotion((current) => ({ ...current, description: event.target.value }))} />
              </label>
              <button className="btn btn-primary" type="submit" disabled={!selectedStudentId || busy === "promote"}>
                <ShieldCheck size={15} /> {busy === "promote" ? "Promoting..." : "Promote to organizer"}
              </button>
            </form>
          </div>

          <section className="dashboard-panel admin-organizer-list">
            <div className="dashboard-panel-heading">
              <div><h2>Organizer accounts</h2><p>Accounts with department event management access.</p></div>
              <span className="dashboard-count">{organizers.length}</span>
            </div>
            {organizers.length ? (
              <div className="admin-account-list">
                {organizers.map((organizer) => (
                  <article key={organizer.id}>
                    <div><b>{organizer.name}</b><small>{organizer.email}</small></div>
                    <span>{organizer.department || "Department not set"}</span>
                    <small>{organizer.organizer?.name || "Organizer profile missing"}</small>
                  </article>
                ))}
              </div>
            ) : <p className="dashboard-empty">No organizer accounts found.</p>}
          </section>

          <section className="dashboard-panel admin-reports">
            <div className="dashboard-panel-heading">
              <div><h2>Event reports</h2><p>Review reports submitted by students. Reports are visible only to admins.</p></div>
              <span className="dashboard-count">{reports.filter((report) => report.status === "pending").length}</span>
            </div>
            {reports.length ? (
              <div className="admin-report-list">
                {reports.map((report) => (
                  <article className="admin-report" key={report.id}>
                    <div className="admin-report-heading">
                      <div><b>{report.event?.title || "Event removed"}</b><small>Reported by {report.user?.name} ({report.user?.email}) · {new Date(report.created_at).toLocaleString()}</small></div>
                      <span className={`admin-report-status ${report.status}`}>{report.status}</span>
                    </div>
                    <p><b>Reason:</b> {report.reason.replaceAll("_", " ")} · {report.details || "No additional details."}</p>
                    {report.event?.organizer && <small>Organizer: {report.event.organizer.name}</small>}
                    {report.status === "pending" ? (
                      <>
                        <textarea
                          rows={2}
                          maxLength={2000}
                          placeholder="Optional review notes"
                          value={reportNotes[report.id] || ""}
                          onChange={(event) => setReportNotes((current) => ({ ...current, [report.id]: event.target.value }))}
                        />
                        <div className="admin-report-actions">
                          <button className="btn btn-primary" type="button" disabled={busy === `report-${report.id}`} onClick={() => reviewReport(report, "reviewed")}>Mark reviewed</button>
                          <button className="btn btn-outline" type="button" disabled={busy === `report-${report.id}`} onClick={() => reviewReport(report, "dismissed")}>Dismiss</button>
                        </div>
                      </>
                    ) : (
                      <small>Reviewed by {report.reviewer?.name || "Admin"} {report.reviewed_at ? `· ${new Date(report.reviewed_at).toLocaleString()}` : ""}{report.review_notes ? ` · ${report.review_notes}` : ""}</small>
                    )}
                  </article>
                ))}
              </div>
            ) : <p className="dashboard-empty">No event reports have been submitted.</p>}
          </section>
        </>
      )}
    </section>
  );
}
