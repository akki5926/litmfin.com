'use client';

import { useEffect, useRef, useCallback } from 'react';
import { useRouter, usePathname } from 'next/navigation';

// ─── Config ────────────────────────────────────────────────────────────────
const INACTIVITY_TIMEOUT_MS = 60 * 60 * 1000;   // 1 hour
const THROTTLE_MS            = 30 * 1000;         // record activity at most once per 30 s
const PERIODIC_CHECK_MS      = 60 * 1000;         // backend session check every 60 s
const LAST_ACTIVITY_KEY      = 'site_last_activity'; // localStorage key
// ───────────────────────────────────────────────────────────────────────────

export default function SessionManager({ children }) {
  const router          = useRouter();
  const pathname        = usePathname();
  const inactivityTimer = useRef(null);
  const checkInterval   = useRef(null);
  const isLoggingOut    = useRef(false);
  const lastThrottleRef = useRef(0); // in-memory throttle guard

  // ── Helpers ───────────────────────────────────────────────────────────────

  const isPublicPage = useCallback(() => {
    const publicPaths = ['/login', '/signup', '/forgot-password', '/verify-email'];
    return publicPaths.includes(pathname) || pathname?.startsWith('/verify-email');
  }, [pathname]);

  // Record that the user was just active (throttled so high-freq events don't spam)
  const recordActivity = useCallback(() => {
    const now = Date.now();
    if (now - lastThrottleRef.current < THROTTLE_MS) return;
    lastThrottleRef.current = now;
    // Persist to localStorage so activity survives page navigation / refresh
    localStorage.setItem(LAST_ACTIVITY_KEY, String(now));
  }, []);

  // How long (ms) since the user last did something
  const msSinceLastActivity = useCallback(() => {
    const stored = localStorage.getItem(LAST_ACTIVITY_KEY);
    if (!stored) return Infinity; // no record → treat as timed out
    return Date.now() - parseInt(stored, 10);
  }, []);

  // ── Logout ────────────────────────────────────────────────────────────────

  const logout = useCallback((redirectToLogin = true) => {
    if (isLoggingOut.current) return;
    isLoggingOut.current = true;

    // Clear auth state
    localStorage.removeItem('site_user');
    localStorage.removeItem('site_auth');
    localStorage.removeItem('site_token');
    localStorage.removeItem(LAST_ACTIVITY_KEY);

    // Clear timers
    if (inactivityTimer.current) clearTimeout(inactivityTimer.current);
    if (checkInterval.current)   clearInterval(checkInterval.current);

    // Tell the backend to destroy the PHP session
    const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/site/api';
    fetch(`${API_BASE_URL}/users.php?action=logout`, {
      method: 'POST',
      credentials: 'include',
    }).catch(() => {});

    if (redirectToLogin && !isPublicPage()) {
      router.push('/login?expired=true');
    }

    setTimeout(() => { isLoggingOut.current = false; }, 500);
  }, [router, isPublicPage]);

  // ── Inactivity timer ─────────────────────────────────────────────────────
  //
  // Uses localStorage timestamp as source of truth so the timer stays correct
  // after page navigations and refreshes.

  const scheduleInactivityLogout = useCallback(() => {
    if (inactivityTimer.current) clearTimeout(inactivityTimer.current);

    const idle = msSinceLastActivity();

    if (idle >= INACTIVITY_TIMEOUT_MS) {
      // Already over the limit — log out immediately
      logout(true);
      return;
    }

    // Schedule the logout for exactly when the 1-hour mark will be reached
    const remaining = INACTIVITY_TIMEOUT_MS - idle;
    inactivityTimer.current = setTimeout(() => logout(true), remaining);
  }, [logout, msSinceLastActivity]);

  // ── Activity listeners ────────────────────────────────────────────────────

  const setupActivityListeners = useCallback(() => {
    // mousemove / scroll fire constantly — throttle to avoid timer spam
    const highFreq  = ['mousemove', 'scroll'];
    const directEvt = ['mousedown', 'keydown', 'touchstart', 'click'];

    const handleThrottled = () => {
      recordActivity();
      scheduleInactivityLogout();
    };

    const handleDirect = () => {
      // Direct interactions always register (reset throttle guard first)
      lastThrottleRef.current = 0;
      recordActivity();
      scheduleInactivityLogout();
    };

    highFreq.forEach(ev  => window.addEventListener(ev, handleThrottled, { passive: true }));
    directEvt.forEach(ev => window.addEventListener(ev, handleDirect));

    return () => {
      highFreq.forEach(ev  => window.removeEventListener(ev, handleThrottled));
      directEvt.forEach(ev => window.removeEventListener(ev, handleDirect));
    };
  }, [recordActivity, scheduleInactivityLogout]);

  // ── Periodic backend session check ────────────────────────────────────────
  //
  // Every 60 s:
  //   1. Check localStorage idle time first — if already over 1 hr, logout
  //      without a network call (works offline too).
  //   2. Otherwise, validate the token with the backend.

  const checkSessionWithBackend = useCallback(async () => {
    const token = localStorage.getItem('site_token');
    const auth  = localStorage.getItem('site_auth');

    if (!auth || auth !== 'true') return false;

    // ① Frontend idle check first
    if (msSinceLastActivity() >= INACTIVITY_TIMEOUT_MS) {
      logout(true);
      return false;
    }

    const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/site/api';

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=check_session`, {
        method: 'GET',
        headers: {
          'Authorization': token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
      });

      if (response.status === 401) { logout(true); return false; }

      const result = await response.json();
      if (!result.success) { logout(true); return false; }

      return true;
    } catch {
      // Don't logout on network errors — user might be offline
      return true;
    }
  }, [logout, msSinceLastActivity]);

  const startPeriodicCheck = useCallback(() => {
    if (checkInterval.current) clearInterval(checkInterval.current);

    checkInterval.current = setInterval(() => {
      if (!isPublicPage()) checkSessionWithBackend();
    }, PERIODIC_CHECK_MS);

    return () => { if (checkInterval.current) clearInterval(checkInterval.current); };
  }, [isPublicPage, checkSessionWithBackend]);

  // ── Bootstrap ─────────────────────────────────────────────────────────────

  useEffect(() => {
    if (isPublicPage()) return;

    const auth = localStorage.getItem('site_auth');
    if (!auth || auth !== 'true') {
      router.push('/login');
      return;
    }

    // Stamp activity now if this is a fresh session (no prior record)
    if (!localStorage.getItem(LAST_ACTIVITY_KEY)) {
      localStorage.setItem(LAST_ACTIVITY_KEY, String(Date.now()));
    }

    // Initial checks
    checkSessionWithBackend();
    scheduleInactivityLogout();

    // Background workers
    const cleanupInterval = startPeriodicCheck();
    const cleanupEvents   = setupActivityListeners();

    return () => {
      cleanupInterval();
      cleanupEvents();
      if (inactivityTimer.current) clearTimeout(inactivityTimer.current);
    };
  }, [isPublicPage, checkSessionWithBackend, scheduleInactivityLogout,
      startPeriodicCheck, setupActivityListeners, router]);

  return children;
}