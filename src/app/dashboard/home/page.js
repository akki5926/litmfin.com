// app/dashboard/home/page.js
'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import TypedText from '@/components/TypedText';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ;
const USER_AC_STATUS_API = `${API_BASE_URL}/user_ac_status_api.php`;

const defaultQuickActions = [
  { 
    id: 0, 
    title: 'KYC Compliant', 
    description: 'Know Your Customer verification', 
    path: 'https://www.camsonline.com/Investors/Transactions/KYC/KYC-Status', 
    priority: 'high',
    external: true,
  },
  { id: 1, title: 'Complete Personal Details', description: 'Fill in your personal information', path: '/dashboard/personal-details', key: 'personal_details_submitted', priority: 'high' },
  { id: 2, title: 'Add Bank Details', description: 'Link your bank account', path: '/dashboard/bank-details', key: 'bank_details_submitted', priority: 'high' },
  { id: 3, title: 'Complete FATCA', description: 'Foreign Account Tax Compliance', path: '/dashboard/fatca', key: 'fatca_done', priority: 'medium' },
  { id: 4, title: 'Nominee Details', description: 'Complete your nominee information', path: '/dashboard/nominee', key: 'nominee_details_submitted', priority: 'medium' },
  { id: 5, title: 'Risk Profile', description: 'Complete your risk assessment', path: '/dashboard/risk-profile', key: 'risk_profile_done', priority: 'medium' },
];

