'use client';

import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import { useRouter } from 'next/navigation';

export default function AifPage() {
  const router = useRouter();

  const handleEnquire = () => {
    router.push(`/contact?subject=${encodeURIComponent('Alternative Investment Funds (AIF)')}`);
  };

  const aifHighlights = [
    {
      title: 'Minimum Investment',
      value: '₹1 Crore',
      description: 'Minimum ticket size mandated for HNIs, Ultra-HNIs, & Institutional Investors.',
      icon: (
        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Lock-in Period',
      value: 'Flexible (0 to 10 Yrs)',
      description: 'Varies from No Lock-in (open-ended) up to 10 years depending on the fund strategy.',
      icon: (
        <svg className="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      ),
    },
    {
      title: 'Cash Flow Options',
      value: 'Payouts or Growth',
      description: 'Defined periodic cash flows (Quarterly or Yearly payouts) or Capital Growth options.',
      icon: (
        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      ),
    },
    {
      title: 'Taxation Rules',
      value: 'Category Dependent',
      description: 'Category I & II taxed at Investor hands. Category III taxed at Fund level.',
      icon: (
        <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 14l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Alternative Assets',
      value: 'Private & Real Assets',
      description: 'Invests in Private Equity, Venture Capital, Hedge Funds, & Infrastructure.',
      icon: (
        <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      ),
    },
    {
      title: 'Target Audience',
      value: 'HNIs & Institutions',
      description: 'Tailored for high-net-worth investors seeking non-traditional market exposure.',
      icon: (
        <svg className="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      ),
    },
  ];

  const aifCategories = [
    {
      category: 'Category I AIF',
      title: 'Social & Early-Stage Capital',
      assets: 'Venture Capital, Angel Funds, SME Funds, Social Venture & Infrastructure Funds.',
      taxation: 'Pass-through status (Taxed directly at the hands of the Investor).',
    },
    {
      category: 'Category II AIF',
      title: 'Private Equity & Real Estate',
      assets: 'Private Equity (PE) Funds, Debt Funds, Real Estate Funds, & Special Situation Funds.',
      taxation: 'Pass-through status (Taxed directly at the hands of the Investor).',
    },
    {
      category: 'Category III AIF',
      title: 'Hedge Funds & Trading Strategies',
      assets: 'Hedge Funds, Long-Short Equity Funds, Derivative Trading & PIPE Strategies.',
      taxation: 'Taxed at the Fund level (No pass-through status).',
    },
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
            Privately Pooled High-Yield Vehicles
          </span>
          <h1 className="text-4xl md:text-5xl font-bold text-blue-950 mb-6 tracking-tight">
            <TypedText text="Alternative Investment Funds (AIF)" className="text-blue-900" />
          </h1>
          <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
            Alternative Investment Funds (AIFs) are privately pooled investment vehicles that invest in alternative assets like private equity, venture capital, hedge funds, and infrastructure. They require a minimum investment of ₹1 crore and are meant for HNIs and institutional investors.
          </p>

          <div className="mt-8 flex justify-center gap-4">
            <button
              onClick={handleEnquire}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
            >
              Enquire & Schedule AIF Advisory
            </button>
          </div>
        </div>

        {/* Highlight Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
          {aifHighlights.map((feature, idx) => (
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

        {/* Detailed Mechanics & Tax Structure */}
        <div className="bg-white rounded-3xl shadow-xl p-8 md:p-12 mb-16 border border-gray-100">
          <div className="max-w-3xl mb-8">
            <h2 className="text-3xl font-bold text-blue-900 mb-4">AIF Structure & Tax Breakdown</h2>
            <p className="text-gray-600 text-base">
              AIFs are categorized into three distinct frameworks by SEBI depending on their underlying asset classes and investment strategies.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            {aifCategories.map((cat, idx) => (
              <div key={idx} className="bg-gradient-to-br from-blue-50/50 to-indigo-50/30 p-6 rounded-2xl border border-blue-100 flex flex-col justify-between">
                <div>
                  <span className="px-3 py-1 bg-blue-600 text-white text-xs font-bold uppercase rounded-md">
                    {cat.category}
                  </span>
                  <h3 className="text-xl font-bold text-blue-950 mt-3 mb-2">{cat.title}</h3>
                  <p className="text-xs text-gray-600 mb-4 leading-relaxed">
                    <strong>Assets:</strong> {cat.assets}
                  </p>
                </div>
                <div className="pt-4 border-t border-blue-100">
                  <span className="text-xs font-semibold text-gray-500 uppercase block mb-1">Taxation Rule</span>
                  <p className="text-xs font-medium text-blue-900">{cat.taxation}</p>
                </div>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
            <div className="bg-gradient-to-br from-emerald-50 to-teal-50/40 p-6 rounded-2xl border border-emerald-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold">
                  ⏱️
                </div>
                <h3 className="text-xl font-bold text-emerald-900">Flexible Lock-in Terms</h3>
              </div>
              <p className="text-sm text-gray-700 leading-relaxed">
                Various AIF strategies feature distinct lock-in durations ranging from <strong>No Lock-in</strong> (open-ended hedge strategies) up to <strong>10 years</strong> of lock-in for long-term venture capital, infrastructure, or real estate development funds.
              </p>
            </div>

            <div className="bg-gradient-to-br from-purple-50 to-pink-50/40 p-6 rounded-2xl border border-purple-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-purple-600 text-white rounded-xl flex items-center justify-center font-bold">
                  💵
                </div>
                <h3 className="text-xl font-bold text-purple-900">Custom Cash Flow Structure</h3>
              </div>
              <p className="text-sm text-gray-700 leading-relaxed">
                Investors can choose funds structured for regular liquidity with defined cash flows (e.g., <strong>Quarterly or Yearly dividend/yield payouts</strong>) or opt for pure <strong>Growth strategies</strong> compounding value over time.
              </p>
            </div>
          </div>
        </div>

        {/* CTA Banner */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-3xl p-8 md:p-12 text-center shadow-xl">
          <h2 className="text-3xl font-bold mb-4">Looking to Explore Alternative Investment Funds?</h2>
          <p className="text-blue-100 mb-8 max-w-2xl mx-auto text-base">
            Connect with our wealth advisors at {siteConfig.CompanyName} to evaluate Category I, II, and III AIF opportunities suitable for your portfolio.
          </p>
          <button
            onClick={handleEnquire}
            className="bg-white text-blue-900 font-bold px-8 py-3.5 rounded-xl hover:bg-blue-50 transition-colors shadow-lg"
          >
            Schedule Consultation / Enquire
          </button>
        </div>
      </div>
    </div>
  );
}
