'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { CheckCircleIcon, PencilIcon, TrashIcon, EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';
import TypedText from '@/components/TypedText';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

export default function BankDetailsPage() {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [initialLoad, setInitialLoad] = useState(true); // Track initial load completion
  const [bankAccount, setBankAccount] = useState(null);
  const [isEditing, setIsEditing] = useState(false);
  const [isAccountNoEditing, setIsAccountNoEditing] = useState(false);
  const [formData, setFormData] = useState({
    accountType: '',
    bdBankCode: '',
    bankName: '',
    accountNo: '',
    reEnterAccountNo: '',
    ifscCode: '',
    bankBranch: '',
    accountHolderName: '',
  });
  const [errors, setErrors] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showAccountNo, setShowAccountNo] = useState(false);
  const [showReEnterAccountNo, setShowReEnterAccountNo] = useState(false);
  const [showReadDetails, setShowReadDetails] = useState(false);
  const [bankList, setBankList] = useState([]);
  const [accountTypes, setAccountTypes] = useState([]);
  const [isLoadingData, setIsLoadingData] = useState(true);
  const [apiError, setApiError] = useState('');

  const headerRef = useRef(null);
  const isMounted = useRef(true);

  // Load all data in a single effect with proper ordering
  useEffect(() => {
    isMounted.current = true;

    const loadAllData = async () => {
      try {
        setLoading(true);
        setInitialLoad(true);

        // 1. Load user data first
        const storedUser = localStorage.getItem('site_user');
        let userData = null;

        if (storedUser) {
          userData = JSON.parse(storedUser);
          setUser(userData);
          setFormData(prev => ({
            ...prev,
            accountHolderName: userData.PRIMARY_HOLDER_NAME || userData.primary_holder_name || userData.name || ''
          }));
        }

        // 2. Fetch dynamic data (banks and account types)
        const [banksResponse, accountTypesResponse] = await Promise.all([
          fetch(`${API_BASE_URL}/master_data.php?action=banks`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            cache: 'no-cache'
          }),
          fetch(`${API_BASE_URL}/master_data.php?action=bank_acc_type`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            cache: 'no-cache'
          })
        ]);

        if (!banksResponse.ok || !accountTypesResponse.ok) {
          throw new Error('Failed to fetch master data');
        }

        const banksData = await banksResponse.json();
        const accountTypesData = await accountTypesResponse.json();

        // Process banks data
        let processedBanks = [];
        if (banksData.success) {
          const banksMap = new Map();
          processedBanks = Array.isArray(banksData.data) ? banksData.data.map(bank => {
            const code = bank.BD_BANK_CODE || bank.bd_bank_code || bank.code || '';
            const name = bank.BD_BANK_NAME || bank.bd_bank_name || bank.name || '';
            const id = bank.id || bank.ID || '';

            let uniqueKey = `${code}_${name}`;
            if (!code && !name) {
              uniqueKey = `bank_${id}`;
            }

            let counter = 1;
            let finalKey = uniqueKey;
            while (banksMap.has(finalKey)) {
              finalKey = `${uniqueKey}_${counter}`;
              counter++;
            }

            banksMap.set(finalKey, true);

            return {
              id: finalKey,
              bdBankCode: code,
              name: name,
              originalId: id
            };
          }).filter(bank => bank.name && bank.name.trim() !== '') : [];

          setBankList(processedBanks);
        }

        // Process account types
        let processedAccountTypes = [];
        if (accountTypesData.success) {
          const typesMap = new Map();
          processedAccountTypes = Array.isArray(accountTypesData.data) ? accountTypesData.data.map(type => {
            const code = type.code || type.CODE || type.bank_acc_type_code || '';
            const label = type.details || type.DETAILS || type.bank_acc_type_name || type.name || '';

            let uniqueKey = `${code}_${label}`;
            let counter = 1;
            let finalKey = uniqueKey;
            while (typesMap.has(finalKey)) {
              finalKey = `${uniqueKey}_${counter}`;
              counter++;
            }

            typesMap.set(finalKey, true);

            return {
              id: finalKey,
              code: code,
              label: label
            };
          }).filter(type => type.code && type.label) : [];

          setAccountTypes(processedAccountTypes);

          // Set default account type
          if (processedAccountTypes.length > 0) {
            setFormData(prev => ({
              ...prev,
              accountType: prev.accountType || processedAccountTypes[0].code
            }));
          }
        }

        setIsLoadingData(false);

        // 3. Fetch bank account if user exists
        if (userData?.id) {
          const accountResponse = await fetch(`${API_BASE_URL}/bank_api.php?user_id=${userData.id}`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            }
          });

          if (accountResponse.ok) {
            const accountData = await accountResponse.json();
            if (accountData.success && accountData.data) {
              const account = accountData.data;
              setBankAccount(account);

              // Find selected bank
              let selectedBank = null;
              if (account.bdBankCode && processedBanks.length > 0) {
                selectedBank = processedBanks.find(bank => bank.bdBankCode === account.bdBankCode);
              }

              setFormData({
                accountType: account.accountType || (processedAccountTypes.length > 0 ? processedAccountTypes[0].code : ''),
                bdBankCode: account.bdBankCode || '',
                bankName: selectedBank ? selectedBank.name : account.bankName || '',
                accountNo: account.accountNo || '',
                reEnterAccountNo: account.accountNo || '',
                ifscCode: account.ifscCode || '',
                bankBranch: account.bankBranch || '',
                accountHolderName: userData?.PRIMARY_HOLDER_NAME || userData?.primary_holder_name || userData?.name || '',
              });

              setIsEditing(false);
              setIsAccountNoEditing(false);
              setShowReadDetails(false);
            } else {
              // No account exists, set to edit mode
              setIsEditing(true);
            }
          } else {
            setIsEditing(true);
          }
        }

      } catch (error) {
        console.error('Error loading data:', error);
        setApiError(`Failed to load data: ${error.message}. Please refresh the page.`);
        setIsLoadingData(false);
        setIsEditing(true); // Allow editing even if API fails
      } finally {
        setLoading(false);
        // Add a small delay before showing content to prevent flash
        setTimeout(() => {
          if (isMounted.current) {
            setInitialLoad(false);
          }
        }, 50);
      }
    };

    loadAllData();

    return () => {
      isMounted.current = false;
    };
  }, []); // Empty dependency array - run only once

  const scrollToHeader = () => {
    setTimeout(() => {
      if (headerRef.current) {
        const headerElement = headerRef.current;
        const headerOffset = 100;
        const elementPosition = headerElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    }, 100);
  };

  const handleEditClick = () => {
    setIsEditing(true);
    setIsAccountNoEditing(false);
    setShowReadDetails(false);
    setErrors({});
    scrollToHeader();
  };

  const handleAccountNoEdit = () => {
    setIsAccountNoEditing(true);
  };

  const handleInputChange = (e) => {
    if (!isEditing) return;

    const { name, value } = e.target;

    if (name === 'bdBankCode') {
      const selectedBank = bankList.find(bank => bank.bdBankCode === value);
      setFormData(prev => ({
        ...prev,
        bdBankCode: value,
        bankName: selectedBank ? selectedBank.name : '',
      }));
    } else if (name === 'accountNo') {
      setIsAccountNoEditing(true);
      setFormData(prev => ({
        ...prev,
        [name]: value
      }));
    } else if (name !== 'accountHolderName') {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }));
    }

    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: ''
      }));
    }

    if (name === 'accountNo' || name === 'reEnterAccountNo') {
      if (errors.accountMismatch) {
        setErrors(prev => ({
          ...prev,
          accountMismatch: ''
        }));
      }
    }
  };

  const handleClearForm = () => {
    if (!isEditing) return;

    if (window.confirm('Are you sure you want to clear all bank details?')) {
      setFormData({
        accountType: accountTypes.length > 0 ? accountTypes[0].code : '',
        bdBankCode: '',
        bankName: '',
        accountNo: '',
        reEnterAccountNo: '',
        ifscCode: '',
        bankBranch: '',
        accountHolderName: user?.PRIMARY_HOLDER_NAME || user?.primary_holder_name || user?.name || '',
      });
      setIsAccountNoEditing(false);
      setErrors({});
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.accountType) {
      newErrors.accountType = 'Account Type is required';
    }

    if (!formData.bdBankCode) {
      newErrors.bdBankCode = 'Bank selection is required';
    }

    if (!formData.accountNo || !/^\d{9,18}$/.test(formData.accountNo)) {
      newErrors.accountNo = 'Account Number must be 9-18 digits';
    }

    if (isAccountNoEditing && formData.accountNo !== formData.reEnterAccountNo) {
      newErrors.accountMismatch = 'Account numbers do not match. Please re-enter the same account number in both fields.';
    }

    if (!formData.ifscCode || !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(formData.ifscCode.toUpperCase())) {
      newErrors.ifscCode = 'IFSC Code must be 11 characters (e.g., SBIN0001234)';
    }

    if (!formData.bankBranch) {
      newErrors.bankBranch = 'Branch is required';
    }

    if (!formData.accountHolderName) {
      newErrors.accountHolderName = 'Account Holder Name is required';
    }

    return newErrors;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!isEditing) {
      handleEditClick();
      return;
    }

    const validationErrors = validateForm();
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    if (!user?.id) {
      alert('User not found. Please login again.');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await fetch(`${API_BASE_URL}/bank_api.php?user_id=${user.id}`, {
        method: 'PUT',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
        },
        body: JSON.stringify({
          accountType: formData.accountType,
          bdBankCode: formData.bdBankCode,
          accountNo: formData.accountNo,
          ifscCode: formData.ifscCode,
          bankBranch: formData.bankBranch,
          accountHolderName: formData.accountHolderName,
          user_id: user.id
        })
      });

      if (!response.ok) {
        throw new Error(`API error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        const updatedAccount = {
          ...data.data,
          bankName: formData.bankName,
          accountHolderName: formData.accountHolderName
        };
        setBankAccount(updatedAccount);
        setIsEditing(false);
        setIsAccountNoEditing(false);
        setShowReadDetails(false);
        setFormData(prev => ({
          ...prev,
          reEnterAccountNo: formData.accountNo
        }));
        alert('Bank details updated successfully!');
        scrollToHeader();
      } else {
        throw new Error(data.message || 'Failed to update bank details');
      }

    } catch (error) {
      console.error('Error updating bank details:', error);
      alert(`Failed to update bank details: ${error.message}`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const getAccountTypeLabel = (code) => {
    const type = accountTypes.find(t => t.code === code);
    return type ? type.label : code;
  };

  const handleCancelEdit = () => {
    if (bankAccount) {
      let selectedBank = null;
      if (bankAccount.bdBankCode) {
        selectedBank = bankList.find(bank => bank.bdBankCode === bankAccount.bdBankCode);
      }

      setFormData({
        accountType: bankAccount.accountType || '',
        bdBankCode: bankAccount.bdBankCode || '',
        bankName: selectedBank ? selectedBank.name : bankAccount.bankName || '',
        accountNo: bankAccount.accountNo || '',
        reEnterAccountNo: bankAccount.accountNo || '',
        ifscCode: bankAccount.ifscCode || '',
        bankBranch: bankAccount.bankBranch || '',
        accountHolderName: user?.PRIMARY_HOLDER_NAME || user?.primary_holder_name || user?.name || '',
      });
      setIsEditing(false);
      setIsAccountNoEditing(false);
      setShowReadDetails(false);
      setErrors({});
      scrollToHeader();
    } else {
      setIsEditing(false);
      setIsAccountNoEditing(false);
      setShowReadDetails(false);
      setErrors({});
      scrollToHeader();
    }
  };

  const formatAccountNumber = (accountNo) => {
    if (!accountNo) return '';
    const length = accountNo.length;
    if (length <= 4) return accountNo;
    return '****' + accountNo.slice(-4);
  };

  const maskValue = (value) => {
    if (!value) return '';
    const str = String(value).trim();
    if (str === '' || str === '0000-00-00' || str === '0000-00-00 00:00:00' || str === 'null' || str === 'undefined') {
      return '';
    }
    if (str.length <= 4) return str;
    return '*****' + str.slice(-4);
  };

  // Show loading state only on initial load
  if (loading || initialLoad) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading bank details...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div ref={headerRef}>
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              <TypedText text="Bank Details" className="text-gray-900" />
            </h1>
            <p className="text-gray-600">Manage your bank account for transactions</p>
          </div>
        </div>
      </div>

      {apiError && (
        <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div className="flex items-center">
            <svg className="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.408 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span className="text-yellow-800">{apiError}</span>
          </div>
        </div>
      )}

      <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div className="p-6">
          <form onSubmit={handleSubmit} className="space-y-6">
            {bankAccount && !isEditing && (
              <div className="mb-6 p-4 bg-gray-50 rounded-lg border">
                <div className="flex items-center justify-between mb-3 border-b pb-2">
                  <h4 className="font-medium text-gray-900">Bank Account Information</h4>
                  <button
                    type="button"
                    onClick={() => setShowReadDetails(!showReadDetails)}
                    className="text-blue-600 hover:text-blue-700 font-medium text-sm flex items-center space-x-1 focus:outline-none"
                    title={showReadDetails ? "Hide Bank Details" : "Show Bank Details"}
                  >
                    {showReadDetails ? (
                      <>
                        <EyeSlashIcon className="h-4 w-4" />
                        <span>Hide Details</span>
                      </>
                    ) : (
                      <>
                        <EyeIcon className="h-4 w-4" />
                        <span>Show Details</span>
                      </>
                    )}
                  </button>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-600 mb-1">Account Type</p>
                    <p className="font-medium text-gray-900">
                      {showReadDetails ? getAccountTypeLabel(bankAccount.accountType) : maskValue(getAccountTypeLabel(bankAccount.accountType))}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600 mb-1">Bank</p>
                    <p className="font-medium text-gray-900">
                      {showReadDetails ? `${bankAccount.bankName} (${bankAccount.bdBankCode})` : maskValue(`${bankAccount.bankName} (${bankAccount.bdBankCode})`)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600 mb-1">Account Number</p>
                    <p className="font-mono font-medium text-gray-900">
                      {showReadDetails ? bankAccount.accountNo : maskValue(bankAccount.accountNo)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600 mb-1">IFSC Code</p>
                    <p className="font-mono font-medium text-gray-900">
                      {showReadDetails ? bankAccount.ifscCode : maskValue(bankAccount.ifscCode)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600 mb-1">Branch</p>
                    <p className="font-medium text-gray-900">
                      {showReadDetails ? bankAccount.bankBranch : maskValue(bankAccount.bankBranch)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600 mb-1">Account Holder Name</p>
                    <p className="font-medium text-gray-900">
                      {showReadDetails ? bankAccount.accountHolderName : maskValue(bankAccount.accountHolderName)}
                    </p>
                  </div>
                </div>
              </div>
            )}

            {(isEditing || !bankAccount) && (
              <>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Account Type <span className="text-red-500">*</span>
                    </label>
                    <select
                      name="accountType"
                      value={formData.accountType}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.accountType ? 'border-red-300' : 'border-gray-300'
                        }`}
                      required
                      disabled={isSubmitting || !isEditing}
                    >
                      <option value="">Select Account Type</option>
                      {accountTypes.map((type) => (
                        <option key={type.id} value={type.code}>
                          {type.label}
                        </option>
                      ))}
                    </select>
                    {errors.accountType && (
                      <p className="mt-1 text-sm text-red-600">{errors.accountType}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Select Bank <span className="text-red-500">*</span>
                    </label>
                    <select
                      name="bdBankCode"
                      value={formData.bdBankCode}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.bdBankCode ? 'border-red-300' : 'border-gray-300'
                        }`}
                      required
                      disabled={isSubmitting || !isEditing}
                    >
                      <option value="">Select Bank</option>
                      {bankList.map((bank) => (
                        <option key={bank.id} value={bank.bdBankCode}>
                          {bank.name} ({bank.bdBankCode})
                        </option>
                      ))}
                    </select>
                    {errors.bdBankCode && (
                      <p className="mt-1 text-sm text-red-600">{errors.bdBankCode}</p>
                    )}
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Account Holder Name <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      name="accountHolderName"
                      value={formData.accountHolderName}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed text-gray-700"
                      placeholder="Account holder name"
                      required
                      disabled={true}
                      readOnly
                    />
                    {errors.accountHolderName && (
                      <p className="mt-1 text-sm text-red-600">{errors.accountHolderName}</p>
                    )}
                  </div>

                  <div className="relative">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Account Number <span className="text-red-500">*</span>
                      <span className="ml-1 text-xs text-gray-500">(9-18 digits)</span>
                    </label>
                    <div className="relative">
                      <input
                        type={showAccountNo ? "text" : "password"}
                        name="accountNo"
                        value={formData.accountNo}
                        onChange={handleInputChange}
                        onFocus={handleAccountNoEdit}
                        className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.accountNo ? 'border-red-300' : 'border-gray-300'
                          }`}
                        placeholder="Enter account number"
                        maxLength={18}
                        required
                        disabled={isSubmitting || !isEditing}
                      />
                      <button
                        type="button"
                        onClick={() => setShowAccountNo(!showAccountNo)}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700"
                        disabled={isSubmitting || !isEditing}
                      >
                        {showAccountNo ? (
                          <EyeSlashIcon className="h-5 w-5" />
                        ) : (
                          <EyeIcon className="h-5 w-5" />
                        )}
                      </button>
                    </div>
                    {errors.accountNo && (
                      <p className="mt-1 text-sm text-red-600">{errors.accountNo}</p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Re-enter Account Number <span className="text-red-500">*</span>
                      <span className="ml-1 text-xs text-gray-500">
                        {isAccountNoEditing ? '(Editable for verification)' : '(Read-only)'}
                      </span>
                    </label>
                    <div className="relative">
                      <input
                        type={showReEnterAccountNo ? "text" : "password"}
                        name="reEnterAccountNo"
                        value={formData.reEnterAccountNo}
                        onChange={handleInputChange}
                        className={`w-full pl-4 pr-12 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.accountMismatch ? 'border-red-300' : 'border-gray-300'
                          } ${!isAccountNoEditing ? 'bg-gray-100' : ''}`}
                        placeholder="Re-enter account number"
                        maxLength={18}
                        required
                        readOnly={!isAccountNoEditing}
                        disabled={isSubmitting || !isEditing}
                      />
                      <button
                        type="button"
                        onClick={() => setShowReEnterAccountNo(!showReEnterAccountNo)}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700"
                        disabled={isSubmitting || !isEditing}
                      >
                        {showReEnterAccountNo ? (
                          <EyeSlashIcon className="h-5 w-5" />
                        ) : (
                          <EyeIcon className="h-5 w-5" />
                        )}
                      </button>
                    </div>
                    {errors.accountMismatch && (
                      <div className="mt-1 flex items-start">
                        <p className="text-sm text-red-600">{errors.accountMismatch}</p>
                      </div>
                    )}
                    {!isAccountNoEditing && bankAccount && (
                      <p className="mt-1 text-xs text-green-600">
                        ✓ Account number verified. Click on Account Number field to edit.
                      </p>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      IFSC Code <span className="text-red-500">*</span>
                      <span className="ml-1 text-xs text-gray-500">(11 characters)</span>
                    </label>
                    <input
                      type="text"
                      name="ifscCode"
                      value={formData.ifscCode}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.ifscCode ? 'border-red-300' : 'border-gray-300'
                        }`}
                      placeholder="Enter 11-character IFSC code (e.g., SBIN0001234)"
                      maxLength={11}
                      required
                      disabled={isSubmitting || !isEditing}
                    />
                    {errors.ifscCode && (
                      <p className="mt-1 text-sm text-red-600">{errors.ifscCode}</p>
                    )}
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Branch Name <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      name="bankBranch"
                      value={formData.bankBranch}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.bankBranch ? 'border-red-300' : 'border-gray-300'
                        }`}
                      placeholder="Enter branch name"
                      required
                      disabled={isSubmitting || !isEditing}
                    />
                    {errors.bankBranch && (
                      <p className="mt-1 text-sm text-red-600">{errors.bankBranch}</p>
                    )}
                  </div>
                </div>

                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <h4 className="font-medium text-blue-900 mb-2">Important Information</h4>
                  <ul className="text-sm text-blue-800 space-y-1">
                    <li>• All fields marked with <span className="text-red-500">*</span> are mandatory</li>
                    <li>• Account holder name is automatically fetched from your profile and cannot be edited</li>
                    <li>• Select bank from dropdown</li>
                    <li>• Bank account verification takes 24-48 hours</li>
                    <li>• Ensure IFSC code and account number are correct</li>
                    <li>• Account number must be 9-18 digits</li>
                    <li>• IFSC code must be exactly 11 characters (e.g., SBIN0001234)</li>
                    <li>• Enter the correct branch name where your account is held</li>
                    <li>• To change account number, click on the Account Number field and then re-enter the new number for verification</li>
                  </ul>
                </div>
              </>
            )}

            <div className="flex justify-between pt-4 border-t">
              {(isEditing || !bankAccount) && (
                <button
                  type="button"
                  onClick={handleClearForm}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled={isSubmitting || !isEditing}
                >
                  <TrashIcon className="h-4 w-4 mr-1" />
                  Clear Form
                </button>
              )}

              <div className="flex space-x-3 ml-auto">
                {(isEditing || !bankAccount) && (
                  <button
                    type="button"
                    onClick={handleCancelEdit}
                    className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled={isSubmitting}
                  >
                    Cancel
                  </button>
                )}
                <button
                  type="submit"
                  className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled={isSubmitting}
                >
                  {isSubmitting ? (
                    <span className="flex items-center">
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                      {bankAccount ? 'Updating...' : 'Saving...'}
                    </span>
                  ) : bankAccount && !isEditing ? (
                    'Edit Bank Details'
                  ) : bankAccount && isEditing ? (
                    'Save Bank Details'
                  ) : (
                    'Add Bank Account'
                  )}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}