import {
  BarChart3,
  CalendarDays,
  ClipboardList,
  Flag,
  Landmark,
  LayoutDashboard,
  Megaphone,
  Settings,
  Users,
} from "lucide-react";

const ADMIN_SECTIONS = [
  ["overview", "Dashboard", LayoutDashboard],
  ["users", "User Management", Users],
  ["departments", "Colleges / Departments", Landmark],
  ["events", "Event Management", CalendarDays],
  ["announcements", "Announcements", Megaphone],
  ["reports", "Reported Events", Flag],
  ["analytics", "Reports & Analytics", BarChart3],
  ["activity", "Activity Log", ClipboardList],
  ["settings", "Settings", Settings],
];

export default ADMIN_SECTIONS;
