'use client';

import { useState, useEffect, useRef, Suspense, useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

// Create a separate component for the content that uses useSearchParams
function SipPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const [sipData, setSipData] = useState({
    dayOfSip: '',
    durationYears: '1',
    initialAmount: '',
    monthlyAmount: '',
    mandateId: '',
    bankAccountNo: '',
    mandateExpiryDate: '',
    mandateLimit: ''
  });

  const [investmentData, setInvestmentData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [requestSuccess, setRequestSuccess] = useState(false);
  const [transactionId, setTransactionId] = useState('');
  const [minInitialAmount, setMinInitialAmount] = useState(500);
  const [minMonthlyAmount, setMinMonthlyAmount] = useState(100);
  const [referrer, setReferrer] = useState('invest'); // Default to invest

  // Mandate related states
  const [mandateOptions, setMandateOptions] = useState([]);
  const [loadingMandates, setLoadingMandates] = useState(false);
  const [selectedMandate, setSelectedMandate] = useState(null);
  // Guard: prevent the no-mandate alert from firing more than once (React StrictMode runs effects twice)
  const mandateAlertShownRef = useRef(false);

  const sipDays = [
    { value: '1', label: 'Every 1st' },
    { value: '5', label: 'Every 5th' },
    { value: '10', label: 'Every 10th' },
    { value: '15', label: 'Every 15th' },
    { value: '20', label: 'Every 20th' },
    { value: '25', label: 'Every 25th' }
  ];

  const durationOptions = Array.from({ length: 15 }, (_, i) => ({
    value: (i + 1).toString(),
    label: `${i + 1} Year${i > 0 ? 's' : ''}`
  }));

  // Fetch scheme master data to get minimum amounts
  const fetchSchemeMasterData = useCallback(async (schemeCode) => {
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
          const newPurchaseMin = parseFloat(schemeData.new_purchase_min_amount) || 500;
          const additionalPurchaseMin = parseFloat(schemeData.additional_purchase_min_amount) || 100;

          setMinInitialAmount(newPurchaseMin);
          setMinMonthlyAmount(additionalPurchaseMin);

          // console.log('Fetched minimum amounts:', { newPurchaseMin, additionalPurchaseMin });
        }
      }
    } catch (error) {
      // console.error('Error fetching scheme master data:', error);
      // Use default values if fetch fails
    }
  }, []);

  // Fetch mandate details for the logged-in user
  const fetchMandateDetails = useCallback(async () => {
    try {
      setLoadingMandates(true);
      const token = localStorage.getItem('site_token');

      // Just call the API without any query parameters
      const response = await fetch(`${API_BASE_URL}/mandate_api.php`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      // console.log('Response status:', response.status);

      if (response.ok) {
        const responseData = await response.json();
        // console.log('Full API response:', responseData);

        if (responseData.success) {
          // IMPORTANT: The mandates array is inside responseData.data.mandates
          let mandates = [];

          if (responseData.data && responseData.data.mandates && Array.isArray(responseData.data.mandates)) {
            mandates = responseData.data.mandates;
          } else if (responseData.mandates && Array.isArray(responseData.mandates)) {
            mandates = responseData.mandates;
          } else if (responseData.data && Array.isArray(responseData.data)) {
            mandates = responseData.data;
          }

          // console.log('Total mandates found:', mandates.length);
          // console.log('Mandates details:', mandates);

          // Filter only active and approved mandates
          const activeMandates = mandates.filter(mandate => {
            const isActive = (mandate.is_active === '1' || mandate.is_active === 1);
            const isApproved = (mandate.reg_status === 'APPROVED' || mandate.reg_status === 'ACTIVE');

            // Also check date validity
            const today = new Date().toISOString().split('T')[0];
            const hasValidStartDate = !mandate.start_date || mandate.start_date <= today;
            const hasValidEndDate = !mandate.end_date || mandate.end_date >= today;

            return isActive && isApproved && hasValidStartDate && hasValidEndDate;
          });

          setMandateOptions(activeMandates);
          // console.log('Active mandates count:', activeMandates.length);
          // console.log('Active mandates:', activeMandates);

          // If no active mandates found, show message and offer to register
          if (activeMandates.length === 0 && !mandateAlertShownRef.current) {
            mandateAlertShownRef.current = true;
            const userConfirmed = window.confirm(
              'No active mandate found. A mandate is required for SIP auto-debit.\n\nClick OK to register a new mandate.'
            );
            if (userConfirmed) {
              router.push('/dashboard/mandate-registration');
            }
          }
        } else {
          // console.error('API returned success=false:', responseData.message);
          setMandateOptions([]);
        }
      } else {
        const errorText = await response.text();
        // console.error('Failed to fetch mandates:', response.status, errorText);
        setMandateOptions([]);
      }
    } catch (error) {
      // console.error('Error fetching mandate details:', error);
      setMandateOptions([]);
    } finally {
      setLoadingMandates(false);
    }
  }, [router]);

  // Load investment data from localStorage and fetch minimum amounts from database
  useEffect(() => {
    const storedData = localStorage.getItem('investment_data');

    // Get referrer from URL query parameter
    const ref = searchParams.get('ref');
    // console.log('Referrer from URL:', ref);

    if (ref) {
      setReferrer(ref);
    }

    if (storedData) {
      try {
        const parsedData = JSON.parse(storedData);
        // console.log('Loaded investment data:', parsedData);
        setInvestmentData(parsedData);

        // Auto-fill monthly amount from the amount entered in previous page
        if (parsedData.monthlyAmount) {
          setSipData(prev => ({
            ...prev,
            monthlyAmount: parsedData.monthlyAmount
          }));
        }

        // Fetch scheme master data to get minimum amounts
        if (parsedData.schemeCode || parsedData.scheme?.product_code) {
          const schemeCode = parsedData.schemeCode || parsedData.scheme?.product_code;
          fetchSchemeMasterData(schemeCode);
        }
      } catch (error) {
        // console.error('Error parsing investment data:', error);
        alert('Invalid investment data. Please start over.');
        router.push('/invest');
      }
    } else {
      alert('No investment data found. Please select a fund first.');
      router.push('/invest');
    }
  }, [router, searchParams, fetchSchemeMasterData]);

  // Fetch mandate details for the logged-in user
  useEffect(() => {
    fetchMandateDetails();
  }, [fetchMandateDetails]);

  // Handle mandate selection change
  const handleMandateChange = (e) => {
    const recordId = e.target.value;
    const mandate = mandateOptions.find(m => m.id.toString() === recordId);

    if (mandate) {
      setSelectedMandate(mandate);
      setSipData(prev => ({
        ...prev,
        selectedMandateRecordId: mandate.id,  // Store the record ID
        mandateId: mandate.mandate_id || mandate.id,  // For display/submission
        bankAccountNo: mandate.account_no || '',
        mandateExpiryDate: mandate.end_date || '',
        mandateLimit: mandate.amount || ''
      }));
    } else {
      setSelectedMandate(null);
      setSipData(prev => ({
        ...prev,
        selectedMandateRecordId: '',
        mandateId: '',
        bankAccountNo: '',
        mandateExpiryDate: '',
        mandateLimit: ''
      }));
    }
  };

  // Navigate to mandate registration page
  const handleRegisterMandate = () => {
    router.push('/dashboard/mandate-registration');
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;

    // Allow only numbers and decimal for amount fields
    if (name === 'initialAmount' || name === 'monthlyAmount') {
      if (value === '' || /^\d*\.?\d*$/.test(value)) {
        setSipData(prev => ({
          ...prev,
          [name]: value
        }));
      }
    } else {
      setSipData(prev => ({
        ...prev,
        [name]: value
      }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    // Validation
    if (!sipData.dayOfSip) {
      alert('Please select SIP day');
      return;
    }

    if (!sipData.durationYears) {
      alert('Please select SIP duration');
      return;
    }

    if (!sipData.monthlyAmount || parseFloat(sipData.monthlyAmount) <= 0) {
      alert('Please enter a valid monthly SIP amount');
      return;
    }

    if (parseFloat(sipData.monthlyAmount) < minMonthlyAmount) {
      alert(`Minimum monthly SIP amount is ₹${minMonthlyAmount}`);
      return;
    }

    if (sipData.initialAmount && parseFloat(sipData.initialAmount) > 0 && parseFloat(sipData.initialAmount) < minInitialAmount) {
      alert(`Minimum initial investment amount is ₹${minInitialAmount}`);
      return;
    }

    // Validate mandate fields
    if (!sipData.mandateId) {
      alert('Please select a mandate ID or register a new mandate');
      return;
    }

    if (!sipData.bankAccountNo) {
      alert('Please select a valid mandate with bank account details');
      return;
    }

    if (sipData.mandateExpiryDate && new Date(sipData.mandateExpiryDate) < new Date()) {
      alert('Selected mandate has expired. Please register a new mandate.');
      return;
    }

    if (sipData.mandateLimit && parseFloat(sipData.monthlyAmount) > parseFloat(sipData.mandateLimit)) {
      alert(`Monthly SIP amount (₹${sipData.monthlyAmount}) exceeds the mandate limit (₹${sipData.mandateLimit}). Please select a mandate with higher limit or reduce the SIP amount.`);
      return;
    }

    try {
      setLoading(true);
      const token = localStorage.getItem('site_token');

      // Prepare SIP request payload
      const payload = {
        fund_house_code: investmentData?.fundHouseCode || investmentData?.fundHouse?.code,
        fund_house_name: investmentData?.fundHouseName || investmentData?.fundHouse?.long_name || investmentData?.fundHouse?.details || 'N/A',
        scheme_code: investmentData?.schemeCode || investmentData?.scheme?.product_code,
        scheme_name: investmentData?.schemeName || investmentData?.scheme?.product_long_name || investmentData?.scheme?.label || 'N/A',
        day_of_sip: parseInt(sipData.dayOfSip),
        duration_years: parseInt(sipData.durationYears),
        initial_amount: sipData.initialAmount ? parseFloat(sipData.initialAmount) : 0,
        monthly_amount: parseFloat(sipData.monthlyAmount),
        request_type: 'sip',
        // Mandate details
        mandate_id: sipData.mandateId,
        bank_account_no: sipData.bankAccountNo,
        mandate_expiry_date: sipData.mandateExpiryDate,
        mandate_limit: sipData.mandateLimit
      };

      // console.log('Sending SIP request:', payload);

      // Call SIP API
      const response = await fetch(`${API_BASE_URL}/invest_api.php?action=sip`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.success) {
        setTransactionId(data.data?.order_id || data.data?.transaction_id || 'SIP-' + Date.now());
        setRequestSuccess(true);

        // Clear localStorage after successful request
        localStorage.removeItem('investment_data');
      } else {
        alert(`SIP request failed: ${data.message}`);
      }
    } catch (error) {
      // console.error('Error submitting SIP request:', error);
      alert(`Error processing SIP request: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleBack = () => {
    // Navigate back based on referrer
    if (referrer === 'tax-saver') {
      router.push('/tax-saver');
    } else if (referrer === 'recommended-funds') {
      router.push('/recommended-funds');
    } else {
      router.push('/invest');
    }
  };

  const handleNewSIP = () => {
    setRequestSuccess(false);
    setSipData({
      dayOfSip: '',
      durationYears: '1',
      initialAmount: '',
      monthlyAmount: '',
      mandateId: '',
      bankAccountNo: '',
      mandateExpiryDate: '',
      mandateLimit: ''
    });
    setSelectedMandate(null);
    router.push('/invest');
  };

  // Calculate total investment amount (Monthly + Initial)
  const calculateTotalAmount = () => {
    const monthly = parseFloat(sipData.monthlyAmount) || 0;
    const initial = parseFloat(sipData.initialAmount) || 0;
    return monthly + initial;
  };

  // Format date for display
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN');
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
            onClick={handleBack}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium transition-colors"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="bg-white rounded-xl shadow-lg p-8 mb-8">
          {/* Page Header */}
          <div className="text-center mb-10">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Put SIP Request</h1>
            <p className="text-gray-600">Set up a Systematic Investment Plan for your selected fund</p>
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
                <h3 className="text-sm font-medium text-blue-600 mb-1">Fund House Name</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.fundHouseName || investmentData.fundHouse?.long_name || investmentData.fundHouse?.details || investmentData.fundHouse?.label || 'N/A'}</p>
              </div>
              <div>
                <h3 className="text-sm font-medium text-blue-600 mb-1">Scheme Name</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.schemeName || investmentData.scheme?.product_long_name || investmentData.scheme?.label || 'N/A'}</p>
              </div>
              <div>
                <h3 className="text-sm font-medium text-blue-600 mb-1">Fund Category</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.fundCategoryName || investmentData.fundCategory?.category_name || investmentData.fundCategory?.details || investmentData.fundCategory?.label || 'N/A'}</p>
              </div>
              <div>
                <h3 className="text-sm font-medium text-blue-600 mb-1">Settlement Type</h3>
                <p className="text-lg font-bold text-gray-900">{investmentData.settlementType || 'NRM'}</p>
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
                  <h2 className="text-xl font-bold text-green-800">SIP Request Submitted Successfully!</h2>
                  <p className="text-green-700">Your SIP request has been received and is being processed.</p>
                </div>
              </div>

              <div className="bg-white rounded-lg p-4 mt-4">
                <h3 className="font-bold text-gray-800 mb-3">SIP Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Transaction ID</p>
                    <p className="font-bold text-gray-900">{transactionId}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">SIP Amount</p>
                    <p className="font-bold text-green-600">₹{sipData.monthlyAmount}/month</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Initial Amount</p>
                    <p className="font-bold text-gray-900">₹{sipData.initialAmount || '0'}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Total Investment</p>
                    <p className="font-bold text-blue-600">₹{calculateTotalAmount().toLocaleString()}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">SIP Day</p>
                    <p className="font-bold text-gray-900">{sipDays.find(d => d.value === sipData.dayOfSip)?.label}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Duration</p>
                    <p className="font-bold text-gray-900">{sipData.durationYears} Year{sipData.durationYears !== '1' ? 's' : ''}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Mandate ID</p>
                    <p className="font-bold text-gray-900">{sipData.mandateId}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Bank Account</p>
                    <p className="font-bold text-gray-900">XXXX{sipData.bankAccountNo?.slice(-4)}</p>
                  </div>
                </div>
              </div>

              <div className="flex gap-4 mt-6">
                <button
                  onClick={handleNewSIP}
                  className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                >
                  Start New SIP
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

          {/* SIP Form */}
          {!requestSuccess && (
            <form onSubmit={handleSubmit}>
              <div className="space-y-8">
                {/* Day of SIP */}
                <div>
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Day of SIP <span className="text-red-500">*</span>
                  </label>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    {sipDays.map((day) => (
                      <label
                        key={day.value}
                        className={`relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-all ${sipData.dayOfSip === day.value
                            ? 'border-blue-600 bg-blue-50 text-blue-700'
                            : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                          }`}
                      >
                        <input
                          type="radio"
                          name="dayOfSip"
                          value={day.value}
                          checked={sipData.dayOfSip === day.value}
                          onChange={handleInputChange}
                          className="sr-only"
                        />
                        <span className="font-medium">{day.label}</span>
                        {sipData.dayOfSip === day.value && (
                          <svg className="w-5 h-5 text-blue-600 absolute top-2 right-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                          </svg>
                        )}
                      </label>
                    ))}
                  </div>
                </div>

                {/* Duration of SIP */}
                <div>
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Duration of SIP <span className="text-red-500">*</span>
                    <span className="text-gray-500 font-normal ml-2">(1 Year to 15 Years)</span>
                  </label>
                  <div className="flex items-center space-x-4">
                    <span className="text-gray-600">Select:</span>
                    <select
                      name="durationYears"
                      value={sipData.durationYears}
                      onChange={handleInputChange}
                      className="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white"
                    >
                      {durationOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  <p className="text-sm text-gray-500 mt-2">
                    Your SIP will continue for {sipData.durationYears} year{sipData.durationYears !== '1' ? 's' : ''}
                  </p>
                </div>

                {/* Monthly SIP Amount */}
                <div>
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Monthly SIP Amount <span className="text-red-500">*</span>
                  </label>
                  <div className="flex items-center max-w-md">
                    <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                      ₹
                    </span>
                    <input
                      type="text"
                      name="monthlyAmount"
                      value={sipData.monthlyAmount}
                      onChange={handleInputChange}
                      placeholder="Enter monthly SIP amount"
                      required
                      className="w-full px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                  </div>
                  <p className="text-sm text-gray-500 mt-2">
                    Amount will be auto-debited monthly (Minimum: ₹{minMonthlyAmount})
                  </p>
                </div>

                {/* Initial Amount (Optional) */}
                <div>
                  <label className="block text-lg font-medium text-gray-700 mb-4">
                    Initial Amount (Optional)
                  </label>
                  <div className="flex items-center max-w-md">
                    <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                      ₹
                    </span>
                    <input
                      type="text"
                      name="initialAmount"
                      value={sipData.initialAmount}
                      onChange={handleInputChange}
                      placeholder="Enter initial investment amount (optional)"
                      className="w-full px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                    />
                  </div>
                  <p className="text-sm text-gray-500 mt-2">
                    One-time investment at the start of SIP (Minimum: ₹{minInitialAmount})
                  </p>
                </div>

                {/* Mandate Selection Section */}
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Mandate Details
                  </h3>
                  <p className="text-sm text-gray-500 mb-4">
                    Select a mandate for auto-debit of SIP amount. The mandate should have sufficient limit for your SIP amount.
                  </p>

                  {/* Mandate Dropdown */}
                  <div className="mb-4">
                    <label className="block text-md font-medium text-gray-700 mb-2">
                      Select Mandate ID <span className="text-red-500">*</span>
                    </label>
                    {loadingMandates ? (
                      <div className="flex items-center space-x-2 text-gray-500">
                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span>Loading mandates...</span>
                      </div>
                    ) : mandateOptions.length > 0 ? (
                      <select
                        value={sipData.selectedMandateRecordId}
                        onChange={handleMandateChange}
                        className="w-full max-w-md px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white"
                      >
                        <option value="">-- Select Mandate --</option>
                        {mandateOptions.map((mandate) => (
                          <option key={mandate.id} value={mandate.id}>
                            {mandate.mandate_id} - {mandate.bank_name} (XXXX{mandate.account_no?.slice(-4)}) - ₹{parseFloat(mandate.amount || 0).toLocaleString()}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <div className="flex flex-col space-y-3">
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                          <p className="text-yellow-800 mb-2">No active mandates found. Please register a mandate to continue with SIP.</p>
                          <button
                            type="button"
                            onClick={handleRegisterMandate}
                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                          >
                            Register New Mandate
                          </button>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Display selected mandate details */}
                  {selectedMandate && (
                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-4">
                      <h4 className="font-semibold text-gray-800 mb-3">Selected Mandate Details</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">

                        <div>
                          <span className="text-gray-500">Bank Name:</span>
                          <span className="ml-2 font-medium text-gray-800">{selectedMandate.bank_name}</span>
                        </div>
                        <div>
                          <span className="text-gray-500">Account No.:</span>
                          <span className="ml-2 font-medium text-gray-800">XXXX{selectedMandate.account_no?.slice(-4)}</span>
                        </div>

                        <div>
                          <span className="text-gray-500">Mandate Limit:</span>
                          <span className="ml-2 font-medium text-green-600">₹{parseFloat(selectedMandate.amount || 0).toLocaleString()}</span>
                        </div>

                        <div>
                          <span className="text-gray-500">Expiry Date:</span>
                          <span className={`ml-2 font-medium ${new Date(selectedMandate.end_date) < new Date() ? 'text-red-600' : 'text-gray-800'}`}>
                            {formatDate(selectedMandate.end_date)}
                            {new Date(selectedMandate.end_date) < new Date() && ' (Expired)'}
                          </span>
                        </div>
                        <div>
                          <span className="text-gray-500">UMRN No.:</span>
                          <span className="ml-2 font-medium text-gray-800">{selectedMandate.umrn_no || 'Pending'}</span>
                        </div>

                      </div>
                    </div>
                  )}

                  {/* Hidden fields for mandate data */}
                  <input type="hidden" name="bankAccountNo" value={sipData.bankAccountNo} />
                  <input type="hidden" name="mandateExpiryDate" value={sipData.mandateExpiryDate} />
                  <input type="hidden" name="mandateLimit" value={sipData.mandateLimit} />
                </div>

                {/* SIP Summary */}
                <div className="bg-gray-50 border border-gray-200 rounded-xl p-6">
                  <h3 className="font-bold text-gray-800 mb-4 flex items-center">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    SIP Summary
                  </h3>
                  <div className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-gray-600">Monthly Investment:</span>
                      <span className="font-bold">
                        {sipData.monthlyAmount ? `₹${parseFloat(sipData.monthlyAmount).toLocaleString()}` : '-'}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Initial Investment:</span>
                      <span className="font-bold">
                        {sipData.initialAmount ? `₹${parseFloat(sipData.initialAmount).toLocaleString()}` : '₹0'}
                      </span>
                    </div>
                    <div className="flex justify-between border-t border-gray-200 pt-3">
                      <span className="text-gray-800 font-semibold">Total Investment:</span>
                      <span className="font-bold text-green-600">
                        ₹{calculateTotalAmount().toLocaleString()}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">SIP Day:</span>
                      <span className="font-bold">
                        {sipData.dayOfSip ? sipDays.find(d => d.value === sipData.dayOfSip)?.label : '-'}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Duration:</span>
                      <span className="font-bold">
                        {sipData.durationYears ? `${sipData.durationYears} Year${sipData.durationYears !== '1' ? 's' : ''}` : '-'}
                      </span>
                    </div>
                    {selectedMandate && (
                      <>
                        <div className="flex justify-between border-t border-gray-200 pt-3">
                          <span className="text-gray-600">Mandate ID:</span>
                          <span className="font-bold">{sipData.mandateId}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-gray-600">Bank Account:</span>
                          <span className="font-bold">XXXX{sipData.bankAccountNo?.slice(-4)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-gray-600">Mandate Limit:</span>
                          <span className="font-bold text-green-600">₹{parseFloat(sipData.mandateLimit || 0).toLocaleString()}</span>
                        </div>
                      </>
                    )}
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-between pt-8 border-t border-gray-200">
                  <button
                    type="button"
                    onClick={handleBack}
                    className="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors flex items-center"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                  </button>

                  <button
                    type="submit"
                    disabled={loading || !sipData.dayOfSip || !sipData.monthlyAmount || !sipData.mandateId}
                    className={`px-8 py-3 font-medium rounded-lg transition-colors flex items-center ${!loading && sipData.dayOfSip && sipData.monthlyAmount && sipData.mandateId && parseFloat(sipData.monthlyAmount) >= 100
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
                        Submit SIP Request
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
              SIP Information & Terms
            </h3>
            <ul className="space-y-2 text-sm text-gray-700">
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>SIP allows you to invest a fixed amount regularly in mutual funds.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>SIP amount will be auto-debited from your registered bank account on the selected date.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Minimum SIP duration is 6 months, but you can cancel anytime after that.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>You can increase, decrease, pause, or stop your SIP anytime.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>All mutual fund investments are subject to market risks.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Read all scheme related documents carefully before investing.</span>
              </li>
              <li className="flex items-start">
                <span className="text-yellow-600 mr-2">•</span>
                <span>Ensure your mandate is active and has sufficient limit for the SIP amount.</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}

// Main page component with Suspense boundary
export default function SipPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading SIP page...</p>
        </div>
      </div>
    }>
      <SipPageContent />
    </Suspense>
  );
}