export default function DashboardWelcomePage() {
  const [user, setUser] = useState(null);
  const [statusData, setStatusData] = useState({});
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  // Helper function to determine KYC status
  const getKYCStatus = (statusData) => {
    return statusData.kyc_complient === 'Y' ? 'completed' : 'pending';
  };

  const [quickActions, setQuickActions] = useState(defaultQuickActions);

  const fetchAccountStatus = async (userId, token) => {
    try {
      // Prevent browser and Next.js caching by adding a timestamp and cache directive
      const res = await fetch(`${USER_AC_STATUS_API}?user_id=${userId}&t=${new Date().getTime()}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        cache: 'no-store'
      });

      if (!res.ok) {
        console.error('Failed to fetch account status:', res.status);
        return {};
      }

      const data = await res.json();
      // console.log('Account status data:', data);
      return data.success && data.data ? data.data : {};
    } catch (error) {
      console.error('Error fetching account status:', error);
      return {};
    }
  };

  useEffect(() => {
    const storedUser = localStorage.getItem('site_user');
    const authStatus = localStorage.getItem('site_auth');
    const storedToken = localStorage.getItem('site_token');

    if (!storedUser || authStatus !== 'true') {
      router.push('/login');
      return;
    }

    try {
      const userData = JSON.parse(storedUser);
      setUser(userData);

      (async () => {
        const status = await fetchAccountStatus(userData.id || userData.user_id, storedToken);
        setStatusData(status);

        // Update quick actions based on status data
        const updatedActions = defaultQuickActions.map(action => {
          if (action.id === 0) {
            const isCompleted = getKYCStatus(status) === 'completed';
            // Special handling for KYC — include remark property (used by renderKYCStatus)
            return {
              ...action,
              status: isCompleted ? 'completed' : 'pending',
              remark: (!isCompleted && status.kyc_status_remark && status.kyc_status_remark.trim() !== '' && status.kyc_status_remark.trim().toLowerCase() !== 'null') ? status.kyc_status_remark : ''
            };
          } else {
            // For other actions, use the key field
            return {
              ...action,
              status: status[action.key] === 'Y' ? 'completed' : 'pending'
            };
          }
        });
        setQuickActions(updatedActions);

        setLoading(false);
      })();
    } catch (error) {
      console.error('Error parsing user data:', error);
      router.push('/login');
    }
  }, [router]);

  const handleQuickAction = (action) => {
    if (action.external) {
      // For KYC Compliant button (id: 0), show confirmation popup
      if (action.id === 0) {
        const userConfirmed = window.confirm("We are open to third party. Do you want to proceed?");
        if (userConfirmed) {
          window.open(action.path, '_blank');
        }
        // If user clicks Cancel, do nothing
      } else {
        window.open(action.path, '_blank');
      }
    } else {
      router.push(action.path);
    }
  };

  if (!user || loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  // derive activation state from statusData (fall back to activation_status OR all flags completed)
  const requiredKeys = [
    'kyc_complient',
    'fatca_done',
    'risk_profile_done',
    'personal_details_submitted',
    'nominee_details_submitted',
    'bank_details_submitted'
  ];

  const statusCompletedCount = requiredKeys.filter(key => statusData[key] === 'Y').length;

  const isActivated = statusData.activation_status === 'Y';

  // use derived progress and activation text
  const progressPercent = Math.round((statusCompletedCount / requiredKeys.length) * 100);
  const activationText = isActivated ? 'Client Activated' : 'Activation Pending';

  // shared button classes for review actions (Tailwind — higher specificity & no external CSS)
  const reviewButtonClass =
    'inline-flex items-center px-3 py-1.5 rounded-md font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-colors';
  
  // Function to display KYC status with remark
  const renderKYCStatus = (action) => {
    if (action.id === 0 && action.remark) {
      return (
        <div className="mt-2">
          <span className="text-sm text-gray-600">Remark: </span>
          <span className="text-sm font-medium text-gray-800">{action.remark}</span>
        </div>
      );
    }
    return null;
  };

  return (
    <div className="space-y-8">
      {/* Welcome Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-8 text-white">
        <div className="flex flex-col md:flex-row md:items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold mb-2">Welcome back, <TypedText text={user.name} className="text-white" />!</h1>
            <p className="text-blue-100 opacity-90">
              {isActivated 
                ? 'Your client code is activated. You can start investing.' 
                : 'Complete your account setup to start investing'}
            </p>
          </div>
          <div className="mt-4 md:mt-0 bg-white/10 backdrop-blur-sm rounded-xl p-4 text-center">
            <p className="text-sm opacity-80">Account Status</p>
            <div className={`mt-2 px-4 py-1 border rounded-full text-sm font-medium ${
              isActivated 
                ? 'bg-green-500/20 border-green-500/30 text-green-300' 
                : 'bg-yellow-500/20 border-yellow-500/30 text-yellow-300'
            }`}>
              {activationText}
            </div>
            {!isActivated && statusData.deactivation_message && statusData.deactivation_message.trim() !== '' && statusData.deactivation_message.trim().toLowerCase() !== 'null' && (
              <div className="mt-3 block max-w-xs mx-auto px-3 py-1.5 bg-red-500/20 border border-red-500/30 rounded-lg text-red-100 text-xs text-left">
                <span className="font-semibold">Notice:</span> {statusData.deactivation_message}
              </div>
            )}
          </div>
        </div>

        {/* Progress Bar */}
        <div className="mt-8">
          <div className="flex justify-between items-center mb-2">
            <span className="text-sm">Setup Progress</span>
            <span className="text-sm font-medium">{progressPercent}%</span>
          </div>
          <div className="w-full bg-white/20 rounded-full h-2.5">
            <div className="bg-white h-2.5 rounded-full" style={{ width: `${progressPercent}%` }}></div>
          </div>
          <p className="text-sm mt-2 text-blue-100 opacity-80">
            {isActivated 
              ? '' 
              : 'Complete all required steps to activate your account'}
          </p>
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-bold text-gray-900">Quick Actions</h2>
          <Link 
            href="/dashboard/account-status" 
            className="text-blue-600 hover:text-blue-800 text-sm font-medium"
          >
            View All Tasks →
          </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {quickActions.map(action => (
            <div 
              key={action.id}
              className="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-md transition-shadow cursor-pointer"
              onClick={() => handleQuickAction(action)}
            >
              <div className="flex items-start justify-between">
                <div className="flex items-center">
                  <div className={`w-12 h-12 rounded-lg flex items-center justify-center mr-4 ${
                    action.priority === 'high' ? 'bg-red-50' : 'bg-blue-50'
                  }`}>
                    <svg 
                      className={`w-6 h-6 ${action.priority === 'high' ? 'text-red-600' : 'text-blue-600'}`}
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={action.icon || 'M5 13l4 4L19 7'} />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">{action.title}</h3>
                    <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                    {/* Display KYC remark if available */}
                    {renderKYCStatus(action)}
                  </div>
                </div>
                <div className="flex flex-col items-end">
                  <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                    action.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                  }`}>
                    {action.status === 'completed' ? 'Completed' : 'Pending'}
                  </span>
                  {action.priority === 'high' && (
                    <span className="mt-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                      High Priority
                    </span>
                  )}
                </div>
              </div>
              <div className="mt-6">
                {action.external ? (
                  <a 
                    href={action.path} 
                    target="_blank" 
                    rel="noopener noreferrer"
                    onClick={(e) => {
                      e.stopPropagation();
                      if (action.id === 0) {
                        e.preventDefault();
                        const userConfirmed = window.confirm("We are open to third party. Do you want to proceed?");
                        if (userConfirmed) {
                          window.open(action.path, '_blank');
                        }
                      }
                    }}
                    className="w-full block text-center py-2.5 rounded-lg font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors"
                  >
                    {action.status === 'completed' ? 'View KYC Status' : 'Check KYC Status'}
                  </a>
                ) : (
                  <button 
                    className="w-full py-2.5 rounded-lg font-medium transition-colors bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    {action.status === 'completed' ? 'Review' : 'Complete Now'}
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Help Section */}
      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
        <div className="flex flex-col md:flex-row md:items-center justify-between">
          <div>
            <h3 className="font-bold text-gray-900 text-lg mb-2">Need Help?</h3>
            <p className="text-gray-600">
              Our support team is here to assist you with the account setup process.
            </p>
          </div>
          <div className="mt-4 md:mt-0 flex gap-4">
            <button 
             onClick={() => router.push('/contact')}
            className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
              Contact Support
            </button>
            <button 
            onClick={() => router.push('/faq')}
            className="px-6 py-3 bg-white hover:bg-gray-50 text-blue-600 font-medium rounded-lg border border-blue-200 transition-colors">
              View FAQs
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}