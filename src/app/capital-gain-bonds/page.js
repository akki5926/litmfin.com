'use client';

import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import { useRouter } from 'next/navigation';

export default function CapitalGainBondsPage() {
  const router = useRouter();

  const handleEnquire = () => {
    router.push(`/contact?subject=${encodeURIComponent('Capital Gain Bonds (Section 54EC)')}`);
  };

  const keyFeatures = [
    {
      title: 'Mode of Holding',
      value: 'Demat & Physical Form',
      description: 'Capital Gain Bonds can be bought easily in both Demat and Physical Certificate forms.',
      icon: (
        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      ),
    },
    {
      title: 'Tax Exemption',
      value: 'Section 54EC Tax Saver',
      description: 'Exemption is claimed only for profits from property sales comprising land & buildings (residential & commercial).',
      icon: (
        <svg className="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Rate of Interest',
      value: '5.25% p.a.',
      description: 'Interest rate payable annually with assured returns backed by PSU issuers.',
      icon: (
        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      ),
    },
    {
      title: 'Investment Timeframe',
      value: 'Within 6 Months',
      description: 'Investment should be made within six (6) months from the date of capital asset (property) sale.',
      icon: (
        <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Lock-in Period',
      value: '5 Years Lock-in',
      description: 'Bonds have a lock-in of 5 years from investment date and automatically redeem at par at maturity.',
      icon: (
        <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      ),
    },
    {
      title: 'Investment Limits',
      value: '₹20,000 to ₹50 Lacs',
      description: 'Minimum investment INR 20,000 | Maximum limit of INR 50 Lacs in a financial year.',
      icon: (
        <svg className="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
  ];

  const approvedIssuers = [
    { name: 'REC Limited', code: 'Rural Electrification Corp', rating: 'AAA Rated (Sovereign Quality)' },
    { name: 'PFC Limited', code: 'Power Finance Corp', rating: 'AAA Rated (Sovereign Quality)' },
    { name: 'NHAI', code: 'National Highways Authority of India', rating: 'AAA Rated (Government Body)' },
    { name: 'IRFC', code: 'Indian Railway Finance Corp', rating: 'AAA Rated (Indian Railways)' },
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
            Section 54EC Tax Exemption Scheme
          </span>
          <h1 className="text-4xl md:text-5xl font-bold text-blue-950 mb-6 tracking-tight">
            <TypedText text="Capital Gain Bonds (54EC)" className="text-blue-900" />
          </h1>
          <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
            Save long-term capital gains tax on profits realized from the sale of land or buildings by investing in AAA-rated 54EC Capital Gain Bonds. Available in both Demat and Physical form.
          </p>

          <div className="mt-8 flex justify-center gap-4">
            <button
              onClick={handleEnquire}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
            >
              Enquire & Save Capital Gains Tax
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

        {/* Detailed Guidelines Section */}
        <div className="bg-white rounded-3xl shadow-xl p-8 md:p-12 mb-16 border border-gray-100">
          <div className="max-w-3xl mb-8">
            <h2 className="text-3xl font-bold text-blue-900 mb-4">Section 54EC Rules & Tax Exemption Rules</h2>
            <p className="text-gray-600 text-base">
              Capital Gain Bonds offer an official, Government-approved mechanism under Section 54EC of the Income Tax Act to exempt long-term capital gains tax.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-gradient-to-br from-blue-50 to-indigo-50/40 p-6 rounded-2xl border border-blue-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center font-bold">
                  📜
                </div>
                <h3 className="text-xl font-bold text-blue-900">Exemption Scope & Timelines</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Qualifying Asset Sale:</strong> Exemption is claimed <strong>only for profits</strong> arising from the sale of long-term real estate assets (land & buildings, both residential & commercial).</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Strict 6-Month Window:</strong> Investment must be completed within <strong>six (6) months</strong> from the date of property transfer/sale.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Flexible Demat & Physical Holding:</strong> Investors can choose to hold these bonds in either <strong>Demat account or Physical Certificate form</strong>.</span>
                </li>
              </ul>
            </div>

            <div className="bg-gradient-to-br from-emerald-50 to-teal-50/40 p-6 rounded-2xl border border-emerald-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold">
                  🔒
                </div>
                <h3 className="text-xl font-bold text-emerald-900">Limits & Interest Mechanics</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Interest Yield:</strong> Currently offers <strong>5.25% p.a.</strong> interest payable annually directly to your bank account.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>5-Year Lock-in:</strong> Lock-in of 5 years from investment date; automatically redeemed at par at maturity.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Investment Limits:</strong> Minimum investment of <strong>INR 20,000</strong> up to a maximum cap of <strong>INR 50 Lacs</strong> per financial year.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Approved PSU Issuers Grid */}
        <div className="bg-gradient-to-r from-slate-900 to-blue-950 text-white rounded-3xl p-8 md:p-12 mb-16 shadow-2xl">
          <div className="mb-8">
            <span className="px-3 py-1 bg-amber-400 text-gray-900 text-xs font-bold uppercase rounded-md tracking-wider">
              Eligible Bond Issuers
            </span>
            <h2 className="text-3xl font-bold mt-2">Government-Backed PSU Issuers</h2>
            <p className="text-blue-200 mt-1 max-w-2xl">
              Section 54EC Capital Gain Bonds are issued by premier public sector undertakings (PSUs) ensuring highest safety.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {approvedIssuers.map((issuer, index) => (
              <div key={index} className="bg-white/10 backdrop-blur-md border border-white/15 p-6 rounded-2xl">
                <div className="text-amber-400 font-bold text-lg mb-1">{issuer.name}</div>
                <div className="text-sm font-medium text-white mb-2">{issuer.code}</div>
                <p className="text-xs text-blue-200">{issuer.rating}</p>
              </div>
            ))}
          </div>
        </div>

        {/* CTA Banner */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-3xl p-8 md:p-12 text-center shadow-xl">
          <h2 className="text-3xl font-bold mb-4">Sold Property Recently & Looking to Save Capital Gain Tax?</h2>
          <p className="text-blue-100 mb-8 max-w-2xl mx-auto text-base">
            Get instant assistance for investing in Capital Gain Bonds (Section 54EC) in Demat or Physical form with {siteConfig.CompanyName}.
          </p>
          <button
            onClick={handleEnquire}
            className="bg-white text-blue-900 font-bold px-8 py-3.5 rounded-xl hover:bg-blue-50 transition-colors shadow-lg"
          >
            Request Assistance / Enquire Now
          </button>
        </div>
      </div>
    </div>
  );
}
