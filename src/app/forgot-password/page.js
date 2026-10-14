'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import Image from 'next/image';
import siteConfig from '@/config/siteConfig';

// Create a separate component for the content that uses useSearchParams
function ForgotPasswordContent() {
  const [pan, setPan] = useState('');
  const [email, setEmail] = useState('');
  const [maskedEmail, setMaskedEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [step, setStep] = useState(1);
  const [otp, setOtp] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [verifyingToken, setVerifyingToken] = useState(false);
  const [tokenValid, setTokenValid] = useState(false);
  
  const router = useRouter();
  const searchParams = useSearchParams();

  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/api';

  useEffect(() => {
    const verifyTokenFromUrl = async () => {
      const urlToken = searchParams.get('token');
      const urlEmail = searchParams.get('email');
      const urlPan = searchParams.get('pan');
      
      if (urlToken && (urlPan || urlEmail)) {
        if (urlEmail) setEmail(urlEmail);
        if (urlPan) setPan(urlPan);
        setResetToken(urlToken);
        setOtp(urlToken);
        setVerifyingToken(true);
        
        try {
          const response = await fetch(`${API_BASE_URL}/users.php?action=verify-token`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              pan: urlPan,
              email: urlEmail,
              token: urlToken
            }),
          });

          const data = await response.json();

          if (data.success) {
            setTokenValid(true);
            setStep(3);
            setMessage('Token verified! Please set your new password.');
          } else {
            setTokenValid(false);
            setStep(2);
            setError('This reset link has expired or is invalid. Please request a new one.');
          }
        } catch (err) {
          console.error('Token verification error:', err);
          setTokenValid(false);
          setStep(2);
          setError('Unable to verify token. Please enter the code manually.');
        } finally {
          setVerifyingToken(false);
        }
      }
    };

    verifyTokenFromUrl();
  }, [searchParams, API_BASE_URL]);

  const handleSendOTP = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    
    const cleanPan = pan.trim().toUpperCase();
    if (!cleanPan || !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(cleanPan)) {
      setError('Please enter a valid 10-character PAN number (e.g. ABCDE1234F)');
      return;
    }
    
    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=forgot-password`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ pan: cleanPan }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage(data.message || 'Password reset instructions sent to your registered email.');
        if (data.data && data.data.maskedEmail) {
          setMaskedEmail(data.data.maskedEmail);
        }
        if (data.data && data.data.resetToken) {
          setResetToken(data.data.resetToken);
        }
        
        setStep(2);
      } else {
        setError(data.message || 'Failed to send reset instructions');
      }
    } catch (err) {
      setError('Network error. Please check your connection and try again.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOTP = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    
    if (!otp || otp.trim() === '') {
      setError('Please enter the verification code');
      return;
    }
    
    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=verify-token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          pan,
          email,
          token: otp
        }),
      });

      const data = await response.json();

      if (data.success) {
        setTokenValid(true);
        setStep(3);
        setMessage('Token verified successfully! Please set your new password.');
      } else {
        setError(data.message || 'Invalid or expired verification code');
        
        if (otp.length === 64 && /^[0-9a-fA-F]+$/.test(otp)) {
          setError('This verification code has expired. Please request a new one.');
        }
      }
    } catch (err) {
      setError('Unable to verify code. Please try again.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleResetPassword = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    
    if (!newPassword || !confirmPassword) {
      setError('Please fill in both password fields');
      return;
    }
    
    if (newPassword !== confirmPassword) {
      setError('Passwords do not match');
      return;
    }
    
    if (newPassword.length < 8) {
      setError('Password must be at least 8 characters');
      return;
    }
    
    if (!/(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/.test(newPassword)) {
      setError('Password should include uppercase, lowercase letters and numbers');
      return;
    }
    
    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=reset-password`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          pan,
          email,
          token: otp || resetToken,
          newPassword
        }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage('Password reset successfully! Redirecting to login...');
        setTimeout(() => {
          router.push('/login');
        }, 2000);
      } else {
        setError(data.message || 'Failed to reset password');
        
        if (data.message && data.message.includes('token')) {
          setError('Reset token has expired. Please start the process again.');
          setStep(1);
        }
      }
    } catch (err) {
      setError('Network error. Please try again.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleResendOTP = async () => {
    setError('');
    setMessage('');
    setLoading(true);

    try {
      const response = await fetch(`${API_BASE_URL}/users.php?action=forgot-password`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ pan, email }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage('New reset instructions sent to your email.');
        if (data.data && data.data.resetToken) {
          setResetToken(data.data.resetToken);
        }
      } else {
        setError('Failed to resend instructions. Please try again.');
      }
    } catch (err) {
      setError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (verifyingToken) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-md">
          <div className="flex justify-center">
            <div className="flex items-center justify-center">
              <Image
                src={siteConfig.branding.logo}
                alt={`${siteConfig.siteName} Logo`}
                width={80}
                height={80}
                className="object-contain"
              />
            </div>
          </div>
          <div className="bg-white py-8 px-4 shadow-2xl sm:rounded-lg sm:px-10 border border-gray-200 text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
            <p className="mt-4 text-gray-600">Verifying your reset link...</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="flex justify-center">
          <div className="flex items-center justify-center">
            <Image
              src={siteConfig.branding.logo}
              alt={`${siteConfig.siteName} Logo`}
              width={80}
              height={80}
              className="object-contain"
            />
          </div>
        </div>
        <h2 className="mt-3 text-center text-3xl font-extrabold text-gray-900">
          Reset Your Password
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          {step === 1 && 'Enter your PAN Number to receive password reset instructions'}
          {step === 2 && 'Enter the verification code sent to your registered email'}
          {step === 3 && 'Set your new password'}
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-2xl sm:rounded-lg sm:px-10 border border-gray-200">
          <div className="mb-6">
            <div className="flex items-center justify-between">
              <div className={`flex items-center ${step >= 1 ? 'text-indigo-600' : 'text-gray-400'}`}>
                <div className={`w-8 h-8 rounded-full flex items-center justify-center ${step >= 1 ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'}`}>
                  1
                </div>
                <span className="ml-2 text-sm font-medium">PAN</span>
              </div>
              <div className="flex-1 h-0.5 mx-2 bg-gray-300"></div>
              <div className={`flex items-center ${step >= 2 ? 'text-indigo-600' : 'text-gray-400'}`}>
                <div className={`w-8 h-8 rounded-full flex items-center justify-center ${step >= 2 ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'}`}>
                  2
                </div>
                <span className="ml-2 text-sm font-medium">Verify</span>
              </div>
              <div className="flex-1 h-0.5 mx-2 bg-gray-300"></div>
              <div className={`flex items-center ${step >= 3 ? 'text-indigo-600' : 'text-gray-400'}`}>
                <div className={`w-8 h-8 rounded-full flex items-center justify-center ${step >= 3 ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'}`}>
                  3
                </div>
                <span className="ml-2 text-sm font-medium">Reset</span>
              </div>
            </div>
          </div>

          {step === 1 && (
            <form onSubmit={handleSendOTP} className="space-y-6">
              <div>
                <label htmlFor="pan" className="block text-sm font-medium text-gray-700">
                  PAN Number
                </label>
                <div className="mt-1">
                  <input
                    id="pan"
                    name="pan"
                    type="text"
                    required
                    maxLength={10}
                    value={pan}
                    onChange={(e) => setPan(e.target.value.toUpperCase())}
                    className="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm uppercase placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm font-mono tracking-wider"
                    placeholder="e.g. ABCDE1234F"
                  />
                </div>
              </div>

              {message && (
                <div className="rounded-md bg-green-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-green-800">{message}</p>
                    </div>
                  </div>
                </div>
              )}

              {error && (
                <div className="rounded-md bg-red-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-red-800">{error}</p>
                    </div>
                  </div>
                </div>
              )}

              <div>
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Sending...
                    </>
                  ) : 'Send Reset Instructions'}
                </button>
              </div>

              <div className="text-center">
                <Link
                  href="/login"
                  className="font-medium text-indigo-600 hover:text-indigo-500 transition-colors duration-200"
                >
                  ← Back to Login
                </Link>
              </div>
            </form>
          )}

          {step === 2 && (
            <form onSubmit={handleVerifyOTP} className="space-y-6">
              {maskedEmail && (
                <div className="rounded-md bg-blue-50 p-3 mb-2 border border-blue-100">
                  <p className="text-xs text-blue-800">
                    Reset instructions sent to registered email: <span className="font-semibold">{maskedEmail}</span>
                  </p>
                </div>
              )}
              <div>
                <div className="flex justify-between items-center mb-1">
                  <label htmlFor="otp" className="block text-sm font-medium text-gray-700">
                    Verification Code
                  </label>
                  <button
                    type="button"
                    onClick={handleResendOTP}
                    disabled={loading}
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50"
                  >
                    Resend Code
                  </button>
                </div>
                <div className="mt-1">
                  <input
                    id="otp"
                    name="otp"
                    type="text"
                    required
                    value={otp}
                    onChange={(e) => setOtp(e.target.value)}
                    className="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="Enter the 64-character code from email"
                    pattern="[0-9a-fA-F]{64}"
                    title="Enter the 64-character hexadecimal code"
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Check your email for the 64-character reset code (also check spam folder)
                </p>
              </div>

              {resetToken && process.env.NODE_ENV === 'development' && (
                <div className="rounded-md bg-yellow-50 p-4 border border-yellow-200">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-yellow-800">
                        Development Mode - Token for testing: <br />
                        <code className="text-xs bg-yellow-100 p-1 rounded mt-1 inline-block break-all">{resetToken}</code>
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {message && (
                <div className="rounded-md bg-green-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-green-800">{message}</p>
                    </div>
                  </div>
                </div>
              )}

              {error && (
                <div className="rounded-md bg-red-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-red-800">{error}</p>
                    </div>
                  </div>
                </div>
              )}

              <div className="flex space-x-3">
                <button
                  type="button"
                  onClick={() => {
                    setStep(1);
                    setError('');
                    setMessage('');
                  }}
                  className="flex-1 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                  Back
                </button>
                <button
                  type="submit"
                  disabled={loading || !otp}
                  className="flex-1 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Verifying...
                    </>
                  ) : 'Verify Code'}
                </button>
              </div>
            </form>
          )}

          {step === 3 && (
            <form onSubmit={handleResetPassword} className="space-y-6">
              <div>
                <label htmlFor="newPassword" className="block text-sm font-medium text-gray-700">
                  New Password
                </label>
                <div className="mt-1 relative">
                  <input
                    id="newPassword"
                    name="newPassword"
                    type="password"
                    required
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    className="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="Minimum 8 characters with uppercase, lowercase and numbers"
                  />
                  {newPassword && (
                    <div className="mt-2 space-y-1">
                      <div className={`text-xs ${newPassword.length >= 8 ? 'text-green-600' : 'text-red-600'}`}>
                        • At least 8 characters {newPassword.length >= 8 ? '✓' : '✗'}
                      </div>
                      <div className={`text-xs ${/(?=.*[A-Z])/.test(newPassword) ? 'text-green-600' : 'text-red-600'}`}>
                        • Uppercase letter {/(?=.*[A-Z])/.test(newPassword) ? '✓' : '✗'}
                      </div>
                      <div className={`text-xs ${/(?=.*[a-z])/.test(newPassword) ? 'text-green-600' : 'text-red-600'}`}>
                        • Lowercase letter {/(?=.*[a-z])/.test(newPassword) ? '✓' : '✗'}
                      </div>
                      <div className={`text-xs ${/(?=.*\d)/.test(newPassword) ? 'text-green-600' : 'text-red-600'}`}>
                        • Number {/(?=.*\d)/.test(newPassword) ? '✓' : '✗'}
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div>
                <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700">
                  Confirm New Password
                </label>
                <div className="mt-1">
                  <input
                    id="confirmPassword"
                    name="confirmPassword"
                    type="password"
                    required
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    className="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="Re-enter your new password"
                  />
                  {confirmPassword && newPassword === confirmPassword && (
                    <p className="mt-1 text-xs text-green-600">Passwords match ✓</p>
                  )}
                  {confirmPassword && newPassword !== confirmPassword && (
                    <p className="mt-1 text-xs text-red-600">Passwords do not match ✗</p>
                  )}
                </div>
              </div>

              {message && (
                <div className="rounded-md bg-green-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-green-800">{message}</p>
                    </div>
                  </div>
                </div>
              )}

              {error && (
                <div className="rounded-md bg-red-50 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-red-800">{error}</p>
                    </div>
                  </div>
                </div>
              )}

              <div className="flex space-x-3">
                <button
                  type="button"
                  onClick={() => setStep(tokenValid ? 2 : 1)}
                  className="flex-1 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                  Back
                </button>
                <button
                  type="submit"
                  disabled={loading || !newPassword || !confirmPassword || newPassword !== confirmPassword}
                  className="flex-1 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Resetting...
                    </>
                  ) : 'Reset Password'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}

// Main page component with Suspense boundary
export default function ForgotPasswordPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-md">
          <div className="flex justify-center">
            <div className="flex items-center justify-center">
              <Image
                src={siteConfig.branding.logo}
                alt={`${siteConfig.siteName} Logo`}
                width={80}
                height={80}
                className="object-contain"
              />
            </div>
          </div>
          <div className="bg-white py-8 px-4 shadow-2xl sm:rounded-lg sm:px-10 border border-gray-200 text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
            <p className="mt-4 text-gray-600">Loading...</p>
          </div>
        </div>
      </div>
    }>
      <ForgotPasswordContent />
    </Suspense>
  );
}