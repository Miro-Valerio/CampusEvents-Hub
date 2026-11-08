import React, { useCallback, useEffect, useState } from "react";
import {
  BarChart3,
  Building2,
  CalendarDays,
  Check,
  ClipboardList,
  Eye,
  Flag,
  Landmark,
  LayoutDashboard,
  Megaphone,
  Pencil,
  Plus,
  RefreshCw,
  Save,
  Search,
  Settings,
  ShieldCheck,
  Trash2,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import "../css/admin-dashboard.css";

const SECTIONS = [
  ["overview", "Dashboard", LayoutDashboard],
  ["users", "User Management", Users],
  ["organizations", "Organizations", Building2],
  ["departments", "Colleges / Departments", Landmark],
  ["events", "Event Management", CalendarDays],
  ["announcements", "Announcements", Megaphone],
  ["reports", "Reported Events", Flag],
  ["analytics", "Reports & Analytics", BarChart3],
  ["activity", "Activity Log", ClipboardList],
  ["settings", "Settings", Settings],
];

const DEFAULT_USER_FORM = {
  name: "",
  email: "",
  role: "faculty",
  department: "CCS",
  course: "",
  organization_id: "",
};
const DEFAULT_ORGANIZATION_FORM = {
  name: "",
  description: "",
  logo_url: "",
  department_id: "",
  is_active: true,
  member_ids: [],
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

export default function AdminDashboard({ http, onNotice }) {
  const [section, setSection] = useState("overview");
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [overview, setOverview] = useState({});
  const [users, setUsers] = useState([]);
  const [userPage, setUserPage] = useState(1);
  const [userPagination, setUserPagination] = useState({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
  const [organizations, setOrganizations] = useState([]);
  const [departments, setDepartments] = useState([]);
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
  const [memberChoices, setMemberChoices] = useState([]);
  const [userFilters, setUserFilters] = useState({ search: "", role: "", department: "" });
  const [eventFilters, setEventFilters] = useState({ search: "", department_id: "", organization_id: "", from: "", to: "", trashed: "" });
  const [analyticsFilters, setAnalyticsFilters] = useState({ from: "", to: "", department_id: "", organization_id: "" });
  const [userForm, setUserForm] = useState(DEFAULT_USER_FORM);
  const [editingUserId, setEditingUserId] = useState(null);
  const [userFormOpen, setUserFormOpen] = useState(false);
  const [organizationForm, setOrganizationForm] = useState(DEFAULT_ORGANIZATION_FORM);
  const [editingOrganizationId, setEditingOrganizationId] = useState(null);
  const [departmentForm, setDepartmentForm] = useState({ code: "", name: "", description: "", is_active: true });
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
        const [response, departmentsResponse, organizationsResponse] = await Promise.all([
          http.get("/admin/users", { params }),
          http.get("/admin/departments"),
          http.get("/admin/organizations"),
        ]);
        setUsers(response.data.data || []);
        setUserPagination(response.data);
        setDepartments(departmentsResponse.data.data || []);
        setOrganizations(organizationsResponse.data.data || []);
      } else if (section === "organizations") {
        const [response, departmentsResponse, facultyResponse, organizerResponse] = await Promise.all([
          http.get("/admin/organizations"),
          http.get("/admin/departments"),
          http.get("/admin/users", { params: { role: "faculty", active: true, per_page: 100 } }),
          http.get("/admin/users", { params: { role: "organizer", active: true, per_page: 100 } }),
        ]);
        setOrganizations(response.data.data || []);
        setDepartments(departmentsResponse.data.data || []);
        setMemberChoices([...(facultyResponse.data.data || []), ...(organizerResponse.data.data || [])]);
      } else if (section === "departments") {
        const response = await http.get("/admin/departments");
        setDepartments(response.data.data || []);
      } else if (section === "events") {
        const params = { ...Object.fromEntries(Object.entries(eventFilters).filter(([, value]) => value)), page: eventPage };
        const [response, departmentsResponse, organizationsResponse] = await Promise.all([
          http.get("/admin/events", { params }),
          http.get("/admin/departments"),
          http.get("/admin/organizations"),
        ]);
        setEvents(response.data.data || []);
        setEventPagination(response.data);
        setDepartments(departmentsResponse.data.data || []);
        setOrganizations(organizationsResponse.data.data || []);
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
        const [response, departmentsResponse, organizationsResponse] = await Promise.all([
          http.get("/admin/analytics", { params }),
          http.get("/admin/departments"),
          http.get("/admin/organizations"),
        ]);
        setAnalytics(response.data);
        setDepartments(departmentsResponse.data.data || []);
        setOrganizations(organizationsResponse.data.data || []);
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
        const { organization_id: _organizationId, ...updates } = userForm;
        await http.put(`/admin/users/${editingUserId}`, updates);
        onNotice("Account details updated.");
      } else {
        const payload = {
          ...userForm,
          department: userForm.role === "admin" ? null : userForm.department,
          organization_id: userForm.role === "organizer" ? Number(userForm.organization_id) : null,
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
      department: user.department || "CCS",
      course: user.course || "",
      organization_id: user.organizations?.[0]?.id || "",
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

  const saveOrganization = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const payload = {
        ...organizationForm,
        department_id: Number(organizationForm.department_id),
        member_ids: organizationForm.member_ids.map(Number),
      };
      if (editingOrganizationId) {
        await http.put(`/admin/organizations/${editingOrganizationId}`, payload);
        onNotice("Organization updated.");
      } else {
        await http.post("/admin/organizations", payload);
        onNotice("Organization created.");
      }
      setOrganizationForm(DEFAULT_ORGANIZATION_FORM);
      setEditingOrganizationId(null);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const editOrganization = (organization) => {
    setOrganizationForm({
      name: organization.name,
      description: organization.description || "",
      logo_url: organization.logo_url || "",
      department_id: String(organization.department_id || ""),
      is_active: organization.is_active,
      member_ids: (organization.members || []).map((member) => member.id),
    });
    setEditingOrganizationId(organization.id);
  };

  const deleteOrganization = async (organization) => {
    if (!window.confirm(`Delete "${organization.name}"? Organizations with event history must be deactivated instead.`)) return;
    try {
      await http.delete(`/admin/organizations/${organization.id}`);
      onNotice("Organization deleted.");
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  };

  const saveDepartment = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const payload = editingDepartmentId
        ? { name: departmentForm.name, description: departmentForm.description, is_active: departmentForm.is_active }
        : departmentForm;
      if (editingDepartmentId) {
        await http.put(`/admin/departments/${editingDepartmentId}`, payload);
        onNotice("Department updated.");
      } else {
        await http.post("/admin/departments", payload);
        onNotice("Department created.");
      }
      setDepartmentForm({ code: "", name: "", description: "", is_active: true });
      setEditingDepartmentId(null);
      await loadSection();
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setBusy(false);
    }
  };

  const deleteDepartment = async (department) => {
    if (!window.confirm(`Delete ${department.name}? Departments with assigned users, organizations, or announcements cannot be deleted.`)) return;
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
        organizer_id: Number(eventEdit.organizer_id),
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

  const heading = SECTIONS.find(([key]) => key === section)?.[1] || "Dashboard";

  return (
    <section className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-sidebar-title"><ShieldCheck size={20} /><span>ADMINISTRATION</span></div>
        <nav aria-label="Admin dashboard navigation">
          {SECTIONS.map(([key, label, Icon]) => (
            <button
              type="button"
              key={key}
              className={section === key ? "active" : ""}
              onClick={() => setSection(key)}
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
            <div className="eyebrow muted">SYSTEM ADMINISTRATION</div>
            <h1>{heading}<span>.</span></h1>
          </div>
          <button className="btn btn-outline" type="button" onClick={loadSection} disabled={loading}>
            <RefreshCw size={15} /> Refresh
          </button>
        </header>
        {error && <div className="dashboard-error" role="alert">{error}</div>}
        {loading && <div className="loading-line"><span /> Loading {heading.toLowerCase()}...</div>}

        {!loading && section === "overview" && (
          <>
            <div className="dashboard-stats admin-stats-grid">
              <Stat label="Total Students" value={overview.total_students} icon={Users} />
              <Stat label="Total Faculty" value={overview.total_faculty} icon={Users} />
              <Stat label="Total Organizers" value={overview.total_organizers} icon={ShieldCheck} />
              <Stat label="Total Organizations" value={overview.total_organizations} icon={Building2} />
              <Stat label="Total Departments" value={overview.total_departments} icon={Landmark} />
              <Stat label="Total Events" value={overview.total_events} icon={CalendarDays} />
              <Stat label="Upcoming Events" value={overview.upcoming_events} icon={CalendarDays} />
              <Stat label="Total Registrations" value={overview.total_registrations} icon={ClipboardList} />
            </div>
            <section className="dashboard-panel admin-overview-note">
              <h2>System overview</h2>
              <p>Organizers publish events directly. Admin tools are for account, organization, department, announcement, event, and report management—there is no event approval queue.</p>
            </section>
          </>
        )}

        {!loading && section === "users" && (
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
                  {userForm.role !== "admin" && <Field label="Department"><select required value={userForm.department} onChange={(e) => setUserForm({ ...userForm, department: e.target.value })}>{departments.filter((d) => d.is_active).map((d) => <option key={d.id} value={d.code}>{d.name} ({d.code})</option>)}</select></Field>}
                  {userForm.role === "student" && <Field label="Course (optional)"><input value={userForm.course} onChange={(e) => setUserForm({ ...userForm, course: e.target.value })} /></Field>}
                  {userForm.role === "organizer" && <Field label="Organization"><select required value={userForm.organization_id} onChange={(e) => setUserForm({ ...userForm, organization_id: e.target.value })}><option value="">Select an organization</option>{organizations.filter((o) => o.is_active && String(o.department_id) === String(departments.find((d) => d.code === userForm.department)?.id)).map((org) => <option key={org.id} value={org.id}>{org.name}</option>)}</select></Field>}
                </div>
                <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Save size={15} />{busy ? "Saving..." : editingUserId ? "Save changes" : "Send invitation"}</button><button type="button" className="btn btn-outline" onClick={() => setUserFormOpen(false)}>Cancel</button></div>
              </form>
            )}
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Organizations</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {users.map((user) => {
                const hasCompletedInvitation = user.invitations?.some((invitation) => invitation.used_at);
                const hasPendingInvitation = user.invitations?.some((invitation) => !invitation.used_at);
                return <tr key={user.id}><td><b>{user.name}</b><small>{user.email}</small></td><td>{user.role}</td><td>{user.department || "—"}</td><td>{user.organizations?.map((org) => org.name).join(", ") || user.organizer?.name || "—"}</td><td><span className={`admin-status ${user.is_active ? "active" : "inactive"}`}>{user.is_active ? "Active" : hasPendingInvitation ? "Invitation pending" : "Inactive"}</span></td><td className="admin-row-actions"><button title="Edit account" onClick={() => startEditUser(user)}><Pencil size={15} /></button>{!user.is_active ? hasCompletedInvitation ? <button title="Reactivate account" onClick={() => activateUser(user)}><Check size={15} /></button> : <button title="Resend invitation" onClick={() => resendInvitation(user)}><RefreshCw size={15} /></button> : <button title="Deactivate account" onClick={() => deactivateUser(user)}><Trash2 size={15} /></button>}</td></tr>;
              })}
            </tbody></table>{!users.length && <p className="dashboard-empty">No accounts match these filters.</p>}</div>
            <div className="admin-pagination">
              <span>Showing {userPagination.from || 0}–{userPagination.to || 0} of {userPagination.total || 0} accounts</span>
              <div><button className="btn btn-outline" type="button" disabled={userPage <= 1 || loading} onClick={() => setUserPage((page) => page - 1)}>Previous</button><span>Page {userPagination.current_page || userPage} of {userPagination.last_page || 1}</span><button className="btn btn-outline" type="button" disabled={userPage >= (userPagination.last_page || 1) || loading} onClick={() => setUserPage((page) => page + 1)}>Next</button></div>
            </div>
          </>
        )}

        {!loading && section === "organizations" && (
          <>
            <form className="settings-card admin-form" onSubmit={saveOrganization}>
              <h2>{editingOrganizationId ? "Edit organization" : "Create organization"}</h2>
              <div className="admin-form-grid">
                <Field label="Organization name"><input required maxLength={190} value={organizationForm.name} onChange={(e) => setOrganizationForm({ ...organizationForm, name: e.target.value })} /></Field>
                <Field label="Department"><select required value={organizationForm.department_id} onChange={(e) => setOrganizationForm({ ...organizationForm, department_id: e.target.value, member_ids: [] })}><option value="">Choose department</option>{departments.filter((d) => d.is_active).map((d) => <option key={d.id} value={d.id}>{d.name} ({d.code})</option>)}</select></Field>
                <Field label="Logo URL (optional)"><input type="url" value={organizationForm.logo_url} onChange={(e) => setOrganizationForm({ ...organizationForm, logo_url: e.target.value })} /></Field>
                <Field label="Status"><select value={organizationForm.is_active ? "true" : "false"} onChange={(e) => setOrganizationForm({ ...organizationForm, is_active: e.target.value === "true" })}><option value="true">Active</option><option value="false">Inactive</option></select></Field>
                <Field label="Description"><textarea rows={3} maxLength={5000} value={organizationForm.description} onChange={(e) => setOrganizationForm({ ...organizationForm, description: e.target.value })} /></Field>
                <Field label="Officers and faculty"><select multiple value={organizationForm.member_ids.map(String)} onChange={(e) => setOrganizationForm({ ...organizationForm, member_ids: [...e.target.selectedOptions].map((o) => Number(o.value)) })}>{memberChoices.filter((m) => String(m.department) === String(departments.find((d) => String(d.id) === String(organizationForm.department_id))?.code)).map((member) => <option key={member.id} value={member.id}>{member.name} · {member.role}</option>)}</select><small>Use Ctrl or Cmd to select more than one person.</small></Field>
              </div>
              <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Save size={15} />{editingOrganizationId ? "Save organization" : "Create organization"}</button>{editingOrganizationId && <button type="button" className="btn btn-outline" onClick={() => { setEditingOrganizationId(null); setOrganizationForm(DEFAULT_ORGANIZATION_FORM); }}>Cancel</button>}</div>
            </form>
            <div className="admin-card-grid">{organizations.map((org) => <article className="dashboard-panel admin-entity-card" key={org.id}><div className="admin-entity-heading"><div><h2>{org.name}</h2><p>{org.department?.name || "Department not assigned"}</p></div><span className={`admin-status ${org.is_active ? "active" : "inactive"}`}>{org.is_active ? "Active" : "Inactive"}</span></div><p>{org.description || "No description."}</p><small>{org.events_count} events · {org.members_count} members: {org.members?.map((m) => m.name).join(", ") || "none"}</small><div className="admin-actions"><button className="btn btn-outline" onClick={() => editOrganization(org)}><Pencil size={14} /> Edit</button><button className="btn btn-outline danger" onClick={() => deleteOrganization(org)}><Trash2 size={14} /> Delete</button></div></article>)}</div>
          </>
        )}

        {!loading && section === "departments" && (
          <>
            <form className="settings-card admin-form admin-department-form" onSubmit={saveDepartment}>
              <h2>{editingDepartmentId ? "Edit department" : "Add department"}</h2>
              <div className="admin-form-grid">
                {!editingDepartmentId && <Field label="Code"><input required maxLength={20} pattern="[A-Z0-9_-]+" value={departmentForm.code} onChange={(e) => setDepartmentForm({ ...departmentForm, code: e.target.value.toUpperCase() })} /></Field>}
                <Field label="Department name"><input required maxLength={190} value={departmentForm.name} onChange={(e) => setDepartmentForm({ ...departmentForm, name: e.target.value })} /></Field>
                <Field label="Description"><input value={departmentForm.description} maxLength={2000} onChange={(e) => setDepartmentForm({ ...departmentForm, description: e.target.value })} /></Field>
                {editingDepartmentId && <Field label="Status"><select value={departmentForm.is_active ? "true" : "false"} onChange={(e) => setDepartmentForm({ ...departmentForm, is_active: e.target.value === "true" })}><option value="true">Active</option><option value="false">Inactive</option></select></Field>}
              </div>
              <div className="admin-actions"><button className="btn btn-primary" disabled={busy}><Save size={15} />Save department</button>{editingDepartmentId && <button type="button" className="btn btn-outline" onClick={() => { setEditingDepartmentId(null); setDepartmentForm({ code: "", name: "", description: "", is_active: true }); }}>Cancel</button>}</div>
            </form>
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Department</th><th>Users</th><th>Organizations</th><th>Events</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {departments.map((dept) => <tr key={dept.id}><td><b>{dept.name}</b><small>{dept.code}{dept.is_legacy ? " · legacy" : ""}</small></td><td>{dept.users_count}</td><td>{dept.organizers_count}</td><td>{dept.events_count}</td><td>{dept.is_active ? "Active" : "Inactive"}</td><td className="admin-row-actions"><button title="View department members and events" onClick={() => viewDepartment(dept)}><Eye size={15} /></button><button title="Edit department" onClick={() => { setEditingDepartmentId(dept.id); setDepartmentForm({ name: dept.name, description: dept.description || "", is_active: dept.is_active }); }}><Pencil size={15} /></button><button title="Delete department" onClick={() => deleteDepartment(dept)}><Trash2 size={15} /></button></td></tr>)}
            </tbody></table></div>
            {departmentDetail && <section className="dashboard-panel admin-detail-panel"><div className="admin-form-heading"><h2>{departmentDetail.name} ({departmentDetail.code})</h2><button type="button" aria-label="Close department details" onClick={() => setDepartmentDetail(null)}><X size={18} /></button></div><h3>Department members</h3>{departmentDetail.users?.length ? <div className="admin-detail-list">{departmentDetail.users.map((member) => <p key={member.id}><b>{member.name}</b><span>{member.role} · {member.email}</span></p>)}</div> : <p>No users assigned.</p>}<h3>Organizations and events</h3>{departmentDetail.organizers?.length ? departmentDetail.organizers.map((organization) => <div className="admin-detail-organization" key={organization.id}><b>{organization.name}</b>{organization.events?.length ? <ul>{organization.events.map((event) => <li key={event.id}>{event.title} · {formatDate(event.starts_at)} · {event.is_cancelled ? "Cancelled" : event.is_published ? "Published" : "Unpublished"}</li>)}</ul> : <p>No events.</p>}</div>) : <p>No organizations assigned.</p>}</section>}
          </>
        )}

        {!loading && section === "events" && (
          <>
            <div className="admin-toolbar">
              <label className="admin-search"><Search size={16} /><input placeholder="Search events" value={eventFilters.search} onChange={(e) => setEventFilters((f) => ({ ...f, search: e.target.value }))} /></label>
              <select value={eventFilters.department_id} onChange={(e) => setEventFilters((f) => ({ ...f, department_id: e.target.value }))}><option value="">All departments</option>{departments.map((d) => <option key={d.id} value={d.id}>{d.code}</option>)}</select>
              <select value={eventFilters.organization_id} onChange={(e) => setEventFilters((f) => ({ ...f, organization_id: e.target.value }))}><option value="">All organizations</option>{organizations.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}</select>
              <label className="admin-date-filter">From<input type="date" value={eventFilters.from} onChange={(e) => setEventFilters((f) => ({ ...f, from: e.target.value }))} /></label>
              <label className="admin-date-filter">To<input type="date" value={eventFilters.to} onChange={(e) => setEventFilters((f) => ({ ...f, to: e.target.value }))} /></label>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventFilters.trashed)} onChange={(e) => setEventFilters((f) => ({ ...f, trashed: e.target.checked ? "1" : "" }))} /> Include removed</label>
            </div>
            {eventEdit && <form className="settings-card admin-form" onSubmit={saveEvent}><div className="admin-form-heading"><h2>Edit event</h2><button type="button" aria-label="Close event editor" onClick={() => setEventEdit(null)}><X size={18} /></button></div><div className="admin-form-grid">
              {["title", "venue", "category", "poster_url"].map((key) => <Field key={key} label={key.replace("_", " ")}><input required={key !== "poster_url"} value={eventEdit[key] || ""} onChange={(e) => setEventEdit({ ...eventEdit, [key]: e.target.value })} /></Field>)}
              <Field label="Organizer"><select value={eventEdit.organizer_id} onChange={(e) => setEventEdit({ ...eventEdit, organizer_id: e.target.value })}>{organizations.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}</select></Field>
              <Field label="Starts at"><input required type="datetime-local" value={localDateTime(eventEdit.starts_at)} onChange={(e) => setEventEdit({ ...eventEdit, starts_at: e.target.value })} /></Field>
              <Field label="Ends at"><input type="datetime-local" value={localDateTime(eventEdit.ends_at)} onChange={(e) => setEventEdit({ ...eventEdit, ends_at: e.target.value })} /></Field>
              <Field label="Capacity"><input required type="number" min="1" value={eventEdit.capacity} onChange={(e) => setEventEdit({ ...eventEdit, capacity: e.target.value })} /></Field>
              <Field label="Description"><textarea required rows={3} value={eventEdit.description} onChange={(e) => setEventEdit({ ...eventEdit, description: e.target.value })} /></Field>
              <Field label="Rules"><textarea rows={3} value={eventEdit.rules || ""} onChange={(e) => setEventEdit({ ...eventEdit, rules: e.target.value })} /></Field>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventEdit.is_published)} onChange={(e) => setEventEdit({ ...eventEdit, is_published: e.target.checked })} /> Published on Discover</label>
              <label className="admin-checkbox"><input type="checkbox" checked={Boolean(eventEdit.is_cancelled)} onChange={(e) => setEventEdit({ ...eventEdit, is_cancelled: e.target.checked })} /> Cancelled</label>
            </div><button className="btn btn-primary" disabled={busy}><Save size={15} />Save event</button></form>}
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Event</th><th>Organization / Dept.</th><th>Date</th><th>Registrations</th><th>Status</th><th>Actions</th></tr></thead><tbody>
              {events.map((event) => <tr key={event.id}><td><b>{event.title}</b><small>{event.venue}</small></td><td>{event.organizer?.name || "—"}<small>{event.organizer?.department?.code || "Unassigned"}</small></td><td>{formatDate(event.starts_at)}</td><td>{event.registrations_count || 0}</td><td>{event.deleted_at ? "Removed" : event.is_cancelled ? "Cancelled" : event.is_published ? "Published" : "Unpublished"}</td><td className="admin-row-actions"><button title="View event registrations and attendance" onClick={() => viewEvent(event)}><Eye size={15} /></button>{!event.deleted_at && <button title="Edit event" onClick={() => setEventEdit(event)}><Pencil size={15} /></button>}{event.deleted_at ? <button title="Restore as unpublished" onClick={() => eventAction(event, "restore")}><RefreshCw size={15} /></button> : <><button title="Unpublish event" onClick={() => eventAction(event, "unpublish")}><X size={15} /></button><button title="Remove event" onClick={() => eventAction(event, "delete")}><Trash2 size={15} /></button></>}</td></tr>)}
            </tbody></table>{!events.length && <p className="dashboard-empty">No events match these filters.</p>}</div>
            {eventDetail && <section className="dashboard-panel admin-detail-panel"><div className="admin-form-heading"><div><h2>{eventDetail.title}</h2><p>{eventDetail.organizer?.name || "Organization"} · {formatDate(eventDetail.starts_at)} · {eventDetail.venue}</p></div><button type="button" aria-label="Close event details" onClick={() => setEventDetail(null)}><X size={18} /></button></div><p>{eventDetail.description}</p><div className="admin-detail-summary"><span>{eventDetail.registrations_count || 0} registrations</span><span>{eventDetail.attendance_count || 0} check-ins</span><span>{eventDetail.deleted_at ? "Removed" : eventDetail.is_cancelled ? "Cancelled" : eventDetail.is_published ? "Published" : "Unpublished"}</span></div><h3>Registration and attendance records</h3>{eventDetail.rsvps?.length ? <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Attendee</th><th>Email</th><th>Registration</th><th>Attendance</th><th>Checked in</th></tr></thead><tbody>{eventDetail.rsvps.map((rsvp) => <tr key={rsvp.id}><td>{rsvp.user?.name || "Deleted account"}</td><td>{rsvp.user?.email || "—"}</td><td>{rsvp.status}</td><td>{rsvp.checked_in_at ? "Present" : "Not checked in"}</td><td>{formatDate(rsvp.checked_in_at)}</td></tr>)}</tbody></table></div> : <p>No registration records.</p>}</section>}
            <div className="admin-pagination">
              <span>Showing {eventPagination.from || 0}–{eventPagination.to || 0} of {eventPagination.total || 0} events</span>
              <div><button className="btn btn-outline" type="button" disabled={eventPage <= 1 || loading} onClick={() => setEventPage((page) => page - 1)}>Previous</button><span>Page {eventPagination.current_page || eventPage} of {eventPagination.last_page || 1}</span><button className="btn btn-outline" type="button" disabled={eventPage >= (eventPagination.last_page || 1) || loading} onClick={() => setEventPage((page) => page + 1)}>Next</button></div>
            </div>
          </>
        )}

        {!loading && section === "announcements" && (
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

        {!loading && section === "reports" && (
          <div className="admin-report-list">{reports.map((report) => <article className="dashboard-panel admin-report" key={report.id}><div className="admin-entity-heading"><div><h2>{report.event?.title || "Event removed"}</h2><p>Reported by {report.user?.name || "Unknown"} ({report.user?.email || "—"}) · {formatDate(report.created_at)}</p></div><span className={`admin-status ${report.status === "pending" ? "inactive" : "active"}`}>{report.status}</span></div><p><b>Reason:</b> {report.reason?.replaceAll("_", " ")}. {report.details || "No extra details."}</p><Field label="Investigation / resolution notes"><textarea rows={2} maxLength={2000} value={reportNotes[report.id] ?? report.resolution ?? report.review_notes ?? ""} onChange={(e) => setReportNotes({ ...reportNotes, [report.id]: e.target.value })} /></Field>{report.status === "pending" && <div className="admin-actions"><button className="btn btn-primary" onClick={() => resolveReport(report, "review")}><Check size={14} /> Mark reviewed</button><button className="btn btn-outline" onClick={() => resolveReport(report, "dismiss")}>Dismiss</button><button className="btn btn-outline" onClick={() => resolveReport(report, "unpublish")}>Unpublish event</button><button className="btn btn-outline danger" onClick={() => resolveReport(report, "remove")}>Remove event</button></div>}{report.status !== "pending" && <small>Resolution: {report.resolution || report.review_notes || "—"} · {report.reviewer?.name || "Admin"}</small>}</article>)}{!reports.length && <p className="dashboard-empty">No event reports have been submitted.</p>}</div>
        )}

        {!loading && section === "analytics" && (
          <>
            <div className="admin-toolbar">
              <Field label="From"><input type="date" value={analyticsFilters.from} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, from: e.target.value })} /></Field>
              <Field label="To"><input type="date" value={analyticsFilters.to} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, to: e.target.value })} /></Field>
              <select value={analyticsFilters.department_id} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, department_id: e.target.value })}><option value="">All departments</option>{departments.map((d) => <option key={d.id} value={d.id}>{d.code}</option>)}</select>
              <select value={analyticsFilters.organization_id} onChange={(e) => setAnalyticsFilters({ ...analyticsFilters, organization_id: e.target.value })}><option value="">All organizations</option>{organizations.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}</select>
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
              <section className="dashboard-panel"><h2>Organization activity</h2>{analytics?.organization_activity?.map((org) => <p className="admin-list-row" key={org.id}><b>{org.name}</b><span>{org.events_count} events · {org.department?.code || "Unassigned"}</span></p>)}</section>
            </div>
          </>
        )}

        {!loading && section === "activity" && (
          <>
            <div className="admin-toolbar"><label className="admin-search"><Search size={16} /><input placeholder="Search activity" value={activitySearch} onChange={(e) => setActivitySearch(e.target.value)} /></label></div>
            <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Who</th><th>Action</th><th>Affected record</th><th>Details</th><th>Date / time</th></tr></thead><tbody>
              {activity.map((entry) => <tr key={entry.id}><td>{entry.actor?.name || "Deleted account"}<small>{entry.actor?.email}</small></td><td>{entry.action}</td><td>{entry.record_type}{entry.record_id ? ` #${entry.record_id}` : ""}</td><td>{entry.summary}{entry.details && <small>{JSON.stringify(entry.details)}</small>}</td><td>{formatDate(entry.created_at)}</td></tr>)}
            </tbody></table>{!activity.length && <p className="dashboard-empty">No administrative activity recorded yet.</p>}</div>
          </>
        )}

        {!loading && section === "settings" && (
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
            <section className="dashboard-panel admin-permission-summary"><h2>User permissions</h2><p>Role boundaries are enforced by the API and are not user-editable settings.</p><div className="admin-detail-list"><p><b>Admin</b><span>Full system management and reporting access.</span></p><p><b>Faculty</b><span>Assigned department and organization access only.</span></p><p><b>Organizer / Officer</b><span>Manage events and announcements for assigned organizations.</span></p><p><b>Student</b><span>Discover and RSVP to public events; receive announcements for their department.</span></p></div></section>
          </>
        )}
      </div>
    </section>
  );
}
