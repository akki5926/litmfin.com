'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

// ─── Date helpers ────────────────────────────────────────────────────────────

const getTodayISO = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const getMaxEndDateISO = () => {
  const d = new Date();
  d.setFullYear(d.getFullYear() + 15);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const formatDateForDisplay = (mysqlDate) => {
  if (!mysqlDate || mysqlDate === '0000-00-00') return '—';
  const d = new Date(mysqlDate);
  if (isNaN(d.getTime())) return mysqlDate;
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
};

const toApiDateFormat = (isoDate) => {
  if (!isoDate) return '';
  const [y, m, d] = isoDate.split('-');
  return `${d}/${m}/${y}`;
};

// ─── Status helpers ───────────────────────────────────────────────────────────

const isMandateActive = (m) => {
  if (!m || m.reg_status !== 'APPROVED') return false;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const s = new Date(m.start_date); s.setHours(0, 0, 0, 0);
  const e = new Date(m.end_date);   e.setHours(0, 0, 0, 0);
  return s <= today && e >= today;
};

const STATUS_MAP = {
  APPROVED:  { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', label: 'Approved' },
  ACTIVE:    { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', label: 'Active' },
  PENDING:   { cls: 'bg-amber-50 text-amber-700 ring-amber-200', label: 'Pending' },
  REJECTED:  { cls: 'bg-rose-50 text-rose-700 ring-rose-200', label: 'Rejected' },
  CANCELLED: { cls: 'bg-gray-100 text-gray-600 ring-gray-200', label: 'Cancelled' },
  EXPIRED:   { cls: 'bg-orange-50 text-orange-700 ring-orange-200', label: 'Expired' },
  FAILED:    { cls: 'bg-rose-50 text-rose-700 ring-rose-200', label: 'Failed' },
};

const getStatusBadge = (status) =>
  STATUS_MAP[status] ?? { cls: 'bg-gray-100 text-gray-600', label: status || 'Unknown' };

// ─── Small reusable components ────────────────────────────────────────────────

function Alert({ type = 'info', children, onClose }) {
  const colourMap = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error:   'bg-rose-50 border-rose-200 text-rose-800',
    warning: 'bg-amber-50 border-amber-200 text-amber-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
  };
  return (
    <div className={`mb-5 flex gap-3 p-4 rounded-xl border ${colourMap[type]}`}>
      <div className="flex-1 text-sm">{children}</div>
      {onClose && (
        <button onClick={onClose} className="shrink-0 opacity-60 hover:opacity-100 text-lg leading-none">×</button>
      )}
    </div>
  );
}

function IconRefresh({ spinning, cls = 'w-4 h-4' }) {
  return (
    <svg className={`${cls} ${spinning ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function MandateRegistrationPage() {
  const router = useRouter();

  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);  // FIX: store JWT token in state
  const [loading, setLoading] = useState(true);
  const [mandates, setMandates] = useState([]);
  const [clientCode, setClientCode] = useState('');

  const [showForm, setShowForm] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [apiError, setApiError] = useState('');

  const [formData, setFormData] = useState({ start_date: getTodayISO(), end_date: '', amount: '' });
  const [errors, setErrors] = useState({});

  const applyMandateData = useCallback((data) => {
    setMandates(data?.mandates ?? []);
    setClientCode(data?.client_code ?? '');
  }, []);

  // FIX: Helper to build auth headers using the JWT token
  const authHeaders = useCallback((extraHeaders = {}) => {
    return {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...extraHeaders,
    };
  }, [token]);

  useEffect(() => {
    const storedUser = localStorage.getItem('site_user');
    // FIX: read token from localStorage — adjust the key if yours differs
    // Common alternatives: 'token', 'authToken', 'jwt', 'access_token'
    const storedToken = localStorage.getItem('site_token');

    if (!storedUser || !storedToken) {
      setLoading(false);
      return;
    }

    try {
      const userData = JSON.parse(storedUser);
      setUser(userData);
      setToken(storedToken); // FIX: save token to state

      // FIX: send JWT in Authorization header instead of user_id query param
      fetch(`${API_BASE_URL}/mandate_api.php`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${storedToken}`,
        },
        cache: 'no-cache',
      })
        .then((r) => r.json())
        .then((data) => { if (data.success) applyMandateData(data.data); })
        .catch((err) => console.error('Mandate fetch error:', err))
        .finally(() => setLoading(false));
    } catch {
      setLoading(false);
    }
  }, [applyMandateData]);

  const handleRefresh = async () => {
    if (!token) return; // FIX: guard on token instead of user.id

    setIsRefreshing(true);
    setApiError('');
    setSuccessMsg('');

    try {
      // FIX: send JWT in Authorization header instead of user_id query param
      const res = await fetch(
        `${API_BASE_URL}/mandate_api.php?action=refresh`,
        {
          headers: authHeaders(),
          cache: 'no-cache',
        }
      );
      const data = await res.json();

      if (data.success) {
        applyMandateData(data.data);
        setSuccessMsg('Mandate status refreshed successfully from NSE.');
      } else {
        setApiError(data.message || 'Refresh failed. Please try again.');
      }
    } catch (err) {
      setApiError('Network error. Please check your connection and try again.');
      console.error('Refresh error:', err);
    } finally {
      setIsRefreshing(false);
    }
  };

  const validateForm = () => {
    const newErrors = {};
    const today = new Date(); today.setHours(0, 0, 0, 0);

    if (!formData.end_date) {
      newErrors.end_date = 'End date is required';
    } else if (formData.start_date) {
      const sd = new Date(formData.start_date); sd.setHours(0, 0, 0, 0);
      const ed = new Date(formData.end_date); ed.setHours(0, 0, 0, 0);
      const max = new Date(); max.setFullYear(max.getFullYear() + 15); max.setHours(0, 0, 0, 0);
      if (ed <= sd) newErrors.end_date = 'End date must be after start date';
      if (ed > max) newErrors.end_date = 'End date cannot exceed 15 years from today';
    }

    if (!formData.amount) {
      newErrors.amount = 'Amount is required';
    } else {
      const val = parseFloat(formData.amount);
      if (isNaN(val) || val <= 0) newErrors.amount = 'Amount must be a positive number';
      else if (!/^\d{1,13}(\.\d{1,2})?$/.test(formData.amount)) newErrors.amount = 'Max 13 digits with up to 2 decimal places';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSuccessMsg('');
    setApiError('');
    if (!validateForm()) return;

    setIsSubmitting(true);
    try {
      // FIX: send JWT in Authorization header; remove user_id from URL and body
      const res = await fetch(`${API_BASE_URL}/mandate_api.php`, {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          start_date: toApiDateFormat(formData.start_date),
          end_date: toApiDateFormat(formData.end_date),
          amount: formData.amount,
        }),
      });
      const data = await res.json();

      if (data.success) {
        setSuccessMsg(data.message || 'Mandate registered successfully!');
        setFormData({ start_date: getTodayISO(), end_date: '', amount: '' });
        setShowForm(false);
        setTimeout(handleRefresh, 4000);
      } else {
        setApiError(data.message || 'Failed to register mandate');
        if (data.data?.mandate) {
          // FIX: use auth header here too
          const res2 = await fetch(`${API_BASE_URL}/mandate_api.php`, {
            headers: authHeaders(),
            cache: 'no-cache',
          });
          const d2 = await res2.json();
          if (d2.success) applyMandateData(d2.data);
        }
      }
    } catch (err) {
      setApiError('Network error. Please try again.');
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((p) => ({ ...p, [name]: value }));
    if (errors[name]) setErrors((p) => ({ ...p, [name]: '' }));
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600" />
      </div>
    );
  }

  const hasClientCode = Boolean(clientCode);

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold tracking-tight text-gray-900">Mandate Registration</h1>
        <p className="text-gray-500 mt-1 text-sm">
          Register a mandate only if you plan to invest via SIP. Not required for lump-sum investments.
        </p>
      </div>

      {/* No client code warning */}
      {!hasClientCode && (
        <Alert type="warning">
          <p className="font-semibold mb-1">Client Code Not Available</p>
          <p>You need to complete your KYC / UCC registration before you can register a mandate.</p>
          <button onClick={() => router.push('/dashboard/home')} className="mt-2 font-medium underline">
            Complete Profile →
          </button>
        </Alert>
      )}

      {/* Success/Error Alerts */}
      {successMsg && (
        <Alert type="success" onClose={() => setSuccessMsg('')}>
          {successMsg}
        </Alert>
      )}
      {apiError && (
        <Alert type="error" onClose={() => setApiError('')}>
          {apiError}
        </Alert>
      )}

      {/* Action Buttons */}
      {hasClientCode && mandates.length > 0 && !showForm && (
        <div className="flex flex-wrap gap-3 mb-8">
          <button
            onClick={handleRefresh}
            disabled={isRefreshing}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 shadow-sm transition-all"
          >
            <IconRefresh spinning={isRefreshing} />
            {isRefreshing ? 'Refreshing...' : 'Refresh Status from NSE'}
          </button>
          <button
            onClick={() => { setShowForm(true); setSuccessMsg(''); setApiError(''); }}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 shadow-sm"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Register New Mandate
          </button>
        </div>
      )}

      {/* Mandates Table */}
      {mandates.length > 0 && (
        <div className="mb-10">
          <div className="flex items-baseline justify-between flex-wrap gap-2 mb-4">
            <h2 className="text-lg font-semibold text-gray-800">Your Mandates</h2>
            <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
              {mandates.filter((m) => m.reg_status === 'APPROVED').length} approved
            </span>
          </div>
          <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-100">
                <tr>
                  {['Start Date', 'End Date', 'Amount (₹)', 'Status', 'UMRN / Mandate ID', 'Registered On'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left font-medium text-gray-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {mandates.map((m, idx) => {
                  const badge = getStatusBadge(m.reg_status);
                  const registeredDate = m.registration_date || m.created_at;
                  return (
                    <tr key={m.id ?? idx} className="hover:bg-gray-50">
                      <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{formatDateForDisplay(m.start_date)}</td>
                      <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{formatDateForDisplay(m.end_date)}</td>
                      <td className="px-4 py-3 font-semibold text-gray-900 text-right whitespace-nowrap">
                        ₹ {parseFloat(m.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${badge.cls}`}>
                          {badge.label}
                        </span>
                        {m.reg_remark && m.reg_status !== 'APPROVED' && (
                          <p className="text-xs text-gray-400 mt-1 max-w-[180px] truncate" title={m.reg_remark}>
                            {m.reg_remark}
                          </p>
                        )}
                      </td>
                      <td className="px-4 py-3 text-gray-500 font-mono text-xs">{m.umrn_no || m.mandate_id || '—'}</td>
                      <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{formatDateForDisplay(registeredDate)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Empty State */}
      {hasClientCode && mandates.length === 0 && !showForm && (
        <div className="mb-10 p-10 text-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50">
          <div className="w-14 h-14 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
            <svg className="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 10h18M6 3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6a3 3 0 013-3z" />
            </svg>
          </div>
          <p className="text-gray-700 font-semibold text-base">No Mandate Found</p>
          <p className="text-gray-500 text-sm mt-1 mb-6">Register a mandate to enable automatic SIP debits.</p>
          <div className="flex flex-wrap gap-3 justify-center">
            <button
              onClick={() => { setShowForm(true); setSuccessMsg(''); setApiError(''); }}
              className="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700"
            >
              + Register Mandate ID
            </button>
            <button
              onClick={handleRefresh}
              disabled={isRefreshing}
              className="inline-flex items-center gap-2 px-5 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium"
            >
              <IconRefresh spinning={isRefreshing} />
              {isRefreshing ? 'Checking NSE...' : 'Check NSE for Existing Mandate'}
            </button>
          </div>
        </div>
      )}

      {/* Registration Form */}
      {showForm && hasClientCode && (
        <form onSubmit={handleSubmit} className="mb-10 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/40">
            <h2 className="text-lg font-semibold text-gray-800">Register New Mandate</h2>
          </div>
          <div className="p-6 space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input
                  type="date"
                  name="start_date"
                  value={formData.start_date}
                  readOnly
                  className="w-full px-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm text-gray-500 cursor-not-allowed"
                />
                <p className="text-xs text-gray-400 mt-1">Set to today by default</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">End Date <span className="text-red-500">*</span></label>
                <input
                  type="date"
                  name="end_date"
                  min={formData.start_date || getTodayISO()}
                  max={getMaxEndDateISO()}
                  value={formData.end_date}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.end_date ? 'border-red-300 bg-red-50' : 'border-gray-300'}`}
                />
                {errors.end_date ? (
                  <p className="text-red-500 text-xs mt-1">{errors.end_date}</p>
                ) : (
                  <p className="text-xs text-gray-400 mt-1">Up to 15 years from today</p>
                )}
              </div>
            </div>
            <div className="max-w-md">
              <label className="block text-sm font-medium text-gray-700 mb-1">Maximum Debit Amount (₹) <span className="text-red-500">*</span></label>
              <input
                type="number"
                step="0.01"
                min="1"
                name="amount"
                placeholder="e.g. 10000.00"
                value={formData.amount}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none ${errors.amount ? 'border-red-300 bg-red-50' : 'border-gray-300'}`}
              />
              {errors.amount ? (
                <p className="text-red-500 text-xs mt-1">{errors.amount}</p>
              ) : (
                <p className="text-xs text-gray-400 mt-1">Maximum amount debited per SIP instalment</p>
              )}
            </div>
            <div className="flex items-center gap-4 pt-2">
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-60 transition-colors"
              >
                {isSubmitting ? (
                  <><div className="animate-spin h-4 w-4 border-b-2 border-white rounded-full"></div> Submitting...</>
                ) : (
                  'Register Mandate'
                )}
              </button>
              <button
                type="button"
                onClick={() => { setShowForm(false); setFormData({ start_date: getTodayISO(), end_date: '', amount: '' }); setErrors({}); }}
                className="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
            </div>
          </div>
        </form>
      )}

      {/* Info Footer */}
      <div className="mt-8 p-5 rounded-xl bg-blue-50/60 border border-blue-100">
        <div className="flex gap-3">
          <svg className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div className="text-sm text-blue-800">
            <p className="font-semibold mb-1">When is mandate registration required?</p>
            <p>
              Mandate registration is needed only for <strong>SIP investments</strong>. It authorises automatic
              debits from your bank account on specified dates. For one-time lump-sum investments, mandate
              registration is <strong>not required</strong>.
            </p>
            <p className="mt-2 text-xs opacity-80">
              After registering, click <strong>Refresh Status from NSE</strong> to pull the latest approval status.
              Approval typically takes 3–5 business days.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}