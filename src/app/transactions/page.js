// app/transactions/page.js
'use client';

import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

export default function TransactionsPage() {
  // Form state
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [dateErrors, setDateErrors] = useState({});
  const [dateInfo, setDateInfo] = useState('');

  // Data state
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [hasFetchedData, setHasFetchedData] = useState(false); // Track if data has been fetched
  
  // Summary state
  const [summaryData, setSummaryData] = useState({
    total: 0,
    totalAmount: 0,
    successCount: 0,
    failedCount: 0,
    pendingCount: 0
  });

  // Transaction detail modal state
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10;

  // Initialize dates only (don't fetch data automatically)
  useEffect(() => {
    const today = new Date();
    const sixDaysAgo = new Date();
    sixDaysAgo.setDate(today.getDate() - 6);
    
    const formatDateForInput = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    const todayFormatted = formatDateForInput(today);
    const sixDaysAgoFormatted = formatDateForInput(sixDaysAgo);

    setFromDate(sixDaysAgoFormatted);
    setToDate(todayFormatted);
    setDateInfo('Select date range and click "Show Transactions" to view data');
  }, []);

  // Helper functions
  const formatDisplayDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
  };

  // FIXED: Handle amount strings with commas like "2,0000.00(A)"
  const calculateTotalAmount = (transactions) => {
    return transactions.reduce((total, transaction) => {
      const amountStr = transaction.amount_units || '0';
      // Remove commas and any non-numeric characters except decimal point
      const cleanAmountStr = amountStr.replace(/[^0-9.]/g, '');
      const amount = parseFloat(cleanAmountStr) || 0;
      return total + amount;
    }, 0);
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  // FIXED: Handle amount formatting with commas
  const formatAmount = (amountStr) => {
    if (!amountStr) return '₹0.00';
    // Remove commas and any non-numeric characters except decimal point
    const cleanAmountStr = amountStr.replace(/[^0-9.]/g, '');
    const amount = parseFloat(cleanAmountStr) || 0;
    return formatCurrency(amount);
  };

  const formatAmount4csv = (amountStr) => {
    if (!amountStr) return '₹0.00';
    const clean = amountStr.replace(/[^0-9.]/g, '');
    const amount = parseFloat(clean || 0);
    return `₹${amount.toFixed(2)}`;
  };

  const getStatusColor = (status) => {
    const statusUpper = status?.toUpperCase() || '';
    switch (statusUpper) {
      case 'SUCCESS':
      case 'COMPLETED':
      case 'VALID':
        return 'bg-green-100 text-green-800';
      case 'FAILED':
      case 'INVALID':
      case 'REJECTED':
        return 'bg-red-100 text-red-800';
      case 'PENDING':
      case 'PROCESSING':
        return 'bg-yellow-100 text-yellow-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Helper function to check if a date is valid and matches DD-MM-YYYY or DD-MM-YYYY HH:MM:SS format
  const isValidDate = (dateValue) => {
    if (!dateValue || dateValue === null || dateValue === undefined) return false;
    
    const dateStr = String(dateValue).trim();
    if (dateStr === '' || dateStr === 'N/A' || dateStr === 'NA' || dateStr === 'n/a') return false;
    
    // Check for DD-MM-YYYY or DD-MM-YYYY HH:MM:SS format
    const datePattern = /^(\d{2})-(\d{2})-(\d{4})(\s+(\d{2}):(\d{2}):(\d{2}))?$/;
    if (!datePattern.test(dateStr)) return false;
    
    try {
      const [day, month, year] = dateStr.split(' ')[0].split('-');
      const parsedDate = new Date(year, month - 1, day);
      // Check if date is valid
      return parsedDate instanceof Date && !isNaN(parsedDate);
    } catch {
      return false;
    }
  };

  const formatTransactionDate = (dateTime) => {
    if (!dateTime || !isValidDate(dateTime)) return null;
    try {
      // Convert from DD-MM-YYYY to YYYY-MM-DD
      const [date, time] = dateTime.split(' ');
      const [day, month, year] = date.split('-');
      return `${year}-${month}-${day} ${time || ''}`;
    } catch {
      return null;
    }
  };

  // Date handling functions
  const calculateToDate = (fromDate) => {
    if (!fromDate) return '';

    const from = new Date(fromDate);
    const to = new Date(from);

    // ✅ Add 6 days so total range = 7 days (inclusive)
    to.setDate(from.getDate() + 6);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Do not allow future dates
    if (to > today) {
      return today.toISOString().split('T')[0];
    }

    const year = to.getFullYear();
    const month = String(to.getMonth() + 1).padStart(2, '0');
    const day = String(to.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
  };

  // FIXED: Date validation to handle timezone issues - UPDATED
  const validateDates = () => {
    const errors = {};

    // Local "today" at midnight
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const parseLocalDate = (dateStr) => {
      const [year, month, day] = dateStr.split('-').map(Number);
      return new Date(year, month - 1, day);
    };

    let from = null;
    let to = null;

    // Validate fromDate
    if (fromDate) {
      from = parseLocalDate(fromDate);

      if (from > today) {
        errors.fromDate = 'From date cannot be in the future';
      }
    }

    // Validate toDate
    if (toDate) {
      to = parseLocalDate(toDate);

      if (to > today) {
        errors.toDate = 'To date cannot be in the future';
      }
    }

    // Validate date range
    if (from && to) {
      if (from > to) {
        errors.toDate = 'To date cannot be before From date';
      } else {
        // Inclusive difference
        const diffInDays =
          Math.floor((to - from) / (1000 * 60 * 60 * 24)) + 1;

        if (diffInDays > 7) {
          errors.dateRange = 'Date range cannot exceed 7 days';
        }
      }
    }

    setDateErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleFromDateChange = (e) => {
    const value = e.target.value;
    setFromDate(value);
    
    if (dateErrors.fromDate) {
      setDateErrors(prev => ({ ...prev, fromDate: '' }));
    }
    
    if (value && !toDate) {
      const calculatedToDate = calculateToDate(value);
      setToDate(calculatedToDate);
      setDateInfo('To date automatically set to 7 days after from date (max: yesterday)');
    }
  };

  const handleToDateChange = (e) => {
    const value = e.target.value;
    setToDate(value);
    setDateInfo('');
    
    if (dateErrors.toDate) {
      setDateErrors(prev => ({ ...prev, toDate: '' }));
    }
  };

  // Main API call function
  const handleFetchTransactions = async (fromDateParam = fromDate, toDateParam = toDate) => {
    setLoading(true);
    setError('');
    setSuccessMessage('');
    setSelectedTransaction(null);
    setCurrentPage(1);

    try {
      // Get authentication token
      const token = localStorage.getItem('site_token');
      // console.log('Fetching transactions with token:', token);
      if (!token) {
        throw new Error('Authentication token not found. Please login again.');
      }

      const userId = localStorage.getItem('user_id') || '';

      const response = await fetch(`${API_BASE_URL}/transaction_history_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          fromDate: fromDateParam,
          toDate: toDateParam
        })
      });

      const apiResult = await response.json();

      if (!response.ok) {
        throw new Error(apiResult.message || 'Failed to fetch transactions');
      }
      const transactionPayload = JSON.parse(apiResult.data.response);

      if (transactionPayload.response_status === 'S') {
        // Extract transaction list
        const transactionList = transactionPayload.report_data || [];
        
        // Console log to check Auth Date
        console.log('Auth Date Example:', {
          authDate: transactionList[0]?.primary_holder_auth_date_time,
          allAuthDates: transactionList.map(t => t.primary_holder_auth_date_time)
        });
        
        const totalTransactions = parseInt(
          transactionPayload.report_data_total || '0',
          10
        );

        // console.log('Transactions received:', transactionList);
        // console.log('Total transactions:', totalTransactions);

        // Update transaction state
        setTransactions(transactionList);
        setHasFetchedData(true);

        // Calculate summary values
        const totalAmount = calculateTotalAmount(transactionList);

        const successCount = transactionList.filter(txn => {
          const status = txn.order_status?.toUpperCase();
          return ['SUCCESS', 'COMPLETED', 'VALID'].includes(status);
        }).length;

        const failedCount = transactionList.filter(txn => {
          const status = txn.order_status?.toUpperCase();
          return ['FAILED', 'INVALID', 'REJECTED'].includes(status);
        }).length;

        const pendingCount = transactionList.filter(txn => {
          const status = txn.order_status?.toUpperCase();
          return ![
            'SUCCESS',
            'COMPLETED',
            'VALID',
            'FAILED',
            'INVALID',
            'REJECTED'
          ].includes(status);
        }).length;

        // Update summary state
        setSummaryData({
          total: totalTransactions,
          totalAmount,
          successCount,
          failedCount,
          pendingCount
        });

        // Success message
        setSuccessMessage(
          `Found ${totalTransactions} transactions from ${formatDisplayDate(fromDateParam)} to ${formatDisplayDate(toDateParam)}`
        );

      } else {
        // Handle API-level failure
        const errorMessage =
          transactionPayload.error_remark ||
          transactionPayload.message ||
          'Failed to fetch transactions';

        throw new Error(errorMessage);
      }

    } catch (error) {
      console.error('Error fetching transactions:', error);
      setError(error.message || 'Failed to fetch transactions. Please try again.');
      setTransactions([]);
      setSummaryData({
        total: 0,
        totalAmount: 0,
        successCount: 0,
        failedCount: 0,
        pendingCount: 0
      });
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    if (!validateDates()) return;

    let effectiveFromDate = fromDate;
    let effectiveToDate = toDate;

    const formatDateForInput = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    // Case 1: No dates selected → last 7 days (inclusive), ending today
    if (!fromDate && !toDate) {
      const today = new Date();

      const sixDaysInclusiveStart = new Date(today);
      sixDaysInclusiveStart.setDate(today.getDate() - 6); // today included → 7 days total

      effectiveFromDate = formatDateForInput(sixDaysInclusiveStart);
      effectiveToDate = formatDateForInput(today);

      setFromDate(effectiveFromDate);
      setToDate(effectiveToDate);
      setDateInfo('Showing last 7 days transactions (ending today)');
    }
    // Case 2: From date selected, to date missing → auto-calculate (7 days inclusive)
    else if (fromDate && !toDate) {
      effectiveToDate = calculateToDate(fromDate);

      setToDate(effectiveToDate);
      setDateInfo(
        'To date automatically set to 7 days from from-date (max: today)'
      );
    }

    // Fetch transactions with final dates
    handleFetchTransactions(effectiveFromDate, effectiveToDate);
  };

  const handleExportTransactions = () => {
    if (transactions.length === 0) return;

    const headers = [
      'Date',
      'Product Type',
      'Scheme',
      'Amount',
      'Status',
      'Transaction Type'
    ];

    const rows = transactions.map(txn => [
      txn.product_reg_date_time || '',
      txn.product_type || '',
      txn.scheme_name_from || '',
      formatAmount4csv(txn.amount_units), // safe now
      txn.order_status || '',
      txn.transaction_type || ''
    ]);

    // Wrap each cell in double quotes
    const csvRows = [
      headers,
      ...rows
    ].map(row =>
      row.map(value => `"${value}"`).join(',')
    );

    // ✅ UTF-8 BOM added
    const csvContent = '\uFEFF' + csvRows.join('\n');

    const blob = new Blob([csvContent], {
      type: 'text/csv;charset=utf-8;'
    });

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `transactions_${fromDate}_to_${toDate}.csv`;
    link.click();

    setSuccessMessage('Transactions exported successfully!');
  };

  // Pagination
  const totalPages = Math.ceil(transactions.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentTransactions = transactions.slice(startIndex, endIndex);

  const handlePageChange = (page) => {
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const renderPagination = () => {
    const pages = [];
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    
    if (endPage - startPage + 1 < maxPagesToShow) {
      startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      pages.push(
        <button
          key={i}
          onClick={() => handlePageChange(i)}
          className={`px-3 py-1 rounded-md ${
            currentPage === i
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          }`}
        >
          {i}
        </button>
      );
    }
    
    return (
      <div className="flex items-center justify-center space-x-2 mt-6">
        <button
          onClick={() => handlePageChange(1)}
          disabled={currentPage === 1}
          className="px-3 py-1 rounded-md bg-gray-100 text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-200"
        >
          First
        </button>
        <button
          onClick={() => handlePageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className="px-3 py-1 rounded-md bg-gray-100 text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-200"
        >
          Previous
        </button>
        {pages}
        <button
          onClick={() => handlePageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className="px-3 py-1 rounded-md bg-gray-100 text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-200"
        >
          Next
        </button>
        <button
          onClick={() => handlePageChange(totalPages)}
          disabled={currentPage === totalPages}
          className="px-3 py-1 rounded-md bg-gray-100 text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-200"
        >
          Last
        </button>
      </div>
    );
  };

  // Get today's date for max attribute
  const getTodayDate = () => {
    return new Date().toISOString().split('T')[0];
  };

  // Helper function to check if a value is valid (not empty, null, undefined, or 'N/A')
  const isValidValue = (value) => {
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (trimmed === '' || trimmed === 'N/A' || trimmed === 'NA' || trimmed === 'n/a') return false;
    }
    return true;
  };

  // Define all possible detail fields with their labels and categories
  const getDetailFields = (transaction) => {
    // Format dates only if they are valid
    const formattedAuthDate = isValidDate(transaction.primary_holder_auth_date_time) 
      ? formatTransactionDate(transaction.primary_holder_auth_date_time) 
      : null;
    
    const formattedPaymentDate = isValidDate(transaction.payment_date_time)
      ? formatTransactionDate(transaction.payment_date_time)
      : null;
    
    const formattedTransactionDate = isValidDate(transaction.product_reg_date_time)
      ? formatTransactionDate(transaction.product_reg_date_time)
      : null;

    return [
      // Transaction Information
      { category: 'Transaction Information', label: 'Transaction Date', value: formattedTransactionDate, key: 'product_reg_date_time' },
      { category: 'Transaction Information', label: 'Transaction Type', value: transaction.transaction_type, key: 'transaction_type' },
      { category: 'Transaction Information', label: 'Product Type', value: transaction.product_type, key: 'product_type' },
      { category: 'Transaction Information', label: 'Product ID', value: transaction.product_id, key: 'product_id' },
      { category: 'Transaction Information', label: 'Amount', value: formatAmount(transaction.amount_units), key: 'amount_units' },
      { category: 'Transaction Information', label: 'Status', value: transaction.order_status, key: 'order_status' },
      { category: 'Transaction Information', label: 'Frequency', value: transaction.frequency, key: 'frequency' },
      { category: 'Transaction Information', label: 'SIP Registration No', value: transaction.sip_registration_no, key: 'sip_registration_no' },
      
      // Holder Information
      { category: 'Holder Information', label: 'Primary Holder', value: transaction.primary_holder_name, key: 'primary_holder_name' },
      { category: 'Holder Information', label: 'PAN', value: transaction.primary_holder_pan, key: 'primary_holder_pan' },
      { category: 'Holder Information', label: 'Mobile', value: transaction.primary_holder_mobile, key: 'primary_holder_mobile' },
      { category: 'Holder Information', label: 'Email', value: transaction.primary_holder_email, key: 'primary_holder_email' },
      { category: 'Holder Information', label: 'Tax Status', value: transaction.tax_status, key: 'tax_status' },
      { category: 'Holder Information', label: 'Mode of Holding', value: transaction.mode_of_holding, key: 'mode_of_holding' },
      { category: 'Holder Information', label: 'Auth Mode', value: transaction.primary_holder_auth_mode, key: 'primary_holder_auth_mode' },
      { category: 'Holder Information', label: 'Auth Status', value: transaction.primary_holder_auth_status, key: 'primary_holder_auth_status' },
      { category: 'Holder Information', label: 'Auth Date', value: formattedAuthDate, key: 'primary_holder_auth_date_time' },
      
      // Scheme Details
      { category: 'Scheme Details', label: 'AMC Name', value: transaction.amc_name, key: 'amc_name' },
      { category: 'Scheme Details', label: 'Scheme Name', value: transaction.scheme_name_from, key: 'scheme_name_from' },
      { category: 'Scheme Details', label: 'Scheme Code', value: transaction.scheme_code_from, key: 'scheme_code_from' },
      { category: 'Scheme Details', label: 'RTA Name', value: transaction.rta_name, key: 'rta_name' },
      { category: 'Scheme Details', label: 'To Scheme Name', value: transaction.scheme_name_to, key: 'scheme_name_to' },
      { category: 'Scheme Details', label: 'To Scheme Code', value: transaction.scheme_code_to, key: 'scheme_code_to' },
      
      // Payment Details
      { category: 'Payment Details', label: 'Mode of Payment', value: transaction.mode_of_payment, key: 'mode_of_payment' },
      { category: 'Payment Details', label: 'Payment Bank', value: transaction.payment_bank_name, key: 'payment_bank_name' },
      { category: 'Payment Details', label: 'Account No', value: transaction.payment_account_no, key: 'payment_account_no' },
      { category: 'Payment Details', label: 'IFSC Code', value: transaction.payment_account_ifsc, key: 'payment_account_ifsc' },
      { category: 'Payment Details', label: 'Payment Date', value: formattedPaymentDate, key: 'payment_date_time' },
      { category: 'Payment Details', label: 'Payment Status', value: transaction.payment_status, key: 'payment_status' },
      { category: 'Payment Details', label: 'Payment Reference No', value: transaction.payment_reference_no, key: 'payment_reference_no' },
      { category: 'Payment Details', label: 'Reconciliation Status', value: transaction.reconciliation_status, key: 'reconciliation_status' },
      
      // Allotment Details
      { category: 'Allotment Details', label: 'Alloted NAV', value: transaction.alloted_nav, key: 'alloted_nav' },
      { category: 'Allotment Details', label: 'Alloted Units', value: transaction.alloted_unit, key: 'alloted_unit' },
      { category: 'Allotment Details', label: 'Alloted Amount', value: transaction.alloted_amount ? formatAmount(transaction.alloted_amount) : null, key: 'alloted_amount' },
      { category: 'Allotment Details', label: 'STT', value: transaction.stt, key: 'stt' },
      { category: 'Allotment Details', label: 'Folio No', value: transaction.folio_no, key: 'folio_no' },
      { category: 'Allotment Details', label: 'DP Folio No', value: transaction.dp_folio_no, key: 'dp_folio_no' },
      
      // UTR Details
      { category: 'UTR Details', label: 'PG UTR No', value: transaction.pg_mis_utr_no, key: 'pg_mis_utr_no' },
      { category: 'UTR Details', label: 'AMC Payout UTR', value: transaction.amc_payout_utr_no, key: 'amc_payout_utr_no' },
      { category: 'UTR Details', label: 'AMC Payout Date', value: transaction.amc_fund_payout_date_time, key: 'amc_fund_payout_date_time' }
    ];
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="container mx-auto px-4">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            <TypedText text="Transaction History" className="text-gray-900" />
          </h1>
          <p className="text-gray-600">
            View and analyze your transaction history
          </p>
        </div>

        {/* Success Message - Only show if data has been fetched */}
        {successMessage && hasFetchedData && (
          <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <span className="text-green-800 font-medium">{successMessage}</span>
            </div>
          </div>
        )}

        {/* Error Message */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-red-800 font-medium">{error}</span>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Left Column - Form only */}
          <div className="lg:col-span-1 space-y-6">
            {/* Date Selection Form */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <form onSubmit={handleSubmit} className="space-y-4">
                <h3 className="font-bold text-gray-900 mb-4">Select Date Range</h3>
                
                {/* Date Range Error */}
                {dateErrors.dateRange && (
                  <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div className="flex items-center">
                      <svg className="w-4 h-4 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span className="text-sm text-red-800 font-medium">{dateErrors.dateRange}</span>
                    </div>
                  </div>
                )}

                {/* Date Info Message */}
                {dateInfo && !dateErrors.dateRange && (
                  <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div className="flex items-center">
                      <svg className="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span className="text-sm text-blue-800 font-medium">{dateInfo}</span>
                    </div>
                  </div>
                )}

                <div className="space-y-4">
                  {/* From Date */}
                  <div>
                    <label htmlFor="fromDate" className="block text-sm font-medium text-gray-700 mb-1">
                      From Date
                    </label>
                    <input
                      type="date"
                      id="fromDate"
                      value={fromDate}
                      onChange={handleFromDateChange}
                      max={new Date().toISOString().split('T')[0]}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${
                        dateErrors.fromDate ? 'border-red-300' : 'border-gray-300'
                      }`}
                      disabled={loading}
                    />
                    {dateErrors.fromDate && (
                      <p className="mt-1 text-sm text-red-600">{dateErrors.fromDate}</p>
                    )}
                  </div>

                  {/* To Date */}
                  <div>
                    <label htmlFor="toDate" className="block text-sm font-medium text-gray-700 mb-1">
                      To Date
                    </label>
                    <input
                      type="date"
                      id="toDate"
                      value={toDate}
                      onChange={handleToDateChange}
                      max={getTodayDate()}
                      min={fromDate}
                      className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${
                        dateErrors.toDate ? 'border-red-300' : 'border-gray-300'
                      }`}
                      disabled={loading}
                    />
                    {dateErrors.toDate && (
                      <p className="mt-1 text-sm text-red-600">{dateErrors.toDate}</p>
                    )}
                  </div>

                  {/* Date Information */}
                  <div className="pt-2">
                    <div className="text-xs text-gray-500 space-y-1">
                      <p className="flex items-start">
                        <svg className="w-3 h-3 text-blue-500 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Date range limited to 7 days for security</span>
                      </p>
                      <p className="flex items-start">
                        <svg className="w-3 h-3 text-blue-500 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>To date cannot be in the future</span>
                      </p>
                      <p className="flex items-start">
                        <svg className="w-3 h-3 text-blue-500 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Leave both empty for last 7 days (ending today)</span>
                      </p>
                      <p className="flex items-start">
                        <svg className="w-3 h-3 text-blue-500 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Enter only From date for auto To date (+7 days, max: today)</span>
                      </p>
                    </div>
                  </div>
                </div>

                {/* Submit Button */}
                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={loading}
                    className={`w-full font-medium py-3 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                      loading
                        ? 'bg-blue-400 text-white cursor-not-allowed focus:ring-blue-300'
                        : 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500'
                    }`}
                  >
                    {loading ? (
                      <div className="flex items-center justify-center">
                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Fetching Transactions...
                      </div>
                    ) : (
                      'Show Transactions'
                    )}
                  </button>
                </div>
              </form>
            </div>

            {/* Help Information */}
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-6">
              <h4 className="font-medium text-gray-900 mb-3">Need Help?</h4>
              <ul className="space-y-2 text-sm text-gray-600">
                <li className="flex items-start">
                  <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>For transaction discrepancies, contact support</span>
                </li>
                <li className="flex items-start">
                  <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Transaction data includes real-time / today&apos;s transaction details</span>
                </li>
                <li className="flex items-start">
                  <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Maximum 7-day range for security purposes</span>
                </li>
              </ul>
            </div>
          </div>

          {/* Right Column - Transaction Summary (Horizontal) + Transaction Table */}
          <div className="lg:col-span-3">
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              {/* Table Header with Actions */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-6">
                <h2 className="text-lg font-bold text-gray-800 mb-2 sm:mb-0">Transaction Details</h2>
                <div className="flex space-x-3">
                  <button
                    onClick={handleExportTransactions}
                    disabled={transactions.length === 0}
                    className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                      transactions.length === 0
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        : 'bg-blue-50 text-blue-600 hover:bg-blue-100'
                    }`}
                  >
                    <svg className="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                  </button>
                </div>
              </div>

              {/* Horizontal Transaction Summary - Only show if data has been fetched */}
              {hasFetchedData && (
                <div className="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    {/* Total Transactions */}
                    <div className="bg-white rounded-lg p-3 shadow-sm">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-xs text-gray-500 uppercase tracking-wide">Total Transactions</p>
                          <p className="text-2xl font-bold text-gray-900">{summaryData.total}</p>
                        </div>
                        <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                          <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                        </div>
                      </div>
                    </div>

                    {/* Total Amount */}
                    <div className="bg-white rounded-lg p-3 shadow-sm">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-xs text-gray-500 uppercase tracking-wide">Total Amount</p>
                          <p className="text-2xl font-bold text-gray-900">{formatCurrency(summaryData.totalAmount)}</p>
                        </div>
                        <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                          <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                      </div>
                    </div>

                    {/* Success Count */}
                    <div className="bg-white rounded-lg p-3 shadow-sm">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-xs text-gray-500 uppercase tracking-wide">Successful/Valid</p>
                          <p className="text-2xl font-bold text-green-600">{summaryData.successCount}</p>
                        </div>
                        <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                          <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                          </svg>
                        </div>
                      </div>
                    </div>

                    {/* Pending Count */}
                    <div className="bg-white rounded-lg p-3 shadow-sm">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-xs text-gray-500 uppercase tracking-wide">Pending</p>
                          <p className="text-2xl font-bold text-yellow-600">{summaryData.pendingCount}</p>
                        </div>
                        <div className="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                          <svg className="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                      </div>
                    </div>

                    {/* Failed Count */}
                    <div className="bg-white rounded-lg p-3 shadow-sm">
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-xs text-gray-500 uppercase tracking-wide">Failed/Invalid</p>
                          <p className="text-2xl font-bold text-red-600">{summaryData.failedCount}</p>
                        </div>
                        <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                          <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {loading ? (
                <div className="text-center py-12">
                  <div className="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
                  <p className="mt-4 text-gray-600">Loading transactions...</p>
                </div>
              ) : hasFetchedData && transactions.length > 0 ? (
                <>
                  {/* Desktop View */}
                  <div className="hidden md:block overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date & Time
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Product Type
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Scheme
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {currentTransactions.map((transaction, index) => (
                          <tr key={index} className="hover:bg-gray-50">
                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                              {formatTransactionDate(transaction.product_reg_date_time) || 'N/A'}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-900">
                              {transaction.product_type || 'N/A'}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-900">
                              {transaction.scheme_name_from || 'N/A'}
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                              {formatAmount(transaction.amount_units)}
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                              <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(transaction.order_status)}`}>
                                {transaction.order_status || 'N/A'}
                              </span>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap text-sm">
                              <button
                                onClick={() => setSelectedTransaction(transaction)}
                                className="text-blue-600 hover:text-blue-900 font-medium"
                              >
                                View Details
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Mobile View */}
                  <div className="md:hidden space-y-4">
                    {currentTransactions.map((transaction, index) => (
                      <div key={index} className="bg-gray-50 rounded-lg p-4">
                        <div className="space-y-3">
                          <div className="flex justify-between items-start">
                            <div>
                              <p className="text-sm text-gray-600">{transaction.product_type || 'N/A'}</p>
                            </div>
                            <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(transaction.order_status)}`}>
                              {transaction.order_status || 'N/A'}
                            </span>
                          </div>
                          
                          <div className="grid grid-cols-2 gap-2 text-sm">
                            <div>
                              <span className="text-gray-500">Amount:</span>
                              <p className="font-medium">{formatAmount(transaction.amount_units)}</p>
                            </div>
                            <div>
                              <span className="text-gray-500">Date:</span>
                              <p>{formatTransactionDate(transaction.product_reg_date_time) || 'N/A'}</p>
                            </div>
                            <div className="col-span-2">
                              <span className="text-gray-500">Scheme:</span>
                              <p className="truncate">{transaction.scheme_name_from || 'N/A'}</p>
                            </div>
                          </div>
                          
                          <button
                            onClick={() => setSelectedTransaction(transaction)}
                            className="w-full text-center text-blue-600 hover:text-blue-900 font-medium py-2 border-t border-gray-200"
                          >
                            View Full Details
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* Pagination */}
                  {totalPages > 1 && renderPagination()}
                </>
              ) : hasFetchedData && transactions.length === 0 ? (
                <div className="text-center py-12">
                  <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <h3 className="mt-2 text-sm font-medium text-gray-900">No transactions found</h3>
                  <p className="mt-1 text-sm text-gray-500">No transactions found for the selected date range</p>
                </div>
              ) : (
                <div className="text-center py-12">
                  <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <h3 className="mt-2 text-sm font-medium text-gray-900">Select date range to view transactions</h3>
                  <p className="mt-1 text-sm text-gray-500">Choose your date range and click &quot;Show Transactions&quot;</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Transaction Details Modal - Dynamic rendering based on available data */}
      {selectedTransaction && (() => {
        // Get all fields and filter only those with valid values
        const allFields = getDetailFields(selectedTransaction);
        
        // Group fields by category
        const groupedFields = allFields.reduce((acc, field) => {
          if (isValidValue(field.value)) {
            if (!acc[field.category]) {
              acc[field.category] = [];
            }
            acc[field.category].push(field);
          }
          return acc;
        }, {});
        
        // Get categories that have at least one valid field
        const categoriesWithData = Object.keys(groupedFields);
        
        // If no categories have data, don't render the modal
        if (categoriesWithData.length === 0) return null;
        
        return (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
              <div className="flex justify-between items-center border-b border-gray-200 px-6 py-4">
                <h3 className="text-lg font-bold text-gray-800">Transaction Details</h3>
                <button
                  onClick={() => setSelectedTransaction(null)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              
              <div className="p-6 overflow-y-auto max-h-[70vh]">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Dynamically render each category that has data */}
                  {categoriesWithData.map((category) => (
                    <div key={category} className="space-y-4">
                      <h4 className="font-semibold text-gray-700 border-b pb-2">{category}</h4>
                      {groupedFields[category].map((field, idx) => (
                        <DetailItem key={`${category}-${idx}`} label={field.label} value={field.value} />
                      ))}
                    </div>
                  ))}
                </div>
                
                {/* Remarks section - always show if there's a remark */}
                {selectedTransaction.order_remark && selectedTransaction.order_remark.trim() && (
                  <div className="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 className="font-semibold text-yellow-800 mb-2">Remarks</h4>
                    <p className="text-yellow-700">{selectedTransaction.order_remark}</p>
                  </div>
                )}
              </div>
              
              <div className="border-t border-gray-200 px-6 py-4 flex justify-end">
                <button
                  onClick={() => setSelectedTransaction(null)}
                  className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
}

// Helper component for detail items in modal
function DetailItem({ label, value }) {
  return (
    <div>
      <span className="text-sm text-gray-500 block">{label}</span>
      <span className="font-medium text-gray-900">{value || 'N/A'}</span>
    </div>
  );
}