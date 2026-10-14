'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

export default function BuyPage() {
  const router = useRouter();

  const [investmentAmount, setInvestmentAmount] = useState('');
  const [investmentData, setInvestmentData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [requestSuccess, setRequestSuccess] = useState(false);
  const [transactionId, setTransactionId] = useState('');
  const [minInvestmentAmount, setMinInvestmentAmount] = useState(100);

  // Folio state
  const [folios, setFolios] = useState([]);
  const [selectedFolio, setSelectedFolio] = useState('');
  const [isLoadingFolios, setIsLoadingFolios] = useState(false);

  useEffect(() => {
    const storedData = localStorage.getItem('investment_data');
    if (storedData) {
      try {
        const parsedData = JSON.parse(storedData);
        setInvestmentData(parsedData);

        if (parsedData.amount) {
          setInvestmentAmount(parsedData.amount.toString());
        }

        const schemeCodeToUse = parsedData.scheme?.product_code || parsedData.scheme?.fund_code || parsedData.scheme?.scheme_code || parsedData.scheme?.product_id;
        const clientCodeToUse = parsedData.scheme?.client_code || parsedData.client_code || '';
        console.log('Using scheme code:', schemeCodeToUse, 'for investment type:', parsedData.type);

        if (schemeCodeToUse) {
          fetchSchemeMasterData(schemeCodeToUse, parsedData.type);
          fetchFolios(schemeCodeToUse, clientCodeToUse);
        } else {
          console.warn('No scheme code found in investment data:', parsedData.scheme);
        }
      } catch (error) {
        console.error('Error parsing investment data:', error);
        alert('Invalid investment data. Please start over.');
        router.push('/invest');
      }
    } else {
      alert('No investment data found. Please select a fund first.');
      router.push('/invest');
    }
  }, [router]);

  const fetchSchemeMasterData = async (schemeCode, investmentType) => {
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
          console.log('Scheme master data:', data.data);
          const schemeData = Array.isArray(data.data) ? data.data[0] : data.data;
          const additionalPurchaseMin = parseFloat(schemeData.additional_purchase_min_amount) || 100;
          const minAmount = Math.max(additionalPurchaseMin, 100);
          console.log('Setting min amount to:', minAmount);
          setMinInvestmentAmount(minAmount);
        } else {
          console.warn('Scheme master data returned false or no data:', data);
        }
      }
    } catch (error) {
      console.error('Error fetching scheme master data:', error);
    }
  };

  const fetchFolios = async (schemeCode, clientCode = '') => {
    try {
      setIsLoadingFolios(true);
      const token = localStorage.getItem('site_token');
      let url = `${API_BASE_URL}/invest_api.php?action=get_folios&scheme_code=${encodeURIComponent(schemeCode)}`;
      if (clientCode) {
        url += `&client_code=${encodeURIComponent(clientCode)}`;
      }
      const response = await fetch(url, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.success && data.data && data.data.folios) {
        const fetchedFolios = data.data.folios;
        setFolios(fetchedFolios);
        if (fetchedFolios.length === 1) {
          setSelectedFolio(fetchedFolios[0]);
        } else if (fetchedFolios.length > 1) {
          // If the investmentData had a folio_number (e.g. from Portfolio), select it
          const storedData = localStorage.getItem('investment_data');
          if (storedData) {
            const parsedData = JSON.parse(storedData);
            if (parsedData.scheme && parsedData.scheme.folio_number && fetchedFolios.includes(parsedData.scheme.folio_number)) {
              setSelectedFolio(parsedData.scheme.folio_number);
              setIsLoadingFolios(false);
              return;
            }
          }
          // Default to new or first
          setSelectedFolio('new');
        } else {
          setSelectedFolio('new');
        }
      } else {
        setFolios([]);
        setSelectedFolio('new');
      }
    } catch (error) {
      console.error('Error fetching folios:', error);
      setFolios([]);
      setSelectedFolio('new');
    } finally {
      setIsLoadingFolios(false);
    }
  };

  const handleInputChange = (e) => {
    const { value } = e.target;
    if (value === '' || /^\d*\.?\d*$/.test(value)) {
      setInvestmentAmount(value);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!investmentAmount || parseFloat(investmentAmount) <= 0) {
      alert('Please enter a valid investment amount');
      return;
    }

    if (parseFloat(investmentAmount) < minInvestmentAmount) {
      alert(`Minimum investment amount is ₹${minInvestmentAmount}`);
      return;
    }

    try {
      setLoading(true);
      const token = localStorage.getItem('site_token');

      const payload = {
        fund_house_code: investmentData?.fundHouse?.amc_code || investmentData?.fundHouse?.code || investmentData?.scheme?.fund_house_code,
        fund_house_name: investmentData?.fundHouse?.long_name || investmentData?.fundHouse?.details || investmentData?.fundHouse?.label || investmentData?.scheme?.fund_house || 'N/A',
        scheme_code: investmentData?.scheme?.product_code || investmentData?.scheme?.fund_code || investmentData?.scheme?.scheme_code || investmentData?.scheme?.product_id,
        scheme_name: investmentData?.scheme?.product_long_name || investmentData?.scheme?.label || investmentData?.scheme?.product_name || investmentData?.scheme?.scheme_name || 'N/A',
        amount: parseFloat(investmentAmount),
        investment_type: investmentData?.type === 'additional' ? 'additional' : 'buy',
        folio_no: selectedFolio === 'new' ? '' : selectedFolio,
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
        setTransactionId(data.data?.order_id || data.data?.transaction_id || 'BUY-' + Date.now());
        setRequestSuccess(true);
        localStorage.removeItem('investment_data');
      } else {
        alert(`Purchase request failed: ${data.message}`);
      }
    } catch (error) {
      console.error('Error submitting buy request:', error);
      alert(`Error processing purchase: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleBackToInvest = () => {
    router.push('/invest');
  };

  const handleNewPurchase = () => {
    setRequestSuccess(false);
    setInvestmentAmount('');
    router.push('/invest');
  };

  if (!investmentData) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading investment data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Back Button Row */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <button
            onClick={handleBackToInvest}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Investment
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="bg-white rounded-xl shadow-lg p-8 mb-8">
          {/* Page Header */}
          <div className="text-center mb-10">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Buy Mutual Fund</h1>
            <p className="text-gray-600">Make a one-time lumpsum investment in your selected fund</p>
          </div>

          {/* Investment Summary */}
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-10">
            <h2 className="text-xl font-bold text-blue-800 mb-4 flex items-center">
              <svg className="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Selected Investment
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 className="text-sm font-medium text-blue-600 mb-1">Scheme Name</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.scheme?.product_long_name || investmentData.scheme?.label || investmentData.scheme?.scheme_name || 'N/A'}</p>
              </div>
              <div>
                <h3 className="text-sm font-medium text-blue-600 mb-1">Fund Category</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.fundCategory?.category_name || investmentData.fundCategory?.details || investmentData.fundCategory?.label || investmentData.scheme?.asset_class || 'N/A'}</p>
              </div>
            </div>
          </div>

          {/* Success Message */}
          {requestSuccess && (
            <div className="bg-green-50 border border-green-200 rounded-xl p-6 mb-10">
              <div className="flex items-center mb-4">
                <svg className="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <h2 className="text-xl font-bold text-green-800">Order Placed Successfully!</h2>
                  <p className="text-green-700">Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.</p>
                </div>
              </div>

              <div className="bg-white rounded-lg p-4 mt-4">
                <h3 className="font-bold text-gray-800 mb-3">Order Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Transaction ID</p>
                    <p className="font-bold text-gray-900">{transactionId}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Investment Amount</p>
                    <p className="font-bold text-green-600">₹{parseFloat(investmentAmount).toLocaleString('en-IN')}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Scheme</p>
                    <p className="font-bold text-gray-900">{investmentData.scheme?.product_long_name || investmentData.scheme?.scheme_name || 'N/A'}</p>
                  </div>
                </div>
              </div>

              <div className="flex gap-4 mt-6">
                <button
                  onClick={handleNewPurchase}
                  className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                >
                  Make Another Investment
                </button>
                <button
                  onClick={() => window.print()}
                  className="px-6 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors"
                >
                  Print Receipt
                </button>
              </div>
            </div>
          )}

          {/* Buy Form */}
          {!requestSuccess && (
            <form onSubmit={handleSubmit}>
              <div className="space-y-8">
                {/* Investment Amount */}
                <div>
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Investment Amount <span className="text-red-500">*</span>
                  </label>
                  <div className="flex items-center max-w-md">
                    <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                      ₹
                    </span>
                    <input
                      type="text"
                      value={investmentAmount}
                      onChange={handleInputChange}
                      placeholder="Enter investment amount"
                      required
                      className="w-full px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                  </div>
                  <p className="text-sm text-gray-500 mt-2">
                    {investmentData?.type === 'additional' ? 'Additional investment minimum amount' : 'Minimum investment amount'}: ₹{minInvestmentAmount}
                  </p>

                  {/* Amount Input (Quick Select) */}
                  <div className="mt-4 mb-2">
                    <p className="text-sm text-gray-600 mb-2">Quick select:</p>
                    <div className="flex flex-wrap gap-2">
                      {[1000, 5000, 10000, 25000, 50000, 100000].map((amount) => (
                        <button
                          key={amount}
                          type="button"
                          onClick={() => setInvestmentAmount(amount.toString())}
                          className="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors text-sm"
                        >
                          ₹{amount.toLocaleString('en-IN')}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Folio Selection */}
                  <div className="mb-6 mt-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Select Folio
                    </label>
                    {isLoadingFolios ? (
                      <p className="text-sm text-blue-600">Loading your folios...</p>
                    ) : (
                      <select
                        value={selectedFolio}
                        onChange={(e) => setSelectedFolio(e.target.value)}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                      >
                        <option value="new">Create New Folio</option>
                        {folios.map((folio, idx) => (
                          <option key={idx} value={folio}>{folio}</option>
                        ))}
                      </select>
                    )}
                  </div>
                </div>

                {/* Purchase Summary */}
                <div className="bg-gray-50 border border-gray-200 rounded-xl p-6">
                  <h3 className="font-bold text-gray-800 mb-4 flex items-center">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Purchase Summary
                  </h3>
                  <div className="space-y-3">
                    <div className="flex justify-between border-t pt-3">
                      <span className="text-gray-600">Investment Amount:</span>
                      <span className="font-bold text-green-600">
                        {investmentAmount ? `₹${parseFloat(investmentAmount).toLocaleString('en-IN')}` : '-'}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-between pt-8 border-t border-gray-200">
                  <button
                    type="button"
                    onClick={handleBackToInvest}
                    className="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors flex items-center"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                  </button>

                  <button
                    type="submit"
                    disabled={loading || !investmentAmount || parseFloat(investmentAmount) < minInvestmentAmount}
                    className={`px-8 py-3 font-medium rounded-lg transition-colors flex items-center ${!loading && investmentAmount && parseFloat(investmentAmount) >= minInvestmentAmount
                      ? 'bg-green-600 hover:bg-green-700 text-white'
                      : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      }`}
                  >
                    {loading ? (
                      <>
                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                        Processing...
                      </>
                    ) : (
                      <>
                        Confirm Purchase
                        <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                      </>
                    )}
                  </button>
                </div>
              </div>
            </form>
          )}

          {/* Important Information */}
          <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mt-10">
            <h3 className="font-bold text-gray-900 mb-4 flex items-center">
              <svg className="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              Important Information
            </h3>
            <ul className="space-y-2 text-sm text-gray-700">
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>This is a one-time lumpsum investment in the selected mutual fund scheme.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>The amount will be debited from your registered bank account.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Units will be allotted based on the applicable NAV on the date of realization of funds.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>All mutual fund investments are subject to market risks. Please read all scheme related documents carefully before investing.</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
