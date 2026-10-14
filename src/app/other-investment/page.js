'use client';

import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

export default function OtherInvestmentPage() {
  const router = useRouter();

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-slate-50">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="mb-8">
          <button
            onClick={() => router.back()}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>

        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Other Investments" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Explore alternative investment options</p>
        </div>

        <div className="bg-white rounded-2xl shadow-xl p-8 md:p-12">
          <div className="text-center py-16">
            <div className="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <svg className="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </div>
            <h2 className="text-3xl font-bold text-gray-900 mb-4">Coming Soon</h2>
            <p className="text-gray-600 text-lg mb-8 max-w-md mx-auto">
              Alternative investment options are currently under development. You&apos;ll soon be able to explore various investment avenues here.
            </p>
            
            <div className="space-y-4 max-w-md mx-auto mb-12">
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 className="font-medium text-gray-800 mb-2">What to expect:</h3>
                <ul className="text-left text-gray-700 space-y-1">
                  <li className="flex items-start">
                    <span className="text-gray-600 mr-2">•</span>
                    <span>Gold investments (Sovereign Gold Bonds, Gold ETFs)</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-gray-600 mr-2">•</span>
                    <span>Corporate bonds and debentures</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-gray-600 mr-2">•</span>
                    <span>Real estate investment options</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-gray-600 mr-2">•</span>
                    <span>International fund investments</span>
                  </li>
                </ul>
              </div>
            </div>

            <div className="space-x-4">
              <button
                onClick={() => router.push('/invest')}
                className="px-6 py-3 bg-gray-800 text-white font-medium rounded-lg hover:bg-gray-900 transition-colors"
              >
                Explore Investments
              </button>
              <button
                onClick={() => router.push('/dashboard/home')}
                className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
              >
                Go to Dashboard
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}