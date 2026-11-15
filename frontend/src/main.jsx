import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import {
  ArrowUpRight,
  Bookmark,
  CalendarDays,
  CheckCircle2,
  Info,
  RefreshCw,
  Users,
  X,
} from "lucide-react";
import AppHeader from "./components/AppHeader";
import AdminDashboard from "./components/AdminDashboard";
import AnnouncementsView from "./components/AnnouncementsView";
import AuthModal from "./components/AuthModal";
import ArchiveView from "./components/ArchiveView";
import CalendarView from "./components/CalendarView";
import CheckInHistoryView from "./components/CheckInHistoryView";
import EventDiscoveryView from "./components/EventDiscoveryView";
import EventListView from "./components/EventListView";
import EventPage from "./components/EventPage";
import InvitationSetupView from "./components/InvitationSetupView";
import OrganizerDashboard from "./components/OrganizerDashboard";
import OrganizerPage from "./components/OrganizerPage";
import OrganizersView from "./components/OrganizersView";
import SeatConfirmationModal from "./components/SeatConfirmationModal";
import UserSettingsView from "./components/UserSettingsView";
import { downloadIcs } from "./utils/ics";
import { isDesignatedOrganizer } from "./utils/organizerAccess";
import "./css/base.css";
import "./css/header.css";
import "./css/buttons.css";
import "./css/hero.css";
import "./css/sections.css";
import "./css/event-card.css";
import "./css/feedback.css";
import "./css/modals.css";
import "./css/organizers.css";
import "./css/organizer-dashboard.css";
import "./css/announcements.css";
import "./css/event-page.css";
import "./css/calendar.css";
import "./css/archive.css";
import "./css/settings.css";
import "./css/footer.css";
import "./css/responsive.css";

const API = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const http = axios.create({ baseURL: API });
const tokenKey = "ceh_token";
const userCacheKey = "ceh_user_cache_v1";
const readCachedUser = () => {
  const token = localStorage.getItem(tokenKey);
  if (!token) return null;
  try {
    const cached = JSON.parse(localStorage.getItem(userCacheKey) || "null");
    return cached?.token === token ? cached.user : null;
  } catch {
    return null;
  }
};
const cacheUser = (token, user) => {
  try {
    localStorage.setItem(userCacheKey, JSON.stringify({ token, user }));
  } catch {
    // Login state remains available from the token if browser storage is full.
  }
};
const publicCachePrefix = "ceh_public_cache_v1:";
const readCache = (key) => {
  try {
    const cached = localStorage.getItem(key);
    return cached ? JSON.parse(cached) : null;
  } catch {
    return null;
  }
};
const writeCache = (key, value) => {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // The app remains usable when browser storage is unavailable or full.
  }
};
const eventsCacheKey = (params) =>
  `${publicCachePrefix}events:${new URLSearchParams(params).toString()}`;
