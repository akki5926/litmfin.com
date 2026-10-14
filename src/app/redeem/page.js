'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

export default function RedeemPage() {
  const router = useRouter();

  const [redemptionData, setRedemptionData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [requestSuccess, setRequestSuccess] = useState(false);
  const [transactionId, setTransactionId] = useState('');

  const [redeemType, setRedeemType] = useState('partial'); // 'all', 'partial', or 'amount'
  const [unitsToRedeem, setUnitsToRedeem] = useState('');
  const [amountToRedeem, setAmountToRedeem] = useState('');

  // Folio state
  const [folios, setFolios] = useState([]);
  const [selectedFolio, setSelectedFolio] = useState('');
  const [isLoadingFolios, setIsLoadingFolios] = useState(false);

  useEffect(() => {
    const storedData = localStorage.getItem('redemption_data');
    if (storedData) {
      try {
        const parsedData = JSON.parse(storedData);
        setRedemptionData(parsedData);

        const schemeCode = parsedData.fund_code || parsedData.id;
        const clientCode = parsedData.client_code || '';
        if (schemeCode) {
          fetchFolios(schemeCode, clientCode);
        }
      } catch (error) {
        console.error('Error parsing redemption data:', error);
        alert('Invalid redemption data. Please start over.');
        router.push('/portfolio');
      }
    } else {
      alert('No redemption data found. Please select a holding to redeem.');
      router.push('/portfolio');
    }
  }, [router]);

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
          setSelectedFolio('');
        }
      } else {
        setFolios([]);
        if (data && data.message && data.message.startsWith('Debug:')) {
           alert(data.message);
        }
      }
    } catch (error) {
      console.error('Error fetching folios:', error);
      setFolios([]);
    } finally {
      setIsLoadingFolios(false);
    }
  };

  const handleInputChange = (e) => {
    const { value, name } = e.target;
    if (value === '' || /^\d*\.?\d*$/.test(value)) {
      if (name === 'units') setUnitsToRedeem(value);
      if (name === 'amount') setAmountToRedeem(value);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!selectedFolio) {
      alert('Please select a folio.');
      return;
    }

    const availableUnits = parseFloat(redemptionData.units || 0);
    const currentNav = parseFloat(redemptionData.current_nav || 0);
    const maxAmount = availableUnits * currentNav;

    let finalUnits = 0;
    let finalAmount = 0;

    if (redeemType === 'partial') {
      finalUnits = parseFloat(unitsToRedeem);
      if (!finalUnits || finalUnits <= 0) {
        alert('Please enter a valid number of units to redeem.');
        return;
      }
      if (finalUnits > availableUnits) {
        alert(`You can only redeem up to ${availableUnits.toLocaleString('en-IN')} units.`);
        return;
      }
    } else if (redeemType === 'amount') {
      finalAmount = parseFloat(amountToRedeem);
      if (!finalAmount || finalAmount <= 0) {
        alert('Please enter a valid amount to redeem.');
        return;
      }
      if (finalAmount > maxAmount) {
        alert(`You can only redeem up to ₹${maxAmount.toLocaleString('en-IN', { maximumFractionDigits: 2 })}.`);
        return;
      }
    } else if (redeemType === 'all') {
      finalUnits = availableUnits;
    }

    try {
      setLoading(true);
      const token = localStorage.getItem('site_token');

      const payload = {
        scheme_code: redemptionData.fund_code || redemptionData.id,
        fund_house_code: redemptionData.fund_house_code || redemptionData.amc_code || '',
        folio_no: selectedFolio,
        redeem_type: redeemType,
      };

      if (redeemType === 'amount') {
        payload.amount = finalAmount;
      } else {
        payload.units = finalUnits;
      }

      const response = await fetch(`${API_BASE_URL}/invest_api.php?action=redeem`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.success) {
        setTransactionId(data.data?.order_id || 'REDEMPTION-' + Date.now());
        setRequestSuccess(true);
        localStorage.removeItem('redemption_data');
      } else {
        alert(`Redemption request failed: ${data.message}`);
      }
    } catch (error) {
      console.error('Error submitting redeem request:', error);
      alert(`Error processing redemption: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleBackToPortfolio = () => {
    router.push('/portfolio');
  };

  if (!redemptionData) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading redemption data...</p>
        </div>
      </div>
    );
  }

  const availableUnits = parseFloat(redemptionData.units || 0);
  const currentNav = parseFloat(redemptionData.current_nav || 0);

  let estimatedValue = 0;
  if (redeemType === 'all') {
    estimatedValue = availableUnits * currentNav;
  } else if (redeemType === 'partial') {
    estimatedValue = (parseFloat(unitsToRedeem) || 0) * currentNav;
  } else if (redeemType === 'amount') {
    estimatedValue = parseFloat(amountToRedeem) || 0;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Back Button Row */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <button
            onClick={handleBackToPortfolio}
            className="flex items-center text-gray-600 hover:text-red-600 font-medium"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Portfolio
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="bg-white rounded-xl shadow-lg p-8 mb-8">
          {/* Page Header */}
          <div className="text-center mb-10">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Redeem Mutual Fund</h1>
            <p className="text-gray-600">Withdraw units from your existing investment</p>
          </div>

          {/* Investment Summary */}
          <div className="bg-red-50 border border-red-200 rounded-xl p-6 mb-10">
            <h2 className="text-xl font-bold text-red-800 mb-4 flex items-center">
              <svg className="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Selected Holding
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div className="lg:col-span-2">
                <h3 className="text-sm font-medium text-red-600 mb-1">Scheme Name</h3>
                <p className="text-lg font-bold text-gray-900">{redemptionData.scheme_name || 'N/A'}</p>
              </div>
              <div>
                <h3 className="text-sm font-medium text-red-600 mb-1">Available Units</h3>
                <p className="text-lg font-bold text-gray-900">{availableUnits.toLocaleString('en-IN')}</p>
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
                  <h2 className="text-xl font-bold text-green-800">Redemption Initiated!</h2>
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
                    <p className="text-sm text-gray-600">Redemption Type</p>
                    <p className="font-bold text-red-600">
                      {redeemType === 'amount' ? `₹${parseFloat(amountToRedeem).toLocaleString('en-IN')}` : `${(redeemType === 'all' ? availableUnits : parseFloat(unitsToRedeem)).toLocaleString('en-IN')} Units`}
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex gap-4 mt-6">
                <button
                  onClick={handleBackToPortfolio}
                  className="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors"
                >
                  Return to Portfolio
                </button>
              </div>
            </div>
          )}

          {/* Redeem Form */}
          {!requestSuccess && (
            <form onSubmit={handleSubmit}>
              <div className="space-y-8">

                {/* Folio Selection */}
                <div className="mb-6">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Select Folio <span className="text-red-500">*</span>
                  </label>
                  {isLoadingFolios ? (
                    <p className="text-sm text-blue-600">Loading your folios...</p>
                  ) : (
                    <select
                      value={selectedFolio}
                      onChange={(e) => setSelectedFolio(e.target.value)}
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white"
                      required
                    >
                      <option value="" disabled>Select a folio to redeem from</option>
                      {folios.map((folio, idx) => (
                        <option key={idx} value={folio}>{folio}</option>
                      ))}
                    </select>
                  )}
                  {folios.length === 0 && !isLoadingFolios && (
                    <p className="text-sm text-red-500 mt-2">No folios found for this scheme. You cannot redeem at this time.</p>
                  )}
                </div>

                {/* Redeem Type Selection */}
                <div className="mb-6">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Redemption Type
                  </label>
                  <div className="flex space-x-4">
                    <label className="flex items-center space-x-2 cursor-pointer">
                      <input
                        type="radio"
                        name="redeemType"
                        value="partial"
                        checked={redeemType === 'partial'}
                        onChange={() => setRedeemType('partial')}
                        className="text-red-600 focus:ring-red-500"
                      />
                      <span className="text-gray-800">Partial Units</span>
                    </label>
                    <label className="flex items-center space-x-2 cursor-pointer">
                      <input
                        type="radio"
                        name="redeemType"
                        value="all"
                        checked={redeemType === 'all'}
                        onChange={() => {
                          setRedeemType('all');
                          setUnitsToRedeem(availableUnits.toString());
                        }}
                        className="text-red-600 focus:ring-red-500"
                      />
                      <span className="text-gray-800">All Units</span>
                    </label>
                    <label className="flex items-center space-x-2 cursor-pointer">
                      <input
                        type="radio"
                        name="redeemType"
                        value="amount"
                        checked={redeemType === 'amount'}
                        onChange={() => setRedeemType('amount')}
                        className="text-red-600 focus:ring-red-500"
                      />
                      <span className="text-gray-800">By Amount</span>
                    </label>
                  </div>
                </div>

                {/* Amount or Units Input */}
                {redeemType !== 'all' && (
                  <div>
                    <label className="block text-lg font-medium text-gray-700 mb-4">
                      {redeemType === 'amount' ? 'Amount to Redeem' : 'Units to Redeem'} <span className="text-red-500">*</span>
                    </label>
                    <div className="flex items-center max-w-md">
                      {redeemType === 'amount' && (
                        <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                          ₹
                        </span>
                      )}
                      <input
                        type="text"
                        name={redeemType === 'amount' ? 'amount' : 'units'}
                        value={redeemType === 'amount' ? amountToRedeem : unitsToRedeem}
                        onChange={handleInputChange}
                        placeholder={redeemType === 'amount' ? 'Enter amount (e.g. 10000)' : 'Enter units (e.g. 10.5)'}
                        required
                        className={`w-full px-4 py-3 border border-gray-300 ${redeemType === 'amount' ? 'rounded-r-lg' : 'rounded-lg'} focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors`}
                      />
                    </div>
                  </div>
                )}

                {/* Redemption Summary */}
                <div className="bg-gray-50 border border-gray-200 rounded-xl p-6">
                  <h3 className="font-bold text-gray-800 mb-4 flex items-center">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Redemption Estimate
                  </h3>
                  <div className="space-y-3">
                    <div className="flex justify-between border-t pt-3">
                      <span className="text-gray-600">Current NAV:</span>
                      <span className="font-medium text-gray-900">₹{currentNav.toLocaleString('en-IN', { minimumFractionDigits: 4 })}</span>
                    </div>
                    <div className="flex justify-between border-t pt-3">
                      <span className="text-gray-600">Estimated Value:</span>
                      <span className="font-bold text-red-600">
                        ₹{estimatedValue > 0 ? estimatedValue.toLocaleString('en-IN', { maximumFractionDigits: 2 }) : '0.00'}
                      </span>
                    </div>
                    <p className="text-xs text-gray-500 mt-2">* Actual redemption value will be based on the applicable NAV on the execution date.</p>
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-between pt-8 border-t border-gray-200">
                  <button
                    type="button"
                    onClick={handleBackToPortfolio}
                    className="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors flex items-center"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Cancel
                  </button>

                  <button
                    type="submit"
                    disabled={loading || !selectedFolio || (redeemType === 'partial' && (!unitsToRedeem || parseFloat(unitsToRedeem) <= 0 || parseFloat(unitsToRedeem) > availableUnits)) || (redeemType === 'amount' && (!amountToRedeem || parseFloat(amountToRedeem) <= 0 || parseFloat(amountToRedeem) > (availableUnits * currentNav)))}
                    className={`px-8 py-3 font-medium rounded-lg transition-colors flex items-center ${!loading && selectedFolio && (
                        redeemType === 'all' ||
                        (redeemType === 'partial' && unitsToRedeem && parseFloat(unitsToRedeem) > 0 && parseFloat(unitsToRedeem) <= availableUnits) ||
                        (redeemType === 'amount' && amountToRedeem && parseFloat(amountToRedeem) > 0 && parseFloat(amountToRedeem) <= (availableUnits * currentNav))
                      )
                        ? 'bg-red-600 hover:bg-red-700 text-white'
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
                        Confirm Redemption
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
                <span>The redemption proceeds will be credited directly to your registered bank account.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Redemption is subject to applicable exit loads and taxes.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Settlement usually takes 1-3 working days for liquid funds and 3-5 working days for equity/debt funds.</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}