'use client';

import { useState, useEffect, useRef } from 'react';
import TypedText from '@/components/TypedText';
import { useRouter } from 'next/navigation';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/api';

export default function TaxSaverFundsPage() {
  const [funds, setFunds] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedFund, setSelectedFund] = useState(null);
  const [comparisonList, setComparisonList] = useState([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [activeTab, setActiveTab] = useState(0);
  const [showInvestmentPanel, setShowInvestmentPanel] = useState(false);
  const [investmentAmount, setInvestmentAmount] = useState('');
  const [buyLoading, setBuyLoading] = useState(false);
  const [minInvestmentAmount, setMinInvestmentAmount] = useState(100);
  const investmentPanelRef = useRef(null);
  const router = useRouter();

  // Color scheme for tax saver theme
  const taxSaverColorScheme = {
    bg: 'bg-blue-50',
    border: 'border-blue-200',
    text: 'text-blue-800',
    badge: 'bg-blue-100 text-blue-800',
    button: 'bg-blue-600 hover:bg-blue-700',
    light: 'bg-blue-50',
    accent: 'bg-purple-100 text-purple-800',
    success: 'bg-green-100 text-green-800'
  };

  useEffect(() => {
    const fetchTaxSaverFunds = async () => {
      try {
        setLoading(true);
        setError(null);
        
        const response = await fetch(`${API_BASE_URL}/tax_saver_api.php?action=get_recommended_tax_saver`);
        const data = await response.json();
        
        if (data.success) {
          // Transform API data to match our structure
          const transformedFunds = data.data.map((fund, index) => ({
            id: index + 1,
            amc: fund.amc_name || fund.amc_code || 'N/A',
            amc_code: fund.amc_code,
            scheme: fund.scheme_name || fund.display_name || fund.scheme_code || 'ELSS Fund',
            scheme_name: fund.scheme_name || fund.display_name || fund.scheme_code || 'ELSS Fund',
            scheme_code: fund.scheme_code,
            fundType: fund.fundType || 'ELSS',
            allocation: 100 / data.data.length,
            rational: fund.rational || fund.investment_rationale || 'Tax saving with equity growth potential',
            options: fund.plan_type === 'Growth' ? 'Direct-Growth' : (fund.plan_type === 'IDCW' ? 'IDCW' : 'Regular-Growth'),
            riskLevel: fund.riskLevel || 'High',
            category: fund.category || 'Equity',
            minimumInvestment: fund.fund_details?.new_purchase_min_amount ? `₹${fund.fund_details.new_purchase_min_amount}` : '₹500',
            minInvestmentAmount: fund.fund_details?.new_purchase_min_amount || 500,
            exitLoad: fund.exit_load || (fund.lock_in_period ? `Nil after ${fund.lock_in_period}` : 'Nil after 3 years lock-in'),
            historicalReturn: '12-18%',
            color: taxSaverColorScheme.bg,
            fundCode: fund.scheme_code || '',
            product_id: fund.scheme_code,
            // Additional details from mtb_scheme_master
            fund_details: fund.fund_details,
            amc_name: fund.amc_name,
            scheme_details: fund.fund_details,
            scheme_type: fund.scheme_type,
            plan_type: fund.plan_type,
            lock_in_period: fund.lock_in_period,
            lock_in_period_flag: fund.lock_in_period_flag,
            sip_allowed: fund.sip_allowed,
            isin: fund.isin,
            fund_cat: fund.fund_cat,
            purchase_allowed: fund.purchase_allowed,
            redemption_allowed: fund.redemption_allowed,
            switch_allowed: fund.switch_allowed,
            stp_enabled: fund.stp_enabled,
            swp_enabled: fund.swp_enabled,
            scheme_start_date: fund.scheme_start_date,
            face_value: fund.face_value,
            exit_load_flag: fund.exit_load_flag,
            tax_benefits: fund.tax_benefits
          }));
          
          setFunds(transformedFunds);
        } else {
          setError(data.message || 'Failed to fetch tax saver funds');
        }
      } catch (err) {
        console.error('Error fetching tax saver funds:', err);
        setError('Unable to load tax saver funds. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchTaxSaverFunds();
  }, [taxSaverColorScheme.bg]);

  const handleViewDetails = async (fund) => {
    try {
      if (fund.amc_code && fund.product_id) {
        const response = await fetch(
          `${API_BASE_URL}/tax_saver_api.php?action=get_tax_saver_by_amc_product&amc_code=${fund.amc_code}&product_code=${fund.product_id}`
        );
        const data = await response.json();
        
        if (data.success) {
          setSelectedFund(data.data);
          setDialogOpen(true);
        } else {
          setSelectedFund(fund);
          setDialogOpen(true);
        }
      } else {
        setSelectedFund(fund);
        setDialogOpen(true);
      }
    } catch (err) {
      console.error('Failed to fetch fund details:', err);
      setSelectedFund(fund);
      setDialogOpen(true);
    }
  };

  const handleAddToComparison = (fund) => {
    if (!comparisonList.some(item => item.id === fund.id)) {
      if (comparisonList.length < 3) {
        setComparisonList([...comparisonList, fund]);
      } else {
        alert('Maximum 3 funds can be compared at a time');
      }
    }
  };

  const handleRemoveFromComparison = (id) => {
    setComparisonList(comparisonList.filter(fund => fund.id !== id));
  };

  const handleTabChange = (newValue) => {
    setActiveTab(newValue);
  };

  // Handle SIP button click - redirect to SIP page
  const handleSIPClick = (fund) => {
    // console.log('SIP Click - Fund Data:', fund);
    
    const investmentData = {
      fundHouse: {
        long_name: fund.amc,
        amc_code: fund.amc_code
      },
      scheme: {
        product_long_name: fund.scheme_name,
        product_code: fund.scheme_code
      },
      fundCategory: {
        category_name: fund.fundType || 'ELSS',
        details: 'Tax Saving Equity Fund'
      },
      schemeCode: fund.scheme_code,
      fundHouseCode: fund.amc_code,
      monthlyAmount: '',
      initialAmount: '0'
    };
    
    // console.log('SIP Investment Data:', investmentData);
    localStorage.setItem('investment_data', JSON.stringify(investmentData));
    const referrer = 'tax-saver';
    // console.log('Navigating to SIP page with referrer:', referrer);
    router.push(`/invest/sip?ref=${referrer}`);
  };

  // Handle BUY button click - show investment panel
  const handleBuyClick = (fund) => {
    setSelectedFund(fund);
    setInvestmentAmount('');
    setShowInvestmentPanel(true);
    
    const minAmount = fund.minInvestmentAmount || 500;
    setMinInvestmentAmount(minAmount);
    
    setTimeout(() => {
      investmentPanelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  };

  // Handle confirm buy - process lumpsum investment
  const handleConfirmBuy = async () => {
    if (!investmentAmount || parseFloat(investmentAmount) <= 0) {
      alert('Please enter a valid investment amount');
      return;
    }
    if (parseFloat(investmentAmount) < minInvestmentAmount) {
      alert(`Minimum investment amount is ₹${minInvestmentAmount}`);
      return;
    }
    
    try {
      setBuyLoading(true);
      const token = localStorage.getItem('site_token');
      
      const payload = {
        fund_house_code: selectedFund.amc_code,
        fund_house_name: selectedFund.amc || 'N/A',
        scheme_code: selectedFund.scheme_code,
        scheme_name: selectedFund.scheme_name || 'N/A',
        amount: parseFloat(investmentAmount),
        investment_type: 'buy'
      };
      
      // console.log('Buy payload:', payload);
      
      const response = await fetch(`${API_BASE_URL}/invest_api.php?action=buy_mf`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      
      if (data.success) {
        alert(`Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.\nScheme: ${selectedFund.scheme_name}\nAmount: ₹${investmentAmount}\nOrder ID: ${data.data?.order_id || 'N/A'}`);
        setShowInvestmentPanel(false);
        setSelectedFund(null);
        setInvestmentAmount('');
      } else {
        alert(`Investment failed: ${data.message}`);
      }
    } catch (error) {
      console.error('Error buying mutual fund:', error);
      alert(`Error processing investment: ${error.message}`);
    } finally {
      setBuyLoading(false);
    }
  };

  // Handle panel SIP (from the investment panel)
  const handlePanelSIP = () => {
    const investmentData = {
      fundHouse: {
        long_name: selectedFund.amc,
        amc_code: selectedFund.amc_code
      },
      scheme: {
        product_long_name: selectedFund.scheme_name,
        product_code: selectedFund.scheme_code
      },
      fundCategory: {
        category_name: selectedFund.fundType || 'ELSS',
        details: 'Tax Saving Equity Fund'
      },
      schemeCode: selectedFund.scheme_code,
      fundHouseCode: selectedFund.amc_code,
      amount: investmentAmount,
      monthlyAmount: investmentAmount,
      initialAmount: '0'
    };
    
    // console.log('Panel SIP Data:', investmentData);
    localStorage.setItem('investment_data', JSON.stringify(investmentData));
    router.push('/invest/sip');
  };

  // Loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading tax saver funds...</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-6 text-center">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Funds</h2>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => window.location.reload()}
            className="w-full py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                <TypedText text="Tax Saver Funds (ELSS)" className="text-gray-900" />
              </h1>
              <p className="mt-2 text-gray-600">
                Invest in Equity Linked Savings Schemes for tax benefits under Section 80C
              </p>
            </div>
            
            <div className="mt-4 md:mt-0">
              <div className="flex flex-wrap gap-2">
                <span className={`px-4 py-2 rounded-lg ${taxSaverColorScheme.bg} ${taxSaverColorScheme.border} border`}>
                  <div className="flex items-center">
                    <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span className="text-sm font-medium text-gray-900">Lock-in: 3 Years</span>
                  </div>
                </span>
                <span className={`px-4 py-2 rounded-lg ${taxSaverColorScheme.success} border border-green-200`}>
                  <div className="flex items-center">
                    <svg className="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="text-sm font-medium text-gray-900">Tax Benefit: ₹1.5 Lakh</span>
                  </div>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Comparison Section */}
      {comparisonList.length > 0 && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className={`rounded-xl ${taxSaverColorScheme.light} border ${taxSaverColorScheme.border} p-6 mb-6`}>
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4">
              <div className="flex items-center">
                <svg className="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <div>
                  <h2 className="text-xl font-bold text-gray-900">Funds for Comparison</h2>
                  <p className="text-sm text-gray-600">Selected {comparisonList.length} out of 3 funds</p>
                </div>
              </div>
              
              {comparisonList.length > 1 && (
                <button
                  onClick={() => handleTabChange(1)}
                  className={`mt-4 lg:mt-0 px-6 py-2 rounded-lg font-medium ${taxSaverColorScheme.button} text-white flex items-center`}
                >
                  <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                  </svg>
                  Compare Now
                </button>
              )}
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {comparisonList.map((fund) => (
                <div key={fund.id} className={`rounded-lg border ${taxSaverColorScheme.border} bg-white p-4`}>
                  <div className="flex justify-between items-start mb-3">
                    <div>
                      <h3 className="font-semibold text-gray-900 text-sm">{fund.scheme}</h3>
                      <p className="text-xs text-gray-500 mt-1">{fund.amc}</p>
                    </div>
                    <button
                      onClick={() => handleRemoveFromComparison(fund.id)}
                      className="text-red-600 hover:text-red-800"
                    >
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <div className="flex items-center">
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${taxSaverColorScheme.badge}`}>
                      ELSS
                    </span>
                    <span className="ml-2 text-xs text-gray-500">Section 80C</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="border-b border-gray-200 mb-8">
          <nav className="-mb-px flex space-x-8">
            <button
              onClick={() => handleTabChange(0)}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 0
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              All ELSS Funds
            </button>
            <button
              onClick={() => handleTabChange(1)}
              disabled={comparisonList.length < 2}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 1
                  ? 'border-blue-500 text-blue-600'
                  : comparisonList.length < 2
                  ? 'border-transparent text-gray-300 cursor-not-allowed'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Compare Funds
            </button>
            <button
              onClick={() => handleTabChange(2)}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                activeTab === 2
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              Tax Benefits Guide
            </button>
          </nav>
        </div>
      </div>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        {/* Tab 1: All Funds */}
        {activeTab === 0 && (
          <>
            {/* Stats Bar */}
            <div className={`mb-8 rounded-xl ${taxSaverColorScheme.bg} border ${taxSaverColorScheme.border} p-6`}>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="text-center">
                  <div className="text-3xl font-bold text-gray-900">{funds.length}</div>
                  <div className="text-sm text-gray-600">ELSS Funds</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl font-bold text-green-600">3</div>
                  <div className="text-sm text-gray-600">Years Lock-in</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl font-bold text-purple-600">₹1.5L</div>
                  <div className="text-sm text-gray-600">Max Tax Benefit</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl font-bold text-blue-600">80C</div>
                  <div className="text-sm text-gray-600">Section</div>
                </div>
              </div>
            </div>

            {/* Funds Table */}
            <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
              <div className="px-6 py-4 border-b border-gray-200">
                {funds.length === 0 && (
                  <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p className="text-yellow-800 text-sm">
                      No tax saver funds available at the moment.
                    </p>
                  </div>
                )}
              </div>

              {/* Desktop Table */}
              {funds.length > 0 && (
                <>
                  <div className="hidden lg:block overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            AMC (Fund House)
                          </th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Scheme Name
                          </th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Investment Rationale
                          </th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Options
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {funds.map((fund) => (
                          <tr key={fund.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex items-center">
                                <div className="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                  <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                  </svg>
                                </div>
                                <div className="ml-4">
                                  <div className="text-sm font-medium text-gray-900">{fund.amc}</div>
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm font-semibold text-gray-900">{fund.scheme}</div>
                              <div className="flex items-center mt-2 space-x-2">
                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${taxSaverColorScheme.badge}`}>
                                  ELSS
                                </span>
                                {fund.fund_cat && (
                                  <span className="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                    {fund.fund_cat}
                                  </span>
                                )}
                                {fund.sip_allowed === 'Y' && (
                                  <span className="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    SIP Available
                                  </span>
                                )}
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="text-sm text-gray-700 max-w-md">{fund.rational}</div>
                              <div className="flex items-center mt-3 space-x-2">
                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                  <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                  </svg>
                                  Lock-in: {fund.lock_in_period || '3 Years'}
                                </span>
                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                  <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>
                                  Section 80C
                                </span>
                              </div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <div className="flex flex-col space-y-2">
                                <button
                                  onClick={() => handleSIPClick(fund)}
                                  className="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors flex items-center justify-center"
                                >
                                  <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>
                                  Start SIP
                                </button>
                                <button
                                  onClick={() => handleBuyClick(fund)}
                                  className={`px-4 py-2 text-sm font-medium rounded-lg ${taxSaverColorScheme.button} text-white flex items-center justify-center`}
                                >
                                  <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                  </svg>
                                  Buy Now
                                </button>
                                <div className="flex space-x-2 pt-2">
                                  <button
                                    onClick={() => handleViewDetails(fund)}
                                    className="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center"
                                  >
                                    <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Details
                                  </button>
                                  <button
                                    onClick={() => handleAddToComparison(fund)}
                                    disabled={comparisonList.some(item => item.id === fund.id)}
                                    className="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                  >
                                    <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                    </svg>
                                    Compare
                                  </button>
                                </div>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Mobile Cards */}
                  <div className="lg:hidden">
                    {funds.map((fund) => (
                      <div key={fund.id} className="border-b border-gray-200 last:border-b-0">
                        <div className="p-6">
                          <div className="flex justify-between items-start mb-4">
                            <div>
                              <div className="text-sm text-gray-500 mb-1">{fund.amc}</div>
                              <h3 className="text-lg font-semibold text-gray-900">{fund.scheme}</h3>
                              <div className="flex items-center mt-2 space-x-2">
                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${taxSaverColorScheme.badge}`}>
                                  ELSS
                                </span>
                                <span className="text-xs text-gray-500">80C</span>
                                {fund.sip_allowed === 'Y' && (
                                  <span className="text-xs text-green-600">SIP Available</span>
                                )}
                              </div>
                            </div>
                          </div>

                          <div className="space-y-4">
                            <div>
                              <h4 className="text-sm font-medium text-gray-700 mb-1">Investment Rationale</h4>
                              <p className="text-sm text-gray-600">{fund.rational}</p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Lock-in: {fund.lock_in_period || '3 Years'}
                              </span>
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Min Investment: {fund.minimumInvestment}
                              </span>
                            </div>

                            <div className="flex space-x-2">
                              <button
                                onClick={() => handleSIPClick(fund)}
                                className="flex-1 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                              >
                                Start SIP
                              </button>
                              <button
                                onClick={() => handleBuyClick(fund)}
                                className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg ${taxSaverColorScheme.button} text-white`}
                              >
                                Buy Now
                              </button>
                            </div>

                            <div className="flex space-x-2 pt-2">
                              <button
                                onClick={() => handleViewDetails(fund)}
                                className="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                              >
                                Details
                              </button>
                              <button
                                onClick={() => handleAddToComparison(fund)}
                                disabled={comparisonList.some(item => item.id === fund.id)}
                                className="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                              >
                                Compare
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>

            {/* Confirm Your Investment Panel - Only BUY button */}
            {showInvestmentPanel && selectedFund && (
              <div ref={investmentPanelRef} className="bg-white rounded-xl shadow-lg p-8 mb-8 border-2 border-blue-200">
                <h2 className="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                  <svg className="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Confirm Your Investment
                </h2>

                {/* Selected Investment Details */}
                <div className="bg-gray-50 rounded-lg p-6 mb-8">
                  <h3 className="font-bold text-gray-800 mb-4">Investment Details:</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                    <div className="bg-white p-4 rounded-lg border">
                      <h4 className="text-sm font-medium text-gray-500 mb-2">Fund House</h4>
                      <p className="font-bold text-gray-900 break-words text-sm">{selectedFund.amc}</p>
                    </div>

                    <div className="bg-white p-4 rounded-lg border">
                      <h4 className="text-sm font-medium text-gray-500 mb-2">Fund Category</h4>
                      <p className="font-bold text-gray-900 break-words text-sm">{selectedFund.fundType || 'ELSS'}</p>
                      {/* <p className="text-sm text-gray-600 mt-2">Type: Tax Saver Fund</p> */}
                    </div>

                    <div className="bg-white p-4 rounded-lg border">
                      <h4 className="text-sm font-medium text-gray-500 mb-2">Scheme</h4>
                      <p className="font-bold text-gray-900 break-words text-sm">{selectedFund.scheme_name}</p>
                    </div>

                    <div className="bg-white p-4 rounded-lg border">
                      <h4 className="text-sm font-medium text-gray-500 mb-2">Risk & Lock-in</h4>
                      <p className="font-bold text-gray-900">{selectedFund.riskLevel}</p>
                      <p className="text-sm text-gray-600 mt-2">Lock-in: {selectedFund.lock_in_period || '3 Years'}</p>
                    </div>
                  </div>
                </div>

                {/* Investment Amount Input */}
                <div className="mb-8">
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Enter Investment Amount (₹)
                  </label>
                  <div className="flex items-center">
                    <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                      ₹
                    </span>
                    <input
                      type="number"
                      value={investmentAmount}
                      onChange={(e) => setInvestmentAmount(e.target.value)}
                      placeholder="Enter amount"
                      min={minInvestmentAmount}
                      step="100"
                      className="w-full px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                  </div>
                  <p className="text-sm text-gray-500 mt-2">Minimum investment: ₹{minInvestmentAmount}</p>
                </div>

                {/* BUY Button Only */}
                <div className="flex gap-6">
                  <button
                    onClick={handleConfirmBuy}
                    disabled={!investmentAmount || parseFloat(investmentAmount) < minInvestmentAmount || buyLoading}
                    className={`flex-1 py-4 font-bold rounded-lg transition-colors flex flex-col items-center justify-center ${
                      investmentAmount && parseFloat(investmentAmount) >= minInvestmentAmount && !buyLoading
                        ? 'bg-blue-600 hover:bg-blue-700 text-white'
                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    }`}
                  >
                    {buyLoading ? (
                      <>
                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white mx-auto mb-2"></div>
                        <span>Processing...</span>
                      </>
                    ) : (
                      <>
                        <span className="text-xl">BUY NOW</span>
                        <span className="text-sm mt-1">One-time Investment</span>
                      </>
                    )}
                  </button>
                </div>

                <div className="mt-6 text-center">
                  <button
                    onClick={() => { setShowInvestmentPanel(false); setSelectedFund(null); setInvestmentAmount(''); }}
                    className="text-gray-600 hover:text-gray-800 font-medium"
                  >
                    ← Back to selection
                  </button>
                </div>
              </div>
            )}
          </>
        )}

        {/* Tab 2: Compare Funds */}
        {activeTab === 1 && comparisonList.length >= 2 && (
          <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Compare ELSS Funds</h2>
              <p className="text-sm text-gray-600 mt-1">
                Compare up to 3 tax saver funds side by side
              </p>
            </div>
            
            <div className="p-6">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Feature
                      </th>
                      {comparisonList.map((fund, index) => (
                        <th key={index} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          <div className="text-center">
                            <div className="font-semibold text-gray-900">{fund.scheme}</div>
                            <div className="text-xs text-gray-500 mt-1">{fund.amc}</div>
                          </div>
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">AMC</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.amc}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Investment Rationale</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.rational}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Lock-in Period</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.lock_in_period || '3 Years'}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Minimum Investment</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.minimumInvestment}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">SIP Available</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.sip_allowed === 'Y' ? 'Yes' : 'No'}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Exit Load</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          {fund.exitLoad}
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Tax Benefit</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-sm text-gray-600 text-center">
                          Up to ₹1.5 Lakh under Section 80C
                        </td>
                      ))}
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">Actions</td>
                      {comparisonList.map((fund, index) => (
                        <td key={index} className="px-4 py-3 text-center">
                          <div className="flex flex-col space-y-2">
                            <button
                              onClick={() => handleSIPClick(fund)}
                              className="px-3 py-1.5 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                            >
                              SIP
                            </button>
                            <button
                              onClick={() => handleBuyClick(fund)}
                              className={`px-3 py-1.5 text-sm font-medium rounded-lg ${taxSaverColorScheme.button} text-white`}
                            >
                              Buy
                            </button>
                          </div>
                        </td>
                      ))}
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div className="mt-6 text-center">
                <button
                  onClick={() => setComparisonList([])}
                  className="px-6 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 transition-colors"
                >
                  Clear All Comparisons
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Tab 3: Tax Benefits Guide */}
        {activeTab === 2 && (
          <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
            <h3 className="text-2xl font-bold text-gray-900 mb-6">ELSS Tax Benefits Guide</h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div className={`p-6 rounded-xl ${taxSaverColorScheme.success} border border-green-200`}>
                <div className="flex items-center mb-4">
                  <div className="w-10 h-10 rounded-full bg-green-200 flex items-center justify-center mr-3">
                    <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900">Tax Deduction under Section 80C</h4>
                    <p className="text-sm text-gray-600">Save tax while investing</p>
                  </div>
                </div>
                <ul className="space-y-3">
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>Deduction Limit:</strong> Up to ₹1.5 lakh per financial year</span>
                  </li>
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>Investment Amount:</strong> Any amount up to ₹1.5 lakh qualifies</span>
                  </li>
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>Tax Savings:</strong> Save tax at your applicable income tax slab rate</span>
                  </li>
                </ul>
              </div>
              
              <div className={`p-6 rounded-xl ${taxSaverColorScheme.bg} border ${taxSaverColorScheme.border}`}>
                <div className="flex items-center mb-4">
                  <div className="w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center mr-3">
                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900">Lock-in Period & Benefits</h4>
                    <p className="text-sm text-gray-600">Shortest among 80C options</p>
                  </div>
                </div>
                <ul className="space-y-3">
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>Mandatory Lock-in:</strong> 3 years from date of investment</span>
                  </li>
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>LTCG Benefit:</strong> Long-term capital gains up to ₹1 lakh are tax-free</span>
                  </li>
                  <li className="flex items-start">
                    <svg className="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="text-gray-700"><strong>SIP Benefit:</strong> Each SIP installment has separate 3-year lock-in</span>
                  </li>
                </ul>
              </div>
            </div>
            
            {/* Comparison Table */}
            <div className="bg-gray-50 rounded-xl p-6">
              <h4 className="text-lg font-semibold text-gray-900 mb-4">Why ELSS is the Best 80C Option</h4>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Option
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Lock-in Period
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Returns Potential
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Risk Level
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Liquidity
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200 bg-white">
                    <tr className={`${taxSaverColorScheme.bg}`}>
                      <td className="px-4 py-3 text-sm font-bold text-gray-900">ELSS Funds</td>
                      <td className="px-4 py-3 text-sm text-gray-600">3 Years</td>
                      <td className="px-4 py-3 text-sm text-green-600 font-medium">High (Equity linked)</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Medium to High</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Good after lock-in</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm text-gray-900">PPF</td>
                      <td className="px-4 py-3 text-sm text-gray-600">15 Years</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Moderate (Govt. fixed)</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Low</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Poor</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm text-gray-900">NSC</td>
                      <td className="px-4 py-3 text-sm text-gray-600">5 Years</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Moderate (Fixed)</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Low</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Poor</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-3 text-sm text-gray-900">Tax-saving FD</td>
                      <td className="px-4 py-3 text-sm text-gray-600">5 Years</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Low (Fixed)</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Low</td>
                      <td className="px-4 py-3 text-sm text-gray-600">Poor</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </main>

      {/* Fund Details Modal */}
      {dialogOpen && selectedFund && (
        <div className="fixed inset-0 z-50 overflow-y-auto">
          <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div className="fixed inset-0 transition-opacity" aria-hidden="true">
              <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            
            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
              <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div className="sm:flex sm:items-start">
                  <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 className="text-lg leading-6 font-bold text-gray-900 mb-2">
                      {selectedFund.scheme || selectedFund.scheme_name || selectedFund.fund_details?.scheme_name}
                    </h3>
                    <p className="text-sm text-gray-500 mb-4">{selectedFund.amc}</p>
                    
                    <div className="mt-4 space-y-4">
                      <div>
                        <h4 className="text-sm font-medium text-gray-900 mb-2">Fund Details</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                          <div>
                            <span className="text-gray-500">Scheme Code:</span>
                            <span className="ml-2 font-medium">{selectedFund.product_id || selectedFund.scheme_code || 'N/A'}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">AMC Code:</span>
                            <span className="ml-2 font-medium">{selectedFund.amc_code || 'N/A'}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">Scheme Type:</span>
                            <span className="ml-2 font-medium">{selectedFund.scheme_type || 'Open Ended'}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">Fund Category:</span>
                            <span className="ml-2 font-medium">{selectedFund.fund_cat || 'ELSS'}</span>
                          </div>
                          {selectedFund.isin && (
                            <div className="col-span-2">
                              <span className="text-gray-500">ISIN:</span>
                              <span className="ml-2 font-medium">{selectedFund.isin}</span>
                            </div>
                          )}
                          {selectedFund.face_value && (
                            <div>
                              <span className="text-gray-500">Face Value:</span>
                              <span className="ml-2 font-medium">₹{selectedFund.face_value}</span>
                            </div>
                          )}
                          {selectedFund.scheme_start_date && (
                            <div>
                              <span className="text-gray-500">Scheme Start Date:</span>
                              <span className="ml-2 font-medium">{selectedFund.scheme_start_date}</span>
                            </div>
                          )}
                        </div>
                      </div>
                      
                      <div>
                        <h4 className="text-sm font-medium text-gray-900 mb-2">Investment Details</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                          <div>
                            <span className="text-gray-500">Minimum Investment:</span>
                            <span className="ml-2 font-medium">{selectedFund.minimumInvestment}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">SIP Available:</span>
                            <span className="ml-2 font-medium">{selectedFund.sip_allowed === 'Y' ? 'Yes' : 'No'}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">Lock-in Period:</span>
                            <span className="ml-2 font-medium">{selectedFund.lock_in_period || '3 Years'}</span>
                          </div>
                          <div>
                            <span className="text-gray-500">Exit Load:</span>
                            <span className="ml-2 font-medium">{selectedFund.exitLoad}</span>
                          </div>
                        </div>
                      </div>
                      
                      <div>
                        <h4 className="text-sm font-medium text-gray-900 mb-2">Tax Benefits</h4>
                        <ul className="list-disc pl-5 text-sm text-gray-600 space-y-1">
                          <li>Deduction under Section 80C up to ₹1.5 lakh</li>
                          <li>Lock-in period: {selectedFund.lock_in_period || '3 years'}</li>
                          <li>Long-term capital gains benefits</li>
                          <li>SIP option {selectedFund.sip_allowed === 'Y' ? 'available' : 'may be available'}</li>
                        </ul>
                      </div>
                      
                      {selectedFund.rational && (
                        <div>
                          <h4 className="text-sm font-medium text-gray-900 mb-2">Investment Rationale</h4>
                          <p className="text-sm text-gray-600">{selectedFund.rational}</p>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
              <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2 w-full">
                  <button
                    onClick={() => {
                      handleBuyClick(selectedFund);
                      setDialogOpen(false);
                    }}
                    className={`w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg ${taxSaverColorScheme.button} text-white`}
                  >
                    Buy Now
                  </button>
                  {selectedFund.sip_allowed === 'Y' && (
                    <button
                      onClick={() => {
                        handleSIPClick(selectedFund);
                        setDialogOpen(false);
                      }}
                      className="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                    >
                      Start SIP
                    </button>
                  )}
                  <button
                    onClick={() => {
                      handleAddToComparison(selectedFund);
                      setDialogOpen(false);
                    }}
                    disabled={comparisonList.some(item => item.id === selectedFund.id)}
                    className="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Add to Compare
                  </button>
                  <button
                    onClick={() => setDialogOpen(false)}
                    className="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}