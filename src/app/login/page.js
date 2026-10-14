'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import ReCAPTCHA from 'react-google-recaptcha';
import TypedText from '@/components/TypedText';
import siteConfig from "@/config/siteConfig";

export default function LoginPage() {
  const [formData, setFormData] = useState({
    identifier: '',
    password: '',
  });
  const [captchaToken, setCaptchaToken] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [showResendVerification, setShowResendVerification] = useState(false);
  const [verificationEmail, setVerificationEmail] = useState('');
  const [resendLoading, setResendLoading] = useState(false);
  const [resendSuccess, setResendSuccess] = useState('');
  const [resendError, setResendError] = useState('');
  const [userList, setUserList] = useState([]);
  const [selectedUser, setSelectedUser] = useState('');
  const recaptchaRef = useRef(null);
  const router = useRouter();

  const RECAPTCHA_SITE_KEY = process.env.NEXT_PUBLIC_RECAPTCHA_SITE_KEY;

  // API Base URL from environment
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

  // Load saved credentials on mount and auto-fill if available
  useEffect(() => {
    const savedCredentials = localStorage.getItem('site_login_credentials');
    if (savedCredentials) {
      try {
        const credentials = JSON.parse(savedCredentials);
        setFormData(prev => ({
          ...prev,
          identifier: credentials.identifier || '',
          password: credentials.password || ''
        }));
        setRememberMe(true);
      } catch (err) {
        console.error('Error loading saved credentials:', err);
      }
    }
  }, []);

  // Auto-populate password when identifier matches saved credentials
  const handleIdentifierChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));

    // Check if entered identifier matches saved one
    if (name === 'identifier') {
      const savedCredentials = localStorage.getItem('site_login_credentials');
      if (savedCredentials) {
        try {
          const credentials = JSON.parse(savedCredentials);
          if (credentials.identifier === value.trim()) {
            // Auto-populate password if identifier matches
            setFormData(prev => ({
              ...prev,
              password: credentials.password || ''
            }));
            setRememberMe(true);
          }
        } catch (err) {
          console.error('Error checking saved credentials:', err);
        }
      }
    }

    if (error) setError('');
    if (resendError) setResendError('');
    if (resendSuccess) setResendSuccess('');
    if (userList.length > 0) {
      setUserList([]);
      setSelectedUser('');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setResendSuccess('');
    setResendError('');
    setShowResendVerification(false);

    // In development/localhost, we can bypass the reCAPTCHA requirement
    const isLocal = typeof window !== 'undefined' &&
      (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

    if (!captchaToken && !isLocal) {
      setError('Please verify that you are not a robot');
      setLoading(false);
      return;
    }

    if (!formData.identifier.trim() || !formData.password.trim()) {
      setError('Please fill in all fields');
      setLoading(false);
      return;
    }

    if (userList.length > 0 && !selectedUser) {
      setError('Please select an account holder name from the dropdown');
      setLoading(false);
      return;
    }

    try {
      // Call PHP API
      const response = await fetch(`${API_BASE_URL}/users.php?action=login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          identifier: formData.identifier.trim(),
          password: formData.password,
          captchaToken: captchaToken || 'local_bypass',
          selected_user: selectedUser || undefined
        })
      });

      // Get raw response first
      const responseText = await response.text();

      // Try to parse JSON
      let result;
      try {
        result = JSON.parse(responseText);
        console.log('Parsed result:', result);
      } catch (parseError) {
        console.error('Failed to parse JSON:', parseError);
        setError('Server returned invalid response. Please try again.');
        setLoading(false);
        return;
      }

      if (result.success) {
        // Clear userList if success
        setUserList([]);
        setSelectedUser('');

        // Store user data in localStorage
        if (result.data?.user) {
          localStorage.setItem('site_user', JSON.stringify(result.data.user));
          localStorage.setItem('site_auth', 'true');
          localStorage.setItem('site_last_activity', String(Date.now()));

          // If remember me is checked, store credentials
          if (rememberMe) {
            localStorage.setItem('site_login_credentials', JSON.stringify({
              identifier: formData.identifier.trim(),
              password: formData.password
            }));
            localStorage.setItem('site_remember', 'true');
          } else {
            // Clear saved credentials if remember me is unchecked
            localStorage.removeItem('site_login_credentials');
            localStorage.removeItem('site_remember');
          }
        }

        // If there's a token, store it
        if (result.data?.token) {
          localStorage.setItem('site_token', result.data.token);
        }

        // Determine redirect URL based on user status and portfolio data
        const redirectUrl = await determineRedirectUrl(result.data?.token || localStorage.getItem('site_token'));
        router.push(redirectUrl);

      } else {
        // Reset captcha so user must re-verify on retry
        recaptchaRef.current?.reset();
        setCaptchaToken(null);

        // Check if multiple users found
        const usersFound = result.data?.users || (Array.isArray(result.data) ? result.data : null);
        if ((result.message?.toLowerCase().includes('multiple user') || usersFound) && Array.isArray(usersFound) && usersFound.length > 0) {
          setUserList(usersFound);
          setSelectedUser(usersFound[0] || '');
          setError('Multiple accounts found. Please select your name from the dropdown below and sign in.');
        } else {
          setError(result.message || 'Login failed. Please check your credentials.');
        }

        // Check if error is about email verification
        if (result.message?.toLowerCase().includes('verification') ||
          result.message?.toLowerCase().includes('verify') ||
          result.message?.toLowerCase().includes('not active')) {

          // Try to extract email from identifier
          const identifier = formData.identifier.trim();
          if (identifier.includes('@')) {
            setVerificationEmail(identifier);
            setShowResendVerification(true);
          } else {
            // Ask for email for resend verification
            setShowResendVerification(true);
          }
        }
      }
    } catch (err) {
      console.error('Login error:', err);
      setError('Network error. Please check your connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleResendVerification = async () => {
    if (!verificationEmail.trim()) {
      setResendError('Please enter your email id');
      return;
    }

    if (!captchaToken) {
      setResendError('Please verify that you are not a robot');
      return;
    }

    setResendLoading(true); // Fixed: setResendLoading instead of setResendResendLoading
    setResendError('');
    setResendSuccess('');

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=send_verification`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: verificationEmail.trim()
        })
      });

      const result = await response.json();

      if (result.success) {
        setResendSuccess('Verification email sent successfully! Please check your inbox (and spam folder).');
        setResendError('');
      } else {
        setResendError(result.message || 'Failed to send verification email. Please try again.');
        setResendSuccess('');
      }
    } catch (err) {
      console.error('Resend verification error:', err);
      setResendError('Network error. Please try again.');
    } finally {
      setResendLoading(false); // Fixed: setResendLoading instead of setResendResendLoading
    }
  };

  const determineRedirectUrl = async (token) => {
    try {
      const storedUser = localStorage.getItem('site_user');
      if (!storedUser) return '/dashboard/home';

      const userData = JSON.parse(storedUser);
      const userId = userData.id || userData.user_id;

      // ── 1. Fetch account status ──────────────────────────────────────────
      const statusResponse = await fetch(
        `${API_BASE_URL}/user_ac_status_api.php?user_id=${userId}&t=${new Date().getTime()}`,
        {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          cache: 'no-store'
        }
      );

      const statusData = statusResponse.ok ? await statusResponse.json() : { success: false };
      const userStatus = statusData.success && statusData.data ? statusData.data : {};

      // console.log('User status:', userStatus);

      // ── 2. Check activation_status ───────────────────────────────────────
      // 'Y' = Client Activated, anything else = Activation Pending
      const isClientActivated = userStatus.activation_status === 'Y';
      // console.log('Client Activated:', isClientActivated);

      if (!isClientActivated) {
        console.log('✗ Redirecting to /dashboard/home - activation pending');
        return '/dashboard/home';
      }

      // ── 3. Activated → check total investment ────────────────────────────
      const portfolioResponse = await fetch(
        `${API_BASE_URL}/grand_folio_api.php`,
        {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        }
      );

      if (!portfolioResponse.ok) {
        // console.log('Portfolio API not ok, status:', portfolioResponse.status);
        return '/invest';
      }

      const portfolioData = await portfolioResponse.json();

      const assetObj = Array.isArray(portfolioData.totalAssetInfo)
        ? portfolioData.totalAssetInfo[0]
        : portfolioData.totalAssetInfo;

      // Strip commas from formatted string e.g. "6,550,673.58" → 6550673.58
      const rawValue = assetObj?.Total_Current_Value ?? '0';
      const totalInvestment = parseFloat(String(rawValue).replace(/,/g, '')) || 0;
      // console.log('Total_Current_Value:', rawValue, '→ parsed:', totalInvestment);

      // ── 4. Final redirect decision ───────────────────────────────────────
      // Client Activated + investment > 0  → /portfolio
      // Client Activated + investment = 0  → /invest
      if (totalInvestment > 0) {
        // console.log('✓ Redirecting to /portfolio - activated & investment > 0');
        return '/portfolio';
      } else {
        console.log('✗ Redirecting to /invest - activated but investment is 0');
        return '/invest';
      }

    } catch (error) {
      console.error('Error determining redirect URL:', error);
      return '/dashboard/home';
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;

    // Use special handler for identifier field
    if (name === 'identifier') {
      handleIdentifierChange(e);
    } else {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }));
      if (error) setError('');
      if (resendError) setResendError('');
      if (resendSuccess) setResendSuccess('');
    }
  };

  const handleRememberMeChange = (e) => {
    const isChecked = e.target.checked;
    setRememberMe(isChecked);

    // If unchecking, clear saved credentials
    if (!isChecked) {
      localStorage.removeItem('site_login_credentials');
      localStorage.removeItem('site_remember');
    }
  };

  const handleVerificationEmailChange = (e) => {
    setVerificationEmail(e.target.value);
    if (resendError) setResendError('');
    if (resendSuccess) setResendSuccess('');
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
      {/* Main Content */}
      <div className="py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md mx-auto">
          {/* Header */}
          <div className="text-center mb-10">
            <h1 className="text-3xl font-bold text-gray-900 mb-4">
              <TypedText text={`Sign In to ${siteConfig.siteName}`} className="text-gray-900" />
            </h1>
            <p className="text-gray-600">
              Access your investment portfolio and financial analytics
            </p>
          </div>

          {/* Login Card */}
          <div className="bg-white rounded-2xl shadow-xl p-8">
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* PAN/Mobile/Email Field */}
              <div>
                <label className="block text-gray-700 mb-2 font-medium">
                  PAN No. or Mobile No. or Email address:
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                  </div>
                  <input
                    type="text"
                    name="identifier"
                    value={formData.identifier}
                    onChange={handleChange}
                    disabled={loading || resendLoading}
                    className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                    placeholder="Enter PAN, mobile or email"
                    required
                  />
                </div>
                <p className="text-sm text-gray-500 mt-2">
                  Enter any one of PAN, Mobile Number, or Email ID
                </p>
              </div>

              {/* User Selection Dropdown when multiple users found */}
              {userList.length > 0 && (
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 animate-fadeIn">
                  <label className="block text-blue-900 mb-2 font-semibold text-sm">
                    Select Account / Primary Holder Name:
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <select
                      value={selectedUser}
                      onChange={(e) => {
                        setSelectedUser(e.target.value);
                        if (error) setError('');
                      }}
                      disabled={loading || resendLoading}
                      className="w-full pl-10 pr-10 py-2.5 border border-blue-300 bg-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-gray-800 font-medium transition-colors cursor-pointer"
                      required
                    >
                      <option value="" disabled>-- Select account holder --</option>
                      {userList.map((name, idx) => (
                        <option key={idx} value={name}>
                          {name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <p className="text-xs text-blue-700 mt-2">
                    Found {userList.length} accounts with this identifier. Choose your name and sign in.
                  </p>
                </div>
              )}

              {/* Password Field */}
              <div>
                <label className="block text-gray-700 mb-2 font-medium">
                  Password:
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <input
                    type={showPassword ? "text" : "password"}
                    name="password"
                    value={formData.password}
                    onChange={handleChange}
                    disabled={loading || resendLoading}
                    className="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                    placeholder="Enter your password"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    disabled={loading || resendLoading}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    {showPassword ? (
                      <svg className="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    ) : (
                      <svg className="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>

              {/* Remember Me & Forgot Password */}
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    id="remember"
                    checked={rememberMe}
                    onChange={handleRememberMeChange}
                    disabled={loading || resendLoading}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <label htmlFor="remember" className="ml-2 text-sm text-gray-700">
                    Remember me
                  </label>
                </div>
                <Link
                  href="/forgot-password"
                  className="text-sm text-blue-600 hover:text-blue-800 hover:underline transition-colors"
                >
                  Forgot password?
                </Link>
              </div>

              {/* Google reCAPTCHA v2 */}
              <div>
                <ReCAPTCHA
                  ref={recaptchaRef}
                  sitekey={RECAPTCHA_SITE_KEY}
                  onChange={(token) => {
                    setCaptchaToken(token);
                    if (error) setError('');
                    if (resendError) setResendError('');
                  }}
                  onExpired={() => {
                    setCaptchaToken(null);
                    setError('CAPTCHA expired. Please verify again.');
                  }}
                  onErrored={() => {
                    setCaptchaToken(null);
                    setError('CAPTCHA failed to load. Please refresh the page.');
                  }}
                />
              </div>

              {/* Error Message */}
              {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                  <div className="flex items-center">
                    <svg className="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                    </svg>
                    <span className="text-red-700">{error}</span>
                  </div>
                </div>
              )}

              {/* Resend Verification Section (shown when email not verified) */}
              {showResendVerification && (
                <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg space-y-3">
                  <div className="flex items-start">
                    <svg className="w-5 h-5 text-yellow-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                    </svg>
                    <div className="flex-1">
                      <p className="text-yellow-800 font-medium">Email Verification Required</p>
                      <p className="text-sm text-yellow-700 mt-1">
                        Your email address needs to be verified before you can log in.
                        Check your inbox for the verification email or resend it below.
                      </p>
                    </div>
                  </div>

                  {!verificationEmail.includes('@') && (
                    <div>
                      <label className="block text-gray-700 mb-1 text-sm font-medium">
                        Email address for verification:
                      </label>
                      <input
                        type="email"
                        value={verificationEmail}
                        onChange={handleVerificationEmailChange}
                        disabled={resendLoading}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                        placeholder="Enter your email address"
                      />
                    </div>
                  )}

                  <div className="space-y-2">
                    <button
                      type="button"
                      onClick={handleResendVerification}
                      disabled={resendLoading || !captchaToken}
                      className={`w-full py-2 px-4 rounded-lg font-medium text-white transition-colors ${resendLoading || !captchaToken
                          ? 'bg-gray-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700'
                        }`}
                    >
                      {resendLoading ? (
                        <div className="flex items-center justify-center">
                          <svg className="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Sending...
                        </div>
                      ) : (
                        'Resend Verification Email'
                      )}
                    </button>

                    {resendSuccess && (
                      <div className="p-2 bg-green-50 border border-green-200 rounded">
                        <div className="flex items-center">
                          <svg className="w-4 h-4 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                          </svg>
                          <span className="text-sm text-green-700">{resendSuccess}</span>
                        </div>
                      </div>
                    )}

                    {resendError && (
                      <div className="p-2 bg-red-50 border border-red-200 rounded">
                        <div className="flex items-center">
                          <svg className="w-4 h-4 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                          </svg>
                          <span className="text-sm text-red-700">{resendError}</span>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Submit Button */}
              <button
                type="submit"
                disabled={loading || resendLoading || !captchaToken}
                className={`w-full py-3 px-4 rounded-lg font-semibold text-white transition-colors ${loading || resendLoading || !captchaToken
                    ? 'bg-gray-400 cursor-not-allowed'
                    : 'bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900'
                  }`}
              >
                {loading ? (
                  <div className="flex items-center justify-center">
                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Signing in...
                  </div>
                ) : (
                  'Sign In'
                )}
              </button>

              {/* Sign Up Link */}
              <div className="text-center pt-4 border-t border-gray-200">
                <p className="text-gray-600">
                  Don&apos;t have an account?{' '}
                  <Link
                    href="/signup"
                    className="text-blue-600 font-semibold hover:text-blue-800 hover:underline transition-colors"
                  >
                    Sign Up
                  </Link>
                </p>
              </div>
            </form>

            {/* Security Note */}
            <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-100">
              <div className="flex items-start">
                <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                </svg>
                <div>
                  <p className="text-sm text-blue-800 font-medium">Secure & Encrypted Login</p>
                  <p className="text-xs text-blue-600 mt-1">
                    Your login information is encrypted with 256-bit SSL encryption and securely transmitted.
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Footer - Only copyright */}
          {/* <div className="mt-8 text-center">
            <p className="text-xs text-gray-400">
              © {new Date().getFullYear()} {siteConfig.siteName}. All rights reserved.
            </p>
          </div> */}
        </div>
      </div>
    </div>
  );
}