import { useEffect, useState } from "react";

export default function useDelayedLoading(loading, delay = 100) {
  const [showLoading, setShowLoading] = useState(false);

  useEffect(() => {
    if (!loading) {
      setShowLoading(false);
      return undefined;
    }

    const timer = window.setTimeout(() => setShowLoading(true), delay);
    return () => window.clearTimeout(timer);
  }, [loading, delay]);

  return showLoading;
}
