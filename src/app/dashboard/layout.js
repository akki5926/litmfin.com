'use client';

import { useState, useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import SessionManager from '@/components/SessionManager';

export default function DashboardLayout({ children }) {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [user, setUser] = useState(null);
  const router = useRouter();
  const pathname = usePathname();

  // Check if user is logged in
  useEffect(() => {
    const checkAuth = () => {
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
    };

    checkAuth();
  }, [router]);

  // Update activeTab based on current route
  useEffect(() => {
    if (pathname === '/dashboard') {
      setActiveTab('dashboard');
    } else if (pathname.includes('/dashboard/account-status')) {
      setActiveTab('account-status');
    } else if (pathname.includes('/dashboard/bank-details')) {
      setActiveTab('bank-details');
    } else if (pathname.includes('/dashboard/personal-details')) {
      setActiveTab('personal-details');
    } else if (pathname.includes('/dashboard/nominee')) {
      setActiveTab('nominee-details');
    } else if (pathname.includes('/dashboard/upload')) {
      setActiveTab('upload-form');
    } else if (pathname.includes('/dashboard/fatca')) {
      setActiveTab('fatca');
    } else if (pathname.includes('/dashboard/risk-profile')) {
      setActiveTab('risk-profile');
    } else if (pathname.includes('/dashboard/change-password')) {
      setActiveTab('change-password');
    } else if (pathname.includes('/dashboard/mandate-registration')) {
      setActiveTab('mandate-registration');
    }
  }, [pathname]);

  const sideMenuItems = [
    { 
      id: 'account-status', 
      label: 'Account Status', 
      icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      path: '/dashboard/account-status' 
    },
    { 
      id: 'personal-details', 
      label: 'Personal Details', 
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
      path: '/dashboard/personal-details' 
    },
    { 
      id: 'nominee-details', 
      label: 'Nominee Details', 
      icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13 0a6 6 0 01-9 5.197M15 7a3 3 0 11-6 0 3 3 0 016 0z',
      path: '/dashboard/nominee' 
    },
    { 
      id: 'bank-details', 
      label: 'Bank Details', 
      icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
      path: '/dashboard/bank-details' 
    },
    { 
      id: 'fatca', 
      label: 'FATCA', 
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      path: '/dashboard/fatca' 
    },
    { 
      id: 'risk-profile', 
      label: 'Risk Profile', 
      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
      path: '/dashboard/risk-profile' 
    },
    { 
      id: 'upload-form', 
      label: 'Upload Form', 
      icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
      path: '/dashboard/upload' 
    },
    { 
      id: 'change-password', 
      label: 'Change Password', 
      icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
      path: '/dashboard/change-password' 
    },
  ];

  // Function to get initials from name
  const getInitials = (name) => {
    if (!name) return 'U';
    const names = name.split(' ');
    if (names.length === 1) return names[0].charAt(0).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
  };

  if (!user) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <SessionManager>
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {/* Left Sidebar - Profile Section */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-xl shadow-lg p-6">
                {/* Profile Avatar Circle */}
                <div className="flex flex-col items-center mb-8">
                  <div className="w-32 h-32 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center shadow-xl mb-4">
                    <span className="text-white font-bold text-4xl">
                      {getInitials(user.name)}
                    </span>
                  </div>
                  <h2 className="text-xl font-bold text-gray-900 text-center">{user.name}</h2>
                  {/* <p className="text-sm text-gray-500 mt-1">{user.clientCode}</p> */}
                </div>

                {/* Side Menu */}
                <nav className="space-y-1">
                  {sideMenuItems.map((item) => (
                    <button
                      key={item.id}
                      onClick={() => {
                        setActiveTab(item.id);
                        router.push(item.path);
                      }}
                      className={`flex items-center w-full px-4 py-3 text-sm rounded-lg transition-colors ${
                        activeTab === item.id
                          ? 'bg-blue-50 text-blue-600 font-medium'
                          : 'text-gray-700 hover:bg-gray-100'
                      }`}
                    >
                      <svg 
                        className="w-5 h-5 mr-3" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                      </svg>
                      {item.label}
                    </button>
                  ))}
                </nav>
              </div>
            </div>

            {/* Main Content Area */}
            <div className="lg:col-span-3">
              <div className="bg-white rounded-xl shadow-lg p-6">
                {children}
              </div>
            </div>
          </div>
        </div>
      </div>
    </SessionManager>
  );
}