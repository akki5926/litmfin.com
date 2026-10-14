'use client';

import { useState, useEffect, useRef, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import siteConfig from "@/config/siteConfig";

// Create a separate component for the content that uses useSearchParams
function VerifyEmailContent() {
  const [status, setStatus] = useState('verifying');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const searchParams = useSearchParams();
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;
  const hasCalledRef = useRef(false);

  useEffect(() => {
    if (hasCalledRef.current) return;
    hasCalledRef.current = true;
  
    const verifyEmail = async () => {
      const token = searchParams.get('token');
      const email = searchParams.get('email');

      if (!token || !email) {
        setStatus('error');
        setMessage('Invalid verification link. Please request a new verification email.');
        setLoading(false);
        return;
      }

      try {
        const url1 = `${API_BASE_URL}/users.php?action=verify`
        // console.log("Verification URL:", url1);
        const response = await fetch(`${API_BASE_URL}/users.php?action=verify`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ token, email }),
        });

        const result = await response.json();
        // console.log("Verification Response:", result);
        if (result.success) {
          setStatus('success');
          setMessage(result.message || 'Email verified successfully! You can now proceed to login.');
          
          // Show login link/button instead of auto-redirect
          // You can add a login button here
        } else {
          setStatus('error');
          setMessage(result.message || 'Verification failed. Please try again.');
        }
      } catch (error) {
        setStatus('error');
        setMessage('Network error. Please check your internet connection and try again.');
        console.error('Verification error:', error);
      } finally {
        setLoading(false);
      }
    };

    verifyEmail();
  }, [searchParams, API_BASE_URL]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center px-4">
      <div className="max-w-md w-full">
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <div className="text-center mb-8">
            <div className="mx-auto w-16 h-16 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full flex items-center justify-center mb-4">
              {status === 'verifying' ? (
                <svg className="w-8 h-8 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              ) : status === 'success' ? (
                <svg className="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                </svg>
              ) : (
                <svg className="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                </svg>
              )}
            </div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">
              {status === 'verifying' ? 'Verifying Email...' : 
               status === 'success' ? 'Email Verified!' : 
               'Verification Failed'}
            </h1>
            <p className="text-gray-600">
              {loading ? 'Please wait while we verify your email address...' : message}
            </p>
          </div>

          {status === 'verifying' && (
            <div className="text-center">
              <div className="text-sm text-gray-500">This may take a few seconds...</div>
            </div>
          )}

          {status === 'success' && (
            <div className="space-y-4">
              <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                <div className="flex items-center">
                  <svg className="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <p className="text-green-800 font-medium">Success!</p>
                    {/* <p className="text-green-700 text-sm mt-1">Redirecting to Login page...</p> */}
                  </div>
                </div>
              </div>

              <div className="text-center">
                <Link
                  href="/login"
                  className="inline-block w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-blue-900 transition-colors"
                >
                  Login Now
                </Link>
              </div>
            </div>
          )}

          {status === 'error' && (
            <div className="space-y-4">
              <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div className="flex items-center">
                  <svg className="w-5 h-5 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <p className="text-red-800 font-medium">Verification Failed</p>
                    <p className="text-red-700 text-sm mt-1">{message}</p>
                  </div>
                </div>
              </div>

              <div className="text-center space-y-3">
                <button
                  onClick={() => window.location.reload()}
                  className="w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-blue-900 transition-colors"
                >
                  Try Again
                </button>
                <p className="text-sm text-gray-600">
                  Or{' '}
                  <Link href="/contact" className="text-blue-600 hover:underline font-medium">
                    contact support
                  </Link>
                  {' '}for assistance
                </p>
              </div>
            </div>
          )}
        </div>

        <div className="text-center mt-6">
          <p className="text-sm text-gray-500">
            © {new Date().getFullYear()} {siteConfig.siteName} Technologies Pvt. Ltd.
          </p>
        </div>
      </div>
    </div>
  );
}

// Main page component with Suspense boundary
export default function VerifyEmailPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center px-4">
        <div className="max-w-md w-full">
          <div className="bg-white rounded-2xl shadow-xl p-8">
            <div className="text-center mb-8">
              <div className="mx-auto w-16 h-16 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full flex items-center justify-center mb-4">
                <svg className="w-8 h-8 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
              <h1 className="text-2xl font-bold text-gray-900 mb-2">Verifying Email...</h1>
              <p className="text-gray-600">Please wait while we verify your email address...</p>
            </div>
          </div>
        </div>
      </div>
    }>
      <VerifyEmailContent />
    </Suspense>
  );
}