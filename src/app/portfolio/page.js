'use client';

import React, { useState, useEffect, useRef, Fragment } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

export default function PortfolioPage() {
  const router = useRouter();
  const hasFetched = useRef(false);

  const parseFormattedFloat = (val) => {
    if (val === undefined || val === null || val === '') return 0;
    const str = val.toString().replace(/,/g, '');
    const num = parseFloat(str);
    return isNaN(num) ? 0 : num;
  };

  const shouldShowSubtext = (holding) => {
    if (!holding) return false;

    // Check if it is a mutual fund
    const isMutualFund = !holding.asset_type || holding.asset_type === 'Mutual Fund' || holding.fund_house_code || holding.fund_code;
    if (isMutualFund) {
      return false;
    }

    // Check if it is a bond (to hide the subtext 'Bond')
    if (holding.asset_type === 'Bond') {
      return false;
    }

    // Check for raw AMC/mutual fund patterns in fund_house or issuer
    const primaryVal = holding.fund_house || holding.issuer;
    if (primaryVal) {
      const upperVal = primaryVal.toString().toUpperCase();
      if (
        upperVal.includes('MUTUALFUND') ||
        upperVal.endsWith('_MF') ||
        upperVal.endsWith('MF') ||
        upperVal === 'BOND'
      ) {
        return false;
      }
    }

    return true;
  };

  // State for portfolio data
  const [portfolioData, setPortfolioData] = useState(null);
  const [rawApiData, setRawApiData] = useState(null);
  const [selectedPortfolioType, setSelectedPortfolioType] = useState('individual');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // State for filters and sorting
  const [activeTab, setActiveTab] = useState('all');
  const [sortBy, setSortBy] = useState('current_value');
  const [sortOrder, setSortOrder] = useState('desc');

  // State for expanded holdings
  const [expandedHoldings, setExpandedHoldings] = useState([]);

  // State for visualization type
  const [visualizationType, setVisualizationType] = useState('donut'); // 'donut' or 'progress'

  // Fetch portfolio data from grand_folio_api.php
  const fetchPortfolioData = async () => {
    try {
      setLoading(true);
      setError(null);

      const token = localStorage.getItem('site_token');
      if (!token) {
        router.push('/login');
        return;
      }

      const response = await fetch(`${API_BASE_URL}/grand_folio_api.php`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log('Portfolio API Data:', data);
      if (data.Success) {
        setRawApiData(data);
        // Transform the API response to match our component structure
        const transformedData = transformPortfolioData(data);
        console.log('transformed data', transformedData);
        setPortfolioData(transformedData);
      } else {
        setError(data.Message || 'Failed to load portfolio data');
      }
    } catch (error) {
      console.error('Error fetching portfolio:', error);
      setError(error.message || 'Failed to load portfolio. Please try again.');
    } finally {
      setLoading(false);
    }
  };
  // Analyze Portfolio function
  const analyzePortfolio = () => {
    if (!portfolioData?.holdings?.length) {
      alert('No portfolio data available for analysis');
      return;
    }

    // Navigate to analysis page with portfolio data
    const analysisData = generatePortfolioAnalysis(portfolioData);
    localStorage.setItem('portfolio_analysis', JSON.stringify(analysisData));
    router.push('/portfolio/analysis');
  };
  // Transform API response to match our component structure
  // Transform API response to match our component structure
  const transformPortfolioData = (apiData) => {

    // Process mutual fund holdings from portfolioInfo
    const mutualFundHoldings = [];

    if (apiData.portfolioInfo) {
      Object.entries(apiData.portfolioInfo).forEach(([fundHouseCode, funds]) => {
        if (Array.isArray(funds)) {
          funds.forEach(fund => {
            Object.entries(fund).forEach(([fundCode, fundDetails]) => {
              mutualFundHoldings.push({
                id: fundCode,
                client_code: apiData.client_code,
                fund_house_code: fundHouseCode,
                fund_code: fundCode,
                scheme_name: fundDetails.Fund_Name || 'Unknown Scheme',
                fund_house: fundDetails.Fund_House_Name || 'Unknown AMC',
                asset_class: fundDetails.Asset_Class || 'EQUITY',
                units: parseFormattedFloat(fundDetails.Unit_Holdings),
                cost_nav: parseFormattedFloat(fundDetails.Cost_NAV),
                cost_value: parseFormattedFloat(fundDetails.Cost_Value),
                current_nav: parseFormattedFloat(fundDetails.Current_NAV),
                nav_date: fundDetails.NAV_Date || '',
                current_value: parseFormattedFloat(fundDetails.Curr_Value),
                payout_received: parseFormattedFloat(fundDetails.PayoutRecvd),
                realized_profit: parseFormattedFloat(fundDetails.RealizedProfit),
                unrealized_profit: parseFormattedFloat(fundDetails.UnRealizedProfit),
                xirr: parseFormattedFloat(fundDetails.XIRR)
              });
            });
          });
        }
      });
    }

    // Process bond holdings
    const bondHoldings = [];
    if (apiData.HoldingInfoBonds) {
      Object.entries(apiData.HoldingInfoBonds).forEach(([bondIssuer, bondList]) => {
        if (Array.isArray(bondList)) {
          bondList.forEach(bondGroup => {
            Object.entries(bondGroup).forEach(([bondName, bondDetails]) => {
              bondHoldings.push({
                id: `bond-${bondName}`,
                client_code: apiData.client_code,
                asset_type: 'Bond',
                sub_type: bondIssuer || 'Bond',
                scheme_name: bondDetails.bondName || bondName,
                asset_class: bondDetails.assetClass || 'Gold',
                units: parseFormattedFloat(bondDetails.unitHoldings),
                cost_nav: parseFormattedFloat(bondDetails.costNAV),
                cost_value: parseFormattedFloat(bondDetails.costValue),
                current_nav: parseFormattedFloat(bondDetails.currentNAV),
                nav_date: bondDetails.navDate || '',
                current_value: parseFormattedFloat(bondDetails.currValue),
                payout_received: parseFormattedFloat(bondDetails.payoutRecvd),
                realized_profit: parseFormattedFloat(bondDetails.realizedProfit),
                unrealized_profit: parseFormattedFloat(bondDetails.unRealizedProfit),
                xirr: parseFormattedFloat(bondDetails.XIRR)
              });
            });
          });
        }
      });
    }

    // Process AIF holdings
    const aifHoldings = [];
    if (apiData.HoldingInfoAif) {
      Object.entries(apiData.HoldingInfoAif).forEach(([aifIssuer, aifList]) => {
        if (Array.isArray(aifList)) {
          aifList.forEach(aifFund => {
            Object.entries(aifFund).forEach(([aifName, aifDetails]) => {
              if (typeof aifDetails === 'object' && aifDetails.aifName) {
                aifHoldings.push({
                  id: `aif-${aifName}`,
                  client_code: apiData.client_code,
                  asset_type: 'AIF',
                  issuer: aifIssuer,
                  scheme_name: aifDetails.aifName || aifName,
                  asset_class: aifDetails.assetClass || 'Debt',
                  units: parseFormattedFloat(aifDetails.units),
                  cost_nav: parseFormattedFloat(aifDetails.costNAV),
                  cost_value: parseFormattedFloat(aifDetails.costValue),
                  current_nav: parseFormattedFloat(aifDetails.currNAV),
                  nav_date: aifDetails.navDate || '',
                  current_value: parseFormattedFloat(aifDetails.currValue),
                  payout_received: parseFormattedFloat(aifDetails.payoutRecvd),
                  realized_profit: parseFormattedFloat(aifDetails.realizedProfit),
                  unrealized_profit: parseFormattedFloat(aifDetails.unRealizedProfit),
                  xirr: parseFormattedFloat(aifDetails.XIRR)
                });
              }
            });
          });
        }
      });
    }

    // Process PMS holdings
    const pmsHoldings = [];
    if (apiData.HoldingInfoPms) {
      Object.entries(apiData.HoldingInfoPms).forEach(([pmsIssuer, pmsList]) => {
        if (Array.isArray(pmsList)) {
          pmsList.forEach(pmsFund => {
            Object.entries(pmsFund).forEach(([pmsName, pmsDetails]) => {
              if (typeof pmsDetails === 'object' && pmsDetails.pmsName) {
                pmsHoldings.push({
                  id: `pms-${pmsName}`,
                  client_code: apiData.client_code,
                  asset_type: 'PMS',
                  issuer: pmsIssuer || 'PMS',
                  scheme_name: pmsDetails.pmsName || pmsName,
                  asset_class: pmsDetails.assetClass || 'Equity',
                  units: 1, // Default units to 1 for PMS
                  cost_nav: parseFormattedFloat(pmsDetails.totalPurchaseCost),
                  cost_value: parseFormattedFloat(pmsDetails.totalPurchaseCost),
                  current_nav: parseFormattedFloat(pmsDetails.currValue),
                  nav_date: pmsDetails.navDate || '',
                  current_value: parseFormattedFloat(pmsDetails.currValue),
                  payout_received: 0,
                  realized_profit: parseFormattedFloat(pmsDetails.totalSellValue),
                  unrealized_profit: parseFormattedFloat(pmsDetails.currValue) - parseFormattedFloat(pmsDetails.totalPurchaseCost),
                  xirr: parseFormattedFloat(pmsDetails.XIRR)
                });
              }
            });
          });
        }
      });
    }

    // Process PMS sold info
    const soldPmsHoldings = [];
    if (apiData.SoldPmsInfo && Array.isArray(apiData.SoldPmsInfo)) {
      apiData.SoldPmsInfo.forEach(pms => {
        soldPmsHoldings.push({
          id: `sold-pms-${Math.random()}`,
          client_code: apiData.client_code,
          asset_type: 'PMS',
          status: 'Sold',
          scheme_name: pms.Pms_Name || 'Unknown PMS',
          issuer: pms.Pms_Issuer_Name || 'Unknown Issuer',
          asset_class: pms.Asset_Class || 'Equity',
          xirr: parseFormattedFloat(pms.XIRR)
        });
      });
    }

    // Process AIF sold info
    const soldAifHoldings = [];
    if (apiData.SoldAifInfo && Array.isArray(apiData.SoldAifInfo)) {
      apiData.SoldAifInfo.forEach(aif => {
        soldAifHoldings.push({
          id: `sold-aif-${Math.random()}`,
          client_code: apiData.client_code,
          asset_type: 'AIF',
          status: 'Sold',
          scheme_name: aif.Aif_Name || 'Unknown AIF',
          issuer: aif.Aif_Issuer_Name || 'Unknown Issuer',
          asset_class: aif.Asset_Class || 'Equity',
          units: parseFormattedFloat(aif.Unit_Holdings),
          payout_received: parseFormattedFloat(aif.PayoutRecvd),
          realized_profit: parseFormattedFloat(aif.RealizedProfit),
          xirr: parseFormattedFloat(aif.XIRR)
        });
      });
    }

    // Process SIF holdings
    const sifHoldings = [];
    if (apiData.HoldingInfoSif) {
      Object.entries(apiData.HoldingInfoSif).forEach(([fundHouseCode, funds]) => {
        if (Array.isArray(funds)) {
          funds.forEach(fund => {
            Object.entries(fund).forEach(([fundCode, fundDetails]) => {
              sifHoldings.push({
                id: `sif-${fundCode}`,
                client_code: apiData.client_code,
                asset_type: 'SIF',
                fund_house_code: fundHouseCode,
                fund_code: fundCode,
                scheme_name: fundDetails.Fund_Name || 'Unknown Scheme',
                fund_house: fundDetails.Fund_House_Name || 'Unknown AMC',
                asset_class: fundDetails.Asset_Class || 'SIF',
                units: parseFormattedFloat(fundDetails.Unit_Holdings),
                cost_nav: parseFormattedFloat(fundDetails.Cost_NAV),
                cost_value: parseFormattedFloat(fundDetails.Cost_Value),
                current_nav: parseFormattedFloat(fundDetails.Current_NAV),
                nav_date: fundDetails.NAV_Date || '',
                current_value: parseFormattedFloat(fundDetails.Curr_Value),
                payout_received: parseFormattedFloat(fundDetails.PayoutRecvd),
                realized_profit: parseFormattedFloat(fundDetails.RealizedProfit),
                unrealized_profit: parseFormattedFloat(fundDetails.UnRealizedProfit),
                xirr: parseFormattedFloat(fundDetails.XIRR)
              });
            });
          });
        }
      });
    }

    // Process SIF sold info
    const soldSifHoldings = [];
    if (apiData.SoldSifInfo && Array.isArray(apiData.SoldSifInfo)) {
      apiData.SoldSifInfo.forEach(sif => {
        soldSifHoldings.push({
          id: `sold-sif-${Math.random()}`,
          client_code: apiData.client_code,
          asset_type: 'SIF',
          status: 'Sold',
          scheme_name: sif.Fund_Name || 'Unknown SIF',
          issuer: sif.Fund_House_Name || 'Unknown Issuer',
          asset_class: sif.Asset_Class || 'SIF',
          units: parseFormattedFloat(sif.Unit_Holdings),
          payout_received: parseFormattedFloat(sif.PayoutRecvd),
          realized_profit: parseFormattedFloat(sif.RealizedProfit),
          xirr: parseFormattedFloat(sif.XIRR)
        });
      });
    }

    // Combine all active holdings
    const allHoldings = [...mutualFundHoldings, ...bondHoldings, ...aifHoldings, ...pmsHoldings, ...sifHoldings];

    // Process total asset info
    const totalAssetInfo = apiData.totalAssetInfo?.[0] || {};
    const rawTotalHoldingsMF = apiData.totalHoldingsMF?.[0] || {};

    // Calculate total mutual fund current value by summing all asset classes
    const totalMFCurrValue =
      parseFormattedFloat(rawTotalHoldingsMF.Total_Equity_Curr_Value) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Debt_Curr_Value) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Hybrid_Curr_Value) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Gold_Curr_Value);

    const totalMFCostValue =
      parseFormattedFloat(rawTotalHoldingsMF.Total_Equity_Cost) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Debt_Cost) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Hybrid_Cost) +
      parseFormattedFloat(rawTotalHoldingsMF.Total_Gold_Cost);

    // Calculate weighted return for mutual funds
    let totalMFReturn = 0;
    if (totalMFCurrValue > 0) {
      totalMFReturn = (
        (parseFormattedFloat(rawTotalHoldingsMF.Total_Equity_Curr_Value) * parseFormattedFloat(rawTotalHoldingsMF.Total_Equity_Return)) +
        (parseFormattedFloat(rawTotalHoldingsMF.Total_Debt_Curr_Value) * parseFormattedFloat(rawTotalHoldingsMF.Total_Debt_Return)) +
        (parseFormattedFloat(rawTotalHoldingsMF.Total_Hybrid_Curr_Value) * parseFormattedFloat(rawTotalHoldingsMF.Total_Hybrid_Return)) +
        (parseFormattedFloat(rawTotalHoldingsMF.Total_Gold_Curr_Value) * parseFormattedFloat(rawTotalHoldingsMF.Total_Gold_Return))
      ) / totalMFCurrValue;
    }

    const totalHoldingsMF = {
      ...rawTotalHoldingsMF,
      Total_Equity_Curr_Value: totalMFCurrValue, // Set to total current value
      Total_Equity_Return: totalMFReturn // Set to total weighted return
    };

    const totalBondsInfo = apiData.TotalHoldingInfoBonds?.[0] || {};
    const totalAifInfo = apiData.TotalHoldingInfoAif?.[0] || {};
    const totalPmsInfo = apiData.TotalHoldingInfoPms?.[0] || {};
    const totalSifInfo = apiData.TotalHoldingInfoSif?.[0] || {};

    // Calculate summary
    const calculateSummary = () => {
      const totalInvestment = parseFormattedFloat(totalAssetInfo.Total_Cost_Value);
      const currentValue = parseFormattedFloat(totalAssetInfo.Total_Current_Value);
      const totalGain = parseFormattedFloat(totalAssetInfo.Total_Unrealized);
      const totalGainPercentage = totalInvestment > 0 ? (totalGain / totalInvestment) * 100 : 0;

      return {
        totalInvestment,
        currentValue,
        totalGain,
        totalGainPercentage,
        assetAllocation: {
          Equity: parseFormattedFloat(totalAssetInfo.Total_Equity_Curr_Value),
          Debt: parseFormattedFloat(totalAssetInfo.Total_Debt_Curr_Value),
          Gold: parseFormattedFloat(totalAssetInfo.Total_Gold_Curr_Value),
          Hybrid: parseFormattedFloat(totalAssetInfo.Total_Hybrid_Curr_Value)
        },
        returns: {
          Equity: parseFormattedFloat(totalAssetInfo.Total_Equity_Return),
          Debt: parseFormattedFloat(totalAssetInfo.Total_Debt_Return),
          Gold: parseFormattedFloat(totalAssetInfo.Total_Gold_Return),
          Hybrid: parseFormattedFloat(totalAssetInfo.Total_Hybrid_Return),
          Total: parseFormattedFloat(totalAssetInfo.Total_Holding_Return)
        }
      };
    };

    const summary = calculateSummary();

    return {
      holdings: allHoldings,
      mutualFunds: mutualFundHoldings,
      bonds: bondHoldings,
      aif: aifHoldings,
      pms: pmsHoldings,
      sif: sifHoldings,
      soldPms: soldPmsHoldings,
      soldAif: soldAifHoldings,
      soldSif: soldSifHoldings,
      summary: {
        totalInvestment: summary.totalInvestment,
        currentValue: summary.currentValue,
        totalGain: summary.totalGain,
        totalGainPercentage: summary.totalGainPercentage,
        assetAllocation: summary.assetAllocation,
        returns: summary.returns,
        mutualFundCount: mutualFundHoldings.length,
        bondCount: bondHoldings.length,
        aifCount: aifHoldings.length,
        pmsCount: pmsHoldings.length,
        sifCount: sifHoldings.length,
        totalHoldings: allHoldings.length
      },
      detailedStats: {
        totalAssetInfo,
        totalHoldingsMF,
        totalBondsInfo,
        totalAifInfo,
        totalPmsInfo,
        totalSifInfo
      },
      zeroHoldings: apiData.zeroHoldingInfo || [],
      goals: apiData.goalsFundInfo || [],
      families: apiData.families || '',
      apiData: apiData
    };
  };

  const mergeHoldingsList = (holdingsList) => {
    const merged = {};
    holdingsList.forEach(h => {
      const key = `${h.asset_type || 'MF'}-${h.scheme_name}`;
      if (!merged[key]) {
        merged[key] = { ...h };
      } else {
        const existing = merged[key];
        existing.units += h.units;
        existing.cost_value += h.cost_value;
        existing.current_value += h.current_value;
        existing.payout_received += h.payout_received;
        existing.realized_profit += h.realized_profit;
        existing.unrealized_profit += h.unrealized_profit;

        const totalVal = existing.current_value + h.current_value;
        if (totalVal > 0) {
          existing.xirr = ((existing.current_value * existing.xirr) + (h.current_value * h.xirr)) / totalVal;
        }
      }
    });

    return Object.values(merged).map(h => {
      if (h.units > 0) {
        h.cost_nav = h.cost_value / h.units;
      }
      return h;
    });
  };

  const consolidateFamilyPortfolios = (memberPortfolios) => {
    const consolidated = {
      holdings: [],
      mutualFunds: [],
      bonds: [],
      aif: [],
      pms: [],
      sif: [],
      soldPms: [],
      soldAif: [],
      soldSif: [],
      summary: {
        totalInvestment: 0,
        currentValue: 0,
        totalGain: 0,
        totalGainPercentage: 0,
        assetAllocation: { Equity: 0, Debt: 0, Gold: 0, Hybrid: 0 },
        returns: { Equity: 0, Debt: 0, Gold: 0, Hybrid: 0, Total: 0 },
        mutualFundCount: 0,
        bondCount: 0,
        aifCount: 0,
        pmsCount: 0,
        sifCount: 0,
        totalHoldings: 0
      },
      detailedStats: {
        totalAssetInfo: {},
        totalHoldingsMF: {},
        totalBondsInfo: {},
        totalAifInfo: {},
        totalPmsInfo: {},
        totalSifInfo: {}
      },
      zeroHoldings: [],
      goals: [],
      families: memberPortfolios[0]?.families || '',
      apiData: null
    };

    let allMutualFunds = [];
    let allBonds = [];
    let allAifs = [];
    let allPms = [];
    let allSifs = [];
    let allSoldPms = [];
    let allSoldAifs = [];
    let allSoldSifs = [];
    let allZeroHoldings = [];
    let allGoals = [];

    let totalEquityVal = 0;
    let totalDebtVal = 0;
    let totalGoldVal = 0;
    let totalHybridVal = 0;

    let weightedEquityReturnSum = 0;
    let weightedDebtReturnSum = 0;
    let weightedGoldReturnSum = 0;
    let weightedHybridReturnSum = 0;
    let weightedTotalReturnSum = 0;

    let weightedMFReturnSum = 0;
    let weightedBondReturnSum = 0;
    let weightedAifReturnSum = 0;
    let weightedPmsReturnSum = 0;
    let weightedSifReturnSum = 0;

    let totalMFCurrSum = 0;
    let totalBondCurrSum = 0;
    let totalAifCurrSum = 0;
    let totalPmsCurrSum = 0;
    let totalSifCurrSum = 0;

    memberPortfolios.forEach(p => {
      allMutualFunds = [...allMutualFunds, ...p.mutualFunds];
      allBonds = [...allBonds, ...p.bonds];
      allAifs = [...allAifs, ...p.aif];
      allPms = [...allPms, ...p.pms];
      allSifs = [...allSifs, ...p.sif];
      allSoldPms = [...allSoldPms, ...p.soldPms];
      allSoldAifs = [...allSoldAifs, ...p.soldAif];
      allSoldSifs = [...allSoldSifs, ...p.soldSif];
      allZeroHoldings = [...allZeroHoldings, ...p.zeroHoldings];
      allGoals = [...allGoals, ...p.goals];

      const s = p.summary;
      consolidated.summary.totalInvestment += s.totalInvestment;
      consolidated.summary.currentValue += s.currentValue;
      consolidated.summary.totalGain += s.totalGain;

      consolidated.summary.assetAllocation.Equity += s.assetAllocation.Equity;
      consolidated.summary.assetAllocation.Debt += s.assetAllocation.Debt;
      consolidated.summary.assetAllocation.Gold += s.assetAllocation.Gold;
      consolidated.summary.assetAllocation.Hybrid += s.assetAllocation.Hybrid;

      totalEquityVal += s.assetAllocation.Equity;
      totalDebtVal += s.assetAllocation.Debt;
      totalGoldVal += s.assetAllocation.Gold;
      totalHybridVal += s.assetAllocation.Hybrid;

      weightedEquityReturnSum += s.assetAllocation.Equity * (s.returns.Equity || 0);
      weightedDebtReturnSum += s.assetAllocation.Debt * (s.returns.Debt || 0);
      weightedGoldReturnSum += s.assetAllocation.Gold * (s.returns.Gold || 0);
      weightedHybridReturnSum += s.assetAllocation.Hybrid * (s.returns.Hybrid || 0);

      weightedTotalReturnSum += s.currentValue * (s.returns.Total || 0);

      const ds = p.detailedStats || {};
      if (ds.totalHoldingsMF) {
        const mfCurr = parseFormattedFloat(ds.totalHoldingsMF.Total_Equity_Curr_Value || 0);
        const mfRet = parseFormattedFloat(ds.totalHoldingsMF.Total_Equity_Return || 0);
        totalMFCurrSum += mfCurr;
        weightedMFReturnSum += mfCurr * mfRet;
      }
      if (ds.totalBondsInfo) {
        const bondCurr = parseFormattedFloat(ds.totalBondsInfo.Total_Bond_Current_Value || 0);
        const bondRet = parseFormattedFloat(ds.totalBondsInfo.Total_Bond_Return || 0);
        totalBondCurrSum += bondCurr;
        weightedBondReturnSum += bondCurr * bondRet;
      }
      if (ds.totalAifInfo) {
        const aifCurr = parseFormattedFloat(ds.totalAifInfo.Total_Aif_Current_Value || 0);
        const aifRet = parseFormattedFloat(ds.totalAifInfo.Total_Aif_Return || 0);
        totalAifCurrSum += aifCurr;
        weightedAifReturnSum += aifCurr * aifRet;
      }
      if (ds.totalPmsInfo) {
        const pmsCurr = parseFormattedFloat(ds.totalPmsInfo.Total_Pms_Current_Value || 0);
        const pmsRet = parseFormattedFloat(ds.totalPmsInfo.Total_Pms_Return || 0);
        totalPmsCurrSum += pmsCurr;
        weightedPmsReturnSum += pmsCurr * pmsRet;
      }
      if (ds.totalSifInfo) {
        const sifCurr = parseFormattedFloat(ds.totalSifInfo.Total_Sif_Current_Value || 0);
        const sifRet = parseFormattedFloat(ds.totalSifInfo.Total_Sif_Return || 0);
        totalSifCurrSum += sifCurr;
        weightedSifReturnSum += sifCurr * sifRet;
      }
    });

    consolidated.mutualFunds = mergeHoldingsList(allMutualFunds);
    consolidated.bonds = mergeHoldingsList(allBonds);
    consolidated.aif = mergeHoldingsList(allAifs);
    consolidated.pms = mergeHoldingsList(allPms);
    consolidated.sif = mergeHoldingsList(allSifs);
    consolidated.holdings = [
      ...consolidated.mutualFunds,
      ...consolidated.bonds,
      ...consolidated.aif,
      ...consolidated.pms,
      ...consolidated.sif
    ];
    consolidated.soldPms = allSoldPms;
    consolidated.soldAif = allSoldAifs;
    consolidated.soldSif = allSoldSifs;
    consolidated.zeroHoldings = allZeroHoldings;
    consolidated.goals = allGoals;

    if (consolidated.summary.totalInvestment > 0) {
      consolidated.summary.totalGainPercentage = (consolidated.summary.totalGain / consolidated.summary.totalInvestment) * 100;
    }

    consolidated.summary.returns.Equity = totalEquityVal > 0 ? weightedEquityReturnSum / totalEquityVal : 0;
    consolidated.summary.returns.Debt = totalDebtVal > 0 ? weightedDebtReturnSum / totalDebtVal : 0;
    consolidated.summary.returns.Gold = totalGoldVal > 0 ? weightedGoldReturnSum / totalGoldVal : 0;
    consolidated.summary.returns.Hybrid = totalHybridVal > 0 ? weightedHybridReturnSum / totalHybridVal : 0;
    consolidated.summary.returns.Total = consolidated.summary.currentValue > 0 ? weightedTotalReturnSum / consolidated.summary.currentValue : 0;

    consolidated.summary.mutualFundCount = consolidated.mutualFunds.length;
    consolidated.summary.bondCount = consolidated.bonds.length;
    consolidated.summary.aifCount = consolidated.aif.length;
    consolidated.summary.pmsCount = consolidated.pms.length;
    consolidated.summary.sifCount = consolidated.sif.length;
    consolidated.summary.totalHoldings = consolidated.holdings.length;

    const aggAssetInfo = {};
    const aggHoldingsMF = {};
    const aggBondsInfo = {};
    const aggAifInfo = {};
    const aggPmsInfo = {};
    const aggSifInfo = {};

    const sumDetailedKeys = (target, source, keys) => {
      keys.forEach(k => {
        const sVal = source[k];
        if (sVal !== undefined && sVal !== null) {
          const val = typeof sVal === 'string' ? parseFloat(sVal.replace(/,/g, '')) : parseFloat(sVal);
          if (!isNaN(val)) {
            target[k] = (target[k] || 0) + val;
          }
        }
      });
    };

    memberPortfolios.forEach(p => {
      const ds = p.detailedStats || {};
      if (ds.totalAssetInfo) {
        sumDetailedKeys(aggAssetInfo, ds.totalAssetInfo, [
          'Total_Cost_Value', 'Total_Current_Value', 'Total_Unrealized',
          'Total_Equity_Curr_Value', 'Total_Debt_Curr_Value', 'Total_Gold_Curr_Value', 'Total_Hybrid_Curr_Value'
        ]);
      }
      if (ds.totalHoldingsMF) {
        sumDetailedKeys(aggHoldingsMF, ds.totalHoldingsMF, [
          'Total_Equity_Cost', 'Total_Debt_Cost', 'Total_Hybrid_Cost', 'Total_Gold_Cost',
          'Total_Equity_Curr_Value', 'Total_Debt_Curr_Value', 'Total_Hybrid_Curr_Value', 'Total_Gold_Curr_Value'
        ]);
      }
      if (ds.totalBondsInfo) {
        sumDetailedKeys(aggBondsInfo, ds.totalBondsInfo, [
          'Total_Bond_Cost', 'Total_Bond_Current_Value', 'Total_Bond_Unrealized'
        ]);
      }
      if (ds.totalAifInfo) {
        sumDetailedKeys(aggAifInfo, ds.totalAifInfo, [
          'Total_Aif_Cost', 'Total_Aif_Current_Value', 'Total_Aif_Unrealized'
        ]);
      }
      if (ds.totalPmsInfo) {
        sumDetailedKeys(aggPmsInfo, ds.totalPmsInfo, [
          'Total_Pms_Cost', 'Total_Pms_Current_Value', 'Total_Pms_Unrealized'
        ]);
      }
      if (ds.totalSifInfo) {
        sumDetailedKeys(aggSifInfo, ds.totalSifInfo, [
          'Total_Sif_Cost', 'Total_Sif_Current_Value', 'Total_Sif_Unrealized'
        ]);
      }
    });

    aggHoldingsMF.Total_Equity_Return = totalMFCurrSum > 0 ? weightedMFReturnSum / totalMFCurrSum : 0;
    aggBondsInfo.Total_Bond_Return = totalBondCurrSum > 0 ? weightedBondReturnSum / totalBondCurrSum : 0;
    aggAifInfo.Total_Aif_Return = totalAifCurrSum > 0 ? weightedAifReturnSum / totalAifCurrSum : 0;
    aggPmsInfo.Total_Pms_Return = totalPmsCurrSum > 0 ? weightedPmsReturnSum / totalPmsCurrSum : 0;
    aggSifInfo.Total_Sif_Return = totalSifCurrSum > 0 ? weightedSifReturnSum / totalSifCurrSum : 0;

    consolidated.detailedStats = {
      totalAssetInfo: aggAssetInfo,
      totalHoldingsMF: aggHoldingsMF,
      totalBondsInfo: aggBondsInfo,
      totalAifInfo: aggAifInfo,
      totalPmsInfo: aggPmsInfo,
      totalSifInfo: aggSifInfo
    };

    return consolidated;
  };

  useEffect(() => {
    if (!rawApiData) return;

    let activeApiData = rawApiData;

    if (selectedPortfolioType === 'individual') {
      const transformedData = transformPortfolioData(rawApiData);
      setPortfolioData(transformedData);
    } else if (selectedPortfolioType === 'family_consolidated') {
      const memberPortfolios = (rawApiData.familyMembersData || []).map(member => {
        return transformPortfolioData({
          ...rawApiData,
          client_code: member.ucc,
          portfolioInfo: member.portfolioInfo,
          totalHoldingsMF: member.totalHoldingsMF,
          totalAssetInfo: member.totalAssetInfo,
          HoldingInfoBonds: member.HoldingInfoBonds,
          TotalHoldingInfoBonds: member.TotalHoldingInfoBonds,
          HoldingInfoPms: member.HoldingInfoPms,
          TotalHoldingInfoPms: member.TotalHoldingInfoPms,
          HoldingInfoAif: member.HoldingInfoAif,
          TotalHoldingInfoAif: member.TotalHoldingInfoAif,
          HoldingInfoSif: member.HoldingInfoSif,
          TotalHoldingInfoSif: member.TotalHoldingInfoSif,
          goalsFundInfo: member.goalsFundInfo,
          runningSIPInfo: member.runningSIPInfo
        });
      });

      const consolidatedData = consolidateFamilyPortfolios(memberPortfolios);
      setPortfolioData(consolidatedData);
    } else {
      const member = (rawApiData.familyMembersData || []).find(m => m.ucc === selectedPortfolioType);
      if (member) {
        const transformedData = transformPortfolioData({
          ...rawApiData,
          client_code: member.ucc,
          portfolioInfo: member.portfolioInfo,
          totalHoldingsMF: member.totalHoldingsMF,
          totalAssetInfo: member.totalAssetInfo,
          HoldingInfoBonds: member.HoldingInfoBonds,
          TotalHoldingInfoBonds: member.TotalHoldingInfoBonds,
          HoldingInfoPms: member.HoldingInfoPms,
          TotalHoldingInfoPms: member.TotalHoldingInfoPms,
          HoldingInfoAif: member.HoldingInfoAif,
          TotalHoldingInfoAif: member.TotalHoldingInfoAif,
          HoldingInfoSif: member.HoldingInfoSif,
          TotalHoldingInfoSif: member.TotalHoldingInfoSif,
          goalsFundInfo: member.goalsFundInfo,
          runningSIPInfo: member.runningSIPInfo
        });
        setPortfolioData(transformedData);
      }
    }
    setExpandedHoldings([]);
  }, [selectedPortfolioType, rawApiData]);

  useEffect(() => {
    if (hasFetched.current) return;
    fetchPortfolioData();
    hasFetched.current = true;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Toggle expanded holding
  const toggleHoldingExpansion = (holdingId) => {
    setExpandedHoldings(prev =>
      prev.includes(holdingId)
        ? prev.filter(id => id !== holdingId)
        : [...prev, holdingId]
    );
  };

  // Filter holdings based on active tab
  const getFilteredHoldings = () => {
    if (!portfolioData?.holdings) return [];

    let filtered = [...portfolioData.holdings];

    // Apply tab filter
    if (activeTab !== 'all') {
      filtered = filtered.filter(holding => {
        const assetClass = holding.asset_class?.toLowerCase();
        switch (activeTab) {
          case 'equity':
            return assetClass === 'equity';
          case 'debt':
            return assetClass === 'debt';
          case 'gold':
            return assetClass === 'gold';
          case 'bond':
            return holding.asset_type === 'Bond';
          case 'aif':
            return holding.asset_type === 'AIF';
          case 'sif':
            return holding.asset_type === 'SIF';
          case 'pms':
            return holding.asset_type === 'PMS';
          case 'mf':
            return !holding.asset_type || holding.asset_type === 'Mutual Fund';
          default:
            return true;
        }
      });
    }

    // Apply sorting
    filtered.sort((a, b) => {
      let valueA, valueB;

      switch (sortBy) {
        case 'scheme_name':
          valueA = a.scheme_name || '';
          valueB = b.scheme_name || '';
          return sortOrder === 'desc'
            ? valueB.localeCompare(valueA)
            : valueA.localeCompare(valueB);
        case 'asset_class':
          valueA = a.asset_class || '';
          valueB = b.asset_class || '';
          return sortOrder === 'desc'
            ? valueB.localeCompare(valueA)
            : valueA.localeCompare(valueB);
        case 'xirr':
          valueA = a.xirr || 0;
          valueB = b.xirr || 0;
          break;
        case 'current_value':
        default:
          valueA = a.current_value || 0;
          valueB = b.current_value || 0;
          break;
      }

      if (sortOrder === 'desc') {
        return valueB - valueA;
      } else {
        return valueA - valueB;
      }
    });

    return filtered;
  };

  // Format currency
  const formatCurrency = (amount) => {
    if (amount === undefined || amount === null) return '₹0';

    const numAmount = typeof amount === 'string' ? parseFloat(amount.replace(/,/g, '')) : amount;

    if (numAmount >= 10000000) {
      return `₹${(numAmount / 10000000).toFixed(2)} Cr`;
    }
    if (numAmount >= 100000) {
      return `₹${(numAmount / 100000).toFixed(2)} L`;
    }
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(numAmount);
  };

  // Format percentage
  const formatPercentage = (value) => {
    if (value === undefined || value === null) return '0.00%';
    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    return `${numValue >= 0 ? '+' : ''}${numValue.toFixed(2)}%`;
  };

  // Format number with commas, preserving up to 4 decimal places for NAV precision
  const formatNumber = (num, forcedDecimals) => {
    if (num === undefined || num === null) return '0';
    const number = typeof num === 'string' ? parseFloat(num.replace(/,/g, '')) : num;

    if (forcedDecimals !== undefined) {
      return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: forcedDecimals,
        maximumFractionDigits: forcedDecimals
      }).format(number);
    }

    const decimalStr = number.toString().split('.')[1];
    const decimalPlaces = decimalStr ? Math.min(decimalStr.length, 4) : 0;

    return new Intl.NumberFormat('en-IN', {
      minimumFractionDigits: decimalPlaces,
      maximumFractionDigits: Math.max(decimalPlaces, 3)
    }).format(number);
  };

  // Get asset class color
  const getAssetClassColor = (assetClass) => {
    switch (assetClass?.toLowerCase()) {
      case 'equity':
        return 'bg-blue-100 text-blue-800';
      case 'debt':
        return 'bg-green-100 text-green-800';
      case 'gold':
        return 'bg-yellow-100 text-yellow-800';
      case 'bond':
        return 'bg-purple-100 text-purple-800';
      case 'aif':
        return 'bg-orange-100 text-orange-800';
      case 'sif':
        return 'bg-pink-100 text-pink-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Get color for visualization
  const getAssetColor = (asset) => {
    switch (asset) {
      case 'Equity': return '#3b82f6';
      case 'Debt': return '#10b981';
      case 'Gold': return '#f59e0b';
      case 'Hybrid': return '#8b5cf6';
      default: return '#6b7280';
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading your portfolio...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center max-w-md p-8 bg-white rounded-xl shadow-lg">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Portfolio</h3>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={fetchPortfolioData}
            className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!portfolioData) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-gray-600">No portfolio data available</p>
        </div>
      </div>
    );
  }

  const filteredHoldings = getFilteredHoldings();
  const summary = portfolioData.summary;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                <TypedText text="My Portfolio" className="text-gray-900" />
              </h1>
              <p className="text-gray-600 mt-2">Track and manage your investments</p>
            </div>
            <div className="mt-4 sm:mt-0 flex gap-3">
              <button
                onClick={fetchPortfolioData}
                className="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors flex items-center"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
              </button>
              <button
                onClick={() => router.push('/invest')}
                className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Invest More
              </button>
            </div>
          </div>
          {rawApiData?.familyMembersData && rawApiData.familyMembersData.length > 0 && (
            <div className="mt-6 border-t border-gray-100 pt-6">
              <div className="flex flex-col gap-4">
                {/* Main Tab Controls */}
                <div className="flex border-b border-gray-200">
                  <button
                    onClick={() => setSelectedPortfolioType('individual')}
                    className={`pb-4 px-4 text-sm font-semibold border-b-2 transition-colors ${selectedPortfolioType === 'individual'
                        ? 'border-blue-600 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      }`}
                  >
                    Individual Portfolio
                  </button>
                  <button
                    onClick={() => setSelectedPortfolioType('family_consolidated')}
                    className={`pb-4 px-4 text-sm font-semibold border-b-2 transition-colors ${selectedPortfolioType !== 'individual'
                        ? 'border-blue-600 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      }`}
                  >
                    Family Portfolio
                  </button>
                </div>

                {/* Sub Tab Controls (Family Portfolio selected) */}
                {selectedPortfolioType !== 'individual' && (
                  <div className="flex flex-wrap gap-2 bg-gray-50 p-2 rounded-lg">
                    <button
                      onClick={() => setSelectedPortfolioType('family_consolidated')}
                      className={`px-4 py-2 text-xs font-semibold rounded-md transition-colors ${selectedPortfolioType === 'family_consolidated'
                          ? 'bg-blue-600 text-white shadow-sm'
                          : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                        }`}
                    >
                      Consolidated View
                    </button>
                    {rawApiData.familyMembersData.map(member => (
                      <button
                        key={member.ucc}
                        onClick={() => setSelectedPortfolioType(member.ucc)}
                        className={`px-4 py-2 text-xs font-semibold rounded-md transition-colors ${selectedPortfolioType === member.ucc
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                          }`}
                      >
                        {member.name} ({member.relation})
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Portfolio Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {/* Total Investment */}
          <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Investment</p>
                <p className="text-2xl font-bold text-gray-900 mt-2">
                  {formatCurrency(summary.totalInvestment)}
                </p>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1" />
                </svg>
              </div>
            </div>
          </div>

          {/* Current Value */}
          <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Current Value</p>
                <p className="text-2xl font-bold text-gray-900 mt-2">
                  {formatCurrency(summary.currentValue)}
                </p>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
            </div>
          </div>

          {/* Total Gain/Loss */}
          <div className={`bg-white rounded-xl shadow-lg p-6 border-l-4 ${summary.totalGain >= 0 ? 'border-green-500' : 'border-red-500'}`}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Gain/Loss</p>
                <div className="flex items-center mt-2">
                  <p className="text-2xl font-bold text-gray-900">
                    {formatCurrency(Math.abs(summary.totalGain))}
                  </p>
                  <span className={`ml-3 px-2 py-1 rounded-full text-sm font-medium ${summary.totalGain >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                    {formatPercentage(summary.totalGainPercentage)}
                  </span>
                </div>
              </div>
              <div className={`w-12 h-12 rounded-full flex items-center justify-center ${summary.totalGain >= 0 ? 'bg-green-100' : 'bg-red-100'}`}>
                <svg className={`w-6 h-6 ${summary.totalGain >= 0 ? 'text-green-600' : 'text-red-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  {summary.totalGain >= 0 ? (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                  ) : (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                  )}
                </svg>
              </div>
            </div>
          </div>

          {/* Total Holdings */}
          <div className="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Holdings</p>
                <p className="text-2xl font-bold text-gray-900 mt-2">
                  {summary.totalHoldings}
                </p>
                <p className="text-sm text-gray-600 mt-1">Active investments</p>
              </div>
              <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        {/* Investment Types Summary */}
        <div className={`grid grid-cols-1 md:grid-cols-2 ${summary.sifCount > 0 ? 'lg:grid-cols-5' : 'lg:grid-cols-4'} gap-6 mb-8`}>
          <div className="bg-white rounded-xl shadow p-4">
            <div className="flex items-center">
              <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <span className="text-blue-600 font-bold">MF</span>
              </div>
              <div>
                <p className="text-sm font-medium text-gray-600">Mutual Funds</p>
                <p className="text-lg font-bold text-gray-900">
                  {summary.mutualFundCount}
                </p>
                <p className="text-xs text-gray-500">
                  {formatCurrency(portfolioData.detailedStats?.totalHoldingsMF?.Total_Equity_Curr_Value || 0)}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl shadow p-4">
            <div className="flex items-center">
              <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                <span className="text-green-600 font-bold">BD</span>
              </div>
              <div>
                <p className="text-sm font-medium text-gray-600">Bonds</p>
                <p className="text-lg font-bold text-gray-900">
                  {summary.bondCount}
                </p>
                <p className="text-xs text-gray-500">
                  {formatCurrency(portfolioData.detailedStats?.totalBondsInfo?.Total_Bond_Current_Value || 0)}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl shadow p-4">
            <div className="flex items-center">
              <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                <span className="text-orange-600 font-bold">AIF</span>
              </div>
              <div>
                <p className="text-sm font-medium text-gray-600">AIF</p>
                <p className="text-lg font-bold text-gray-900">
                  {summary.aifCount}
                </p>
                <p className="text-xs text-gray-500">
                  {formatCurrency(portfolioData.detailedStats?.totalAifInfo?.Total_Aif_Current_Value || 0)}
                </p>
              </div>
            </div>
          </div>

          {summary.sifCount > 0 && (
            <div className="bg-white rounded-xl shadow p-4">
              <div className="flex items-center">
                <div className="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center mr-3">
                  <span className="text-pink-600 font-bold">SIF</span>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">SIF</p>
                  <p className="text-lg font-bold text-gray-900">
                    {summary.sifCount}
                  </p>
                  <p className="text-xs text-gray-500">
                    {formatCurrency(portfolioData.detailedStats?.totalSifInfo?.Total_Sif_Current_Value || 0)}
                  </p>
                </div>
              </div>
            </div>
          )}

          <div className="bg-white rounded-xl shadow p-4">
            <div className="flex items-center">
              <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                <span className="text-yellow-600 font-bold">TOT</span>
              </div>
              <div>
                <p className="text-sm font-medium text-gray-600">Total Return</p>
                <p className="text-lg font-bold text-gray-900">
                  {formatPercentage(summary.returns.Total)}
                </p>
                <p className="text-xs text-gray-500">Portfolio XIRR</p>
              </div>
            </div>
          </div>
        </div>

        {/* ========== OPTION 1: DONUT CHART VISUALIZATION ========== */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-xl font-bold text-gray-900">Asset Allocation - Donut Chart</h2>
            <div className="flex space-x-2">
              <button
                onClick={() => setVisualizationType('donut')}
                className={`px-3 py-1 text-sm rounded-lg ${visualizationType === 'donut' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}
              >
                Donut Chart
              </button>
              <button
                onClick={() => setVisualizationType('progress')}
                className={`px-3 py-1 text-sm rounded-lg ${visualizationType === 'progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}
              >
                Progress Rings
              </button>
            </div>
          </div>

          {visualizationType === 'donut' ? (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Donut Chart */}
              <div className="lg:col-span-2">
                <div className="h-80 flex items-center justify-center">
                  <div className="relative w-64 h-64">
                    {/* Donut Chart Container */}
                    <div className="absolute inset-0 rounded-full border-8 border-transparent"></div>

                    {/* Calculate angles for each asset */}
                    {(() => {
                      const assets = Object.entries(summary.assetAllocation).filter(([_, value]) => value > 0);
                      const totalValue = summary.currentValue;

                      if (totalValue === 0 || assets.length === 0) {
                        return <div className="absolute inset-0 rounded-full bg-gray-100"></div>;
                      }

                      let gradientSegments = [];
                      let cumulativePercentage = 0;

                      assets.forEach(([asset, value]) => {
                        const percentage = (value / totalValue) * 100;
                        const start = cumulativePercentage;
                        const end = cumulativePercentage + percentage;
                        gradientSegments.push(`${getAssetColor(asset)} ${start}% ${end}%`);
                        cumulativePercentage = end;
                      });

                      const gradientStyle = {
                        background: `conic-gradient(${gradientSegments.join(', ')})`
                      };

                      return (
                        <div className="absolute inset-0 rounded-full" style={gradientStyle}></div>
                      );
                    })()}

                    {/* Center hole for donut effect */}
                    <div className="absolute inset-8 rounded-full bg-white"></div>

                    {/* Center text */}
                    <div className="absolute inset-0 flex items-center justify-center">
                      <div className="text-center">
                        <p className="text-2xl font-bold text-gray-900">{formatCurrency(summary.currentValue)}</p>
                        <p className="text-sm text-gray-600">Total Value</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Legend and Details */}
              <div>
                <div className="space-y-4">
                  {Object.entries(summary.assetAllocation).map(([asset, value]) => {
                    if (value > 0) {
                      const percentage = summary.currentValue > 0 ? (value / summary.currentValue) * 100 : 0;
                      const returnPercent = summary.returns[asset] || 0;

                      return (
                        <div key={asset} className="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                          <div className="flex items-center justify-between mb-2">
                            <div className="flex items-center">
                              <div className="w-4 h-4 rounded-full mr-3" style={{ backgroundColor: getAssetColor(asset) }}></div>
                              <span className="font-medium text-gray-900">{asset}</span>
                            </div>
                            <span className="font-bold text-gray-900">{percentage.toFixed(1)}%</span>
                          </div>

                          <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Value:</span>
                            <span className="font-medium">{formatCurrency(value)}</span>
                          </div>

                          <div className="flex justify-between text-sm mt-1">
                            <span className="text-gray-600">Return:</span>
                            <span className={`font-medium ${returnPercent >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                              {formatPercentage(returnPercent)}
                            </span>
                          </div>
                        </div>
                      );
                    }
                    return null;
                  })}
                </div>
              </div>
            </div>
          ) : (
            /* ========== OPTION 2: PROGRESS RINGS ========== */
            <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
              {Object.entries(summary.assetAllocation).map(([asset, value]) => {
                if (value <= 0) return null;

                const percentage = summary.currentValue > 0 ? (value / summary.currentValue) * 100 : 0;
                const circumference = 2 * Math.PI * 45; // 45px radius for larger rings
                const strokeDashoffset = circumference - (percentage / 100) * circumference;
                const returnPercent = summary.returns[asset] || 0;

                return (
                  <div key={asset} className="text-center">
                    <div className="relative w-32 h-32 mx-auto mb-4">
                      <svg className="w-32 h-32 transform -rotate-90">
                        {/* Background circle */}
                        <circle
                          cx="64"
                          cy="64"
                          r="45"
                          stroke="#e5e7eb"
                          strokeWidth="8"
                          fill="none"
                        />
                        {/* Progress circle */}
                        <circle
                          cx="64"
                          cy="64"
                          r="45"
                          stroke={getAssetColor(asset)}
                          strokeWidth="8"
                          fill="none"
                          strokeDasharray={circumference}
                          strokeDashoffset={strokeDashoffset}
                          strokeLinecap="round"
                          className="transition-all duration-1000"
                        />
                      </svg>
                      <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-2xl font-bold text-gray-900">{percentage.toFixed(0)}%</span>
                        <span className={`text-sm font-medium ${returnPercent >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                          {formatPercentage(returnPercent)}
                        </span>
                      </div>
                    </div>
                    <p className="text-lg font-medium text-gray-900 mb-1">{asset}</p>
                    <p className="text-sm text-gray-600">{formatCurrency(value)}</p>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* ========== OPTION 3: PERFORMANCE BAR CHART ========== */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Performance Comparison</h2>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Bar Chart */}
            <div>
              <div className="h-64 flex items-end space-x-4 mt-8">
                {Object.entries(summary.returns).map(([asset, returnPercent]) => {
                  if (asset === 'Total') return null;

                  const height = Math.min(Math.abs(returnPercent) * 3, 180);
                  const isPositive = returnPercent >= 0;

                  return (
                    <div key={asset} className="flex flex-col items-center flex-1">
                      <div className="text-center mb-2">
                        <div className="text-sm font-medium text-gray-900">{asset}</div>
                        <div className={`text-sm font-bold ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                          {formatPercentage(returnPercent)}
                        </div>
                      </div>
                      <div
                        className="w-3/4 rounded-t-lg transition-all duration-500 hover:opacity-80 relative group"
                        style={{
                          height: `${height}px`,
                          backgroundColor: isPositive ? getAssetColor(asset) : '#ef4444',
                          minHeight: '10px'
                        }}
                      >
                        {/* Tooltip on hover */}
                        <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                          {formatPercentage(returnPercent)}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Performance Details */}
            <div>
              <div className="space-y-4">
                <div className="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-sm font-medium text-blue-800">Portfolio XIRR</p>
                      <p className="text-2xl font-bold text-blue-900">
                        {formatPercentage(summary.returns.Total)}
                      </p>
                    </div>
                    <div className="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                      <svg className="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                      </svg>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm font-medium text-gray-600">Best Performer</p>
                    {(() => {
                      const assets = Object.entries(summary.returns).filter(([asset]) => asset !== 'Total');
                      const bestAsset = assets.reduce((best, [asset, value]) =>
                        value > best.value ? { asset, value } : best,
                        { asset: '', value: -Infinity }
                      );

                      return (
                        <>
                          <p className="text-lg font-bold text-gray-900">{bestAsset.asset}</p>
                          <p className={`text-sm font-medium ${bestAsset.value >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {formatPercentage(bestAsset.value)}
                          </p>
                        </>
                      );
                    })()}
                  </div>

                  <div className="p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm font-medium text-gray-600">Risk Adjusted</p>
                    <p className="text-lg font-bold text-gray-900">Good</p>
                    <p className="text-sm text-gray-600">Based on allocation</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* ========== OPTION 4: INVESTMENT DISTRIBUTION CARDS ========== */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Investment Distribution</h2>
          <div className={`grid grid-cols-1 md:grid-cols-2 ${summary.sifCount > 0 ? 'lg:grid-cols-6' : 'lg:grid-cols-5'} gap-6`}>
            {/* Mutual Funds Card */}
            <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 transform transition-transform hover:scale-105">
              <div className="flex items-center justify-between mb-4">
                <div className="w-12 h-12 bg-blue-200 rounded-lg flex items-center justify-center">
                  <span className="text-blue-700 font-bold text-lg">MF</span>
                </div>
                <span className="text-2xl font-bold text-blue-900">{summary.mutualFundCount}</span>
              </div>
              <p className="text-lg font-medium text-blue-800 mb-2">Mutual Funds</p>
              <p className="text-3xl font-bold text-blue-900 mb-2">
                {formatCurrency(portfolioData.detailedStats?.totalHoldingsMF?.Total_Equity_Curr_Value || 0)}
              </p>
              <div className="flex items-center">
                <svg className="w-5 h-5 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                <span className="text-sm font-medium text-green-600">
                  {formatPercentage(portfolioData.detailedStats?.totalHoldingsMF?.Total_Equity_Return || 0)}
                </span>
              </div>
            </div>

            {/* Bonds Card */}
            <div className="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 transform transition-transform hover:scale-105">
              <div className="flex items-center justify-between mb-4">
                <div className="w-12 h-12 bg-green-200 rounded-lg flex items-center justify-center">
                  <span className="text-green-700 font-bold text-lg">BD</span>
                </div>
                <span className="text-2xl font-bold text-green-900">{summary.bondCount}</span>
              </div>
              <p className="text-lg font-medium text-green-800 mb-2">Bonds</p>
              <p className="text-3xl font-bold text-green-900 mb-2">
                {formatCurrency(portfolioData.detailedStats?.totalBondsInfo?.Total_Bond_Current_Value || 0)}
              </p>
              <div className="flex items-center">
                <svg className="w-5 h-5 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                <span className="text-sm font-medium text-green-600">
                  {formatPercentage(portfolioData.detailedStats?.totalBondsInfo?.Total_Bond_Return || 0)}
                </span>
              </div>
            </div>

            {/* PMS Card */}
            <div className="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-6 transform transition-transform hover:scale-105">
              <div className="flex items-center justify-between mb-4">
                <div className="w-12 h-12 bg-teal-200 rounded-lg flex items-center justify-center">
                  <span className="text-teal-700 font-bold text-lg">PMS</span>
                </div>
                <span className="text-2xl font-bold text-teal-900">{summary.pmsCount || 0}</span>
              </div>
              <p className="text-lg font-medium text-teal-800 mb-2">PMS Holdings</p>
              <p className="text-3xl font-bold text-teal-900 mb-2">
                {formatCurrency(portfolioData.detailedStats?.totalPmsInfo?.Total_Pms_Current_Value || 0)}
              </p>
              <div className="flex items-center">
                <svg className="w-5 h-5 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14" />
                </svg>
                <span className="text-sm font-medium text-gray-600">
                  {formatPercentage(portfolioData.detailedStats?.totalPmsInfo?.Total_Pms_Return || 0)}
                </span>
              </div>
            </div>

            {/* AIF Card */}
            <div className="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-6 transform transition-transform hover:scale-105">
              <div className="flex items-center justify-between mb-4">
                <div className="w-12 h-12 bg-orange-200 rounded-lg flex items-center justify-center">
                  <span className="text-orange-700 font-bold text-lg">AIF</span>
                </div>
                <span className="text-2xl font-bold text-orange-900">{summary.aifCount}</span>
              </div>
              <p className="text-lg font-medium text-orange-800 mb-2">Alternative Funds</p>
              <p className="text-3xl font-bold text-orange-900 mb-2">
                {formatCurrency(portfolioData.detailedStats?.totalAifInfo?.Total_Aif_Current_Value || 0)}
              </p>
              <div className="flex items-center">
                <svg className="w-5 h-5 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14" />
                </svg>
                <span className="text-sm font-medium text-gray-600">
                  {formatPercentage(portfolioData.detailedStats?.totalAifInfo?.Total_Aif_Return || 0)}
                </span>
              </div>
            </div>

            {/* SIF Card */}
            {summary.sifCount > 0 && (
              <div className="bg-gradient-to-br from-pink-50 to-pink-100 rounded-xl p-6 transform transition-transform hover:scale-105">
                <div className="flex items-center justify-between mb-4">
                  <div className="w-12 h-12 bg-pink-200 rounded-lg flex items-center justify-center">
                    <span className="text-pink-700 font-bold text-lg">SIF</span>
                  </div>
                  <span className="text-2xl font-bold text-pink-900">{summary.sifCount}</span>
                </div>
                <p className="text-lg font-medium text-pink-800 mb-2">SIF Portfolio</p>
                <p className="text-3xl font-bold text-pink-900 mb-2">
                  {formatCurrency(portfolioData.detailedStats?.totalSifInfo?.Total_Sif_Current_Value || 0)}
                </p>
                <div className="flex items-center">
                  <svg className="w-5 h-5 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14" />
                  </svg>
                  <span className="text-sm font-medium text-gray-600">
                    {formatPercentage(portfolioData.detailedStats?.totalSifInfo?.Total_Sif_Return || 0)}
                  </span>
                </div>
              </div>
            )}

            {/* Total Portfolio Card */}
            <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 transform transition-transform hover:scale-105">
              <div className="flex items-center justify-between mb-4">
                <div className="w-12 h-12 bg-purple-200 rounded-lg flex items-center justify-center">
                  <svg className="w-6 h-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </div>
                <span className="text-2xl font-bold text-purple-900">{summary.totalHoldings}</span>
              </div>
              <p className="text-lg font-medium text-purple-800 mb-2">Total Portfolio</p>
              <p className="text-3xl font-bold text-purple-900 mb-2">
                {formatCurrency(summary.currentValue)}
              </p>
              <div className="flex items-center">
                <svg className={`w-5 h-5 mr-1 ${summary.totalGain >= 0 ? 'text-green-500' : 'text-red-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  {summary.totalGain >= 0 ? (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                  ) : (
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                  )}
                </svg>
                <span className={`text-sm font-medium ${summary.totalGain >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {formatPercentage(summary.totalGainPercentage)}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* ========== OPTION 5: PERFORMANCE HEAT MAP ========== */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Performance Heat Map</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {portfolioData.holdings.slice(0, 6).map((holding) => {
              const intensity = Math.min(Math.abs(holding.xirr) / 50, 1);
              const opacity = 0.1 + (intensity * 0.7);
              const isPositive = holding.xirr >= 0;

              // Truncate long scheme names
              const schemeName = holding.scheme_name?.length > 30
                ? holding.scheme_name.substring(0, 27) + '...'
                : holding.scheme_name;

              const fundHouse = (holding.fund_house || holding.asset_type || '')?.length > 25
                ? (holding.fund_house || holding.asset_type || '').substring(0, 22) + '...'
                : (holding.fund_house || holding.asset_type || '');

              return (
                <div
                  key={holding.id}
                  className={`rounded-xl p-4 border transform transition-transform hover:scale-105 ${isPositive
                      ? 'border-green-200'
                      : 'border-red-200'
                    }`}
                  style={{
                    backgroundColor: isPositive
                      ? `rgba(34, 197, 94, ${opacity})`
                      : `rgba(239, 68, 68, ${opacity})`,
                  }}
                >
                  <div className="flex justify-between items-start gap-2 mb-3">
                    <div className="flex-1 min-w-0">
                      <p className="font-bold text-gray-900 truncate" title={holding.scheme_name}>
                        {schemeName}
                      </p>
                      {shouldShowSubtext(holding) && (
                        <p className="text-xs text-gray-600 truncate mt-0.5" title={holding.fund_house || holding.asset_type}>
                          {fundHouse}
                        </p>
                      )}
                    </div>
                    <span className={`text-base font-bold shrink-0 ${isPositive ? 'text-green-700' : 'text-red-700'}`}>
                      {formatPercentage(holding.xirr)}
                    </span>
                  </div>

                  <div className="flex justify-between items-center mb-3">
                    <span className="text-xs px-2 py-0.5 rounded-full bg-white/50 text-gray-700">
                      {holding.asset_class || holding.asset_type || 'Other'}
                    </span>
                    <span className="font-semibold text-gray-900 text-sm">
                      {formatCurrency(holding.current_value)}
                    </span>
                  </div>

                  <div className="w-full bg-gray-200/50 rounded-full h-2">
                    <div
                      className={`h-2 rounded-full transition-all duration-300 ${isPositive ? 'bg-green-600' : 'bg-red-600'}`}
                      style={{ width: `${Math.min(Math.abs(holding.xirr), 30)}%` }}
                    />
                  </div>

                  <div className="flex justify-between items-center mt-2">
                    <span className="text-xs text-gray-500">XIRR</span>
                    <span className="text-xs text-gray-500">
                      Invested: {formatCurrency(holding.cost_value)}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* ========== OPTION 6: INVESTMENT TIMELINE ========== */}
        {portfolioData.holdings.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Recent Investments Timeline</h2>
            <div className="relative pl-8">
              {/* Timeline line */}
              <div className="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-200"></div>

              <div className="space-y-8">
                {portfolioData.holdings.slice(0, 5).map((holding, index) => {
                  const isPositive = holding.xirr >= 0;

                  return (
                    <div key={holding.id} className="relative">
                      {/* Timeline dot */}
                      <div className={`absolute left-0 transform -translate-x-1/2 w-4 h-4 rounded-full border-4 border-white ${isPositive ? 'bg-green-500' : 'bg-red-500'
                        }`}></div>

                      <div className="ml-8">
                        <div className="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                          <div className="flex justify-between items-start">
                            <div>
                              <p className="font-bold text-gray-900">{holding.scheme_name}</p>
                              {shouldShowSubtext(holding) && (
                                <p className="text-sm text-gray-600">{holding.fund_house || holding.asset_type}</p>
                              )}
                            </div>
                            <span className={`font-bold ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                              {formatPercentage(holding.xirr)}
                            </span>
                          </div>
                          <div className="grid grid-cols-2 gap-4 mt-3">
                            <div>
                              <p className="text-sm text-gray-600">Current Value</p>
                              <p className="font-medium text-gray-900">{formatCurrency(holding.current_value)}</p>
                            </div>
                            <div>
                              <p className="text-sm text-gray-600">Invested</p>
                              <p className="font-medium text-gray-900">{formatCurrency(holding.cost_value)}</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {/* Filters and Tabs */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
            <div className="flex flex-wrap gap-2 pb-4 lg:pb-0">
              {['all', 'equity', 'debt', 'gold', 'bond', 'pms', 'aif', ...(summary.sifCount > 0 ? ['sif'] : []), 'mf'].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-4 py-2 rounded-lg font-medium whitespace-nowrap ${activeTab === tab
                      ? 'bg-blue-100 text-blue-700'
                      : 'text-gray-600 hover:bg-gray-100'
                    }`}
                >
                  {tab === 'all' ? 'All Holdings' :
                    tab === 'mf' ? 'Mutual Funds' :
                      tab === 'pms' ? 'PMS' :
                        tab === 'sif' ? 'SIF' :
                          tab.charAt(0).toUpperCase() + tab.slice(1)}
                </button>
              ))}
            </div>

            <div className="flex flex-col sm:flex-row sm:items-center space-y-4 sm:space-y-0 sm:space-x-4 mt-4 lg:mt-0">
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-600">Sort by:</span>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                >
                  <option value="current_value">Current Value</option>
                  <option value="scheme_name">Scheme Name</option>
                  <option value="asset_class">Asset Class</option>
                  <option value="xirr">XIRR</option>
                </select>
              </div>

              <button
                onClick={() => {
                  setSortOrder(sortOrder === 'desc' ? 'asc' : 'desc');
                }}
                className="flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                <svg className={`w-5 h-5 ${sortOrder === 'desc' ? 'transform rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                </svg>
              </button>
            </div>
          </div>

          {/* Holdings Table */}
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-200">
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[4%]">S.No.</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap">Scheme</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[8%]">Type</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[10%]">Units</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[11%]">Cost NAV</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[11%]">Current NAV</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[11%]">Invested</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[11%]">Current Value</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 whitespace-nowrap w-[10%]">XIRR</th>
                  <th className="py-3 px-4 text-left text-sm font-medium text-gray-600 w-[2%]"></th>
                </tr>
              </thead>
              <tbody>
                {filteredHoldings.length > 0 ? (
                  filteredHoldings.map((holding, index) => {
                    const isExpanded = expandedHoldings.includes(holding.id);
                    const assetType = holding.asset_type || 'Mutual Fund';

                    return (
                      <Fragment key={holding.id}>
                        <tr className="border-b border-gray-100 hover:bg-gray-50">
                          <td className="py-4 px-4 w-[4%] whitespace-nowrap">
                            <div className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                              <span className="font-bold text-blue-600 text-sm">
                                {index + 1}
                              </span>
                            </div>
                          </td>
                          <td className="py-4 px-4">
                            <div>
                              <p className="font-medium text-gray-900">{holding.scheme_name}</p>
                              {shouldShowSubtext(holding) && (
                                <p className="text-xs text-gray-600 mt-1">{holding.fund_house || holding.issuer || assetType}</p>
                              )}
                            </div>
                          </td>
                          <td className="py-4 px-4 whitespace-nowrap">
                            <span className={`text-xs px-2 py-1 rounded-full ${getAssetClassColor(holding.asset_class)}`}>
                              {holding.asset_class || assetType}
                            </span>
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-900 whitespace-nowrap">
                            {formatNumber(holding.units)}
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-900 whitespace-nowrap">
                            ₹{formatNumber(holding.cost_nav, holding.asset_type === 'AIF' ? 4 : undefined)}
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-900 whitespace-nowrap">
                            ₹{formatNumber(holding.current_nav, holding.asset_type === 'AIF' ? 4 : undefined)}
                            <p className="text-xs text-gray-500">{holding.nav_date || ''}</p>
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-900 whitespace-nowrap">
                            {formatCurrency(holding.cost_value)}
                          </td>
                          <td className="py-4 px-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                            {formatCurrency(holding.current_value)}
                          </td>
                          <td className="py-4 px-4 whitespace-nowrap">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${holding.xirr >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                              {formatPercentage(holding.xirr)}
                            </div>
                          </td>
                          <td className="py-4 px-4">
                            <button
                              onClick={() => toggleHoldingExpansion(holding.id)}
                              className="text-blue-600 hover:text-blue-800"
                            >
                              <svg className={`w-5 h-5 transform ${isExpanded ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                              </svg>
                            </button>
                          </td>
                        </tr>

                        {/* Expanded Details */}
                        {isExpanded && (
                          <tr className="bg-blue-50">
                            <td colSpan="10" className="p-4">
                              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div className="bg-white p-4 rounded-lg">
                                  <h4 className="font-medium text-gray-900 mb-3">Investment Details</h4>
                                  <div className="space-y-2">
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">Unrealized Profit:</span>
                                      <span className={`text-sm font-medium ${holding.unrealized_profit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                        {formatCurrency(holding.unrealized_profit)}
                                      </span>
                                    </div>
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">Realized Profit:</span>
                                      <span className="text-sm font-medium text-gray-900">
                                        {formatCurrency(holding.realized_profit)}
                                      </span>
                                    </div>
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">Payout Received:</span>
                                      <span className="text-sm font-medium text-gray-900">
                                        {formatCurrency(holding.payout_received)}
                                      </span>
                                    </div>
                                  </div>
                                </div>

                                <div className="bg-white p-4 rounded-lg">
                                  <h4 className="font-medium text-gray-900 mb-3">NAV Information</h4>
                                  <div className="space-y-2">
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">Cost per Unit:</span>
                                      <span className="text-sm font-medium text-gray-900">
                                        ₹{formatNumber(holding.cost_nav, holding.asset_type === 'AIF' ? 4 : undefined)}
                                      </span>
                                    </div>
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">Current per Unit:</span>
                                      <span className="text-sm font-medium text-gray-900">
                                        ₹{formatNumber(holding.current_nav, holding.asset_type === 'AIF' ? 4 : undefined)}
                                      </span>
                                    </div>
                                    <div className="flex justify-between">
                                      <span className="text-sm text-gray-600">NAV Date:</span>
                                      <span className="text-sm font-medium text-gray-900">
                                        {holding.nav_date || 'N/A'}
                                      </span>
                                    </div>
                                  </div>
                                </div>

                                {(!holding.asset_type || holding.asset_type === 'Mutual Fund' || holding.asset_type === 'SIF') && (
                                  <div className="bg-white p-4 rounded-lg">
                                    <h4 className="font-medium text-gray-900 mb-3">Actions</h4>
                                    <div className="space-y-2">
                                      <button
                                        onClick={() => {
                                          localStorage.setItem('investment_data', JSON.stringify({
                                            scheme: holding,
                                            type: 'additional'
                                          }));
                                          router.push('/invest/buy');
                                        }}
                                        className="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                                      >
                                        Invest More
                                      </button>
                                      <button
                                        onClick={() => {
                                          localStorage.setItem('redemption_data', JSON.stringify(holding));
                                          router.push('/redeem');
                                        }}
                                        className="w-full px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
                                      >
                                        Redeem
                                      </button>
                                    </div>
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </Fragment>
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="10" className="py-12 text-center">
                      <div className="text-gray-500">
                        <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p className="text-lg font-medium mb-2">No holdings found</p>
                        <p className="mb-6">You don&apos;t have any investments in this category yet</p>
                        <button
                          onClick={() => router.push('/invest')}
                          className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                          Start Investing
                        </button>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Sold Investments Section */}
        {(portfolioData.soldPms.length > 0 || portfolioData.soldAif.length > 0 || portfolioData.soldSif.length > 0) && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Previously Held Investments</h2>

            {/* Sold PMS */}
            {portfolioData.soldPms.length > 0 && (
              <div className="mb-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Sold PMS Investments</h3>
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-gray-200">
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">PMS Name</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Issuer</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Asset Class</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">XIRR</th>
                      </tr>
                    </thead>
                    <tbody>
                      {portfolioData.soldPms.map((pms, index) => (
                        <tr key={pms.id} className="border-b border-gray-100 hover:bg-gray-50">
                          <td className="py-4 px-4">
                            <p className="font-medium text-gray-900">{pms.scheme_name}</p>
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-600">
                            {pms.issuer}
                          </td>
                          <td className="py-4 px-4">
                            <span className={`text-xs px-2 py-1 rounded-full ${getAssetClassColor(pms.asset_class)}`}>
                              {pms.asset_class}
                            </span>
                          </td>
                          <td className="py-4 px-4">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${pms.xirr >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                              {formatPercentage(pms.xirr)}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Sold AIF */}
            {portfolioData.soldAif.length > 0 && (
              <div className="mb-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Sold AIF Investments</h3>
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-gray-200">
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">AIF Name</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Issuer</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Asset Class</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Realized Profit</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">XIRR</th>
                      </tr>
                    </thead>
                    <tbody>
                      {portfolioData.soldAif.map((aif, index) => (
                        <tr key={aif.id} className="border-b border-gray-100 hover:bg-gray-50">
                          <td className="py-4 px-4">
                            <p className="font-medium text-gray-900">{aif.scheme_name}</p>
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-600">
                            {aif.issuer}
                          </td>
                          <td className="py-4 px-4">
                            <span className={`text-xs px-2 py-1 rounded-full ${getAssetClassColor(aif.asset_class)}`}>
                              {aif.asset_class}
                            </span>
                          </td>
                          <td className="py-4 px-4 text-sm font-medium text-green-600">
                            {formatCurrency(aif.realized_profit)}
                          </td>
                          <td className="py-4 px-4">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${aif.xirr >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                              {formatPercentage(aif.xirr)}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Sold SIF */}
            {portfolioData.soldSif.length > 0 && (
              <div>
                <h3 className="text-lg font-medium text-gray-900 mb-4">Sold SIF Investments</h3>
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-gray-200">
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">SIF Name</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Issuer</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Asset Class</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Realized Profit</th>
                        <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">XIRR</th>
                      </tr>
                    </thead>
                    <tbody>
                      {portfolioData.soldSif.map((sif, index) => (
                        <tr key={sif.id} className="border-b border-gray-100 hover:bg-gray-50">
                          <td className="py-4 px-4">
                            <p className="font-medium text-gray-900">{sif.scheme_name}</p>
                          </td>
                          <td className="py-4 px-4 text-sm text-gray-600">
                            {sif.issuer}
                          </td>
                          <td className="py-4 px-4">
                            <span className={`text-xs px-2 py-1 rounded-full ${getAssetClassColor(sif.asset_class)}`}>
                              {sif.asset_class}
                            </span>
                          </td>
                          <td className="py-4 px-4 text-sm font-medium text-green-600">
                            {formatCurrency(sif.realized_profit)}
                          </td>
                          <td className="py-4 px-4">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${sif.xirr >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                              {formatPercentage(sif.xirr)}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Portfolio Actions */}
        <div className="mt-8 flex flex-wrap gap-4">

          <button
            onClick={() => router.push('/transactions')}
            className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 flex items-center"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            View All Transactions
          </button>

        </div>
      </div>
    </div>
  );
}

// Portfolio Analysis Functions (outside the component)

const generatePortfolioAnalysis = (portfolioData) => {
  const holdings = portfolioData.holdings;
  const summary = portfolioData.summary;

  // 1. Risk Analysis
  const riskAnalysis = {
    overallRiskScore: calculateOverallRisk(holdings),
    volatilityScore: calculateVolatility(holdings),
    concentrationRisk: calculateConcentrationRisk(holdings, summary.currentValue),
    liquidityScore: calculateLiquidityScore(holdings)
  };

  // 2. Diversification Metrics
  const diversification = {
    assetClassDistribution: calculateAssetClassDistribution(holdings),
    fundHouseConcentration: calculateFundHouseConcentration(holdings),
    sectorExposure: estimateSectorExposure(holdings),
    marketCapDistribution: estimateMarketCapDistribution(holdings)
  };

  // 3. Performance Metrics
  const performance = {
    portfolioXIRR: summary.returns.Total,
    benchmarkComparison: compareWithBenchmarks(holdings),
    rollingReturns: calculateRollingReturns(holdings),
    sharpeRatio: calculateSharpeRatio(holdings),
    sortinoRatio: calculateSortinoRatio(holdings)
  };

  // 4. Cost Analysis
  const costAnalysis = {
    totalExpenseRatio: calculateTotalExpenseRatio(holdings),
    exitLoadImpact: calculateExitLoadImpact(holdings),
    taxImplications: calculateTaxImplications(holdings, summary.totalGain)
  };

  // 5. Recommendations
  const recommendations = generateRecommendations(riskAnalysis, diversification, performance, costAnalysis);

  return {
    riskAnalysis,
    diversification,
    performance,
    costAnalysis,
    recommendations,
    timestamp: new Date().toISOString()
  };
};

// Risk calculation functions
const calculateOverallRisk = (holdings) => {
  let totalRiskScore = 0;
  let totalWeight = 0;

  holdings.forEach(holding => {
    const value = holding.current_value || 0;
    if (value > 0) {
      const assetClass = holding.asset_class?.toLowerCase();
      let riskWeight = 1; // Default

      if (assetClass === 'equity') riskWeight = 7;
      else if (assetClass === 'hybrid') riskWeight = 5;
      else if (assetClass === 'debt') riskWeight = 3;
      else if (assetClass === 'gold') riskWeight = 4;
      else if (assetClass === 'bond') riskWeight = 2;
      else if (assetClass === 'aif') riskWeight = 8;
      else if (assetClass === 'sif') riskWeight = 8;

      totalRiskScore += riskWeight * value;
      totalWeight += value;
    }
  });

  return totalWeight > 0 ? Math.min(10, totalRiskScore / totalWeight) : 5;
};

const calculateVolatility = (holdings) => {
  const assetClassVolatility = {
    'equity': 7,
    'debt': 2,
    'gold': 5,
    'hybrid': 4,
    'bond': 3,
    'aif': 6,
    'sif': 6
  };

  let weightedVolatility = 0;
  let totalValue = 0;

  holdings.forEach(holding => {
    const value = holding.current_value || 0;
    const assetClass = holding.asset_class?.toLowerCase() || 'equity';
    const volatility = assetClassVolatility[assetClass] || 5;

    weightedVolatility += volatility * value;
    totalValue += value;
  });

  return totalValue > 0 ? weightedVolatility / totalValue : 5;
};

const calculateConcentrationRisk = (holdings, totalValue) => {
  if (!totalValue || totalValue === 0) return 0;

  let hhi = 0;

  const assetClassValues = {};
  holdings.forEach(holding => {
    const assetClass = holding.asset_class || 'Other';
    assetClassValues[assetClass] = (assetClassValues[assetClass] || 0) + (holding.current_value || 0);
  });

  Object.values(assetClassValues).forEach(value => {
    const share = (value / totalValue) * 100;
    hhi += Math.pow(share, 2);
  });

  return Math.min(10, hhi / 100);
};

const calculateLiquidityScore = (holdings) => {
  let totalScore = 0;
  let totalValue = 0;

  const liquidityScores = {
    'equity': 9,
    'debt': 7,
    'gold': 8,
    'hybrid': 8,
    'bond': 6,
    'aif': 3,
    'sif': 3
  };

  holdings.forEach(holding => {
    const value = holding.current_value || 0;
    const assetClass = holding.asset_class?.toLowerCase() || 'equity';
    const score = liquidityScores[assetClass] || 5;

    totalScore += score * value;
    totalValue += value;
  });

  return totalValue > 0 ? totalScore / totalValue : 5;
};

// Diversification metrics
const calculateAssetClassDistribution = (holdings) => {
  const distribution = {};
  let totalValue = 0;

  holdings.forEach(holding => {
    const value = holding.current_value || 0;
    const assetClass = holding.asset_class || 'Other';
    distribution[assetClass] = (distribution[assetClass] || 0) + value;
    totalValue += value;
  });

  Object.keys(distribution).forEach(key => {
    distribution[key] = {
      value: distribution[key],
      percentage: totalValue > 0 ? (distribution[key] / totalValue) * 100 : 0
    };
  });

  return distribution;
};

const calculateFundHouseConcentration = (holdings) => {
  const fundHouseValues = {};
  let totalValue = 0;

  holdings.forEach(holding => {
    const fundHouse = holding.fund_house || holding.issuer || 'Unknown';
    const value = holding.current_value || 0;
    fundHouseValues[fundHouse] = (fundHouseValues[fundHouse] || 0) + value;
    totalValue += value;
  });

  const sorted = Object.entries(fundHouseValues)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 3);

  return {
    topFundHouses: sorted.map(([name, value]) => ({
      name,
      value,
      percentage: totalValue > 0 ? (value / totalValue) * 100 : 0
    })),
    concentrationScore: sorted.reduce((acc, [_, value]) =>
      acc + (value / totalValue) * 100, 0
    )
  };
};

const estimateSectorExposure = (holdings) => {
  const commonSectors = [
    'Financial Services', 'Technology', 'Healthcare',
    'Consumer Goods', 'Automobile', 'Energy',
    'Metals & Mining', 'Construction', 'Chemicals'
  ];

  const sectorExposure = {};
  commonSectors.forEach(sector => {
    sectorExposure[sector] = Math.random() * 20;
  });

  const total = Object.values(sectorExposure).reduce((a, b) => a + b, 0);
  Object.keys(sectorExposure).forEach(sector => {
    sectorExposure[sector] = (sectorExposure[sector] / total) * 100;
  });

  return sectorExposure;
};

const estimateMarketCapDistribution = (holdings) => {
  const marketCapDistribution = {
    'Large Cap': 0,
    'Mid Cap': 0,
    'Small Cap': 0
  };

  const equityHoldings = holdings.filter(h =>
    h.asset_class?.toLowerCase() === 'equity'
  );

  equityHoldings.forEach((holding, index) => {
    const weights = [
      [0.6, 0.3, 0.1],
      [0.4, 0.4, 0.2],
      [0.3, 0.4, 0.3],
    ];

    const weightSet = weights[index % weights.length];
    const value = holding.current_value || 0;

    marketCapDistribution['Large Cap'] += value * weightSet[0];
    marketCapDistribution['Mid Cap'] += value * weightSet[1];
    marketCapDistribution['Small Cap'] += value * weightSet[2];
  });

  const totalEquityValue = equityHoldings.reduce((sum, h) =>
    sum + (h.current_value || 0), 0
  );

  if (totalEquityValue > 0) {
    Object.keys(marketCapDistribution).forEach(key => {
      marketCapDistribution[key] = (marketCapDistribution[key] / totalEquityValue) * 100;
    });
  }

  return marketCapDistribution;
};

// Performance metrics
const compareWithBenchmarks = (holdings) => {
  const benchmarks = {
    'Nifty 50': 12.5,
    'Sensex': 12.0,
    'Nifty Next 50': 14.0,
    'Nifty Midcap 100': 15.5,
    'Nifty Smallcap 100': 16.0,
    'Nifty Debt': 7.5,
    'Gold': 9.0
  };

  const portfolioReturns = {};
  holdings.forEach(holding => {
    const assetClass = holding.asset_class || 'Other';
    const xirr = holding.xirr || 0;
    const value = holding.current_value || 0;

    if (!portfolioReturns[assetClass]) {
      portfolioReturns[assetClass] = { totalReturn: 0, totalValue: 0 };
    }

    portfolioReturns[assetClass].totalReturn += xirr * value;
    portfolioReturns[assetClass].totalValue += value;
  });

  const comparisons = [];

  Object.entries(portfolioReturns).forEach(([assetClass, data]) => {
    let benchmarkName = '';
    let benchmarkReturn = 0;

    switch (assetClass.toLowerCase()) {
      case 'equity':
        benchmarkName = 'Nifty 50';
        benchmarkReturn = benchmarks[benchmarkName];
        break;
      case 'debt':
        benchmarkName = 'Nifty Debt';
        benchmarkReturn = benchmarks[benchmarkName];
        break;
      case 'gold':
        benchmarkName = 'Gold';
        benchmarkReturn = benchmarks[benchmarkName];
        break;
      default:
        benchmarkName = 'Nifty 50';
        benchmarkReturn = benchmarks[benchmarkName];
    }

    const portfolioReturn = data.totalValue > 0 ?
      data.totalReturn / data.totalValue : 0;

    comparisons.push({
      assetClass,
      portfolioReturn,
      benchmarkName,
      benchmarkReturn,
      outperformance: portfolioReturn - benchmarkReturn
    });
  });

  return comparisons;
};

const calculateSharpeRatio = (holdings) => {
  const riskFreeRate = 6.0;

  let totalReturn = 0;
  let totalValue = 0;

  holdings.forEach(holding => {
    const returnValue = holding.xirr || 0;
    const value = holding.current_value || 0;
    totalReturn += returnValue * value;
    totalValue += value;
  });

  const avgReturn = totalValue > 0 ? totalReturn / totalValue : 0;
  const portfolioVolatility = calculateVolatility(holdings) * 2;

  return portfolioVolatility > 0 ?
    (avgReturn - riskFreeRate) / portfolioVolatility : 0;
};

const calculateSortinoRatio = (holdings) => {
  const riskFreeRate = 6.0;

  let totalReturn = 0;
  let totalValue = 0;

  holdings.forEach(holding => {
    const returnValue = holding.xirr || 0;
    const value = holding.current_value || 0;
    totalReturn += returnValue * value;
    totalValue += value;
  });

  const avgReturn = totalValue > 0 ? totalReturn / totalValue : 0;
  const downsideDeviation = 4.0;

  return downsideDeviation > 0 ?
    (avgReturn - riskFreeRate) / downsideDeviation : 0;
};

const calculateRollingReturns = (holdings) => {
  return {
    '1 Year': 12.5,
    '3 Years': 14.2,
    '5 Years': 13.8,
    'Since Inception': 12.0
  };
};

// Cost analysis
const calculateTotalExpenseRatio = (holdings) => {
  const terRanges = {
    'equity': { min: 1.0, max: 2.5 },
    'debt': { min: 0.5, max: 2.0 },
    'hybrid': { min: 1.0, max: 2.2 },
    'gold': { min: 0.5, max: 1.5 },
    'bond': { min: 0.1, max: 0.5 },
    'aif': { min: 2.0, max: 4.0 },
    'sif': { min: 2.0, max: 4.0 }
  };

  let totalWeightedTER = 0;
  let totalValue = 0;

  holdings.forEach(holding => {
    const assetClass = holding.asset_class?.toLowerCase() || 'equity';
    const range = terRanges[assetClass] || terRanges['equity'];

    const ter = range.min + Math.random() * (range.max - range.min);
    const value = holding.current_value || 0;

    totalWeightedTER += ter * value;
    totalValue += value;
  });

  return totalValue > 0 ? totalWeightedTER / totalValue : 1.5;
};

const calculateExitLoadImpact = (holdings) => {
  let totalExitLoad = 0;
  let totalValue = 0;

  holdings.forEach(holding => {
    const assetClass = holding.asset_class?.toLowerCase();
    let exitLoad = 0;

    if (assetClass === 'equity' || assetClass === 'hybrid') {
      exitLoad = 1.0;
    } else if (assetClass === 'debt') {
      exitLoad = 0.5;
    } else if (assetClass === 'aif' || assetClass === 'sif') {
      exitLoad = 2.0;
    }

    const value = holding.current_value || 0;
    totalExitLoad += exitLoad * value;
    totalValue += value;
  });

  return totalValue > 0 ? totalExitLoad / totalValue : 0;
};

const calculateTaxImplications = (holdings, totalGain) => {
  const equityGains = holdings.filter(h =>
    h.asset_class?.toLowerCase() === 'equity' ||
    h.asset_class?.toLowerCase() === 'hybrid'
  ).reduce((sum, h) => sum + (h.unrealized_profit || 0), 0);

  const debtGains = holdings.filter(h =>
    h.asset_class?.toLowerCase() === 'debt' ||
    h.asset_class?.toLowerCase() === 'bond'
  ).reduce((sum, h) => sum + (h.unrealized_profit || 0), 0);

  const goldGains = holdings.filter(h =>
    h.asset_class?.toLowerCase() === 'gold'
  ).reduce((sum, h) => sum + (h.unrealized_profit || 0), 0);

  return {
    equity: {
      stcg: 15,
      ltcg: 10,
      exemptUpTo: 100000
    },
    debt: {
      stcg: 'As per slab',
      ltcg: 20,
    },
    gold: {
      stcg: 'As per slab',
      ltcg: 20,
    },
    potentialTax: {
      equity: Math.max(0, equityGains - 100000) * 0.10,
      debt: debtGains * 0.20,
      gold: goldGains * 0.20,
      total: 0
    }
  };
};

// Generate recommendations
const generateRecommendations = (riskAnalysis, diversification, performance, costAnalysis) => {
  const recommendations = [];

  if (riskAnalysis.overallRiskScore > 7) {
    recommendations.push({
      type: 'risk',
      priority: 'high',
      title: 'High Portfolio Risk',
      description: 'Your portfolio has high risk exposure. Consider adding more debt funds for stability.',
      action: 'Increase debt allocation to 30-40%',
      impact: 'Reduce portfolio volatility by 15-20%'
    });
  }

  if (diversification.fundHouseConcentration.concentrationScore > 60) {
    recommendations.push({
      type: 'diversification',
      priority: 'medium',
      title: 'Fund House Concentration',
      description: `Over ${Math.round(diversification.fundHouseConcentration.concentrationScore)}% of your portfolio is in top 3 fund houses`,
      action: 'Consider diversifying across more fund houses',
      impact: 'Reduce concentration risk'
    });
  }

  if (diversification.assetClassDistribution.Equity?.percentage > 70) {
    recommendations.push({
      type: 'allocation',
      priority: 'medium',
      title: 'High Equity Allocation',
      description: 'Your portfolio is heavily weighted towards equity',
      action: 'Consider adding gold and debt funds for better diversification',
      impact: 'Better risk-adjusted returns'
    });
  }

  const underperforming = performance.benchmarkComparison.filter(c => c.outperformance < -2);
  if (underperforming.length > 0) {
    recommendations.push({
      type: 'performance',
      priority: 'high',
      title: 'Underperforming Funds',
      description: `${underperforming.length} asset classes are underperforming their benchmarks`,
      action: 'Review and consider switching underperforming funds',
      impact: 'Potential 2-5% improvement in returns'
    });
  }

  if (costAnalysis.totalExpenseRatio > 1.8) {
    recommendations.push({
      type: 'cost',
      priority: 'medium',
      title: 'High Expense Ratio',
      description: `Your portfolio's average expense ratio is ${costAnalysis.totalExpenseRatio.toFixed(2)}%, which is above optimal`,
      action: 'Consider switching to low-cost index funds or direct plans',
      impact: `Save ${(costAnalysis.totalExpenseRatio - 1.0).toFixed(2)}% annually in fees`
    });
  }

  recommendations.sort((a, b) => {
    const priorityOrder = { high: 0, medium: 1, low: 2 };
    return priorityOrder[a.priority] - priorityOrder[b.priority];
  });

  return recommendations;
};