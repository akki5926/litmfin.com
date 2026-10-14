'use client';

import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import { useRouter } from 'next/navigation';

export default function RbiBondsPage() {
  const router = useRouter();

  const handleEnquire = () => {
    router.push(`/contact?subject=${encodeURIComponent('RBI Floating Rate Savings Bonds')}`);
  };

  const keyFeatures = [
    {
      title: 'Maturity Period',
      value: '7 Years',
      description: 'Tenure of 7 years from the date of issue with fixed term structure.',
      icon: (
        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      ),
    },
    {
      title: 'Rate of Interest',
      value: '8.05% Floating',
      description: 'Spread linked to NSC (National Savings Certificate) over 35 bps (+0.35%).',
      icon: (
        <svg className="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      ),
    },
    {
      title: 'Interest Reset Frequency',
      value: 'Half-Yearly',
      description: 'Reset twice a year starting 1st Jan 2021 based on prevailing NSC interest rates.',
      icon: (
        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      ),
    },
    {
      title: 'Interest Payout Dates',
      value: '1st July & 1st Jan',
      description: 'Payouts directly credited to investor bank account semi-annually.',
      icon: (
        <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      ),
    },
    {
      title: 'Minimum Investment',
      value: '₹10,000/-',
      description: 'Start investing with a minimum of ₹10,000 (in multiples of ₹1,000 thereafter).',
      icon: (
        <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Maximum Investment Limit',
      value: 'No Maximum Limit',
      description: 'Invest any amount as per your financial surplus without any cap.',
      icon: (
        <svg className="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      ),
    },
  ];

  const seniorCitizenProvisions = [
    { ageGroup: '60 to 70 Years', lockIn: '6 Years', requirement: 'Can withdraw after completing 6 years of tenure.' },
    { ageGroup: '70 to 80 Years', lockIn: '5 Years', requirement: 'Can withdraw after completing 5 years of tenure.' },
    { ageGroup: '80 Years and Above', lockIn: '4 Years', requirement: 'Can withdraw after completing 4 years of tenure.' },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 via-white to-gray-50">
      <div className="container mx-auto px-4 py-12">
        {/* Back Navigation Button */}
        <div className="mb-6">
          <button
            onClick={() => router.back()}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium transition-colors"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>

        {/* Hero Section */}
        <div className="text-center mb-14">
          <span className="inline-block px-4 py-1.5 bg-blue-100 text-blue-800 text-sm font-semibold rounded-full mb-4 shadow-sm">
            Government of India Securities
          </span>
          <h1 className="text-4xl md:text-5xl font-bold text-blue-950 mb-6 tracking-tight">
            <TypedText text="RBI Floating Rate Savings Bonds" className="text-blue-900" />
          </h1>
          <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
            Secure your savings with 100% sovereign guarantee, attractive floating yields reset half-yearly, and predictable semi-annual interest income.
          </p>

          <div className="mt-8 flex justify-center gap-4">
            <button
              onClick={handleEnquire}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
            >
              Enquire & Invest Now
            </button>
          </div>
        </div>

        {/* Highlight Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
          {keyFeatures.map((feature, idx) => (
            <div
              key={idx}
              className="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between mb-4">
                  <span className="p-3 bg-blue-50 rounded-xl">{feature.icon}</span>
                  <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Feature #{idx + 1}</span>
                </div>
                <h3 className="text-sm font-medium text-gray-500 mb-1">{feature.title}</h3>
                <p className="text-2xl font-bold text-gray-900 mb-3">{feature.value}</p>
                <p className="text-sm text-gray-600">{feature.description}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Detailed Features & Interest Mechanics */}
        <div className="bg-white rounded-3xl shadow-xl p-8 md:p-12 mb-16 border border-gray-100">
          <div className="max-w-3xl mb-8">
            <h2 className="text-3xl font-bold text-blue-900 mb-4">Key Investment Highlights & Features</h2>
            <p className="text-gray-600 text-base">
              RBI Floating Rate Savings Bonds, 2020 (Taxable) offer a safe, sovereign-backed avenue for individuals and HUFs to earn regular income.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-gradient-to-br from-blue-50 to-indigo-50/40 p-6 rounded-2xl border border-blue-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center font-bold">
                  %
                </div>
                <h3 className="text-xl font-bold text-blue-900">Interest Rate & Benchmark Linkage</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Current Floating Rate:</strong> 8.05% per annum.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Spread Over NSC:</strong> Linked to NSC (National Savings Certificate) with an added spread of +35 basis points (0.35%).</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Half-Yearly Reset:</strong> Interest is reset twice every year starting from 1st Jan 2021, keeping returns dynamic in changing market environments.</span>
                </li>
              </ul>
            </div>

            <div className="bg-gradient-to-br from-emerald-50 to-teal-50/40 p-6 rounded-2xl border border-emerald-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold">
                  📅
                </div>
                <h3 className="text-xl font-bold text-emerald-900">Payout & Investment Limits</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Fixed Payout Schedule:</strong> Interest is paid half-yearly on <strong>1st July</strong> and <strong>1st January</strong> each year.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Low Entry Minimum:</strong> Minimum subscription is <strong>Rs. 10,000/-</strong> and in multiples of Rs. 1,000 thereafter.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>No Investment Ceiling:</strong> There is <strong>no maximum limit</strong> on investment. You can invest as much as you require.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Special Provisions for Senior Citizens */}
        <div className="bg-gradient-to-r from-slate-900 to-blue-950 text-white rounded-3xl p-8 md:p-12 mb-16 shadow-2xl">
          <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-8 gap-4">
            <div>
              <span className="px-3 py-1 bg-amber-400 text-gray-900 text-xs font-bold uppercase rounded-md tracking-wider">
                Senior Citizen Benefit
              </span>
              <h2 className="text-3xl font-bold mt-2">Premature Withdrawal Provisions</h2>
              <p className="text-blue-200 mt-1 max-w-2xl">
                While the bond has a standard tenure of 7 years, senior citizens enjoy special premature withdrawal options after a specified minimum lock-in period.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {seniorCitizenProvisions.map((item, index) => (
              <div key={index} className="bg-white/10 backdrop-blur-md border border-white/15 p-6 rounded-2xl">
                <div className="text-amber-400 font-bold text-sm mb-1 uppercase tracking-wider">Age Category</div>
                <div className="text-2xl font-bold text-white mb-2">{item.ageGroup}</div>
                <div className="text-blue-200 text-sm mb-3">
                  Minimum Lock-in: <strong className="text-white font-semibold">{item.lockIn}</strong>
                </div>
                <p className="text-xs text-gray-300 leading-relaxed">{item.requirement}</p>
              </div>
            ))}
          </div>
          <p className="text-xs text-gray-400 mt-6 italic">
            * Note: Premature withdrawal is subject to a deduction of half a year&apos;s interest for the period held.
          </p>
        </div>

        {/* Why Choose RBI Bonds Card */}
        <div className="bg-white rounded-3xl p-8 md:p-12 shadow-xl border border-gray-100 mb-16">
          <h2 className="text-3xl font-bold text-gray-900 mb-8 text-center">Why Invest in RBI Floating Rate Bonds with {siteConfig.CompanyName}?</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="text-center p-4">
              <div className="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-blue-600 font-bold text-2xl">
                🛡️
              </div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">100% Capital Safety</h3>
              <p className="text-gray-600 text-sm">
                Backed by the Sovereign Guarantee of the Government of India, making default risk zero.
              </p>
            </div>
            <div className="text-center p-4">
              <div className="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-emerald-600 font-bold text-2xl">
                💸
              </div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">Regular Cash Flows</h3>
              <p className="text-gray-600 text-sm">
                Half-yearly interest payouts ensure steady liquidity directly credited to your savings account.
              </p>
            </div>
            <div className="text-center p-4">
              <div className="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-purple-600 font-bold text-2xl">
                🎯
              </div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">Hassle-Free Processing</h3>
              <p className="text-gray-600 text-sm">
                Our experts guide you through seamless application, documentation, and portfolio tracking.
              </p>
            </div>
          </div>
        </div>

        {/* CTA Banner */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-3xl p-8 md:p-12 text-center shadow-xl">
          <h2 className="text-3xl font-bold mb-4">Ready to Invest in RBI Bonds?</h2>
          <p className="text-blue-100 mb-8 max-w-2xl mx-auto text-base">
            Get complete guidance on RBI Floating Rate Savings Bonds, interest estimation, and paperless subscription through {siteConfig.siteName}.
          </p>
          <button
            onClick={handleEnquire}
            className="bg-white text-blue-900 font-bold px-8 py-3.5 rounded-xl hover:bg-blue-50 transition-colors shadow-lg"
          >
            Request Call back / Enquire
          </button>
        </div>
      </div>
    </div>
  );
}
