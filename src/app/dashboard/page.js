// app/dashboard/page.js
'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import TypedText from '@/components/TypedText';
import siteConfig from "@/config/siteConfig";

export default function DashboardWelcomePage() {
  const [user, setUser] = useState(null);
  const [quickActions, setQuickActions] = useState([
    { 
      id: 1, 
      title: 'Complete Personal Details', 
      description: 'Fill in your personal information',
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
      path: '/dashboard/personal-details',
      status: 'pending',
      priority: 'high'
    },
    { 
      id: 2, 
      title: 'Add Bank Details', 
      description: 'Link your bank account',
      icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
      path: '/dashboard/bank-details',
      status: 'pending',
      priority: 'high'
    },
    { 
      id: 3, 
      title: 'Upload Documents', 
      description: 'Submit required verification documents',
      icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
      path: '/dashboard/upload',
      status: 'pending',
      priority: 'medium'
    },
    { 
      id: 4, 
      title: 'Complete FATCA', 
      description: 'Foreign Account Tax Compliance',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      path: '/dashboard/fatca',
      status: 'pending',
      priority: 'medium'
    },
  ]);
  const router = useRouter();

  useEffect(() => {
    // Check if user is logged in
    const storedUser = localStorage.getItem('site_user');
    const authStatus = localStorage.getItem('site_auth');
    
    if (!storedUser || authStatus !== 'true') {
      router.push('/login');
      return;
    }
    
    try {
      const userData = JSON.parse(storedUser);
      setUser(userData);
    } catch (error) {
      console.error('Error parsing user data:', error);
      router.push('/login');
    }
  }, [router]);

  const handleQuickAction = (path) => {
    router.push(path);
  };

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* Welcome Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-8 text-white">
        <div className="flex flex-col md:flex-row md:items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold mb-2">Welcome back, <TypedText text={user.name} className="text-white" />!</h1>
            <p className="text-blue-100 opacity-90">
              Complete your account setup to start investing
            </p>
          </div>
          <div className="mt-4 md:mt-0 bg-white/10 backdrop-blur-sm rounded-xl p-4">
            <div className="text-center">
              <p className="text-sm opacity-80">Account Status</p>
              <div className="mt-2 px-4 py-1 bg-yellow-500/20 border border-yellow-500/30 rounded-full text-yellow-300 text-sm font-medium">
                Activation Pending
              </div>
            </div>
          </div>
        </div>
        
        {/* Progress Bar */}
        <div className="mt-8">
          <div className="flex justify-between items-center mb-2">
            <span className="text-sm">Setup Progress</span>
            <span className="text-sm font-medium">0%</span>
          </div>
          <div className="w-full bg-white/20 rounded-full h-2.5">
            <div className="bg-white h-2.5 rounded-full" style={{ width: '0%' }}></div>
          </div>
          <p className="text-sm mt-2 text-blue-100 opacity-80">
            Complete all required steps to activate your account
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
          {quickActions.map((action) => (
            <div 
              key={action.id}
              className="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-md transition-shadow cursor-pointer"
              onClick={() => handleQuickAction(action.path)}
            >
              <div className="flex items-start justify-between">
                <div className="flex items-center">
                  <div className={`w-12 h-12 rounded-lg flex items-center justify-center mr-4 ${
                    action.priority === 'high' ? 'bg-red-50' : 'bg-blue-50'
                  }`}>
                    <svg 
                      className={`w-6 h-6 ${action.priority === 'high' ? 'text-red-600' : 'text-blue-600'}`}
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={action.icon} />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">{action.title}</h3>
                    <p className="text-sm text-gray-500 mt-1">{action.description}</p>
                  </div>
                </div>
                <div className="flex flex-col items-end">
                  <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                    action.status === 'completed' 
                      ? 'bg-green-100 text-green-800' 
                      : 'bg-yellow-100 text-yellow-800'
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
                <button 
                  className={`w-full py-2.5 rounded-lg font-medium transition-colors ${
                    action.priority === 'high' 
                      ? 'bg-red-600 hover:bg-red-700 text-white' 
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  }`}
                >
                  {action.status === 'completed' ? 'Review' : 'Complete Now'}
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Recent Activity / Notifications */}
      <div className="bg-gray-50 rounded-xl p-6">
        <h2 className="text-xl font-bold text-gray-900 mb-4">Recent Notifications</h2>
        <div className="space-y-4">
          <div className="flex items-center p-4 bg-white rounded-lg border border-gray-200">
            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-4">
              <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div className="flex-1">
              <p className="text-gray-900">Account setup started. Complete remaining steps to activate.</p>
              <p className="text-sm text-gray-500 mt-1">Just now</p>
            </div>
          </div>
          
          <div className="flex items-center p-4 bg-white rounded-lg border border-gray-200">
            <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-4">
              <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div className="flex-1">
              <p className="text-gray-900">Welcome to {siteConfig.SITENAME_CAP}! Your account has been created successfully.</p>
              <p className="text-sm text-gray-500 mt-1">Yesterday</p>
            </div>
          </div>
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
            <button className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
              Contact Support
            </button>
            <button className="px-6 py-3 bg-white hover:bg-gray-50 text-blue-600 font-medium rounded-lg border border-blue-200 transition-colors">
              View FAQs
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}