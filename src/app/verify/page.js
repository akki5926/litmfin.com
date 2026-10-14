
'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import siteConfig from "@/config/siteConfig";

export default function VerifyPage() {
  const [userData, setUserData] = useState(null);
  const [emailVerified, setEmailVerified] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [resendLoading, setResendLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [countdown, setCountdown] = useState(30);
  const [canResend, setCanResend] = useState(false);

  const initialized = useRef(false);
  
  const router = useRouter();

  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

  const sendVerificationEmail = useCallback(async (isInitial = false, customUserData = null) => {
    try {
      if (!isInitial) {
        setResendLoading(true);
      }
      
      setError('');
      setSuccess('');
      
      // Construct app base URL for verification link
      const appBaseUrl = typeof window !== 'undefined' ? window.location.origin : '';
      
      const targetUser = customUserData || userData;

      const response = await fetch(`${API_BASE_URL}/email_verification_api?action=send_verification`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: targetUser?.email,
          name: targetUser?.name,
          clientCode: targetUser?.clientCode,
          baseUrl: appBaseUrl
        }),
      });

      const result = await response.json();
      
      if (result.success) {
        if (isInitial) {
          localStorage.setItem('email_sent', 'true');
          setEmailSent(true);
        }
        
        setSuccess(isInitial ? 
          'Verification email sent! Check your inbox.' : 
          'Verification email resent successfully!');
        
        // Reset countdown for resend
        if (!isInitial) {
          setCountdown(30);
          setCanResend(false);
        }
        
      } else {
        setError(result.message || 'Failed to send verification email');
      }
    } catch (error) {
      console.error('Error sending verification email:', error);
      setError('Failed to send verification email. Please try again.');
    } finally {
      if (!isInitial) {
        setResendLoading(false);
      }
    }
  }, [API_BASE_URL, userData]);

  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;

    // Get user data from localStorage
    const storedUser = localStorage.getItem('temp_user');
    if (storedUser) {
      try {
        const parsedData = JSON.parse(storedUser);
        setUserData(parsedData);
        
        // Check if email is already verified
        const emailVerified = localStorage.getItem('email_verified');
        if (emailVerified === 'true') {
          setEmailVerified(true);
          // Redirect to dashboard if already verified
          setTimeout(() => {
            router.push('/dashboard/home');
          }, 2000);
        } else {
          // Auto-send verification email if not sent yet
          const emailSent = localStorage.getItem('email_sent');
          if (!emailSent) {
                sendVerificationEmail(true, parsedData); // Initial send
          } else {
            setEmailSent(true);
          }
        }
      } catch (error) {
        console.error('Error parsing user data:', error);
        // Still allow verification with default data
        const fallbackData = {
          email: 'user@example.com',
          name: `${siteConfig.siteName} User`,
          clientCode: 'UCC000'
        };
        setUserData(fallbackData);
        sendVerificationEmail(true, fallbackData);
      }
    } else {
      // Redirect to signup if no user data
      router.push('/signup');
    }
  }, [router, sendVerificationEmail]);

  useEffect(() => {
    // Countdown timer for email resend
    if (countdown > 0 && !canResend) {
      const timer = setTimeout(() => {
        setCountdown(prev => prev - 1);
      }, 1000);
      return () => clearTimeout(timer);
    } else if (countdown === 0) {
      setCanResend(true);
    }
  }, [countdown, canResend]);

  const checkEmailVerification = async () => {
  setLoading(true);
  setError('');
  setSuccess('');

  console.group('🔍 Email Verification Debug');

  try {
    if (!userData?.email) {
      console.warn('No email found in userData. Using fallback flow.');
      await new Promise(resolve => setTimeout(resolve, 1500));
      handleSuccessfulVerification();
      return;
    }

    const requestPayload = {
      email: userData.email,
      clientCode: userData.clientCode
    };

    //console.log('➡️ Request payload:', requestPayload);
const apiUrl = `${API_BASE_URL}/users.php?action=check_email_verification`;

// console.log('🌐 API URL:', apiUrl);
    const response = await fetch(
      `${API_BASE_URL}/users.php?action=check_email_verification`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestPayload)
      }
    );

    //console.log('⬅️ HTTP status:', response.status);

    const rawText = await response.text();
    //console.log('⬅️ Raw response:', rawText);
    const result = JSON.parse(rawText);
    //console.log('⬅️ Parsed response:', result);

    if (result.success ) {
      handleSuccessfulVerification();
    } else {
      setError(result.message || 'Email not verified yet.');
    }

  } catch (err) {
    console.error('🔥 Verification error:', err);
    setError('Unable to verify email at this time. Please try again later.');
  } finally {
    console.groupEnd();
    setLoading(false);
  }
};


  const handleSuccessfulVerification = () => {
    // Mark email as verified in localStorage
    localStorage.setItem('email_verified', 'true');
    localStorage.removeItem('email_sent');
    setEmailVerified(true);
    setSuccess('Email verified successfully! Redirecting to dashboard...');
    
    // Store auth data
    localStorage.setItem('auth_token', `token_${Date.now()}`);
    localStorage.setItem('user_data', JSON.stringify({
      name: userData?.name || '{siteConfig.siteName} User',
      email: userData?.email || 'user@example.com',
      clientCode: userData?.clientCode || 'UCC000',
      mobile: userData?.mobile
    }));
    
    // Redirect to dashboard after 2 seconds
    setTimeout(() => {
      router.push('/dashboard/home');
    }, 2000);
  };

  const resendVerificationEmail = async () => {
    if (!canResend) return;
    
    await sendVerificationEmail(false);
  };

  const handleUseDifferentEmail = () => {
    localStorage.removeItem('temp_user');
    localStorage.removeItem('email_verified');
    localStorage.removeItem('email_sent');
    router.push('/signup');
  };

  const handleBackToSignup = () => {
    localStorage.removeItem('temp_user');
    localStorage.removeItem('email_verified');
    localStorage.removeItem('email_sent');
    router.push('/signup');
  };

  const formatMobile = (mobile) => {
    if (!mobile) return 'Not provided';
    return `${mobile.slice(0, 5)}****${mobile.slice(-2)}`;
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
      <div className="py-8 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md mx-auto">
          {/* Header */}
          <div className="text-center mb-8">
            <Link href="/" className="inline-block mb-4">
              <span className="text-2xl font-bold text-blue-600">{siteConfig.siteName}</span>
            </Link>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">
              Verify Your Email
            </h1>
            <p className="text-gray-600">
              One last step to activate your account
            </p>
          </div>

          {/* Success Message */}
          {success && (
            <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-fadeIn">
              <div className="flex items-center">
                <svg className="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                <span className="text-green-800 font-medium">{success}</span>
              </div>
            </div>
          )}

          {/* Error Message */}
          {error && (
            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
              <div className="flex items-center">
                <svg className="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                </svg>
                <span className="text-red-700">{error}</span>
              </div>
            </div>
          )}

          {/* Main Verification Card */}
          <div className="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <div className="space-y-6">
              {/* Email Status */}
              <div className="flex items-start">
                <div className="flex-shrink-0">
                  <div className={`w-12 h-12 rounded-full flex items-center justify-center ${emailVerified ? 'bg-green-100' : 'bg-blue-100'}`}>
                    {emailVerified ? (
                      <svg className="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                      </svg>
                    ) : (
                      <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    )}
                  </div>
                </div>
                <div className="ml-4">
                  <h3 className="text-lg font-semibold text-gray-900">
                    {emailVerified ? 'Email Verified' : 'Verify Email Address'}
                  </h3>
                  <p className="text-gray-600 mt-1">
                    {emailVerified ? (
                      'Your email has been successfully verified!'
                    ) : (
                      <>
                        Verification email sent to:
                        <br />
                        <span className="font-medium text-blue-600">{userData?.email || 'your registered email'}</span>
                      </>
                    )}
                  </p>
                </div>
              </div>

              {/* Email Sent Success */}
              {emailSent && !emailVerified && (
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <div className="flex items-center">
                    <svg className="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                    <div>
                      <p className="text-sm text-blue-800 font-medium">Email Sent Successfully!</p>
                      <p className="text-xs text-blue-700 mt-1">
                        Check your inbox and click the verification link to activate your account.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Instructions */}
              {!emailVerified && (
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                  <div className="flex items-start">
                    <svg className="w-5 h-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                    </svg>
                    <div>
                      <p className="text-sm text-yellow-800">
                    <strong>Important:</strong> Check your spam folder if you don&apos;t see the email.
                        Click the verification link in the email to activate your account.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Action Buttons */}
              <div className="space-y-4">
                {!emailVerified ? (
                  <>
                    <button
                      onClick={checkEmailVerification}
                      disabled={loading}
                      className={`w-full py-3 px-4 rounded-lg font-semibold text-white transition-colors ${loading ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900'}`}
                    >
                      {loading ? (
                        <div className="flex items-center justify-center">
                          <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Checking Verification...
                        </div>
                      ) : (
                        'I Have Verified My Email'
                      )}
                    </button>

                    {/* Resend Email Button */}
                    <div className="text-center">
                      {!canResend ? (
                        <p className="text-gray-600 text-sm">
                          Resend email in <span className="font-bold text-blue-600">{countdown}</span> seconds
                        </p>
                      ) : (
                        <button
                          onClick={resendVerificationEmail}
                          disabled={resendLoading}
                          className="text-sm text-blue-600 hover:text-blue-800 font-medium hover:underline disabled:text-gray-400 disabled:cursor-not-allowed"
                        >
                          {resendLoading ? (
                            <span className="flex items-center justify-center">
                              <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                              </svg>
                              Sending...
                            </span>
                          ) : (
                            'Resend Verification Email'
                          )}
                        </button>
                      )}
                    </div>

                    <div className="border-t border-gray-200 pt-4">
                      <div className="text-center">
                        <button
                          onClick={handleUseDifferentEmail}
                          className="text-sm text-gray-500 hover:text-gray-700 hover:underline"
                        >
                          Use different email address
                        </button>
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="text-center">
                    <div className="mb-4">
                      <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      </div>
                      <p className="text-green-600 font-medium">Your account is now active!</p>
                      <p className="text-gray-600 text-sm mt-1">
                        You will be redirected to your dashboard shortly.
                      </p>
                    </div>
                    <Link
                      href="/dashboard/home"
                      className="inline-block w-full py-3 px-4 bg-gradient-to-r from-green-600 to-green-800 text-white font-semibold rounded-lg hover:from-green-700 hover:to-green-900 transition-colors"
                    >
                      Go to Dashboard Now
                    </Link>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* User Info */}
          {userData && (
            <div className="bg-gray-50 rounded-xl p-4 mb-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-900">
                    {userData?.name || '{siteConfig.siteName} User'}
                  </p>
                  <div className="flex items-center mt-1">
                    <div className={`mr-2 ${emailVerified ? 'text-green-600' : 'text-yellow-600'}`}>
                      {emailVerified ? (
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                      ) : (
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd" />
                        </svg>
                      )}
                    </div>
                    <span className="text-xs text-gray-600">
                      {emailVerified ? 'Email Verified' : 'Verification Pending'}
                    </span>
                  </div>
                  {userData?.clientCode && (
                    <p className="text-xs text-gray-600 mt-1">
                      Client Code: <span className="font-medium">{userData.clientCode}</span>
                    </p>
                  )}
                  {userData?.mobile && (
                    <p className="text-xs text-gray-600">
                      Mobile: <span className="font-medium">+91 {formatMobile(userData.mobile)}</span>
                    </p>
                  )}
                </div>
                <div>
                  <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${emailVerified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                    {emailVerified ? 'Verified' : 'Pending'}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Help & Support */}
          <div className="text-center space-y-4">
            <div className="text-sm text-gray-600">
              <p>
                Need help?{' '}
                <Link href="/contact" className="text-blue-600 hover:underline font-medium">
                  Contact Support
                </Link>
              </p>
              <p className="text-xs text-gray-500 mt-1">
                Email us at: support@{siteConfig.domain}
              </p>
            </div>
            
            <button
              onClick={handleBackToSignup}
              className="text-sm text-gray-500 hover:text-gray-700 hover:underline"
            >
              ← Back to Sign Up
            </button>
          </div>

          {/* Security Note */}
          <div className="mt-8 p-4 bg-blue-50 border border-blue-100 rounded-lg">
            <div className="flex items-start">
              <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
              </svg>
              <div>
                <p className="text-sm text-blue-800 font-medium">Account Security</p>
                <p className="text-xs text-blue-700 mt-1">
                  Your email verification link is valid for 24 hours. Keep your login credentials secure and never share them with anyone.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
