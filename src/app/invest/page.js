// /app/invest/page.js
'use client';

import { useRouter } from 'next/navigation';
import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';
import siteConfig from '@/config/siteConfig';

export default function InvestPage() {
  const router = useRouter();
  const [user, setUser] = useState(null);

  const fundCategories = [
    {
      id: 'equity',
      title: 'Equity',
      description: 'Stock provide part-ownership which in turn create Long term Wealth',
      color: 'bg-blue-500',
      icon: '\u{1F4C8}'
    },
    ...(siteConfig.features.SIF ? [{
      id: 'sif',
      title: 'SIF',
      description: 'Systematic Investment Funds for investors who prefer regular periodic investments.',
      color: 'bg-pink-500',
      icon: '\u{1F4B3}'
    }] : []),
    {
      id: 'gold',
      title: 'Gold',
      description: 'Precious Metal for all ages which provides protection against inflation.',
      color: 'bg-yellow-500',
      icon: '\u{1F947}'
    },
    {
      id: 'fof',
      title: 'FOF',
      description: 'Let Fund Manager decide the Funds to invest primarily for International Equities.',
      color: 'bg-purple-500',
      icon: '\u{1F310}'
    },
    {
      id: 'debt',
      title: 'Debt',
      description: 'Funds that can easily replace Bank FDs if used properly.',
      color: 'bg-green-500',
      icon: '\u{1F4B0}'
    },
    {
      id: 'hybrid',
      title: 'Hybrid',
      description: 'Funds that create balance for whom choice between debt and equity is difficult.',
      color: 'bg-orange-500',
      icon: '\u2696\uFE0F'
    },
    {
      id: 'arbitrage',
      title: 'Arbitrage',
      description: "Risk Free Strategy to provide better post tax returns especially for HNI's and Corporates.",
      color: 'bg-teal-500',
      icon: '\u{1F504}'
    },
    {
      id: 'tax-saving',
      title: 'Tax Saving',
      description: 'Funds that give high possible returns plus Tax rebates to customers.',
      color: 'bg-red-500',
      icon: '\u{1F4B8}'
    },
    {
      id: 'etf',
      title: 'ETF',
      description: 'Exchange traded funds for active investors.',
      color: 'bg-indigo-500',
      icon: '\u{1F4CA}'
    }
  ];

  useEffect(() => {
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

  const handleCategoryClick = (categoryId) => {
    router.push(`/invest/${categoryId}`);
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
    <div className="min-h-screen bg-gray-50">
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <button
            onClick={() => router.push('/dashboard/home')}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Profile
          </button>
        </div>
      </div>

      <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
          <h1 className="text-4xl font-bold mb-4">
            <TypedText text="Start Your Investment Journey" className="text-white" />
          </h1>
          <p className="text-xl text-blue-100 max-w-3xl mx-auto">
            Choose from our wide range of mutual fund categories to build a diversified portfolio
          </p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {fundCategories.map((category) => (
            <div
              key={category.id}
              className="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 cursor-pointer overflow-hidden border border-gray-200 hover:border-blue-300 transform hover:-translate-y-1"
            >
              <div className={`${category.color} p-6 text-white`}>
                <div className="flex items-center justify-between">
                  <div className="text-4xl">{category.icon}</div>
                  <div className="text-right">
                    <div className="text-2xl font-bold">{category.title}</div>
                    <div className="text-sm opacity-90 mt-1">Mutual Funds</div>
                  </div>
                </div>
              </div>

              <div className="p-6">
                <p className="text-gray-600 text-sm leading-relaxed mb-6">{category.description}</p>

                <div className="mt-6">
                  <button
                    onClick={() => handleCategoryClick(category.id)}
                    className="w-full py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium rounded-lg transition-colors flex items-center justify-center"
                  >
                    Explore Funds
                    <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
