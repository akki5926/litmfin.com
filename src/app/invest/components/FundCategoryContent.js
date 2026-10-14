// /app/invest/components/FundCategoryContent.js
'use client';

import { useRouter } from 'next/navigation';
import { useState, useEffect, useRef, useCallback } from 'react';
import TypedText from '@/components/TypedText';
import siteConfig from '@/config/siteConfig';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || '/api';

export default function FundCategoryContent({ category }) {
  const router = useRouter();

  // Define category details locally
  const categoryDetails = {
    equity: { title: 'Equity', description: 'Stock provide part-ownership which in turn create Long term Wealth', color: 'bg-blue-500' },
    gold: { title: 'Gold', description: 'Precious Metal for all ages which provides protection against inflation.', color: 'bg-yellow-500' },
    fof: { title: 'FOF', description: 'Let Fund Manager decide the Funds to invest – primarily for International Equities.', color: 'bg-purple-500' },
    debt: { title: 'Debt', description: 'Funds that can easily replace Bank FDs if used properly.', color: 'bg-green-500' },
    hybrid: { title: 'Hybrid', description: 'Funds that create balance for whom choice between debt and equity is difficult.', color: 'bg-orange-500' },
    arbitrage: { title: 'Arbitrage', description: 'Risk Free Strategy to provide better post tax returns especially for HNI\'s and Corporates.', color: 'bg-teal-500' },
    'tax-saving': { title: 'Tax Saving', description: 'Funds that give high possible returns plus Tax rebates to customers.', color: 'bg-red-500' },
    etf: { title: 'ETF', description: 'Exchange traded funds for active investors.', color: 'bg-indigo-500' },
    ...(siteConfig.features.SIF ? {
      sif: { title: 'SIF', description: 'Systematic Investment Funds for investors who prefer regular periodic investments.', color: 'bg-pink-500' }
    } : {})
  };

  const [selectedFundHouse, setSelectedFundHouse] = useState('');
  const [selectedFundCategory, setSelectedFundCategory] = useState('');
  const [selectedSettlementType, setSelectedSettlementType] = useState('NRM');
  const [selectedScheme, setSelectedScheme] = useState('');
  const [investmentAmount, setInvestmentAmount] = useState('');
  const [showInvestmentPanel, setShowInvestmentPanel] = useState(false);

  const [fundHouses, setFundHouses] = useState([]);
  const [fundCategories, setFundCategories] = useState([]);
  const [schemes, setSchemes] = useState([]);
  const [sifCategories, setSifCategories] = useState([]);
  const [sifFunds, setSifFunds] = useState([]);

  const [fundHousesLoading, setFundHousesLoading] = useState(false);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const [schemesLoading, setSchemesLoading] = useState(false);
  const [sifCategoriesLoading, setSifCategoriesLoading] = useState(false);
  const [sifFundsLoading, setSifFundsLoading] = useState(false);
  const [buyLoading, setBuyLoading] = useState(false);

  const [selectedFundHouseData, setSelectedFundHouseData] = useState(null);
  const [selectedFundCategoryData, setSelectedFundCategoryData] = useState(null);
  const [selectedSchemeData, setSelectedSchemeData] = useState(null);
  const [selectedSifCategoryData, setSelectedSifCategoryData] = useState(null);
  const [minInvestmentAmount, setMinInvestmentAmount] = useState(100);

  const [categoryInfo, setCategoryInfo] = useState(null);
  const [error, setError] = useState('');
  const [sifError, setSifError] = useState('');
  const [isHydrated, setIsHydrated] = useState(false);

  const investmentPanelRef = useRef(null);

  // Define all callback functions first, before useEffect hooks that depend on them

  // Fetch all fund houses (or only SIF fund houses if category is 'sif')
  const fetchFundHouses = useCallback(async () => {
    if (!isHydrated) return;

    try {
      setFundHousesLoading(true);
      setError('');
      const token = localStorage.getItem('site_token');

      if (!token) {
        setError('Authentication token not found. Please log in again.');
        return;
      }

      // Use special endpoint for SIF
      const endpoint = category === 'sif'
        ? `${API_BASE_URL}/master_data.php?action=sif_amc_master`
        : `${API_BASE_URL}/master_data.php?action=amc_master`;

      // console.log('Fetching fund houses from:', endpoint);

      const response = await fetch(endpoint, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();

      if (data.success && Array.isArray(data.data)) {
        // Remove duplicates by label (if any)
        const seen = new Set();
        const formattedFundHouses = data.data.filter(amc => {
          const label = amc.long_name || amc.details || `Fund House ${amc.code || amc.amc_code}`;
          if (seen.has(label)) return false;
          seen.add(label);
          return true;
        }).map(amc => ({
          value: amc.code || amc.amc_code,
          label: amc.long_name || amc.details || `Fund House ${amc.code || amc.amc_code}`,
          data: amc
        }));
        setFundHouses(formattedFundHouses);
      } else {
        setError('Failed to load fund houses');
      }
    } catch (error) {
      console.error('Error fetching fund houses:', error);
      setError(`Error loading fund houses: ${error.message}`);
    } finally {
      setFundHousesLoading(false);
    }
  }, [isHydrated, category]);

  const fetchSifCategories = useCallback(async (amcCode) => {
    if (!isHydrated) return;

    try {
      setSifCategoriesLoading(true);
      setSifError('');
      const token = localStorage.getItem('site_token');

      if (!token) {
        setSifError('Authentication token not found.');
        return;
      }

      const apiUrl = `${API_BASE_URL}/master_data.php?action=sif_fund_categories&amc_code=${encodeURIComponent(amcCode)}`;
      // console.log('Fetching SIF categories from:', apiUrl);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();

      // console.log('SIF categories response:', data);

      if (data.success && Array.isArray(data.data)) {
        const formattedCategories = data.data.map(cat => ({
          value: cat.code,
          label: cat.details || cat.code,
          data: cat
        }));
        // console.log('Formatted SIF categories:', formattedCategories);
        setSifCategories(formattedCategories);
        if (formattedCategories.length === 1) {
          setSelectedFundCategory(formattedCategories[0].value);
          setSelectedSifCategoryData(formattedCategories[0].data);
        }
      } else {
        setSifError('No SIF categories available for selected fund house');
        setSifCategories([]);
      }
    } catch (error) {
      console.error('Error fetching SIF categories:', error);
      setSifError(`Error loading SIF categories: ${error.message}`);
      setSifCategories([]);
    } finally {
      setSifCategoriesLoading(false);
    }
  }, [isHydrated]);

  const fetchFundCategories = useCallback(async (amcCode, assetClass) => {
    if (!isHydrated) return;

    try {
      setCategoriesLoading(true);
      setError('');
      const token = localStorage.getItem('site_token');

      if (!token) {
        setError('Authentication token not found.');
        return;
      }

      const apiUrl = `${API_BASE_URL}/master_data.php?action=fund_category&amc_code=${encodeURIComponent(amcCode)}&asset_class=${encodeURIComponent(assetClass)}`;
      // console.log('Fetching fund categories from:', apiUrl);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();

      // console.log('Fund categories response:', data);

      if (data.success && Array.isArray(data.data)) {
        const formattedCategories = data.data.map(cat => ({
          value: cat.code,
          label: cat.details || cat.code,
          data: cat
        }));
        // console.log('Formatted categories:', formattedCategories);
        setFundCategories(formattedCategories);
        if (formattedCategories.length === 1) {
          setSelectedFundCategory(formattedCategories[0].value);
          setSelectedFundCategoryData(formattedCategories[0].data);
        }
      } else {
        setError('No fund categories available for selected fund house');
        setFundCategories([]);
      }
    } catch (error) {
      console.error('Error fetching fund categories:', error);
      setError(`Error loading fund categories: ${error.message}`);
      setFundCategories([]);
    } finally {
      setCategoriesLoading(false);
    }
  }, [isHydrated]);

  const fetchSifFunds = useCallback(async (amcCode, categoryCode) => {
    if (!isHydrated) return;

    try {
      setSifFundsLoading(true);
      setSifError('');
      const token = localStorage.getItem('site_token');

      if (!token) {
        setSifError('Authentication token not found.');
        return;
      }

      const apiUrl = `${API_BASE_URL}/master_data.php?action=mf_master&amc_code=${encodeURIComponent(amcCode)}&category_code=${encodeURIComponent(categoryCode)}&asset_class=sif&settlementType=${encodeURIComponent(selectedSettlementType)}`;
      // console.log('Fetching SIF funds from:', apiUrl);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();

      // console.log('SIF funds response:', data);

      if (data.success && Array.isArray(data.data)) {
        const formattedFunds = data.data.map(fund => ({
          value: fund.product_code || fund.scheme_code,
          label: fund.product_long_name || fund.scheme_name || `SIF Fund`,
          data: fund
        }));
        // console.log('Formatted SIF funds:', formattedFunds);
        setSifFunds(formattedFunds);
        if (formattedFunds.length === 1) setSelectedScheme(formattedFunds[0].value);
      } else {
        setSifError('No SIF funds available for selected category');
        setSifFunds([]);
      }
    } catch (error) {
      console.error('Error fetching SIF funds:', error);
      setSifError(`Error loading SIF funds: ${error.message}`);
      setSifFunds([]);
    } finally {
      setSifFundsLoading(false);
    }
  }, [isHydrated, selectedSettlementType]);

  // Fetch schemes when fund category and settlement type are selected
  const fetchSchemes = useCallback(async (amcCode, categoryCode, assetClass, settlementType) => {
    if (!isHydrated) return;

    try {
      setSchemesLoading(true);
      setError('');
      const token = localStorage.getItem('site_token');

      if (!token) {
        setError('Authentication token not found.');
        return;
      }

      const apiUrl = `${API_BASE_URL}/master_data.php?action=mf_master&amc_code=${encodeURIComponent(amcCode)}&category_code=${encodeURIComponent(categoryCode)}&asset_class=${encodeURIComponent(assetClass)}&settlementType=${encodeURIComponent(settlementType)}`;
      // console.log('Fetching schemes from:', apiUrl);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();

      // console.log('Schemes response:', data);

      if (data.success && Array.isArray(data.data)) {
        const formattedSchemes = data.data.map(scheme => ({
          value: scheme.product_code || scheme.scheme_code,
          label: scheme.product_long_name || scheme.scheme_name || `Scheme`,
          data: scheme
        }));
        // console.log('Formatted schemes:', formattedSchemes);
        setSchemes(formattedSchemes);
        if (formattedSchemes.length === 1) setSelectedScheme(formattedSchemes[0].value);
      } else {
        setError('No schemes available for selected fund house and category');
        setSchemes([]);
      }
    } catch (error) {
      console.error('Error fetching schemes:', error);
      setError(`Error loading schemes: ${error.message}`);
      setSchemes([]);
    } finally {
      setSchemesLoading(false);
    }
  }, [isHydrated]);

  // Fetch scheme master data to get minimum investment amounts
  const fetchSchemeMasterData = useCallback(async (schemeCode) => {
    if (!isHydrated) return;

    try {
      const token = localStorage.getItem('site_token');

      if (!token) {
        console.warn('Authentication token not found.');
        return;
      }

      const apiUrl = `${API_BASE_URL}/master_data.php?action=scheme_master&product_code=${encodeURIComponent(schemeCode)}`;
      // console.log('🔍 Fetching scheme master data from:', apiUrl);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        // console.log('✅ Scheme master response:', data);

        if (data.success && data.data) {
          const schemeData = Array.isArray(data.data) ? data.data[0] : data.data;
          const additionalPurchaseMin = parseFloat(schemeData.additional_purchase_min_amount) || 100;
          // Ensure minimum is at least 100
          const minAmount = Math.max(additionalPurchaseMin, 100);

          setMinInvestmentAmount(minAmount);
        } else {
          console.warn('⚠️ No data in response:', data);
        }
      } else {
        console.error('❌ API response not ok:', response.status);
      }
    } catch (error) {
      console.error('❌ Error fetching scheme master data:', error);
    }
  }, [isHydrated]);

  // Load category info
  useEffect(() => {
    setIsHydrated(true);
  }, []);

  useEffect(() => {
    if (category && categoryDetails[category]) {
      setCategoryInfo(categoryDetails[category]);
      if (isHydrated) {
        fetchFundHouses();
      }
    } else {
      router.push('/invest'); // redirect if invalid category
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [category, isHydrated, fetchFundHouses, router]);

  // Fetch SIF categories when fund house is selected (only for SIF category)
  useEffect(() => {
    if (category === 'sif' && selectedFundHouse) {
      fetchSifCategories(selectedFundHouse);
    } else if (selectedFundHouse) {
      fetchFundCategories(selectedFundHouse, category);
    } else {
      setFundCategories([]);
      setSifCategories([]);
      setSelectedFundCategory('');
      setSelectedSifCategoryData(null);
      setSelectedSettlementType('NRM');
      setSelectedScheme('');
      setSchemes([]);
      setSifFunds([]);
    }
  }, [selectedFundHouse, category, fetchSifCategories, fetchFundCategories]);

  // Fetch SIF funds when category is selected (only for SIF category)
  useEffect(() => {
    if (category === 'sif' && selectedFundHouse && selectedFundCategory) {
      fetchSifFunds(selectedFundHouse, selectedFundCategory);
    } else if (selectedFundCategory && selectedFundHouse && selectedSettlementType) {
      fetchSchemes(selectedFundHouse, selectedFundCategory, category, selectedSettlementType);
    } else {
      setSchemes([]);
      setSifFunds([]);
      setSelectedScheme('');
    }
  }, [selectedFundCategory, selectedFundHouse, selectedSettlementType, category, fetchSifFunds, fetchSchemes]);

  // Update selected data objects
  useEffect(() => {
    setSelectedFundHouseData(fundHouses.find(h => h.value === selectedFundHouse)?.data || null);
  }, [selectedFundHouse, fundHouses]);

  useEffect(() => {
    if (category === 'sif') {
      setSelectedSifCategoryData(sifCategories.find(c => c.value === selectedFundCategory)?.data || null);
    } else {
      setSelectedFundCategoryData(fundCategories.find(c => c.value === selectedFundCategory)?.data || null);
    }
  }, [selectedFundCategory, fundCategories, sifCategories, category]);

  useEffect(() => {
    if (category === 'sif') {
      setSelectedSchemeData(sifFunds.find(s => s.value === selectedScheme)?.data || null);
    } else {
      setSelectedSchemeData(schemes.find(s => s.value === selectedScheme)?.data || null);
    }
    // Fetch scheme master data when scheme is selected
    if (selectedScheme) {
      fetchSchemeMasterData(selectedScheme);
    }
  }, [selectedScheme, schemes, sifFunds, category, fetchSchemeMasterData]);

  const handleProceed = () => {
    if (!selectedFundHouse || !selectedFundCategory || !selectedSettlementType || !selectedScheme) {
      alert('Please select all options before proceeding');
      return;
    }
    setShowInvestmentPanel(true);
  };

  // Auto-scroll to investment panel when it appears
  useEffect(() => {
    if (showInvestmentPanel && investmentPanelRef.current) {
      setTimeout(() => {
        investmentPanelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }
  }, [showInvestmentPanel]);

  const handleSIP = () => {
    // Determine current categories and schemes based on category type
    const isSif = category === 'sif';
    const currentCats = isSif ? sifCategories : fundCategories;
    const currentSch = isSif ? sifFunds : schemes;

    // Get the scheme name properly
    const schemeName = currentSch.find(s => s.value === selectedScheme)?.label ||
      selectedSchemeData?.product_long_name ||
      selectedSchemeData?.scheme_name ||
      selectedScheme;

    // Get fund house name
    const fundHouseName = fundHouses.find(h => h.value === selectedFundHouse)?.label ||
      selectedFundHouseData?.long_name ||
      selectedFundHouse;

    // Get category name
    const categoryName = currentCats.find(c => c.value === selectedFundCategory)?.label ||
      (isSif ? selectedSifCategoryData?.details : selectedFundCategoryData?.details) ||
      selectedFundCategory;

    // Store investment data in localStorage for the SIP page
    const investmentData = {
      // Fund House Info
      fundHouse: selectedFundHouseData,
      fundHouseCode: selectedFundHouse,
      fundHouseName: fundHouseName,

      // Fund Category Info
      fundCategory: isSif ? selectedSifCategoryData : selectedFundCategoryData,
      fundCategoryCode: selectedFundCategory,
      fundCategoryName: categoryName,

      // Scheme Info
      scheme: selectedSchemeData,
      schemeCode: selectedScheme,
      schemeName: schemeName,

      // Amount Info - SIMPLE CALCULATION
      monthlyAmount: investmentAmount, // This will be the monthly SIP amount (autofilled from Enter Investment Amount)
      initialAmount: '0', // Optional initial amount - set to 0 by default

      // Other Info
      type: 'sip',
      category: category,
      categoryTitle: categoryInfo?.title,
      settlementType: selectedSettlementType,
      minInvestmentAmount: minInvestmentAmount
    };

    // Clear any existing data first
    localStorage.removeItem('investment_data');

    // Save the new data
    localStorage.setItem('investment_data', JSON.stringify(investmentData));

    // Verify data was saved
    const savedData = localStorage.getItem('investment_data');
    // console.log('Investment data saved for SIP:', JSON.parse(savedData));

    // Navigate to SIP page
    router.push('/invest/sip');
  };

  const handleBuy = async () => {
    if (!investmentAmount || parseFloat(investmentAmount) <= 0) {
      alert('Enter a valid investment amount');
      return;
    }

    if (!isHydrated) {
      alert('Please wait for the page to fully load');
      return;
    }

    try {
      setBuyLoading(true);
      const token = localStorage.getItem('site_token');

      if (!token) {
        alert('Authentication token not found. Please log in again.');
        return;
      }

      const payload = {
        fund_house_code: selectedFundHouse,
        fund_category_code: selectedFundCategory,
        scheme_code: selectedScheme,
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
        alert(`Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.\nScheme: ${selectedSchemeData?.product_long_name || selectedSchemeData?.scheme_name}\nAmount: ₹${investmentAmount}\nOrder ID: ${data.data?.order_id || 'N/A'}`);
        handleClear();
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

  const handleClear = () => {
    setSelectedFundHouse('');
    setSelectedFundCategory('');
    setSelectedSettlementType('NRM');
    setSelectedScheme('');
    setInvestmentAmount('');
    setShowInvestmentPanel(false);
    setMinInvestmentAmount(100);
    setError('');
    setSifError('');
  };

  const isProceedEnabled = selectedFundHouse && selectedFundCategory && (category === 'sif' || selectedSettlementType) && selectedScheme;

  if (!categoryInfo) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  // Determine which dropdowns to show based on category
  const isSifCategory = category === 'sif';
  const currentCategories = isSifCategory ? sifCategories : fundCategories;
  const currentCategoriesLoading = isSifCategory ? sifCategoriesLoading : categoriesLoading;
  const currentSchemes = isSifCategory ? sifFunds : schemes;
  const currentSchemesLoading = isSifCategory ? sifFundsLoading : schemesLoading;
  const currentError = isSifCategory ? sifError : error;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Back Button Row */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <button
            onClick={() => router.push('/invest')}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to All Funds
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Category Header */}
        <div className={`${categoryInfo.color} rounded-2xl p-8 text-white mb-8`}>
          <div>
            <h1 className="text-3xl font-bold mb-2">
              <TypedText text={`${categoryInfo.title} Funds`} className="text-white" />
            </h1>
            <p className="text-white/90 text-lg">{categoryInfo.description}</p>
          </div>
        </div>

        {/* Error Display */}
        {currentError && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p className="text-red-700">{currentError}</p>
          </div>
        )}

        {/* Investment Selection Form */}
        <div className="bg-white rounded-xl shadow-lg p-8 mb-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Select Your Investment</h2>

          <div className="space-y-8">
            {/* Fund House Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                1. Select Fund House <span className="text-red-500">*</span>
              </label>
              <select
                value={selectedFundHouse}
                onChange={(e) => setSelectedFundHouse(e.target.value)}
                disabled={fundHousesLoading}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
              >
                <option value="">{fundHousesLoading ? 'Loading...' : 'Select Fund House'}</option>
                {fundHouses.map((house, index) => (
                  <option key={index} value={house.value}>
                    {house.label}
                  </option>
                ))}
              </select>
              {fundHousesLoading && (
                <p className="text-sm text-blue-600 mt-2">Loading fund houses...</p>
              )}
            </div>

            {/* Fund Category Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                2. Select Fund Category <span className="text-red-500">*</span>
              </label>
              <select
                value={selectedFundCategory}
                onChange={(e) => setSelectedFundCategory(e.target.value)}
                disabled={!selectedFundHouse || currentCategoriesLoading}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
              >
                <option value="">
                  {!selectedFundHouse ? 'Select fund house first' :
                    currentCategoriesLoading ? 'Loading...' :
                      currentCategories.length === 0 ? 'No categories available' : 'Select Fund Category'}
                </option>
                {currentCategories.map((cat, index) => (
                  <option key={index} value={cat.value}>
                    {cat.label}
                  </option>
                ))}
              </select>
              {currentCategoriesLoading && (
                <p className="text-sm text-blue-600 mt-2">Loading categories...</p>
              )}
            </div>

            {/* Settlement Type Selection */}
            {category !== 'sif' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  3. Select Settlement Type <span className="text-red-500">*</span>
                </label>
                <select
                  value={selectedSettlementType}
                  onChange={(e) => setSelectedSettlementType(e.target.value)}
                  disabled={!selectedFundCategory}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                  <option value="L0">L0 - Liquid orders - any amount</option>
                  <option value="L1">L1 - More than 2 lakhs order - equity/debt</option>
                  <option value="NRM">NRM - Less than 2 lakhs orders - equity/debt</option>
                  <option value="NFO">NFO - New Fund Offer</option>
                </select>
              </div>
            )}

            {/* Scheme Name Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-3">
                {category === 'sif' ? '3.' : '4.'} Select Scheme Name <span className="text-red-500">*</span>
              </label>
              <select
                value={selectedScheme}
                onChange={(e) => setSelectedScheme(e.target.value)}
                disabled={!selectedFundCategory || (category !== 'sif' && !selectedSettlementType) || currentSchemesLoading}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
              >
                <option value="">
                  {!selectedFundCategory ? 'Select fund category first' :
                    (category !== 'sif' && !selectedSettlementType) ? 'Select settlement type first' :
                      currentSchemesLoading ? 'Loading...' :
                        currentSchemes.length === 0 ? 'No schemes available' : 'Select Scheme'}
                </option>
                {currentSchemes.map((scheme, index) => (
                  <option key={index} value={scheme.value}>
                    {scheme.label}
                  </option>
                ))}
              </select>
              {currentSchemesLoading && (
                <p className="text-sm text-blue-600 mt-2">Loading schemes...</p>
              )}
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex justify-between items-center mt-12 pt-8 border-t border-gray-200">
            <button
              onClick={() => router.back()}
              className="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors flex items-center"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back
            </button>

            <div className="flex gap-4">
              <button
                onClick={handleClear}
                className="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-lg transition-colors"
              >
                Clear All
              </button>
              <button
                onClick={handleProceed}
                disabled={!isProceedEnabled}
                className={`px-8 py-3 font-medium rounded-lg transition-colors flex items-center ${isProceedEnabled
                    ? 'bg-blue-600 hover:bg-blue-700 text-white'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  }`}
              >
                Proceed to Invest
                <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        {/* Investment Panel (shown after clicking Proceed) */}
        {showInvestmentPanel && selectedSchemeData && (
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
                  <p className="font-bold text-gray-900 break-words text-sm">
                    {fundHouses.find(h => h.value === selectedFundHouse)?.label || selectedFundHouseData?.long_name || selectedFundHouse}
                  </p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Fund Category</h4>
                  <p className="font-bold text-gray-900 break-words text-sm">
                    {currentCategories.find(c => c.value === selectedFundCategory)?.label ||
                      (isSifCategory ? selectedSifCategoryData?.details : selectedFundCategoryData?.details) ||
                      selectedFundCategory}
                  </p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Settlement Type</h4>
                  <p className="font-bold text-gray-900">{selectedSettlementType}</p>
                  <p className="text-sm text-gray-600 mt-2">
                    {selectedSettlementType === 'L0' ? 'Liquid orders' :
                      selectedSettlementType === 'L1' ? '> 2 lakhs order' :
                        selectedSettlementType === 'NRM' ? '< 2 lakhs order' :
                          selectedSettlementType === 'NFO' ? 'New Fund Offer' : ''}
                  </p>
                </div>

                <div className="bg-white p-4 rounded-lg border">
                  <h4 className="text-sm font-medium text-gray-500 mb-2">Scheme</h4>
                  <p className="font-bold text-gray-900 break-words text-sm">
                    {currentSchemes.find(s => s.value === selectedScheme)?.label ||
                      selectedSchemeData?.product_long_name ||
                      selectedSchemeData?.scheme_name ||
                      selectedScheme}
                  </p>
                </div>
              </div>
            </div>

            {/* Investment Amount Input - This amount will autofill Monthly SIP Amount */}
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
                onClick={handleSIP}
                disabled={!investmentAmount || parseFloat(investmentAmount) < minInvestmentAmount}
                className={`flex-1 py-4 font-bold rounded-lg transition-colors flex flex-col items-center justify-center ${investmentAmount && parseFloat(investmentAmount) >= minInvestmentAmount
                    ? 'bg-green-600 hover:bg-green-700 text-white'
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  }`}
              >
                <span className="text-xl">Start SIP</span>
                <span className="text-sm mt-1">Systematic Investment Plan</span>
              </button>

              <button
                onClick={handleBuy}
                disabled={!investmentAmount || parseFloat(investmentAmount) < minInvestmentAmount || buyLoading}
                className={`flex-1 py-4 font-bold rounded-lg transition-colors flex flex-col items-center justify-center ${investmentAmount && parseFloat(investmentAmount) >= minInvestmentAmount && !buyLoading
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
                onClick={() => setShowInvestmentPanel(false)}
                className="text-gray-600 hover:text-gray-800 font-medium"
              >
                ← Back to selection
              </button>
            </div>
          </div>
        )}

        {/* Important Information */}
        <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
          <h3 className="font-bold text-gray-900 mb-4 flex items-center">
            <svg className="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            Important Information
          </h3>
          <ul className="space-y-2 text-sm text-gray-700">
            <li className="flex items-start">
              <span className="text-yellow-600 mr-2">•</span>
              <span>All mutual fund investments are subject to market risks, read all scheme related documents carefully.</span>
            </li>
            <li className="flex items-start">
              <span className="text-yellow-600 mr-2">•</span>
              <span>Past performance is not indicative of future returns.</span>
            </li>
            <li className="flex items-start">
              <span className="text-yellow-600 mr-2">•</span>
              <span>Investments in mutual funds are subject to NAV fluctuations.</span>
            </li>
            <li className="flex items-start">
              <span className="text-yellow-600 mr-2">•</span>
              <span>Please consult with your financial advisor before making any investment decisions.</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  );
}

// Static Generation: Generate all possible category pages at build time
export async function generateStaticParams() {
  const categories = [
    'equity',
    'gold',
    'fof',
    'debt',
    'hybrid',
    'arbitrage',
    'tax-saving',
    'etf',
    'sif'
  ];

  return categories.map((category) => ({
    category,
  }));
}