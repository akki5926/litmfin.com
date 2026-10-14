// app/recommended-funds/page.js
'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

export default function RecommendedFundsPage() {
  const router = useRouter();
  const [userRiskProfile, setUserRiskProfile] = useState(null);
  const [recommendedFunds, setRecommendedFunds] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [riskProfileDetails, setRiskProfileDetails] = useState(null);
  const [selectedFund, setSelectedFund] = useState(null);
  const [showInvestmentPanel, setShowInvestmentPanel] = useState(false);
  const [investmentAmount, setInvestmentAmount] = useState('');
  const [buyLoading, setBuyLoading] = useState(false);
  const [minInvestmentAmount, setMinInvestmentAmount] = useState(100);
  const investmentPanelRef = useRef(null);

  // Fetch user's risk profile and recommended funds on component mount
  useEffect(() => {
    const fetchRiskProfileAndFunds = async () => {
      try {
        setLoading(true);
        setError(null);

        // Get user ID from localStorage
        const userId = localStorage.getItem('site_user_id');
        
        if (!userId) {
          setError('Please login to view recommended funds');
          setLoading(false);
          return;
        }

        // Fetch risk profile from API
        const response = await fetch(`${API_BASE_URL}/risk_profile_api.php?userId=${userId}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          credentials: 'include',
        });

        if (!response.ok) {
          throw new Error('Failed to fetch risk profile');
        }

        const result = await response.json();
        
        if (result.success && result.data.hasRiskProfile) {
          const riskProfile = result.data.riskProfile;
          setUserRiskProfile(riskProfile);
          
          // Fetch recommended funds based on risk profile ID
          await fetchRecommendedFunds(riskProfile.riskId);
        } else {
          setError('Please complete your risk profile assessment first');
          // Redirect to risk profile page after 3 seconds
          setTimeout(() => {
            router.push('/dashboard/risk-profile');
          }, 3000);
        }
      } catch (error) {
        console.error('Error fetching risk profile:', error);
        setError('Unable to load recommendations. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    // Function to fetch recommended funds from API
    const fetchRecommendedFunds = async (riskId) => {
      try {
        // First, get the risk code from the riskId mapping
        const riskCodeMap = {
          'high': '1',
          'medium': '2', 
          'low': '3',
          'conservative': '4'
        };
        
        const categoryId = riskCodeMap[riskId] || riskId;
        
        // Fetch recommended funds for this category
        const fundsResponse = await fetch(
          `${API_BASE_URL}/recommended_funds_api.php?action=get_by_category&category_id=${categoryId}`,
          {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            credentials: 'include',
          }
        );

        if (!fundsResponse.ok) {
          throw new Error('Failed to fetch recommended funds');
        }

        const fundsResult = await fundsResponse.json();
        
        // console.log('Raw API Response:', fundsResult);
        
        if (fundsResult.success && fundsResult.data) {
          // Transform API data to match existing structure
          const transformedFunds = fundsResult.data.map(fund => {
            // Get scheme name - clean it to remove any codes
            let schemeName = fund.scheme_name || fund.fund_name || fund.product_id || '';
            // Remove any text that looks like a code (uppercase with underscores, or short codes)
            schemeName = schemeName.replace(/_\w+$/, '').replace(/\s+-\s+\w+$/, '').trim();
            
            // IMPORTANT: Use scheme_code or product_id for actual transactions
            const schemeCode = fund.scheme_code || fund.product_id || '';
            
            return {
              id: fund.recmd_id,
              amc: fund.amc_name || fund.amc,
              amc_code: fund.amc,
              scheme_name: schemeName,
              scheme_code: schemeCode, // This is the actual scheme code for transactions
              fundType: fund.invest_type || 'Equity',
              allocation: parseFloat(fund.allocation) || 0,
              rational: fund.recmd_rational || 'Recommended based on your risk profile',
              options: 'Direct-Growth',
              riskLevel: getRiskLevelLabel(fund.invest_type || 'equity'),
              category: fund.fund_cat || 'Equity',
              minimumInvestment: '₹5,000',
              exitLoad: fund.category_id === '1' || fund.category_id === '2' ? '1% if redeemed before 1 year' : 'Nil',
              historicalReturn: getHistoricalReturn(fund.category_id),
              color: getColorClass(fund.category_id),
              amcWebsite: getAmcWebsite(fund.amc),
              product_id: fund.product_id
            };
          });
          
          setRecommendedFunds(transformedFunds);
        }

        await fetchRiskProfileDetails(categoryId);

      } catch (error) {
        console.error('Error fetching recommended funds:', error);
        setRecommendedFunds([]);
      }
    };

    // Function to fetch risk profile details
    const fetchRiskProfileDetails = async (categoryId) => {
      try {
        const response = await fetch(
          `${API_BASE_URL}/recommended_funds_api.php?action=get_risk_profile&risk_code=${categoryId}`,
          {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            credentials: 'include',
          }
        );

        if (response.ok) {
          const result = await response.json();
          if (result.success && result.data) {
            setRiskProfileDetails(result.data);
          }
        }
      } catch (error) {
        console.error('Error fetching risk profile details:', error);
      }
    };

    // Helper functions
    const getRiskLevelLabel = (investType) => {
      switch (investType) {
        case 'equity': return 'High';
        case 'debt': return 'Low';
        case 'hybrid': return 'Moderate';
        default: return 'Moderate';
      }
    };

    const getHistoricalReturn = (categoryId) => {
      switch (categoryId) {
        case '1': return '18-28%';
        case '2': return '12-18%';
        case '3': return '6-12%';
        case '4': return '4-8%';
        default: return '10-15%';
      }
    };

    const getColorClass = (categoryId) => {
      switch (categoryId) {
        case '1': return 'bg-red-50 border-red-200';
        case '2': return 'bg-yellow-50 border-yellow-200';
        case '3': return 'bg-green-50 border-green-200';
        case '4': return 'bg-blue-50 border-blue-200';
        default: return 'bg-gray-50 border-gray-200';
      }
    };

    const getAmcWebsite = (amcCode) => {
      const websites = {
        'MOSL': 'https://www.motilaloswalmf.com',
        'TATA': 'https://www.tatamutualfund.com',
        'SUNDARAM': 'https://www.sundarammutual.com',
        'KOTAK': 'https://www.kotakmutual.com',
        'MIRAE': 'https://www.miraeassetmf.co.in',
        'ABSL': 'https://mutualfund.adityabirlacapital.com',
        'HDFC': 'https://www.hdfcfund.com',
        'SBI': 'https://www.sbimf.com',
        'ICICI': 'https://www.icicipruamc.com',
        'NIPPON': 'https://www.nipponindiamf.com',
        'AXIS': 'https://www.axismf.com',
        'UTI': 'https://www.utimf.com'
      };
      return websites[amcCode] || '#';
    };

    fetchRiskProfileAndFunds();
  }, [router]);

  // Get risk profile color scheme
  const getRiskColorScheme = (riskId) => {
    switch (riskId) {
      case 'high':
        return {
          bg: 'bg-red-50',
          border: 'border-red-200',
          text: 'bg-red-600',
          badge: 'bg-red-100 text-red-800',
          button: 'bg-red-600 hover:bg-red-700'
        };
      case 'medium':
        return {
          bg: 'bg-yellow-50',
          border: 'border-yellow-200',
          text: 'bg-yellow-600',
          badge: 'bg-yellow-100 text-yellow-800',
          button: 'bg-yellow-600 hover:bg-yellow-700'
        };
      case 'low':
        return {
          bg: 'bg-green-50',
          border: 'border-green-200',
          text: 'bg-green-600',
          badge: 'bg-green-100 text-green-800',
          button: 'bg-green-600 hover:bg-green-700'
        };
      case 'conservative':
        return {
          bg: 'bg-blue-50',
          border: 'border-blue-200',
          text: 'bg-blue-600',
          badge: 'bg-blue-100 text-blue-800',
          button: 'bg-blue-600 hover:bg-blue-700'
        };
      default:
        return {
          bg: 'bg-gray-50',
          border: 'border-gray-200',
          text: 'bg-gray-600',
          badge: 'bg-gray-100 text-gray-800',
          button: 'bg-gray-600 hover:bg-gray-700'
        };
    }
  };

  // Get bar color based on allocation percentage
  const getBarColorByPercentage = (percentage) => {
    if (percentage <= 40) return 'bg-green-500';
    if (percentage <= 70) return 'bg-yellow-500';
    return 'bg-red-500';
  };

  // Handle SIP button click
  const handleSIPClick = (fund) => {
    const investmentData = {
      fundHouse: {
        long_name: fund.amc,
        amc_code: fund.amc_code
      },
      scheme: {
        product_long_name: fund.scheme_name,
        product_code: fund.scheme_code  // Use scheme_code, not name
      },
      fundCategory: {
        category_name: fund.category,
        details: fund.fundType
      },
      schemeCode: fund.scheme_code,  // Use scheme_code
      fundHouseCode: fund.amc_code,
      monthlyAmount: '',
      initialAmount: '0'
    };
    
    localStorage.setItem('investment_data', JSON.stringify(investmentData));
    const referrer = 'recommended-funds';
    router.push(`/invest/sip?ref=${referrer}`);
  };

  // Handle BUY button click
  const handleBuyClick = (fund) => {
    // console.log('Selected fund for buy:', fund); // Debug log
    setSelectedFund(fund);
    setInvestmentAmount('');
    setShowInvestmentPanel(true);

    if (fund.scheme_code) {
      fetchMinInvestment(fund.scheme_code);
    }

    setTimeout(() => {
      investmentPanelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
  };

  const fetchMinInvestment = async (schemeCode) => {
    try {
      const token = localStorage.getItem('site_token');
      const response = await fetch(
        `${API_BASE_URL}/master_data.php?action=scheme_master&product_code=${encodeURIComponent(schemeCode)}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        }
      );
      if (response.ok) {
        const data = await response.json();
        if (data.success && data.data) {
          const schemeData = Array.isArray(data.data) ? data.data[0] : data.data;
          const additionalPurchaseMin = parseFloat(schemeData.additional_purchase_min_amount) || 100;
          const minAmount = Math.max(additionalPurchaseMin, 100);
          setMinInvestmentAmount(minAmount);
        }
      }
    } catch (error) {
      console.error('Error fetching scheme master data:', error);
    }
  };

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
      
      // console.log('Buy payload:', {
      //   fund_house_code: selectedFund.amc_code,
      //   fund_house_name: selectedFund.amc || 'N/A',
      //   scheme_code: selectedFund.scheme_code,  // Make sure this is the correct scheme code
      //   scheme_name: selectedFund.scheme_name || 'N/A',
      //   amount: parseFloat(investmentAmount),
      //   investment_type: 'buy'
      // });
      
      const payload = {
        fund_house_code: selectedFund.amc_code,
        fund_house_name: selectedFund.amc || 'N/A',
        scheme_code: selectedFund.scheme_code,
        scheme_name: selectedFund.scheme_name || 'N/A',
        amount: parseFloat(investmentAmount),
        investment_type: 'buy'
      };
      
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

  // Loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading your fund recommendations...</p>
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
          <h2 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Recommendations</h2>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => router.push('/dashboard/risk-profile')}
            className="w-full py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Go to Risk Profile
          </button>
        </div>
      </div>
    );
  }

  const colorScheme = userRiskProfile ? getRiskColorScheme(userRiskProfile.riskId) : getRiskColorScheme('medium');

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">
              <TypedText text="Recommended Funds" className="text-gray-900" />
            </h1>
            <p className="mt-2 text-gray-600">
              Personalized fund recommendations based on your risk profile
            </p>
          </div>
        </div>
      </div>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Portfolio Summary */}
        <div className={`mb-8 rounded-xl ${colorScheme.bg} border ${colorScheme.border} p-6`}>
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-xl font-bold text-gray-900 mb-2">
                Your Personalized Portfolio
              </h2>
              {riskProfileDetails && (
                <p className="text-gray-700">
                  <span className="block text-sm text-gray-600">
                    {riskProfileDetails.portfolio_description || 'Balanced portfolio for optimal growth'}
                  </span>
                </p>
              )}
            </div>
            
            <div className="mt-4 lg:mt-0">
              <div className="flex items-center space-x-4">
                <div className="text-center">
                  <div className="text-3xl font-bold text-gray-900">
                    {recommendedFunds.reduce((sum, fund) => sum + (fund.allocation || 0), 0)}%
                  </div>
                  <div className="text-sm text-gray-600">Total Allocation</div>
                </div>
                <div className="text-center">
                  <div className="text-3xl font-bold text-gray-900">{recommendedFunds.length}</div>
                  <div className="text-sm text-gray-600">Funds</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Funds Table */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
          {recommendedFunds.length === 0 && (
            <div className="p-6 bg-yellow-50 border border-yellow-200 rounded-lg m-4">
              <p className="text-yellow-800 text-sm">
                No recommended funds found for your risk profile. Please contact support.
              </p>
            </div>
          )}

          {/* Desktop Table - Only show if there are funds */}
          {recommendedFunds.length > 0 && (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Scheme
                      </th>
                      <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Fund Type
                      </th>
                      <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Allocation
                      </th>
                      <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Rational
                      </th>
                      <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {recommendedFunds.map((fund) => {
                      const allocationPercent = Math.min(100, Math.max(0, fund.allocation || 0));
                      const barColor = getBarColorByPercentage(allocationPercent);
                      return (
                        <tr key={fund.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4">
                            <div className="text-sm font-semibold text-gray-900">{fund.scheme_name}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{fund.fundType}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className="w-32 bg-gray-200 rounded-full h-2.5 mr-4 overflow-hidden">
                                <div 
                                  className={`h-2.5 rounded-full ${barColor}`}
                                  style={{ width: `${allocationPercent}%` }}
                                ></div>
                              </div>
                              <span className="font-bold text-gray-900">{allocationPercent}%</span>
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="text-sm text-gray-700 max-w-md">{fund.rational}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex flex-col space-y-2">
                              <button
                                onClick={() => handleSIPClick(fund)}
                                className="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                              >
                                Start SIP
                              </button>
                              <button
                                onClick={() => handleBuyClick(fund)}
                                className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${colorScheme.button} text-white`}
                              >
                                Buy Now
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {/* Mobile Cards */}
              <div className="lg:hidden">
                {recommendedFunds.map((fund) => {
                  const allocationPercent = Math.min(100, Math.max(0, fund.allocation || 0));
                  const barColor = getBarColorByPercentage(allocationPercent);
                  return (
                    <div key={fund.id} className="border-b border-gray-200 last:border-b-0">
                      <div className="p-6">
                        <div className="flex justify-between items-start mb-4">
                          <div>
                            <h3 className="text-lg font-semibold text-gray-900">{fund.scheme_name}</h3>
                            <div className="flex items-center mt-2 space-x-2">
                              <span className={`px-2 py-1 text-xs font-medium rounded-full ${colorScheme.badge}`}>
                                {fund.fundType}
                              </span>
                              <span className="text-xs text-gray-500">{fund.riskLevel}</span>
                            </div>
                          </div>
                          <div className="text-right">
                            <div className="text-2xl font-bold text-gray-900">{fund.allocation}%</div>
                            <div className="text-xs text-gray-500">Allocation</div>
                          </div>
                        </div>

                        <div className="mb-4">
                          <div className="flex items-center">
                            <div className="flex-1 bg-gray-200 rounded-full h-2.5 mr-4 overflow-hidden">
                              <div 
                                className={`h-2.5 rounded-full ${barColor}`}
                                style={{ width: `${allocationPercent}%` }}
                              ></div>
                            </div>
                            <span className="font-bold text-gray-900 text-sm">{allocationPercent}%</span>
                          </div>
                        </div>

                        <div className="space-y-4">
                          <div>
                            <h4 className="text-sm font-medium text-gray-700 mb-1">Rational</h4>
                            <p className="text-sm text-gray-600">{fund.rational}</p>
                          </div>

                          <div className="flex space-x-2 pt-2">
                            <button
                              onClick={() => handleSIPClick(fund)}
                              className="flex-1 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors"
                            >
                              Start SIP
                            </button>
                            <button
                              onClick={() => handleBuyClick(fund)}
                              className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors ${colorScheme.button} text-white`}
                            >
                              Buy
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>

        {/* Confirm Your Investment Panel - Simplified */}
        {showInvestmentPanel && selectedFund && (
          <div ref={investmentPanelRef} className="bg-white rounded-xl shadow-lg p-8 mb-8 border-2 border-blue-200">
            <h2 className="text-2xl font-bold text-gray-900 mb-6 flex items-center">
              <svg className="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Confirm Your Investment
            </h2>

            {/* Selected Investment Details - Simplified */}
            <div className="bg-gray-50 rounded-lg p-6 mb-8">
              <h3 className="font-bold text-gray-800 mb-4">Investment Details:</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Fund House</h4>
                  <p className="font-bold text-gray-900 break-words text-base">{selectedFund.amc}</p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Fund Category</h4>
                  <p className="font-bold text-gray-900 break-words text-base">{selectedFund.category}</p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Scheme Name</h4>
                  <p className="font-bold text-gray-900 break-words text-base">{selectedFund.scheme_name}</p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Allocation</h4>
                  <p className="font-bold text-gray-900">{selectedFund.allocation}%</p>
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

            {/* Investment Type Buttons */}
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
                    <span className="text-xl">BUY</span>
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
                ← Back to recommendations
              </button>
            </div>
          </div>
        )}

        {/* Investment Guidelines */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Investment Guidelines</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className="font-medium text-gray-800 mb-3">SIP Advantages</h4>
              <ul className="space-y-2">
                <li className="flex items-start">
                  <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span className="text-gray-700"><strong>Rupee Cost Averaging:</strong> Buy more units when prices are low</span>
                </li>
                <li className="flex items-start">
                  <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span className="text-gray-700"><strong>Discipline:</strong> Regular investing cultivates financial discipline</span>
                </li>
                <li className="flex items-start">
                  <svg className="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span className="text-gray-700"><strong>Power of Compounding:</strong> Small regular investments grow significantly over time</span>
                </li>
              </ul>
            </div>
            
            <div>
              <h4 className="font-medium text-gray-800 mb-3">Important Notes</h4>
              <div className={`p-4 rounded-lg ${colorScheme.bg}`}>
                <p className="text-sm text-gray-700">
                  These recommendations are specifically curated for your risk tolerance.
                  {riskProfileDetails?.capital_erosion_percentage && (
                    <span className="block mt-2">
                      Maximum capital erosion tolerance: <strong>{riskProfileDetails.capital_erosion_percentage}%</strong>
                    </span>
                  )}
                  Changing risk profile is not allowed once set.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row gap-4">
          <button
            onClick={() => router.push('/dashboard/home')}
            className="flex-1 py-3 bg-gray-200 text-gray-800 font-medium rounded-lg hover:bg-gray-300 transition-colors flex items-center justify-center"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Dashboard
          </button>
        </div>
      </main>
    </div>
  );
}