const defaultEventsParams = {
  search: "",
  date: "",
  category: "",
  organizer_id: "",
  sort: "soonest",
  page: 1,
};
// Attach the saved Sanctum token to protected API requests.
http.interceptors.request.use((config) => {
  const token = localStorage.getItem(tokenKey);
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
const prettyDate = (value) =>
  new Date(value).toLocaleString([], { dateStyle: "medium", timeStyle: "short" });
const userHomeView = (account) => {
  if (account?.role === "admin") return "admin-dashboard";
  if (isDesignatedOrganizer(account)) {
    return "dashboard";
  }
  return "discover";
};

function App() {
  // Which page is showing, plus the event / organizer being viewed.
  const invitationToken = new URLSearchParams(window.location.hash.split("?")[1] || "").get("token");
  const [view, setView] = useState(() =>
    invitationToken ? "setup-account" : userHomeView(readCachedUser()),
  );
  const [adminSection, setAdminSection] = useState("overview");
  const [selected, setSelected] = useState(null);
  const [organizerPage, setOrganizerPage] = useState(null);
  const [backView, setBackView] = useState("discover");

  // Discovery filters and results.
  const [events, setEvents] = useState(
    () => readCache(eventsCacheKey(defaultEventsParams))?.data || [],
  );
  const [pagination, setPagination] = useState(
    () => readCache(eventsCacheKey(defaultEventsParams))?.pagination || {},
  );
  const [search, setSearch] = useState("");
  const [date, setDate] = useState("");
  const [category, setCategory] = useState("");
  const [organizer, setOrganizer] = useState("");
  const [sort, setSort] = useState("soonest");
  const [organizers, setOrganizers] = useState(
    () => readCache(`${publicCachePrefix}organizers`) || [],
  );
  const [organizersLoading, setOrganizersLoading] = useState(
    () => !readCache(`${publicCachePrefix}organizers`),
  );
  const [loading, setLoading] = useState(false);

  // Archive (past events).
  const [archive, setArchive] = useState([]);
  const [archivePagination, setArchivePagination] = useState({});
  const [archiveSearch, setArchiveSearch] = useState("");
  const [archiveLoading, setArchiveLoading] = useState(false);

  // The signed-in user's lists.
  const [myRsvps, setMyRsvps] = useState([]);
  const [saved, setSaved] = useState([]);
  const [calendarList, setCalendarList] = useState([]);
  const [checkIns, setCheckIns] = useState([]);
  const [checkInsLoading, setCheckInsLoading] = useState(false);
  const [listLoading, setListLoading] = useState(false);
  const [rsvpBusyEventId, setRsvpBusyEventId] = useState(null);
  const [seatConfirmation, setSeatConfirmation] = useState(null);

  // Campus announcements feed and notifications.
  const [announcements, setAnnouncements] = useState([]);
  const [announcementEmojis, setAnnouncementEmojis] = useState([]);
  const [announcementCourse, setAnnouncementCourse] = useState("");
  const [announcementOrganizer, setAnnouncementOrganizer] = useState("");
  const [announcementSearch, setAnnouncementSearch] = useState("");
  const [announcementsLoading, setAnnouncementsLoading] = useState(false);
  const [unreadAnnouncements, setUnreadAnnouncements] = useState(0);
  const [announcementBusyId, setAnnouncementBusyId] = useState(null);
  const [notifications, setNotifications] = useState([]);
  const [notificationsUnread, setNotificationsUnread] = useState(0);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notificationsLoading, setNotificationsLoading] = useState(false);

  // Auth dialog and page-level feedback.
  const [authOpen, setAuthOpen] = useState(false);
  const [authMode, setAuthMode] = useState("login");
  const [authSubmitting, setAuthSubmitting] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [user, setUser] = useState(readCachedUser);
  const [departmentOptions, setDepartmentOptions] = useState([]);
  const [departmentsLoading, setDepartmentsLoading] = useState(true);
  const [departmentsError, setDepartmentsError] = useState("");
  const [authChecking, setAuthChecking] = useState(
    () => Boolean(localStorage.getItem(tokenKey) && !readCachedUser()),
  );
  const [notice, setNotice] = useState("");
  const [noticeType, setNoticeType] = useState("success");
  const noticeTimer = useRef(null);
  const [error, setError] = useState("");
  const [retryAction, setRetryAction] = useState(null);
  const [authForm, setAuthForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    department: "",
    course: "",
  });

  useEffect(() => {
    let active = true;
    http.get("/departments")
      .then(({ data }) => {
        if (active) setDepartmentOptions(data.data || []);
      })
      .catch(() => {
        if (active) {
          setDepartmentOptions([]);
          setDepartmentsError("Department options could not be loaded. Refresh the page and try again.");
        }
      })
      .finally(() => {
        if (active) setDepartmentsLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  const showError = (message, retry = null) => {
    setError(message);
    setRetryAction(() => retry);
  };
  const showNotice = (message, type = "success") => {
    window.clearTimeout(noticeTimer.current);
    noticeTimer.current = null;
    setNoticeType(type);
    setNotice(message);
    if (type === "success") {
      noticeTimer.current = window.setTimeout(() => {
        setNotice("");
        noticeTimer.current = null;
      }, 3000);
    }
  };
  const dismissNotice = () => {
    window.clearTimeout(noticeTimer.current);
    noticeTimer.current = null;
    setNotice("");
  };
  const clearError = () => {
    setError("");
    setRetryAction(null);
  };
  const requireLogin = (message) => {
    setAuthMode("login");
    setAuthOpen(true);
    showError(message);
  };
  const isLoggedIn = () => !!localStorage.getItem(tokenKey);

  const hasRsvped = (id) => myRsvps.some((e) => e.id === id);
  const isSaved = (id) => saved.some((e) => e.id === id);
  const inCalendar = (id) => calendarList.some((e) => e.id === id);

  // ---------- data loading ----------
  const loadEvents = async (page = 1) => {
    const params = { search, date, category, organizer_id: organizer, sort, page };
    const cacheKey = eventsCacheKey(params);
    const hasCachedResults = Boolean(readCache(cacheKey));
    setLoading(!hasCachedResults);
    clearError();
    try {
      const { data } = await http.get("/events", {
        params,
      });
      setEvents(data.data || []);
      setPagination(data);
      writeCache(cacheKey, { data: data.data || [], pagination: data });
    } catch {
      showError(
        "We couldn’t load events. Check that the Laravel API is running, then try again.",
        () => loadEvents(page),
      );
    } finally {
      setLoading(false);
    }
  };
  const loadOrganizers = async () => {
    const hasCachedOrganizers = Boolean(readCache(`${publicCachePrefix}organizers`));
    setOrganizersLoading(!hasCachedOrganizers);
    try {
      const { data } = await http.get("/organizers");
      setOrganizers(data.data || []);
      writeCache(`${publicCachePrefix}organizers`, data.data || []);
    } catch {
      showError("Could not load organizers. Please retry.", loadOrganizers);
    } finally {
      setOrganizersLoading(false);
    }
  };
  const loadArchive = async (page = 1) => {
    setArchiveLoading(true);
    try {
      const { data } = await http.get("/events/past", { params: { search: archiveSearch, page } });
      setArchive(data.data || []);
      setArchivePagination(data);
    } catch {
      showError("Could not load the archive. Please retry.", () => loadArchive(page));
    } finally {
      setArchiveLoading(false);
    }
  };
  const loadMyLists = async () => {
    setListLoading(true);
    try {
      const [rsvps, saves, cal] = await Promise.all([
        http.get("/my-rsvps"),
        http.get("/my-saves"),
        http.get("/my-calendar"),
      ]);
      setMyRsvps(rsvps.data.data || []);
      setSaved(saves.data.data || []);
      setCalendarList(cal.data.data || []);
    } catch {
      showError("Could not load your lists. Please retry.", loadMyLists);
    } finally {
      setListLoading(false);
    }
  };
  const loadCheckIns = async () => {
    setCheckInsLoading(true);
    try {
      const { data } = await http.get("/my-checkins");
      setCheckIns(data.data || []);
    } catch {
      showError("Could not load your check-in history. Please retry.", loadCheckIns);
    } finally {
      setCheckInsLoading(false);
    }
  };

  const loadAnnouncements = async () => {
    setAnnouncementsLoading(true);
    try {
      const { data } = await http.get("/announcements", {
        params: {
          course: announcementCourse,
          organizer_id: announcementOrganizer,
          search: announcementSearch,
        },
      });
      setAnnouncements(data.data || []);
      setAnnouncementEmojis(data.emojis || []);
      setUnreadAnnouncements(data.unread_count || 0);
    } catch {
      showError("Could not load the campus feed. Please retry.", loadAnnouncements);
    } finally {
      setAnnouncementsLoading(false);
    }
  };
  const loadNotifications = async (silent = false) => {
    if (!localStorage.getItem(tokenKey)) {
      setNotifications([]);
      setNotificationsUnread(0);
      return;
    }
    if (!silent) setNotificationsLoading(true);
    try {
      const { data } = await http.get("/notifications");
      setNotifications(data.data || []);
      setNotificationsUnread(data.unread_count || 0);
    } catch {
      if (!silent) showError("Could not load your notifications. Please retry.");
    } finally {
      if (!silent) setNotificationsLoading(false);
    }
  };

  useEffect(() => () => window.clearTimeout(noticeTimer.current), []);

  useEffect(() => {
    const timer = setTimeout(() => loadAnnouncements(), 250);
    return () => clearTimeout(timer);
  }, [announcementCourse, announcementOrganizer, announcementSearch]);
  useEffect(() => {
    if (!user) return undefined;
    const timer = setInterval(() => loadNotifications(true), 60000);
    return () => clearInterval(timer);
  }, [user]);

  useEffect(() => {
    const timer = setTimeout(() => loadEvents(1), 250);
    return () => clearTimeout(timer);
  }, [search, date, category, organizer, sort]);
  useEffect(() => {
    const timer = setTimeout(() => view === "archive" && loadArchive(1), 250);
    return () => clearTimeout(timer);
  }, [archiveSearch]);
  useEffect(() => {
    loadOrganizers();
    const token = localStorage.getItem(tokenKey);
    if (!token) return;
    http
      .get("/user")
      .then(({ data }) => {
        if (localStorage.getItem(tokenKey) !== token) return;
        cacheUser(token, data);
        setUser(data);
        setAuthChecking(false);
        setView(userHomeView(data));
        return Promise.all([loadMyLists(), loadNotifications(), loadAnnouncements()]);
      })
      .catch((err) => {
        if (localStorage.getItem(tokenKey) === token) {
          if ([401, 403].includes(err.response?.status)) {
            localStorage.removeItem(tokenKey);
            localStorage.removeItem(userCacheKey);
            setUser(null);
            setAuthChecking(false);
          } else if (!readCachedUser()) {
            showError("Could not verify your saved session. Check your connection and retry.");
          }
        }
      });
  }, []);

  // ---------- navigation ----------
  const go = (target, needsLogin = false) => {
    if (target === "dashboard" && !isDesignatedOrganizer(user)) {
      target = "discover";
    }
    if (target === "admin-dashboard" && user?.role !== "admin") {
      target = "discover";
    }
    if (needsLogin && !isLoggedIn()) return requireLogin("Log in to see this page.");
    clearError();
    setSelected(null);
    setOrganizerPage(null);
    setNotificationsOpen(false);
    setView(target);
    window.scrollTo({ top: 0 });
    if (target === "archive") loadArchive(1);
    if (target === "announcements") loadAnnouncements();
    if (["my-rsvps", "saved", "my-calendar"].includes(target)) loadMyLists();
    if (target === "check-in-history") loadCheckIns();
    if (target === "organizers") loadOrganizers();
  };
  useEffect(() => {
    if (view === "dashboard" && user && !isDesignatedOrganizer(user)) {
      setView(userHomeView(user));
    }
    if (view === "admin-dashboard" && user && user.role !== "admin") {
      setView(userHomeView(user));
    }
  }, [view, user, authChecking]);
  const openEvent = async (event) => {
    setBackView(view === "event" ? backView : view);
    setSelected(event);
    setView("event");
    window.scrollTo({ top: 0 });
    try {
      const { data } = await http.get(`/events/${event.id}`);
      setSelected(data);
    } catch {
      showNotice("Showing event preview. Full details could not be refreshed.", "info");
    }
  };
  const openOrganizer = async (id) => {
    setBackView(view === "organizer" ? backView : view === "event" ? backView : view);
    try {
      const { data } = await http.get(`/organizers/${id}`);
      setOrganizerPage(data);
      setView("organizer");
      window.scrollTo({ top: 0 });
    } catch {
      showError("Could not load organizer details. Please retry.", () => openOrganizer(id));
    }
  };

  const submitEventReport = async (eventId, report) => {
    try {
      await http.post(`/events/${eventId}/reports`, report);
    } catch (requestError) {
      throw new Error(
        requestError.response?.data?.message ||
          Object.values(requestError.response?.data?.errors || {}).flat()[0] ||
          "Could not submit the report. Please try again.",
      );
    }
  };

  const updateSignedInUser = (updatedUser) => {
    setUser(updatedUser);
    const token = localStorage.getItem(tokenKey);
    if (token) cacheUser(token, updatedUser);
  };

  // ---------- auth ----------
  const loginOrRegister = async (e) => {
    e.preventDefault();
    if (authSubmitting) return;
    setAuthSubmitting(true);
    clearError();
    setNotice("");
    try {
      const { data } = await http.post(authMode === "login" ? "/login" : "/register", authForm);
      localStorage.setItem(tokenKey, data.token);
      cacheUser(data.token, data.user);
      setUser(data.user);
      setAuthChecking(false);
      setView(userHomeView(data.user));
      setSelected(null);
      setAuthOpen(false);
      showNotice(data.department_request
        ? "Account created. Your department request is pending organizer approval."
        : `Welcome${data.user?.name ? `, ${data.user.name}` : ""}!`);
      await Promise.all([loadMyLists(), loadNotifications(), loadAnnouncements()]);
    } catch (err) {
      showError(
        err.response?.data?.message ||
          Object.values(err.response?.data?.errors || {}).flat()[0] ||
          "Account request failed. Please check your details and retry.",
      );
    } finally {
      setAuthSubmitting(false);
    }
  };
  const logout = async () => {
    if (loggingOut) return;
    setLoggingOut(true);
    let logoutError = null;
    try {
      if (localStorage.getItem(tokenKey)) {
        try {
          await http.post("/logout");
        } catch (err) {
          if (err.response?.status !== 401) {
            logoutError =
              "You were logged out on this device, but the server could not invalidate your session.";
          }
        }
      }
    } finally {
      localStorage.removeItem(tokenKey);
      localStorage.removeItem(userCacheKey);
      setUser(null);
      setAuthChecking(false);
      setMyRsvps([]);
      setSaved([]);
      setCalendarList([]);
      setCheckIns([]);
      setView("discover");
      setSelected(null);
      setNotifications([]);
      setNotificationsUnread(0);
      setNotificationsOpen(false);
      showNotice("You have logged out.");
      if (logoutError) showError(logoutError);
      setLoggingOut(false);
      loadAnnouncements();
    }
  };

  // ---------- actions ----------
  const updateRegisteredCount = (eventId, registeredCount) => {
    const patch = (list) =>
      list.map((e) => (e.id === eventId ? { ...e, registered_count: registeredCount } : e));
    setEvents(patch);
    setMyRsvps(patch);
    setSaved(patch);
    setCalendarList(patch);
    setSelected((cur) => (cur?.id === eventId ? { ...cur, registered_count: registeredCount } : cur));
    setOrganizerPage((cur) =>
      cur ? { ...cur, events: patch(cur.events || []), past_events: patch(cur.past_events || []) } : cur,
    );
  };

  // Bookmark (save) / unbookmark an event.
  const toggleSave = async (event) => {
    if (!isLoggedIn()) return requireLogin("Please log in to bookmark an event.");
    clearError();
    setNotice("");
    try {
      const { data } = await http.post(`/events/${event.id}/save`);
      setSaved((cur) => (data.saved ? [...cur, event] : cur.filter((e) => e.id !== event.id)));
      showNotice(data.message || (data.saved ? "Event saved to your bookmarks." : "Event removed from your bookmarks."));
    } catch {
      showError(
        "Couldn’t save this event. Your changes may not have been saved. Please try again.",
        () => toggleSave(event),
      );
    }
  };

  // Add / remove from the user's calendar list.
  const toggleCalendar = async (event) => {
    if (!isLoggedIn()) return requireLogin("Please log in to use your calendar list.");
    clearError();
    setNotice("");
    try {
      const { data } = await http.post(`/events/${event.id}/calendar`);
      setCalendarList((cur) =>
        data.in_calendar ? [...cur, event] : cur.filter((e) => e.id !== event.id),
      );
      showNotice(data.message || (data.in_calendar ? "Event added to your calendar." : "Event removed from your calendar."));
    } catch {
      showError("Couldn’t update your calendar list. Please try again.", () => toggleCalendar(event));
    }
  };

  // Create or cancel an RSVP in one tap and refresh the seat counts.
  const toggleRsvp = async (event) => {
    if (!isLoggedIn()) return requireLogin("Log in to RSVP for this event.");
    clearError();
    setNotice("");
    setRsvpBusyEventId(event.id);
    try {
      const { data } = hasRsvped(event.id)
        ? await http.delete(`/events/${event.id}/rsvp`)
        : await http.post(`/events/${event.id}/rsvp`);
      updateRegisteredCount(event.id, data.registered_count);
      if (data.rsvped) setSeatConfirmation({ event, seatNumber: data.seat_number });
      showNotice(data.message || (data.rsvped ? "Your RSVP was confirmed." : "Your RSVP was cancelled."));
      await loadMyLists();
    } catch (err) {
      if (err.response?.status === 409) {
        showNotice("This event is full. No seats are available.", "info");
        updateRegisteredCount(event.id, event.capacity);
      } else
        showError(
          err.response?.data?.message || "Could not update your RSVP. Please try again.",
          () => toggleRsvp(event),
        );
    } finally {
      setRsvpBusyEventId(null);
    }
  };

  const resetFilters = () => {
    setSearch("");
    setDate("");
    setCategory("");
    setOrganizer("");
    setSort("soonest");
  };

  const resetAnnouncementFilters = () => {
    setAnnouncementCourse("");
    setAnnouncementOrganizer("");
    setAnnouncementSearch("");
  };

  const markAnnouncementRead = async (announcement) => {
    if (!isLoggedIn() || announcement.is_read) return;
    clearError();
    setAnnouncementBusyId(announcement.id);
    try {
      const { data } = await http.post(`/announcements/${announcement.id}/read`);
      setAnnouncements((current) =>
        current.map((item) =>
          item.id === announcement.id ? { ...item, is_read: true } : item,
        ),
      );
      setUnreadAnnouncements(data.unread_count || 0);
      showNotice("Your reaction was added.");
    } catch {
      showError("Could not mark that announcement as read. Please try again.");
    } finally {
      setAnnouncementBusyId(null);
    }
  };

  const reactToAnnouncement = async (announcement, emoji) => {
    if (!isLoggedIn()) return requireLogin("Log in to react to announcements.");
    clearError();
    setNotice("");
    setAnnouncementBusyId(announcement.id);
    try {
      const { data } = await http.post(`/announcements/${announcement.id}/react`, {
        emoji,
      });
      setAnnouncements((current) =>
        current.map((item) =>
          item.id === announcement.id
            ? {
                ...item,
                is_read: true,
                reactions: data.reactions,
                reaction_count: data.reaction_count,
              }
            : item,
        ),
      );
      setUnreadAnnouncements(data.unread_count || 0);
    } catch {
      showError("Could not update your reaction. Please try again.");
    } finally {
      setAnnouncementBusyId(null);
    }
  };

  const toggleNotifications = () => {
    if (!isLoggedIn()) return requireLogin("Log in to see your notifications.");
    if (!notificationsOpen) loadNotifications();
    setNotificationsOpen((open) => !open);
  };

  const openNotification = (notification) => {
    setNotificationsOpen(false);
    if (notification?.data?.event_id) {
      if (notification && !notification.read_at) {
        http
          .post(`/notifications/${notification.id}/read`)
          .then(({ data }) => {
            setNotificationsUnread(data.unread_count || 0);
            setNotifications((current) =>
              current.map((item) =>
                item.id === notification.id
                  ? { ...item, read_at: new Date().toISOString() }
                  : item,
              ),
            );
          })
          .catch(() => showError("Could not mark your notification as read."));
      }
      openEvent({ id: notification.data.event_id });
      return;
    }
    setAnnouncementCourse("");
    setAnnouncementOrganizer("");
    setAnnouncementSearch("");
    if (notification && !notification.read_at) {
      http
        .post(`/notifications/${notification.id}/read`)
        .then(({ data }) => {
          setNotificationsUnread(data.unread_count || 0);
          setNotifications((current) =>
            current.map((item) =>
              item.id === notification.id
                ? { ...item, read_at: new Date().toISOString() }
                : item,
            ),
          );
        })
        .catch(() => showError("Could not mark your notification as read."));
    }
    go("announcements");
  };

  const markAllNotificationsRead = async () => {
    clearError();
    try {
      const { data } = await http.post("/notifications/read-all");
      setNotificationsUnread(data.unread_count || 0);
      setNotifications((current) =>
        current.map((item) => ({
          ...item,
          read_at: item.read_at || new Date().toISOString(),
        })),
      );
      showNotice("All notifications marked as read.");
    } catch {
      showError("Could not mark your notifications as read. Please try again.");
    }
  };

  // Props shared by every EventCard.
  const cardProps = (event) => ({
    prettyDate,
    canRsvp: user?.role !== "organizer",
    isRsvped: hasRsvped(event.id),
    isSaved: isSaved(event.id),
    inCalendar: inCalendar(event.id),
    isBusy: rsvpBusyEventId === event.id,
    onRsvp: toggleRsvp,
    onSave: toggleSave,
    onCalendar: toggleCalendar,
    onOpen: openEvent,
    onOrganizer: openOrganizer,
  });

  return (
    <div className="app-shell">
      <AppHeader
        user={user}
        authChecking={authChecking}
        isLoggingOut={loggingOut}
        view={view}
        notifications={notifications}
        notificationsUnread={notificationsUnread}
        notificationsOpen={notificationsOpen}
        notificationsLoading={notificationsLoading}
        prettyDate={prettyDate}
        onNavigate={go}
        onLogin={() => {
          setAuthMode("login");
          setAuthOpen(true);
          clearError();
        }}
        onLogout={logout}
        onToggleNotifications={toggleNotifications}
        onCloseNotifications={() => setNotificationsOpen(false)}
        onMarkAllNotificationsRead={markAllNotificationsRead}
        onOpenNotifications={openNotification}
        adminSection={adminSection}
        onAdminSectionChange={setAdminSection}
      />
      <main id="top">
        {view === "discover" && !user && (
          <section className="hero">
            <div className="hero-copy">
              <div className="eyebrow">
                <span className="live-dot" /> YOUR CAMPUS, YOUR MOMENTS
              </div>
              <h1>
                Make campus life
                <br />
                <span>unforgettable.</span>
              </h1>
              <p>
                Discover SPC events, meet your people, and never miss a moment that matters.
              </p>
              <a href="#events" className="btn btn-light">
                Explore events <ArrowUpRight size={17} />
              </a>
              <div className="hero-stats">
                <div>
                  <b>
                    {events.length}
                    {pagination.total > events.length ? "+" : ""}
                  </b>
                  <span>Events shown</span>
                </div>
                <div>
                  <b>{organizers.length}</b>
                  <span>Departments</span>
                </div>
                <div>
                  <b>100%</b>
                  <span>Campus spirit</span>
                </div>
              </div>
            </div>
            <div className="hero-art">
              <div className="orb orb-one" />
              <div className="orb orb-two" />
              <div className="floating-card">
                <span className="calendar-mini">
                  <CalendarDays size={23} />
                </span>
                <div>
                  <b>Something’s happening</b>
                  <small>Find your next campus moment</small>
                </div>
                <span className="sparkle">✦</span>
              </div>
              <div className="hero-ticket">
                <span className="ticket-label">UP NEXT AT SPC</span>
                <div className="ticket-icon">
                  <Users size={28} />
                </div>
                <b>Find your crowd.</b>
                <span>Events worth showing up for.</span>
                <div className="ticket-bottom">
                  <span>Campus · Community · You</span>
                  <span>✦ ✦ ✦</span>
                </div>
              </div>
              <div className="tiny-pill">✦ Made for SPC students</div>
            </div>
          </section>
        )}
        {view === "discover" && user && (
          <section className="student-discover-intro">
            <div className="eyebrow">YOUR SPC, YOUR NEXT MOMENT</div>
            <h1>
              Find what’s <span>happening, {user.name?.split(" ")[0] || "there"}.</span>
            </h1>
            <p>Explore upcoming events, find your community, and make campus life yours.</p>
            <div className="student-discover-meta">
              <span>{pagination.total ?? events.length} events to explore</span>
              <span>{organizers.length} departments</span>
            </div>
          </section>
        )}

        {notice && (
          <div className={`notice ${noticeType === "info" ? "notice-info" : ""}`} role="status" aria-live="polite">
            <span className="notice-icon">
              {noticeType === "info" ? <Info size={20} /> : <CheckCircle2 size={20} />}
            </span>
            <span className="notice-copy">
              <strong>{noticeType === "info" ? "Update" : "Success"}</strong>
              <span>{notice}</span>
            </span>
            <button onClick={dismissNotice} aria-label="Dismiss">
              <X size={15} />
            </button>
          </div>
        )}
        {error && (
          <div className="error-banner" role="alert">
            <span>{error}</span>
            <button
              onClick={() => {
                const retry = retryAction;
                clearError();
                retry ? retry() : loadEvents();
              }}
            >
              <RefreshCw size={15} /> Retry
            </button>
            <button className="dismiss" onClick={clearError} aria-label="Dismiss">
              <X size={15} />
            </button>
          </div>
        )}

        {view === "discover" && (
          <EventDiscoveryView
            events={events}
            pagination={pagination}
            search={search}
            setSearch={setSearch}
            date={date}
            setDate={setDate}
            category={category}
            setCategory={setCategory}
            organizer={organizer}
            setOrganizer={setOrganizer}
            organizers={organizers}
            sort={sort}
            setSort={setSort}
            loading={loading}
            cardProps={cardProps}
            onResetFilters={resetFilters}
            onPage={loadEvents}
          />
        )}
        {view === "admin-dashboard" && user?.role === "admin" && (
          <AdminDashboard
            http={http}
            user={user}
            onNotice={showNotice}
            section={adminSection}
            onSectionChange={setAdminSection}
          />
        )}
        {view === "setup-account" && (
          <InvitationSetupView
            http={http}
            token={invitationToken}
            onLogin={() => {
              setAuthMode("login");
              setAuthOpen(true);
              setView("discover");
              window.history.replaceState(null, "", window.location.pathname + window.location.search);
            }}
          />
        )}
        {view === "announcements" && (
          <AnnouncementsView
            announcements={announcements}
            loading={announcementsLoading}
            course={announcementCourse}
            setCourse={setAnnouncementCourse}
            organizer={announcementOrganizer}
            setOrganizer={setAnnouncementOrganizer}
            organizers={organizers}
            search={announcementSearch}
            setSearch={setAnnouncementSearch}
            emojis={announcementEmojis}
            unreadCount={unreadAnnouncements}
            user={user}
            busyId={announcementBusyId}
            prettyDate={prettyDate}
            onMarkRead={markAnnouncementRead}
            onReact={reactToAnnouncement}
            onOpenOrganizer={openOrganizer}
            onLogin={() => requireLogin("Log in to react to announcements.")}
            onReset={resetAnnouncementFilters}
            onReload={loadAnnouncements}
          />
        )}
        {view === "organizers" && (
          <OrganizersView organizers={organizers} loading={organizersLoading} onOpen={openOrganizer} />
        )}
        {view === "organizer" && organizerPage && (
          <OrganizerPage
            organizer={organizerPage}
            cardProps={cardProps}
            onBack={() => go(backView === "organizer" ? "organizers" : backView)}
          />
        )}
        {view === "event" && selected && (
          <EventPage
            event={selected}
            isRsvped={hasRsvped(selected.id)}
            isSaved={isSaved(selected.id)}
            inCalendar={inCalendar(selected.id)}
            isBusy={rsvpBusyEventId === selected.id}
            loadAttendees={(id) => http.get(`/events/${id}/attendees`).then((r) => r.data)}
            onBack={() => go(backView)}
            onRsvp={toggleRsvp}
            onSave={toggleSave}
            onCalendar={toggleCalendar}
            onOrganizer={openOrganizer}
            onReport={submitEventReport}
            onLogin={user ? undefined : () => {
              setAuthMode("login");
              setAuthOpen(true);
              clearError();
            }}
            onNotice={showNotice}
            prettyDate={prettyDate}
            canRsvp={user?.role !== "organizer"}
            canReport={Boolean(user) && user.organizer?.id !== selected.organizer_id}
          />
        )}
        {view === "calendar" && (
          <CalendarView
            loadMonth={(month) =>
              http.get("/events/calendar", { params: { month } }).then((r) => r.data.data || [])
            }
            onOpen={openEvent}
          />
        )}
        {view === "archive" && (
          <ArchiveView
            events={archive}
            pagination={archivePagination}
            loading={archiveLoading}
            search={archiveSearch}
            setSearch={setArchiveSearch}
            cardProps={cardProps}
            onPage={loadArchive}
          />
        )}
        {view === "dashboard" && ["organizer", "faculty"].includes(user?.role) && (
          <OrganizerDashboard
            http={http}
            userName={user.name}
            organizer={user.organizer}
            onNotice={showNotice}
          />
        )}
        {view === "settings" && user && (
          <UserSettingsView
            http={http}
            user={user}
            onUserChange={updateSignedInUser}
            onNotice={showNotice}
          />
        )}
        {view === "my-rsvps" && (
          <EventListView
            eyebrow="YOUR CAMPUS PLANS"
            title="My"
            titleAccent="RSVPs."
            subtitle="Events you have registered to attend."
            emptyIcon={<CalendarDays size={27} />}
            emptyTitle="No RSVPs yet"
            emptyText="Choose an event and RSVP to see it here."
            events={myRsvps}
            loading={listLoading}
            cardProps={cardProps}
            myRsvp
            onRefresh={loadMyLists}
            onBrowse={() => go("discover")}
          />
        )}
        {view === "check-in-history" && (
          <CheckInHistoryView
            checkIns={checkIns}
            loading={checkInsLoading}
            prettyDate={prettyDate}
            onRefresh={loadCheckIns}
          />
        )}
        {view === "saved" && (
          <EventListView
            eyebrow="FOR LATER"
            title="Bookmarked"
            titleAccent="events."
            subtitle="Events you saved so you can find them again."
            emptyIcon={<Bookmark size={27} />}
            emptyTitle="Nothing bookmarked yet"
            emptyText="Tap the bookmark icon on an event to keep it here."
            events={saved}
            loading={listLoading}
            cardProps={cardProps}
            onRefresh={loadMyLists}
            onBrowse={() => go("discover")}
          />
        )}
        {view === "my-calendar" && (
          <EventListView
            eyebrow="YOUR SCHEDULE"
            title="My calendar"
            titleAccent="list."
            subtitle="Events you added to your personal calendar list."
            emptyIcon={<CalendarDays size={27} />}
            emptyTitle="Your calendar list is empty"
            emptyText="Use the calendar-plus icon on an event to add it."
            events={calendarList}
            loading={listLoading}
            cardProps={cardProps}
            onRefresh={loadMyLists}
            onBrowse={() => go("discover")}
            onDownloadAll={() => downloadIcs(calendarList, "my-campus-events.ics")}
          />
        )}

        {view === "discover" && !user && (
          <section className="bottom-cta" id="about">
            <div className="cta-icon">
              <CalendarDays size={25} />
            </div>
            <div>
              <h2>There’s more to college than classes.</h2>
              <p>Show up, get involved, and make your SPC story count.</p>
            </div>
            <a
              href="#events"
              className="btn btn-light"
              onClick={(e) => {
                e.preventDefault();
                go("discover");
              }}
            >
              Find an event <ArrowUpRight size={16} />
            </a>
          </section>
        )}
      </main>
      <footer>
        <span>© 2026 CampusEvents Hub</span>
        <span>
          Built for the SPC community <b>♥</b>
        </span>
      </footer>

      {authOpen && (
        <AuthModal
          mode={authMode}
          form={authForm}
          departments={departmentOptions}
          departmentsLoading={departmentsLoading}
          departmentsError={departmentsError}
          onFormChange={(field, value) => setAuthForm((cur) => ({ ...cur, [field]: value }))}
          onModeChange={setAuthMode}
          onSubmit={loginOrRegister}
          isSubmitting={authSubmitting}
          onClose={() => {
            if (!authSubmitting) setAuthOpen(false);
          }}
          onClearError={clearError}
          error={error}
        />
      )}
      <SeatConfirmationModal
        confirmation={seatConfirmation}
        onClose={() => setSeatConfirmation(null)}
      />
    </div>
  );
}

createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
