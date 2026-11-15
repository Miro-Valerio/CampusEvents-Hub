import React, { useCallback, useEffect, useState } from "react";
import {
  CalendarDays,
  Check,
  ClipboardList,
  Eye,
  Landmark,
  Megaphone,
  Pencil,
  Plus,
  RefreshCw,
  Save,
  Search,
  ShieldCheck,
  Trash2,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import "../css/admin-dashboard.css";
import ADMIN_SECTIONS from "../adminSections";

const DEFAULT_USER_FORM = {
  name: "",
  email: "",
  role: "faculty",
  department: "",
  course: "",
};
const DEFAULT_ANNOUNCEMENT_FORM = {
  title: "",
  body: "",
  audience: "campus",
  department_id: "",
  course: "",
  pinned: false,
};
const DEFAULT_SETTINGS = {
  campus_name: "CampusEvents Hub",
  public_registration_enabled: true,
  campus_announcements_enabled: true,
  minimum_password_length: 12,
};

function messageFrom(error) {
  return error.response?.data?.message
    || Object.values(error.response?.data?.errors || {}).flat()[0]
    || "The request could not be completed. Please retry.";
}

function localDateTime(value) {
  if (!value) return "";
  const date = new Date(value);
  const offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString([], { dateStyle: "medium", timeStyle: "short" });
}

function Field({ label, children }) {
  return <label className="admin-field">{label}{children}</label>;
}

function Stat({ label, value, icon: Icon }) {
  return (
    <article className="dashboard-stat">
      <span className="dashboard-stat-icon"><Icon size={19} /></span>
      <div><strong>{Number(value || 0).toLocaleString()}</strong><small>{label}</small></div>
    </article>
  );
}

export default function AdminDashboard({ http, user, onNotice, section, onSectionChange }) {
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [overview, setOverview] = useState({});
  const [users, setUsers] = useState([]);
  const [userPage, setUserPage] = useState(1);
  const [userPagination, setUserPagination] = useState({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
  const [departments, setDepartments] = useState([]);
  const [organizerChoices, setOrganizerChoices] = useState([]);
  const [departmentDetail, setDepartmentDetail] = useState(null);
  const [events, setEvents] = useState([]);
  const [eventDetail, setEventDetail] = useState(null);
  const [eventPage, setEventPage] = useState(1);
  const [eventPagination, setEventPagination] = useState({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
  const [announcements, setAnnouncements] = useState([]);
  const [reports, setReports] = useState([]);
  const [analytics, setAnalytics] = useState(null);
  const [activity, setActivity] = useState([]);
  const [settings, setSettings] = useState(DEFAULT_SETTINGS);
  const [userFilters, setUserFilters] = useState({ search: "", role: "", department: "" });
  const [eventFilters, setEventFilters] = useState({ search: "", department_id: "", from: "", to: "", trashed: "" });
  const [analyticsFilters, setAnalyticsFilters] = useState({ from: "", to: "", department_id: "" });
  const [userForm, setUserForm] = useState(DEFAULT_USER_FORM);
  const [editingUserId, setEditingUserId] = useState(null);
  const [userFormOpen, setUserFormOpen] = useState(false);
  const [departmentForm, setDepartmentForm] = useState({ code: "", name: "", description: "", is_active: true, organizer_user_id: "" });
  const [editingDepartmentId, setEditingDepartmentId] = useState(null);
  const [eventEdit, setEventEdit] = useState(null);
  const [announcementForm, setAnnouncementForm] = useState(DEFAULT_ANNOUNCEMENT_FORM);
  const [editingAnnouncementId, setEditingAnnouncementId] = useState(null);
  const [reportNotes, setReportNotes] = useState({});
  const [activitySearch, setActivitySearch] = useState("");

  const loadSection = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      if (section === "overview") {
        const [stats, reportsResponse] = await Promise.all([
          http.get("/admin/overview"),
          http.get("/admin/reports"),
        ]);
        setOverview(stats.data);
        setReports(reportsResponse.data.data || []);
      } else if (section === "users") {
        const params = { ...Object.fromEntries(Object.entries(userFilters).filter(([, value]) => value)), page: userPage };
        const [response, departmentsResponse] = await Promise.all([
          http.get("/admin/users", { params }),
          http.get("/admin/departments"),
        ]);
        setUsers(response.data.data || []);
        setUserPagination(response.data);
        setDepartments(departmentsResponse.data.data || []);
      } else if (section === "departments") {
        const [response, organizersResponse] = await Promise.all([
          http.get("/admin/departments"),
          http.get("/admin/users", { params: { role: "organizer", active: true, per_page: 100 } }),
        ]);
        setDepartments(response.data.data || []);
        setOrganizerChoices(organizersResponse.data.data || []);
      } else if (section === "events") {
        const params = { ...Object.fromEntries(Object.entries(eventFilters).filter(([, value]) => value)), page: eventPage };
        const [response, departmentsResponse] = await Promise.all([
          http.get("/admin/events", { params }),
          http.get("/admin/departments"),
        ]);
        setEvents(response.data.data || []);
        setEventPagination(response.data);
        setDepartments(departmentsResponse.data.data || []);
      } else if (section === "announcements") {
        const [response, departmentsResponse] = await Promise.all([
          http.get("/admin/announcements"),
          http.get("/admin/departments"),
        ]);
        setAnnouncements(response.data.data || []);
        setDepartments(departmentsResponse.data.data || []);
      } else if (section === "reports") {
        const response = await http.get("/admin/reports");
        setReports(response.data.data || []);
      } else if (section === "analytics") {
        const params = Object.fromEntries(Object.entries(analyticsFilters).filter(([, value]) => value));
        const [response, departmentsResponse] = await Promise.all([
          http.get("/admin/analytics", { params }),
          http.get("/admin/departments"),
        ]);
        setAnalytics(response.data);
        setDepartments(departmentsResponse.data.data || []);
      } else if (section === "activity") {
        const response = await http.get("/admin/activity", {
          params: activitySearch ? { search: activitySearch } : {},
        });
        setActivity(response.data.data || []);
      } else if (section === "settings") {
        const response = await http.get("/admin/settings");
        setSettings({ ...DEFAULT_SETTINGS, ...response.data.data });
      }
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setLoading(false);
    }
  }, [http, section, userFilters, userPage, eventFilters, eventPage, analyticsFilters, activitySearch]);

  useEffect(() => setUserPage(1), [userFilters]);
  useEffect(() => setEventPage(1), [eventFilters]);

  useEffect(() => {
    loadSection();
  }, [loadSection]);

  const submitUser = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      if (editingUserId) {
        await http.put(`/admin/users/${editingUserId}`, {
          ...userForm,
          department: userForm.role === "admin" ? null : userForm.department,
        });
        onNotice("Account details updated.");
      } else {
        const payload = {
          ...userForm,
          department: userForm.role === "admin" ? null : userForm.department,
        };
        await http.post("/admin/users", payload);
        onNotice("Account created. A password setup invitation was sent to the email address.");
      }
      setUserForm(DEFAULT_USER_FORM);
      setEditingUserId(null);
      setUserFormOpen(false);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const startEditUser = (user) => {
    setUserForm({
      name: user.name,
      email: user.email,
      role: user.role,
      department: user.department || "",
      course: user.course || "",
    });
    setEditingUserId(user.id);
    setUserFormOpen(true);
  };

  const deactivateUser = async (user) => {
    if (!window.confirm(`Deactivate ${user.name}'s account? Existing RSVPs and attendance history will remain.`)) return;
    setBusy(true);
    try {
      await http.delete(`/admin/users/${user.id}`);
      onNotice("Account deactivated.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const resendInvitation = async (user) => {
    setBusy(true);
    try {
      const { data } = await http.post(`/admin/users/${user.id}/invitation`);
      onNotice(data.message);
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const activateUser = async (user) => {
    if (!window.confirm(`Reactivate ${user.name}'s account?`)) return;
    setBusy(true);
    try {
      await http.put(`/admin/users/${user.id}`, { is_active: true });
      onNotice("Account reactivated.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const saveDepartment = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const payload = editingDepartmentId
        ? {
          name: departmentForm.name,
          description: departmentForm.description,
          is_active: departmentForm.is_active,
          organizer_user_id: departmentForm.organizer_user_id ? Number(departmentForm.organizer_user_id) : null,
        }
        : departmentForm;
      if (editingDepartmentId) {
        await http.put(`/admin/departments/${editingDepartmentId}`, payload);
        onNotice("Department updated.");
      } else {
        await http.post("/admin/departments", payload);
        onNotice("Department created.");
      }
      setDepartmentForm({ code: "", name: "", description: "", is_active: true, organizer_user_id: "" });
      setEditingDepartmentId(null);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const deleteDepartment = async (department) => {
    if (!window.confirm(`Delete ${department.name}? Departments with members, requests, events, or announcements cannot be deleted.`)) return;
    try {
      await http.delete(`/admin/departments/${department.id}`);
      onNotice("Department deleted.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const viewDepartment = async (department) => {
    setError("");
    try {
      const { data } = await http.get(`/admin/departments/${department.id}`);
      setDepartmentDetail(data.data);
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const viewEvent = async (event) => {
    setError("");
    try {
      const { data } = await http.get(`/admin/events/${event.id}`);
      setEventDetail(data.data);
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const saveEvent = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      await http.put(`/admin/events/${eventEdit.id}`, {
        ...eventEdit,
        capacity: Number(eventEdit.capacity),
      });
      onNotice("Event updated.");
      setEventEdit(null);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const eventAction = async (event, action) => {
    if (action === "delete" && !window.confirm(`Remove "${event.title}"? Registration and attendance records will be preserved.`)) return;
    if (action === "unpublish" && !window.confirm(`Unpublish "${event.title}" from Discover?`)) return;
    try {
      if (action === "delete") await http.delete(`/admin/events/${event.id}`);
      else if (action === "restore") await http.post(`/admin/events/${event.id}/restore`);
      else await http.post(`/admin/events/${event.id}/unpublish`);
      onNotice(action === "delete" ? "Event removed." : action === "restore" ? "Event restored as unpublished." : "Event unpublished.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const saveAnnouncement = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const payload = {
        ...announcementForm,
        department_id: announcementForm.audience === "department" ? Number(announcementForm.department_id) : null,
      };
      if (editingAnnouncementId) {
        await http.put(`/admin/announcements/${editingAnnouncementId}`, payload);
        onNotice("Announcement updated successfully.");
      } else {
        await http.post("/admin/announcements", payload);
        onNotice("Announcement published successfully.");
      }
      setAnnouncementForm(DEFAULT_ANNOUNCEMENT_FORM);
      setEditingAnnouncementId(null);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const deleteAnnouncement = async (announcement) => {
    if (!window.confirm(`Delete announcement "${announcement.title}"?`)) return;
    try {
      await http.delete(`/admin/announcements/${announcement.id}`);
      onNotice("Announcement removed.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const resolveReport = async (report, action) => {
    const prompts = {
      dismiss: `Dismiss the report about "${report.event?.title || "this event"}"?`,
      unpublish: `Unpublish "${report.event?.title || "this event"}" from Discover?`,
      remove: `Remove "${report.event?.title || "this event"}" while preserving its records?`,
      review: "Mark this report as reviewed?",
    };
    if (!window.confirm(prompts[action])) return;
    try {
      await http.put(`/admin/reports/${report.id}`, {
        action,
        review_notes: reportNotes[report.id] || "",
        resolution: reportNotes[report.id] || "",
      });
      onNotice("Report resolution recorded.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const saveSettings = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const { data } = await http.put("/admin/settings", settings);
      setSettings({ ...DEFAULT_SETTINGS, ...data.data });
      onNotice("System settings saved.");
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const heading = ADMIN_SECTIONS.find(([key]) => key === section)?.[1] || "Dashboard";

  return (
    <section className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-sidebar-title"><ShieldCheck size={20} /><span>ADMINISTRATION</span></div>
        <nav aria-label="Admin dashboard navigation">
          {ADMIN_SECTIONS.map(([key, label, Icon]) => (
            <button
              type="button"
              key={key}
              className={section === key ? "active" : ""}
              onClick={() => onSectionChange(key)}
            >
              <Icon size={17} /> <span>{label}</span>
              {key === "reports" && overview.pending_reports > 0 && <b>{overview.pending_reports}</b>}
            </button>
          ))}
        </nav>
        <div className="admin-sidebar-note">Administrator access is logged.</div>
      </aside>

      <div className="admin-content">
        <header className="admin-page-heading">
          <div>
            <div className="eyebrow muted">
              {section === "overview" ? "YOUR ADMIN DASHBOARD" : "SYSTEM ADMINISTRATION"}
            </div>
            <h1>
              {section === "overview" && user?.name
                ? <>Welcome, <span>{user.name}</span>.</>
                : <>{heading}<span>.</span></>}
            </h1>
          </div>
          <button className="btn btn-outline" type="button" onClick={loadSection} disabled={loading}>
            <RefreshCw size={15} /> Refresh
          </button>
        </header>
        {error && <div className="dashboard-error" role="alert">{error}</div>}
        {section === "overview" && (
          <>
            <div className="dashboard-stats admin-stats-grid">
              <Stat label="Total Students" value={overview.total_students} icon={Users} />
              <Stat label="Total Faculty" value={overview.total_faculty} icon={Users} />
              <Stat label="Total Organizers" value={overview.total_organizers} icon={ShieldCheck} />
              <Stat label="Departments with organizers" value={overview.assigned_department_organizers} icon={Landmark} />
              <Stat label="Total Departments" value={overview.total_departments} icon={Landmark} />
              <Stat label="Total Events" value={overview.total_events} icon={CalendarDays} />
              <Stat label="Upcoming Events" value={overview.upcoming_events} icon={CalendarDays} />
              <Stat label="Total Registrations" value={overview.total_registrations} icon={ClipboardList} />
            </div>
            <section className="dashboard-panel admin-overview-note">
              <h2>System overview</h2>
              <p>Each department has one administrator-designated organizer. Students request department membership, and that organizer reviews the requests.</p>
            </section>
          </>
        )}

        {section === "users" && (
          <>
            <div className="admin-toolbar">
              <label className="admin-search"><Search size={16} /><input placeholder="Search name or email" value={userFilters.search} onChange={(e) => setUserFilters((f) => ({ ...f, search: e.target.value }))} /></label>
              <select value={userFilters.role} onChange={(e) => setUserFilters((f) => ({ ...f, role: e.target.value }))}>
                <option value="">All roles</option>{["student", "faculty", "organizer", "admin"].map((role) => <option key={role}>{role}</option>)}
              </select>
              <select value={userFilters.department} onChange={(e) => setUserFilters((f) => ({ ...f, department: e.target.value }))}>
                <option value="">All departments</option>{departments.map((dept) => <option key={dept.id} value={dept.code}>{dept.code}</option>)}
              </select>
              <button className="btn btn-primary" type="button" onClick={() => { setUserForm(DEFAULT_USER_FORM); setEditingUserId(null); setUserFormOpen((open) => !open); }}>
                <UserPlus size={15} /> Invite account
              </button>
            </div>
            {userFormOpen && (
              <form className="settings-card admin-form" onSubmit={submitUser}>
                <h2>{editingUserId ? "Edit account" : "Invite a user"}</h2>
                <p>New accounts receive a single-use password setup link by email. Passwords are never shown to admins.</p>
                <div className="admin-form-grid">
                  <Field label="Full name"><input required maxLength={120} value={userForm.name} onChange={(e) => setUserForm({ ...userForm, name: e.target.value })} /></Field>
                  <Field label="Email"><input required type="email" value={userForm.email} onChange={(e) => setUserForm({ ...userForm, email: e.target.value })} /></Field>
                  <Field label="Role"><select value={userForm.role} onChange={(e) => setUserForm({ ...userForm, role: e.target.value })}>{["student", "faculty", "organizer", "admin"].map((role) => <option key={role}>{role}</option>)}</select></Field>
                  {userForm.role !== "admin" && <Field label="Department"><select required value={userForm.department} onChange={(e) => setUserForm({ ...userForm, department: e.target.value })}><option value="">Select a department</option>{departments.filter((d) => d.is_active && !d.is_legacy).map((d) => <option key={d.id} value={d.code}>{d.name} ({d.code})</option>)}</select></Field>}
                  {userForm.role === "student" && <Field label="Course (optional)"><input value={userForm.course} onChange={(e) => setUserForm({ ...userForm, course: e.target.value })} /></Field>}
                  {userForm.role === "organizer" && <p className="admin-form-hint">After account setup, assign this organizer to the department in Colleges / Departments.</p>}
                </div>
                <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Save size={15} />{editingUserId ? "Save changes" : "Send invitation"}</button><button type="button" className="btn btn-outline" onClick={() => setUserFormOpen(false)}>Cancel</button></div>
              </form>
            )}
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {users.map((user) => {
                const hasCompletedInvitation = user.invitations?.some((invitation) => invitation.used_at);
                const hasPendingInvitation = user.invitations?.some((invitation) => !invitation.used_at);
                const pendingDepartment = user.department_membership_requests?.[0]?.department;
                return <tr key={user.id}><td><b>{user.name}</b><small>{user.email}</small></td><td>{user.role}</td><td>{user.department || (pendingDepartment ? `Request pending: ${pendingDepartment.code}` : "—")}</td><td><span className={`admin-status ${user.is_active ? "active" : "inactive"}`}>{user.is_active ? "Active" : hasPendingInvitation ? "Invitation pending" : "Inactive"}</span></td><td className="admin-row-actions"><button title="Edit account" onClick={() => startEditUser(user)}><Pencil size={15} /></button>{!user.is_active ? hasCompletedInvitation ? <button title="Reactivate account" onClick={() => activateUser(user)}><Check size={15} /></button> : <button title="Resend invitation" onClick={() => resendInvitation(user)}><RefreshCw size={15} /></button> : <button title="Deactivate account" onClick={() => deactivateUser(user)}><Trash2 size={15} /></button>}</td></tr>;
              })}
            </tbody></table>{!users.length && <p className="dashboard-empty">No accounts match these filters.</p>}</div>
            <div className="admin-pagination">
              <span>Showing {userPagination.from || 0}–{userPagination.to || 0} of {userPagination.total || 0} accounts</span>
              <div><button className="btn btn-outline" type="button" disabled={userPage <= 1 || loading} onClick={() => setUserPage((page) => page - 1)}>Previous</button><span>Page {userPagination.current_page || userPage} of {userPagination.last_page || 1}</span><button className="btn btn-outline" type="button" disabled={userPage >= (userPagination.last_page || 1) || loading} onClick={() => setUserPage((page) => page + 1)}>Next</button></div>
            </div>
          </>
        )}

        {section === "departments" && (
          <>
            <form className="settings-card admin-form admin-department-form" onSubmit={saveDepartment}>
              <h2>{editingDepartmentId ? "Edit department" : "Add department"}</h2>
              <div className="admin-form-grid">
                {!editingDepartmentId && <Field label="Code"><input required maxLength={20} pattern="[A-Z0-9_-]+" value={departmentForm.code} onChange={(e) => setDepartmentForm({ ...departmentForm, code: e.target.value.toUpperCase() })} /></Field>}
                <Field label="Department name"><input required maxLength={190} value={departmentForm.name} onChange={(e) => setDepartmentForm({ ...departmentForm, name: e.target.value })} /></Field>
                <Field label="Description"><input value={departmentForm.description} maxLength={2000} onChange={(e) => setDepartmentForm({ ...departmentForm, description: e.target.value })} /></Field>
                {editingDepartmentId && <Field label="Designated organizer"><select value={departmentForm.organizer_user_id} onChange={(e) => setDepartmentForm({ ...departmentForm, organizer_user_id: e.target.value })}><option value="">Unassigned</option>{organizerChoices.filter((organizer) => organizer.department === departments.find((department) => department.id === editingDepartmentId)?.code).map((organizer) => <option key={organizer.id} value={organizer.id}>{organizer.name} · {organizer.email}</option>)}</select></Field>}
                {editingDepartmentId && <Field label="Status"><select value={departmentForm.is_active ? "true" : "false"} onChange={(e) => setDepartmentForm({ ...departmentForm, is_active: e.target.value === "true" })}><option value="true">Active</option><option value="false">Inactive</option></select></Field>}
              </div>
              <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Save size={15} />Save department</button>{editingDepartmentId && <button type="button" className="btn btn-outline" onClick={() => { setEditingDepartmentId(null); setDepartmentForm({ code: "", name: "", description: "", is_active: true, organizer_user_id: "" }); }}>Cancel</button>}</div>
            </form>
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Department</th><th>Designated organizer</th><th>Members</th><th>Events</th><th>Pending requests</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {departments.map((dept) => <tr key={dept.id}><td><b>{dept.name}</b><small>{dept.code}{dept.is_legacy ? " · legacy" : ""}</small></td><td>{dept.designated_organizer?.name || "Unassigned"}</td><td>{dept.users_count}</td><td>{dept.events_count}</td><td>{dept.pending_requests_count}</td><td>{dept.is_active ? "Active" : "Inactive"}</td><td className="admin-row-actions"><button title="View department members, requests, and events" onClick={() => viewDepartment(dept)}><Eye size={15} /></button><button title="Edit department" onClick={() => { setEditingDepartmentId(dept.id); setDepartmentForm({ name: dept.name, description: dept.description || "", is_active: dept.is_active, organizer_user_id: String(dept.organizer_user_id || "") }); }}><Pencil size={15} /></button><button title="Delete department" onClick={() => deleteDepartment(dept)}><Trash2 size={15} /></button></td></tr>)}
            </tbody></table></div>
            {departmentDetail && <section className="dashboard-panel admin-detail-panel"><div className="admin-form-heading"><h2>{departmentDetail.name} ({departmentDetail.code})</h2><button type="button" aria-label="Close department details" onClick={() => setDepartmentDetail(null)}><X size={18} /></button></div><h3>Designated organizer</h3><p>{departmentDetail.designated_organizer?.name || "No organizer assigned."}</p><h3>Department members</h3>{departmentDetail.users?.length ? <div className="admin-detail-list">{departmentDetail.users.map((member) => <p key={member.id}><b>{member.name}</b><span>{member.role} · {member.email}</span></p>)}</div> : <p>No users assigned.</p>}<h3>Pending student requests</h3>{departmentDetail.membership_requests?.length ? <div className="admin-detail-list">{departmentDetail.membership_requests.map((request) => <p key={request.id}><b>{request.user?.name}</b><span>{request.user?.email} · {formatDate(request.created_at)}</span></p>)}</div> : <p>No pending requests.</p>}<h3>Department events</h3>{departmentDetail.organizers?.flatMap((record) => record.events || []).length ? <ul>{departmentDetail.organizers.flatMap((record) => record.events || []).map((event) => <li key={event.id}>{event.title} · {formatDate(event.starts_at)} · {event.is_cancelled ? "Cancelled" : event.is_published ? "Published" : "Unpublished"}</li>)}</ul> : <p>No events.</p>}</section>}
          </>
        )}

        {section === "events" && (
          <>
            <div className="admin-toolbar">
              <label className="admin-search"><Search size={16} /><input placeholder="Search events" value={eventFilters.search} onChange={(e) => setEventFilters((f) => ({ ...f, search: e.target.value }))} /></label>
              <select value={eventFilters.department_id} onChange={(e) => setEventFilters((f) => ({ ...f, department_id: e.target.value }))}><option value="">All departments</option>{departments.map((d) => <option key={d.id} value={d.id}>{d.code}</option>)}</select>
              <label className="admin-date-filter">From<input type="date" value={eventFilters.from} onChange={(e) => setEventFilters((f) => ({ ...f, from: e.target.value }))} /></label>
              <label className="admin-date-filter">To<input type="date" value={eventFilters.to} onChange={(e) => setEventFilters((f) => ({ ...f, to: e.target.value }))} /></label>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventFilters.trashed)} onChange={(e) => setEventFilters((f) => ({ ...f, trashed: e.target.checked ? "1" : "" }))} /> Include removed</label>
            </div>
            {eventEdit && <form className="settings-card admin-form" onSubmit={saveEvent}><div className="admin-form-heading"><h2>Edit event</h2><button type="button" aria-label="Close event editor" onClick={() => setEventEdit(null)}><X size={18} /></button></div><div className="admin-form-grid">
              {["title", "venue", "category", "poster_url"].map((key) => <Field key={key} label={key.replace("_", " ")}><input required={key !== "poster_url"} value={eventEdit[key] || ""} onChange={(e) => setEventEdit({ ...eventEdit, [key]: e.target.value })} /></Field>)}
              <Field label="Department"><input value={eventEdit.organizer?.department?.name || "Unassigned"} readOnly /></Field>
              <Field label="Starts at"><input required type="datetime-local" value={localDateTime(eventEdit.starts_at)} onChange={(e) => setEventEdit({ ...eventEdit, starts_at: e.target.value })} /></Field>
              <Field label="Ends at"><input type="datetime-local" value={localDateTime(eventEdit.ends_at)} onChange={(e) => setEventEdit({ ...eventEdit, ends_at: e.target.value })} /></Field>
              <Field label="Capacity"><input required type="number" min="1" value={eventEdit.capacity} onChange={(e) => setEventEdit({ ...eventEdit, capacity: e.target.value })} /></Field>
              <Field label="Description"><textarea required rows={3} value={eventEdit.description} onChange={(e) => setEventEdit({ ...eventEdit, description: e.target.value })} /></Field>
              <Field label="Rules"><textarea rows={3} value={eventEdit.rules || ""} onChange={(e) => setEventEdit({ ...eventEdit, rules: e.target.value })} /></Field>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventEdit.is_published)} onChange={(e) => setEventEdit({ ...eventEdit, is_published: e.target.checked })} /> Published on Discover</label>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventEdit.is_cancelled)} onChange={(e) => setEventEdit({ ...eventEdit, is_cancelled: e.target.checked })} /> Cancelled</label>
            </div><button className="btn btn-primary" disabled={busy}><Save size={15} />Save event</button></form>}
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Event</th><th>Department</th><th>Date</th><th>Registrations</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {events.map((event) => <tr key={event.id}><td><b>{event.title}</b><small>{event.venue}</small></td><td>{event.organizer?.department?.name || "Unassigned"}<small>{event.organizer?.department?.code || "—"}</small></td><td>{formatDate(event.starts_at)}</td><td>{event.registrations_count || 0}</td><td>{event.deleted_at ? "Removed" : event.is_cancelled ? "Cancelled" : event.is_published ? "Published" : "Unpublished"}</td><td className="admin-row-actions"><button title="View event registrations and attendance" onClick={() => viewEvent(event)}><Eye size={15} /></button>{!event.deleted_at && <button title="Edit event" onClick={() => setEventEdit(event)}><Pencil size={15} /></button>}{event.deleted_at ? <button title="Restore as unpublished" onClick={() => eventAction(event, "restore")}><RefreshCw size={15} /></button> : <><button title="Unpublish event" onClick={() => eventAction(event, "unpublish")}><X size={15} /></button><button title="Remove event" onClick={() => eventAction(event, "delete")}><Trash2 size={15} /></button></>}</td></tr>)}
            </tbody></table>{!events.length && <p className="dashboard-empty">No events match these filters.</p>}</div>
            {eventDetail && <section className="dashboard-panel admin-detail-panel"><div className="admin-form-heading"><div><h2>{eventDetail.title}</h2><p>{eventDetail.organizer?.name || "Organization"} · {formatDate(eventDetail.starts_at)} · {eventDetail.venue}</p></div><button type="button" aria-label="Close event details" onClick={() => setEventDetail(null)}><X size={18} /></button></div><p>{eventDetail.description}</p><div className="admin-detail-summary"><span>{eventDetail.registrations_count || 0} registrations</span><span>{eventDetail.attendance_count || 0} check-ins</span><span>{eventDetail.deleted_at ? "Removed" : eventDetail.is_cancelled ? "Cancelled" : eventDetail.is_published ? "Published" : "Unpublished"}</span></div><h3>Registration and attendance records</h3>{eventDetail.rsvps?.length ? <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Attendee</th><th>Email</th><th>Registration</th><th>Attendance</th><th>Checked in</th></tr></thead><tbody>{eventDetail.rsvps.map((rsvp) => <tr key={rsvp.id}><td>{rsvp.user?.name || "Deleted account"}</td><td>{rsvp.user?.email || "—"}</td><td>{rsvp.status}</td><td>{rsvp.checked_in_at ? "Present" : "Not checked in"}</td><td>{formatDate(rsvp.checked_in_at)}</td></tr>)}</tbody></table></div> : <p>No registration records.</p>}</section>}
            <div className="admin-pagination">
              <span>Showing {eventPagination.from || 0}–{eventPagination.to || 0} of {eventPagination.total || 0} events</span>
              <div><button className="btn btn-outline" type="button" disabled={eventPage <= 1 || loading} onClick={() => setEventPage((page) => page - 1)}>Previous</button><span>Page {eventPagination.current_page || eventPage} of {eventPagination.last_page || 1}</span><button className="btn btn-outline" type="button" disabled={eventPage >= (eventPagination.last_page || 1) || loading} onClick={() => setEventPage((page) => page + 1)}>Next</button></div>
            </div>
          </>
        )}

        {section === "announcements" && (
          <>
            <form className="settings-card admin-form" onSubmit={saveAnnouncement}>
              <h2>{editingAnnouncementId ? "Edit announcement" : "Publish announcement"}</h2>
              <div className="admin-form-grid">
                <Field label="Title"><input required maxLength={190} value={announcementForm.title} onChange={(e) => setAnnouncementForm({ ...announcementForm, title: e.target.value })} /></Field>
                <Field label="Audience"><select value={announcementForm.audience} onChange={(e) => setAnnouncementForm({ ...announcementForm, audience: e.target.value, department_id: "" })}><option value="campus">Campus-wide</option><option value="department">Specific department</option></select></Field>
                {announcementForm.audience === "department" && <Field label="Department"><select required value={announcementForm.department_id} onChange={(e) => setAnnouncementForm({ ...announcementForm, department_id: e.target.value })}><option value="">Choose department</option>{departments.filter((d) => d.is_active).map((d) => <option key={d.id} value={d.id}>{d.name} ({d.code})</option>)}</select></Field>}
                <Field label="Course audience (optional)"><input maxLength={100} value={announcementForm.course} onChange={(e) => setAnnouncementForm({ ...announcementForm, course: e.target.value })} /></Field>
                <Field label="Announcement"><textarea required rows={4} maxLength={10000} value={announcementForm.body} onChange={(e) => setAnnouncementForm({ ...announcementForm, body: e.target.value })} /></Field>
                <label className="admin-checkbox"><input type="checkbox" checked={announcementForm.pinned} onChange={(e) => setAnnouncementForm({ ...announcementForm, pinned: e.target.checked })} /> Pin announcement</label>
              </div>
              <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Megaphone size={15} />{editingAnnouncementId ? "Save changes" : "Publish now"}</button>{editingAnnouncementId && <button type="button" className="btn btn-outline" onClick={() => { setEditingAnnouncementId(null); setAnnouncementForm(DEFAULT_ANNOUNCEMENT_FORM); }}>Cancel</button>}</div>
            </form>
            <div className="admin-card-grid">{announcements.map((announcement) => <article className="dashboard-panel admin-entity-card" key={announcement.id}><div className="admin-entity-heading"><div><h2>{announcement.title}</h2><p>{announcement.department?.name || "Campus-wide"}{announcement.course ? ` · ${announcement.course}` : ""} · {formatDate(announcement.created_at)}</p></div></div><p>{announcement.body}</p><small>By {announcement.author?.name || announcement.organizer?.name || "Campus Administration"} · {announcement.reads_count || 0} reads · {announcement.reactions_count || 0} reactions</small><div className="admin-actions"><button className="btn btn-outline" onClick={() => { setEditingAnnouncementId(announcement.id); setAnnouncementForm({ title: announcement.title, body: announcement.body, audience: announcement.department_id ? "department" : "campus", department_id: announcement.department_id || "", course: announcement.course || "", pinned: announcement.pinned }); }}><Pencil size={14} /> Edit</button><button className="btn btn-outline danger" onClick={() => deleteAnnouncement(announcement)}><Trash2 size={14} /> Delete</button></div></article>)}</div>
          </>
        )}

        {section === "reports" && (
          <div className="admin-report-list">{reports.map((report) => <article className="dashboard-panel admin-report" key={report.id}><div className="admin-entity-heading"><div><h2>{report.event?.title || "Event removed"}</h2><p>Reported by {report.user?.name || "Unknown"} ({report.user?.email || "—"}) · {formatDate(report.created_at)}</p></div><span className={`admin-status ${report.status === "pending" ? "inactive" : "active"}`}>{report.status}</span></div><p><b>Reason:</b> {report.reason?.replaceAll("_", " ")}. {report.details || "No extra details."}</p><Field label="Investigation / resolution notes"><textarea rows={2} maxLength={2000} value={reportNotes[report.id] ?? report.resolution ?? report.review_notes ?? ""} onChange={(e) => setReportNotes({ ...reportNotes, [report.id]: e.target.value })} /></Field>{report.status === "pending" && <div className="admin-actions"><button className="btn btn-primary" onClick={() => resolveReport(report, "review")}><Check size={14} /> Mark reviewed</button><button className="btn btn-outline" onClick={() => resolveReport(report, "dismiss")}>Dismiss</button><button className="btn btn-outline" onClick={() => resolveReport(report, "unpublish")}>Unpublish event</button><button className="btn btn-outline danger" onClick={() => resolveReport(report, "remove")}>Remove event</button></div>}{report.status !== "pending" && <small>Resolution: {report.resolution || report.review_notes || "—"} · {report.reviewer?.name || "Admin"}</small>}</article>)}{!reports.length && <p className="dashboard-empty">No event reports have been submitted.</p>}</div>
        )}

        {section === "analytics" && (
          <>
            <div className="admin-toolbar">
              <Field label="From"><input type="date" value={analyticsFilters.from} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, from: e.target.value })} /></Field>
              <Field label="To"><input type="date" value={analyticsFilters.to} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, to: e.target.value })} /></Field>
              <select value={analyticsFilters.department_id} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, department_id: e.target.value })}><option value="">All departments</option>{departments.map((d) => <option key={d.id} value={d.id}>{d.code}</option>)}</select>
            </div>
            <div className="dashboard-stats admin-stats-grid">
              <Stat label="Students" value={analytics?.totals.students} icon={Users} />
              <Stat label="Faculty" value={analytics?.totals.faculty} icon={Users} />
              <Stat label="Organizers" value={analytics?.totals.organizers} icon={ShieldCheck} />
              <Stat label="Events" value={analytics?.totals.events} icon={CalendarDays} />
              <Stat label="Registrations" value={analytics?.totals.registrations} icon={ClipboardList} />
              <Stat label="Check-ins" value={analytics?.totals.attendances} icon={Check} />
            </div>
            <div className="admin-analytics-grid">
              <section className="dashboard-panel"><h2>Events per department</h2>{analytics?.events_per_department?.map((row) => <div className="admin-bar-row" key={row.department_id || "none"}><span>{row.code || "Unassigned"}</span><b>{row.events_count}</b><i style={{ width: `${Math.min(100, Number(row.events_count) * 10)}%` }} /></div>)}</section>
              <section className="dashboard-panel"><h2>Most attended events</h2>{analytics?.most_attended_events?.map((event) => <p className="admin-list-row" key={event.id}><b>{event.title}</b><span>{event.attendance_count || 0} check-ins</span></p>)}</section>
              <section className="dashboard-panel"><h2>Department activity</h2>{analytics?.department_activity?.map((department) => <p className="admin-list-row" key={department.department_id || "none"}><b>{department.name || "Unassigned"}</b><span>{department.events_count} published events · {department.code || "No code"}</span></p>)}</section>
            </div>
          </>
        )}

        {section === "activity" && (
          <>
            <div className="admin-toolbar"><label className="admin-search"><Search size={16} /><input placeholder="Search activity" value={activitySearch} onChange={(e) => setActivitySearch(e.target.value)} /></label></div>
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Who</th><th>Action</th><th>Affected record</th><th>Details</th><th>Date / time</th></tr></thead><tbody>
              {activity.map((entry) => <tr key={entry.id}><td>{entry.actor?.name || "Deleted account"}<small>{entry.actor?.email}</small></td><td>{entry.action}</td><td>{entry.record_type}{entry.record_id ? ` #${entry.record_id}` : ""}</td><td>{entry.summary}{entry.details && <small>{JSON.stringify(entry.details)}</small>}</td><td>{formatDate(entry.created_at)}</td></tr>)}
            </tbody></table>{!activity.length && <p className="dashboard-empty">No administrative activity recorded yet.</p>}</div>
          </>
        )}

        {section === "settings" && (
          <>
            <form className="settings-card admin-form admin-settings-form" onSubmit={saveSettings}>
              <h2>System configuration</h2>
              <p>These controls do not expose credentials. Mail delivery depends on the server's configured mail provider.</p>
              <Field label="Campus / system name"><input required maxLength={120} value={settings.campus_name} onChange={(e) => setSettings({ ...settings, campus_name: e.target.value })} /></Field>
              <Field label="Minimum invited-account password length"><input type="number" min={12} max={128} value={settings.minimum_password_length} onChange={(e) => setSettings({ ...settings, minimum_password_length: Number(e.target.value) })} /></Field>
              <label className="notification-preference"><input type="checkbox" checked={settings.public_registration_enabled} onChange={(e) => setSettings({ ...settings, public_registration_enabled: e.target.checked })} /><span><b>Public student registration</b><small>Allow new students to register through the existing signup flow.</small></span></label>
              <label className="notification-preference"><input type="checkbox" checked={settings.campus_announcements_enabled} onChange={(e) => setSettings({ ...settings, campus_announcements_enabled: e.target.checked })} /><span><b>Announcement notifications</b><small>Deliver database notifications to matching students and faculty.</small></span></label>
              <button className="btn btn-primary" disabled={busy}><Save size={15} />Save settings</button>
            </form>
            <section className="dashboard-panel admin-permission-summary"><h2>User permissions</h2><p>Role boundaries are enforced by the API and are not user-editable settings.</p><div className="admin-detail-list"><p><b>Admin</b><span>Full system management and reporting access.</span></p><p><b>Faculty</b><span>May join one department without approval.</span></p><p><b>Organizer</b><span>Review student membership requests and manage events for the designated department.</span></p><p><b>Student</b><span>Discover and RSVP to public events; request membership in one department.</span></p></div></section>
          </>
        )}
      </div>
    </section>
  );
}
