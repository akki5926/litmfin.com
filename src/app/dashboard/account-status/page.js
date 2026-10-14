// app/dashboard/account-status/page.js
'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;
const USER_AC_STATUS_API = `${API_BASE_URL}/user_ac_status_api.php`;

export default function AccountStatusPage() {
  const [user, setUser] = useState(null);
  const [statusData, setStatusData] = useState({});
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  
  const hasFetched = useRef(false);
  const componentMounted = useRef(true);

  const checkUserAccountStatus = useCallback(async (userId, token) => {
    try {
      const response = await fetch(`${USER_AC_STATUS_API}?user_id=${userId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        console.error('Check existing data failed:', response.status);
        return {};
      }

      const data = await response.json();
      if (data.success && data.data) {
        return data.data;
      }
      return {};
    } catch (error) {
      console.error('Error checking existing data:', error);
      return {};
    }
  }, []);

  useEffect(() => {
    componentMounted.current = true; 
    
    const initialize = async () => {
      if (hasFetched.current || !componentMounted.current) {
        return;
      }
      
      const storedUser = localStorage.getItem('site_user');
      const authStatus = localStorage.getItem('site_auth');
      const storedToken = localStorage.getItem('site_token');

      if (!storedUser || authStatus !== 'true') {
        router.push('/login');
        return;
      }

      try {
        const userData = JSON.parse(storedUser);
        
        if (!componentMounted.current) return;
        
        setUser(userData);
        hasFetched.current = true;
        
        const status = await checkUserAccountStatus(userData.id || userData.user_id, storedToken);
        
        if (componentMounted.current) {
          setStatusData(status);
          setLoading(false);
        }
      } catch (error) {
        console.error('Error parsing user data:', error);
        if (componentMounted.current) {
          router.push('/login');
        }
      }
    };

    initialize();

    return () => {
      componentMounted.current = false;
    };
  }, [router, checkUserAccountStatus]);

  if (!user || loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading account status...</p>
        </div>
      </div>
    );
  }

  const completedCount = [
    'kyc_complient',
    'fatca_done',
    'risk_profile_done',
    'personal_details_submitted',
    'nominee_details_submitted',
    'bank_details_submitted'
  ].filter(key => statusData[key] === 'Y').length;

  const isActivated = statusData.activation_status === 'Y' || completedCount === 6;
  const showDeactivationWarning = statusData.deactivation_message && statusData.deactivation_message.trim() !== '';

  const renderStatusBadge = (key, label) => {
    const completed = key === 'activation_status' ? isActivated : statusData[key] === 'Y';
    return (
      <span className={`px-3 py-1 text-sm font-medium rounded-full ${completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
        {completed ? 'Completed' : 'Pending'}
      </span>
    );
  };

  return (
    <div className="space-y-6">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          <TypedText text="Account Status" className="text-gray-900" />
        </h1>
        <div className="flex items-center gap-3">
          {renderStatusBadge('activation_status', 'Account Activated')}
          <p className="text-gray-600 text-sm">
            Complete all pending tasks to activate your account
          </p>
        </div>
      </div>

      {/* Deactivation Warning Message */}
      {showDeactivationWarning && (
        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-yellow-700">
                <span className="font-medium">Note:</span> {statusData.deactivation_message}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Account Status Items */}
      <div className="space-y-4">
        {/* KYC */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">KYC Compliant</h3>
              <p className="text-sm text-gray-500">Know Your Customer verification</p>
              {/* {statusData.kyc_status_remark && (
                <p className="text-xs text-gray-400 mt-1">Remark: {statusData.kyc_status_remark}</p>
              )} */}
            </div>
          </div>
          {renderStatusBadge('kyc_complient', 'KYC')}
        </div>
        

        {/* Personal Details */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">Personal Details</h3>
              <p className="text-sm text-gray-500">Complete your personal information</p>
            </div>
          </div>
          {renderStatusBadge('personal_details_submitted', 'Personal Details')}
        </div>

        {/* Nominee Details */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">Nominee Details</h3>
              <p className="text-sm text-gray-500">Complete your Nominee information</p>
            </div>
          </div>
          {renderStatusBadge('nominee_details_submitted', 'Nominee Details')}
        </div>

        {/* Bank Details */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">Bank Details</h3>
              <p className="text-sm text-gray-500">Add your bank account information</p>
            </div>
          </div>
          {renderStatusBadge('bank_details_submitted', 'Bank Details')}
        </div>

        {/* FATCA Declaration - Now reading from fatca_done column */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">FATCA Declaration</h3>
              <p className="text-sm text-gray-500">Foreign Account Tax Compliance Act</p>
            </div>
          </div>
          {renderStatusBadge('fatca_done', 'FATCA')}
        </div>

        {/* Risk Profile */}
        <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          <div className="flex items-center">
            <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
              <svg className="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <div>
              <h3 className="font-medium text-gray-900">Risk Profile</h3>
              <p className="text-sm text-gray-500">Complete your risk assessment</p>
            </div>
          </div>
          {renderStatusBadge('risk_profile_done', 'Risk Profile')}
        </div>
      </div>

      {/* Progress Summary */}
      <div className="mt-8 p-6 bg-blue-50 rounded-lg border border-blue-100">
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-bold text-gray-900">Account Activation Progress</h3>
          <span className="text-sm font-medium text-blue-700">
            {completedCount}/6 Completed
          </span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2.5 mb-2">
          <div
            className="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
            style={{ width: `${(completedCount / 6) * 100}%` }}
          ></div>
        </div>
      </div>
    </div>
  );
}