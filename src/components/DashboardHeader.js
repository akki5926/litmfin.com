'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import Image from 'next/image';
import siteConfig from "@/config/siteConfig";

export default function DashboardHeader({ user }) {
  const [showDropdown, setShowDropdown] = useState(false);
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  const router = useRouter();

  const handleLogout = () => {
    // Clear all auth data
    localStorage.removeItem('site_user');
    localStorage.removeItem('site_auth');
    localStorage.removeItem('site_token');
    localStorage.removeItem('site_remember');
    
    // Redirect to login
    router.push('/login');
  };

  // Get initials for avatar
  const getInitials = () => {
    if (!user?.name) return 'U';
    const names = user.name.split(' ');
    if (names.length === 1) return names[0].charAt(0).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
  };

  // Dashboard menu items (for the dropdown)
  const dashboardMenuItems = [
    { label: 'Dashboard', href: '/dashboard/account-status', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Invest Now', href: '/invest', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Redeem Now', href: '/redeem', icon: 'M19 14l-7 7m0 0l-7-7m7 7V3' },
    { label: 'Current SIPs', href: '/sips', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Portfolio', href: '/portfolio', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { label: 'Transaction History', href: '/transactions', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { label: 'Recommended Funds', href: '/recommended-funds', icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
    { label: 'Tax Saver Funds', href: '/tax-saver', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Other Investment', href: '/other-investment', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
    { label: 'Mandate Registration', href: '/dashboard/mandate-registration', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { label: 'Profile', href: '/profile', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { label: 'FAQ', href: '/faq', icon: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  ];

  // Updated homepage navigation items for dashboard header
  const homepageNavItems = [
    { name: 'Home', href: '/' },
    { name: 'Multi Asset', href: '/asset-allocation' },
    { name: 'Forex Advisory', href: '/forex-advisory' },
    { name: 'Smart Loans', href: '/smart-loans' },
    { name: 'About Us', href: '/about-us' },
  ];

  // Get UCC from user object
  // const getUCC = () => {
  //   return user?.UCC || user?.ucc || user?.clientCode || 'N/A';
  // };

  return (
    <>
      {/* Mobile Menu Overlay */}
      {showMobileMenu && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={() => setShowMobileMenu(false)}
        />
      )}

      <header className="bg-white shadow-sm border-b sticky top-0 z-30">
        <div className="w-full px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            {/* Logo */}
            <div className="flex items-center">
              <button
                onClick={() => setShowMobileMenu(!showMobileMenu)}
                className="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
              >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
              <Link href="/dashboard" className="flex items-center ml-2 lg:ml-0 space-x-3">
                <Image
                  src={siteConfig.branding.logo}
                  alt={`${siteConfig.siteName} - ${siteConfig.tagline}`}
                  width={150}
                  height={50}
                  className="h-10 w-auto object-contain"
                  priority
                />
              </Link>
            </div>

            {/* Desktop Navigation - Professional Financial Style */}
            <div className="hidden lg:flex items-center">
              <div className="flex items-center space-x-1 bg-white/95 backdrop-blur-sm rounded-2xl px-3 py-2 shadow-sm border border-gray-200">
                {homepageNavItems.map((item, index) => (
                  <div key={item.name} className="relative">
                    <Link
                      href={item.href}
                      className={`flex items-center px-4 py-2.5 rounded-lg font-medium transition-all duration-200 ${
                        router.pathname === item.href
                          ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-md'
                          : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50/50'
                      }`}
                    >
                      <span className="relative">
                        {item.name}
                        {item.name === 'Smart Loans' && (
                          <span className="absolute -top-2 -right-6 text-[10px] px-1.5 py-0.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-full font-bold">
                            LOW RATES
                          </span>
                        )}
                      </span>
                    </Link>
                    
                    {/* Separator between items (except last) */}
                    {index < homepageNavItems.length - 1 && (
                      <div className="absolute top-1/2 right-0 transform -translate-y-1/2 w-px h-4 bg-gray-300"></div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* User Dropdown */}
            <div className="flex items-center">
              <div className="relative">
                <button
                  onClick={() => setShowDropdown(!showDropdown)}
                  className="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center">
                    <span className="text-white font-bold">{getInitials()}</span>
                  </div>
                  <div className="hidden md:block text-left">
                    <p className="text-sm font-medium text-gray-900">{user?.name || 'User'}</p>
                    <p className="text-xs text-gray-500">Activation Pending</p>
                  </div>
                  <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                {/* Dropdown Menu */}
                {showDropdown && (
                  <>
                    <div 
                      className="fixed inset-0 z-20"
                      onClick={() => setShowDropdown(false)}
                    />
                    <div className="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border py-2 z-30">
                      <div className="px-4 py-3 border-b">
                        <p className="text-sm font-medium text-gray-900">{user?.name || 'User'}</p>
                      </div>
                      
                      <div className="py-2 max-h-96 overflow-y-auto">
                        {/* Dashboard Menu Section Header */}
                        <div className="px-4 py-2">
                          <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dashboard Menu</h4>
                        </div>
                        
                        {dashboardMenuItems.map((item) => (
                          <Link
                            key={item.label}
                            href={item.href}
                            className="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors group"
                            onClick={() => setShowDropdown(false)}
                          >
                            <svg 
                              className="w-5 h-5 mr-3 text-gray-400 group-hover:text-blue-500" 
                              fill="none" 
                              stroke="currentColor" 
                              viewBox="0 0 24 24"
                            >
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                            </svg>
                            <span>{item.label}</span>
                            {item.label === 'Profile' && (
                              <span className="ml-auto bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">New</span>
                            )}
                          </Link>
                        ))}
                      </div>
                      
                      <div className="border-t pt-2">
                        <button
                          onClick={handleLogout}
                          className="w-full flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                        >
                          <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                          </svg>
                          Logout
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      </header>

      {/* Mobile Sidebar */}
      <div className={`
        fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-xl transform transition-transform duration-300 lg:hidden
        ${showMobileMenu ? 'translate-x-0' : '-translate-x-full'}
      `}>
        <div className="h-full flex flex-col">
          {/* Mobile Sidebar Header */}
          <div className="p-6 border-b">
            <div className="flex items-center space-x-3">
              <div className="w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center">
                <span className="text-white font-bold text-lg">{getInitials()}</span>
              </div>
              <div>
                <p className="font-semibold text-gray-900">{user?.name || 'User'}</p>
              </div>
            </div>
          </div>

          {/* Mobile Sidebar Menu - Updated with new design */}
          <div className="flex-1 overflow-y-auto py-4">
            <nav className="space-y-1 px-4">
              {/* Homepage Navigation */}
              <div className="mb-6">
                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Website Navigation</h3>
                {homepageNavItems.map((item) => (
                  <Link
                    key={item.name}
                    href={item.href}
                    className={`flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200 mb-1 ${
                      router.pathname === item.href
                        ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-md'
                        : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'
                    }`}
                    onClick={() => setShowMobileMenu(false)}
                  >
                    <span className="font-medium">{item.name}</span>
                    {item.name === 'Smart Loans' && (
                      <span className="px-2 py-1 text-xs bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-full font-bold">
                        LOW RATES
                      </span>
                    )}
                  </Link>
                ))}
              </div>
              
              {/* Dashboard Menu Section */}
              <div className="mt-6">
                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Dashboard Menu</h3>
                {dashboardMenuItems.map((item) => (
                  <Link
                    key={item.label}
                    href={item.href}
                    className="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-600 mb-1"
                    onClick={() => setShowMobileMenu(false)}
                  >
                    <svg 
                      className="w-5 h-5 mr-3 text-gray-400" 
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                    </svg>
                    <span>{item.label}</span>
                  </Link>
                ))}
              </div>
            </nav>
          </div>

          {/* Mobile Sidebar Footer */}
          <div className="p-6 border-t">
            <button
              onClick={handleLogout}
              className="w-full flex items-center justify-center px-4 py-2.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors font-medium"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Logout
            </button>
          </div>
        </div>
      </div>
    </>
  );
}