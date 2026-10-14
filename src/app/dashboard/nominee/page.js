'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

export default function NomineeDetailsPage() {
  const router = useRouter();
  const [isInitialized, setIsInitialized] = useState(false);
  const [visibleFields, setVisibleFields] = useState({});
  const toggleFieldVisibility = (fieldName) => {
    setVisibleFields(prev => ({ ...prev, [fieldName]: !prev[fieldName] }));
  };
  const [formData, setFormData] = useState({
    // Nomination Details
    nomination: '',
    authentication: '',
    
    // Nominee 1 Details
    nominee1Name: '',
    nominee1Relationship: '',
    nominee1ApplicablePercentage: '',
    nominee1MinorFlag: 'N',
    nominee1DOB: '',
    nominee1Guardian: '',
    nominee1GuardianPAN: '',
    nominee1IdentityType: '',
    nominee1IdentityNo: '',
    nominee1Email: '',
    nominee1Mobile: '',
    nominee1Address: '',
    nominee1City: '',
    nominee1Pincode: '',
    nominee1Country: '',
    nominee1AddressSameAsHolder: 'N',
    
    // Nominee 2 Details
    nominee2Name: '',
    nominee2Relationship: '',
    nominee2ApplicablePercentage: '',
    nominee2MinorFlag: 'N',
    nominee2DOB: '',
    nominee2Guardian: '',
    nominee2GuardianPAN: '',
    nominee2IdentityType: '',
    nominee2IdentityNo: '',
    nominee2Email: '',
    nominee2Mobile: '',
    nominee2Address: '',
    nominee2City: '',
    nominee2Pincode: '',
    nominee2Country: '',
    nominee2AddressSameAsHolder: 'N',
    
    // Nominee 3 Details
    nominee3Name: '',
    nominee3Relationship: '',
    nominee3ApplicablePercentage: '',
    nominee3MinorFlag: 'N',
    nominee3DOB: '',
    nominee3Guardian: '',
    nominee3GuardianPAN: '',
    nominee3IdentityType: '',
    nominee3IdentityNo: '',
    nominee3Email: '',
    nominee3Mobile: '',
    nominee3Address: '',
    nominee3City: '',
    nominee3Pincode: '',
    nominee3Country: '',
    nominee3AddressSameAsHolder: 'N',
    
    // Additional
    nomineeNamesRegistrationStatus: '',
    filler1: ''
  });

  const [errors, setErrors] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [hasNominee, setHasNominee] = useState(false);

  // State variables
  const [isEditMode, setIsEditMode] = useState(false);
  const [hasExistingData, setHasExistingData] = useState(false);
  const [userId, setUserId] = useState(null);
  const [token, setToken] = useState('');
  const [primaryHolderAddress, setPrimaryHolderAddress] = useState({
    address: '',
    city: '',
    pincode: '',
    country: ''
  });

  const fetchPrimaryHolderAddress = async (uid, tok) => {
    try {
      const response = await fetch(`${API_BASE_URL}/personal_details_api.php?user_id=${uid}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${tok}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });
      if (response.ok) {
        const resData = await response.json();
        if (resData.success && resData.data) {
          const d = resData.data;
          const holderAddr = {
            address: d.ADDRESS_1 || d.address || '',
            city: d.CITY || d.city || '',
            pincode: d.PINCODE || d.pincode || '',
            country: d.COUNTRY || d.country || ''
          };
          setPrimaryHolderAddress(holderAddr);
          return holderAddr;
        }
      }
    } catch (err) {
      console.error('Error fetching primary holder address:', err);
    }
    return { address: '', city: '', pincode: '', country: '' };
  };

  // Add ref for message container
  const messageContainerRef = useRef(null);
  const successTimeoutRef = useRef(null);

  // State for dropdown data
  const [dropdownData, setDropdownData] = useState({
    relationshipOptions: [],
    identityTypeOptions: [],
    yesNoOptions: [],
    authenticationOptions: [],
    countryOptions: []
  });

  const [isLoading, setIsLoading] = useState(true);
  const [apiError, setApiError] = useState('');

  // API base URL from environment
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;
  const NOMINEE_DETAILS_API = `${API_BASE_URL}/nominee_details_api.php`;

  // Function to check if an identity type is Aadhar
  const isAadharType = (identityTypeValue) => {
    // Check by value (code)
    if (identityTypeValue === '02' || identityTypeValue === '2') return true;
    
    // Also check by label if options are available
    const selectedOption = dropdownData.identityTypeOptions.find(opt => opt.value === identityTypeValue);
    if (selectedOption && selectedOption.label.toLowerCase().includes('aadhar')) return true;
    
    return false;
  };

  // Function to scroll to message container
  const scrollToMessages = useCallback(async (retryCount = 0) => {
    const maxRetries = 10;
    const retryDelay = 100;
    
    await new Promise(resolve => setTimeout(resolve, 0));
    
    if (messageContainerRef.current) {
      requestAnimationFrame(() => {
        if (messageContainerRef.current) {
          messageContainerRef.current.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start',
            inline: 'nearest'
          });
        }
      });
      return true;
    } else if (retryCount < maxRetries) {
      const delay = retryDelay * Math.pow(1.5, retryCount);
      return new Promise((resolve) => {
        setTimeout(() => {
          resolve(scrollToMessages(retryCount + 1));
        }, delay);
      });
    } else {
      return false;
    }
  }, []);

  // MutationObserver to watch for message changes
  useEffect(() => {
    if (!messageContainerRef.current) return;
    
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          scrollToMessages();
        }
      });
    });
    
    observer.observe(messageContainerRef.current, {
      childList: true,
      subtree: true
    });
    
    return () => observer.disconnect();
  }, [scrollToMessages]);

  // Function to scroll to error field
  const scrollToErrorField = useCallback(() => {
    const errorFields = document.querySelectorAll('.border-red-300');
    
    if (errorFields.length > 0) {
      errorFields[0].scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
      });
      
      const input = errorFields[0].querySelector('input, select, textarea');
      if (input) {
        input.focus();
      }
      return true;
    }
    return false;
  }, []);

  // Fetch master data from API
  useEffect(() => {
    const fetchMasterData = async () => {
      try {
        setIsLoading(true);
        setApiError('');
        
        const response = await fetch(`${API_BASE_URL}/master_data.php?action=get_all_tables`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          cache: 'no-cache'
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
          processMasterData(data.data || data);
        } else {
          throw new Error(data.message || 'Failed to fetch master data');
        }
        
      } catch (error) {
        console.error('Error fetching master data:', error);
        setApiError(`Failed to load dropdown data: ${error.message}. Dropdowns will be empty.`);
        
        setDropdownData({
          relationshipOptions: [],
          identityTypeOptions: [],
          yesNoOptions: [],
          authenticationOptions: [],
          countryOptions: []
        });
      } finally {
        setIsLoading(false);
      }
    };

    const processMasterData = (masterData) => {
      const transformData = (dataArray, idKey = 'code', labelKey = 'description') => {
        if (!Array.isArray(dataArray)) return [];
        
        return dataArray.map(item => ({
          value: item[idKey] || item.id || item.value,
          label: item[labelKey] || item.name || item.label || item.value
        })).filter(item => item.value && item.label);
      };
      
      const relationshipOptions = transformData(masterData.nominee_relation || [], 'code', 'value');
      const identityTypeOptions = transformData(masterData.nominee_identity || [], 'code', 'value');
      const countryOptions = transformData(masterData.country || [], 'details', 'details');
      
      const authenticationOptions = transformData(masterData.nominee_auth || [], 'code', 'description');
      
      if (authenticationOptions.length === 0) {
        authenticationOptions.push(
          { value: 'E', label: 'E-Sign' },
          { value: 'O', label: 'OTP' },
          { value: 'W', label: 'Wet Signature' }
        );
      }
      
      const yesNoOptions = [
        { value: 'N', label: 'No' },
        { value: 'Y', label: 'Yes' }
      ];
      
      setDropdownData({
        relationshipOptions,
        identityTypeOptions,
        yesNoOptions,
        authenticationOptions,
        countryOptions
      });
    };

    fetchMasterData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Load user and nominee data on component mount
  useEffect(() => {
    const loadUserAndData = async () => {
      try {
        const storedToken = localStorage.getItem('site_token');
        const storedUser = localStorage.getItem('site_user');
        
        if (!storedToken || !storedUser) {
          router.push('/login');
          return;
        }
        
        const user = JSON.parse(storedUser);
        
        setToken(storedToken);
        setUserId(user.id || user.user_id);
        
        const holderAddr = await fetchPrimaryHolderAddress(user.id || user.user_id, storedToken);
        const hasData = await checkExistingNomineeDetails(user.id || user.user_id, storedToken);
        setHasExistingData(hasData);
        
        if (hasData) {
          await loadNomineeDetails(user.id || user.user_id, storedToken, holderAddr);
          setIsEditMode(false);
        } else {
          setIsEditMode(true);
        }
        
        setIsInitialized(true);
      } catch (error) {
        console.error('Error loading user data:', error);
        setApiError('Failed to load user data');
        setIsEditMode(true);
        setIsInitialized(true);
      } finally {
        setIsLoading(false);
      }
    };

    loadUserAndData();
    
    return () => {
      if (successTimeoutRef.current) {
        clearTimeout(successTimeoutRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [router]);

  const checkExistingNomineeDetails = async (userId, token) => {
    try {
      const response = await fetch(`${NOMINEE_DETAILS_API}?action=get&user_id=${userId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });
      
      if (!response.ok) {
        return false;
      }
      
      const data = await response.json();
      
      // Consider data as existing if:
      // 1. nomination='Y' with nominee data (data.data is populated), OR
      // 2. nomination='N' — user explicitly opted out (data.data exists with NOMINATION='N')
      if (data.success && data.data) {
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('Error checking existing nominee data:', error);
      return false;
    }
  };

  const loadNomineeDetails = async (userId, token, holderAddrParam) => {
    try {
      setIsLoading(true);
      setApiError('');
      
      const response = await fetch(`${NOMINEE_DETAILS_API}?action=get&user_id=${userId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success && data.data) {
        const apiData = data.data;
        const hAddr = holderAddrParam || primaryHolderAddress;
        
        const formatDateForInput = (dateString) => {
          if (!dateString || dateString === '0000-00-00') return '';
          if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateString;
          }
          const date = new Date(dateString);
          if (!isNaN(date.getTime())) {
            return date.toISOString().split('T')[0];
          }
          return '';
        };
        
        const isSame1 = (apiData.NOMINEE_1_ADDRESS_SAME_AS_HOLDER || 'N') === 'Y';
        const isSame2 = (apiData.NOMINEE_2_ADDRESS_SAME_AS_HOLDER || 'N') === 'Y';
        const isSame3 = (apiData.NOMINEE_3_ADDRESS_SAME_AS_HOLDER || 'N') === 'Y';

        const updatedFormData = {
          nomination: apiData.NOMINATION || '',
          authentication: apiData.AUTHENTICATION || '',
          nominee1Name: apiData.NOMINEE_1_NAME || '',
          nominee1Relationship: apiData.NOMINEE_1_RELATIONSHIP || '',
          nominee1ApplicablePercentage: apiData.NOMINEE_1_APPLICABLE_PERCENT || '',
          nominee1MinorFlag: apiData.NOMINEE_1_MINOR_FLAG || 'N',
          nominee1DOB: formatDateForInput(apiData.NOMINEE_1_DOB || ''),
          nominee1Guardian: apiData.NOMINEE_1_GUARDIAN || '',
          nominee1GuardianPAN: apiData.NOMINEE_1_GUARDIAN_PAN || '',
          nominee1IdentityType: apiData.NOMINEE_1_IDENTITY_TYPE || '',
          nominee1IdentityNo: apiData.NOMINEE_1_IDENTITY_NO || '',
          nominee1Email: apiData.NOMINEE_1_EMAIL || '',
          nominee1Mobile: apiData.NOMINEE_1_MOBILE || '',
          nominee1Address: isSame1 ? (apiData.NOMINEE_1_ADDRESS1 || hAddr?.address || '') : (apiData.NOMINEE_1_ADDRESS1 || ''),
          nominee1City: isSame1 ? (apiData.NOMINEE_1_CITY || hAddr?.city || '') : (apiData.NOMINEE_1_CITY || ''),
          nominee1Pincode: isSame1 ? (apiData.NOMINEE_1_PINCODE || hAddr?.pincode || '') : (apiData.NOMINEE_1_PINCODE || ''),
          nominee1Country: isSame1 ? (apiData.NOMINEE_1_COUNTRY || hAddr?.country || '') : (apiData.NOMINEE_1_COUNTRY || ''),
          nominee1AddressSameAsHolder: apiData.NOMINEE_1_ADDRESS_SAME_AS_HOLDER || 'N',
          nominee2Name: apiData.NOMINEE_2_NAME || '',
          nominee2Relationship: apiData.NOMINEE_2_RELATIONSHIP || '',
          nominee2ApplicablePercentage: apiData.NOMINEE_2_APPLICABLE_PERCENT || '',
          nominee2MinorFlag: apiData.NOMINEE_2_MINOR_FLAG || 'N',
          nominee2DOB: formatDateForInput(apiData.NOMINEE_2_DOB || ''),
          nominee2Guardian: apiData.NOMINEE_2_GUARDIAN || '',
          nominee2GuardianPAN: apiData.NOMINEE_2_GUARDIAN_PAN || '',
          nominee2IdentityType: apiData.NOMINEE_2_IDENTITY_TYPE || '',
          nominee2IdentityNo: apiData.NOMINEE_2_IDENTITY_NO || '',
          nominee2Email: apiData.NOMINEE_2_EMAIL || '',
          nominee2Mobile: apiData.NOMINEE_2_MOBILE || '',
          nominee2Address: isSame2 ? (apiData.NOMINEE_2_ADDRESS1 || hAddr?.address || '') : (apiData.NOMINEE_2_ADDRESS1 || ''),
          nominee2City: isSame2 ? (apiData.NOMINEE_2_CITY || hAddr?.city || '') : (apiData.NOMINEE_2_CITY || ''),
          nominee2Pincode: isSame2 ? (apiData.NOMINEE_2_PINCODE || hAddr?.pincode || '') : (apiData.NOMINEE_2_PINCODE || ''),
          nominee2Country: isSame2 ? (apiData.NOMINEE_2_COUNTRY || hAddr?.country || '') : (apiData.NOMINEE_2_COUNTRY || ''),
          nominee2AddressSameAsHolder: apiData.NOMINEE_2_ADDRESS_SAME_AS_HOLDER || 'N',
          nominee3Name: apiData.NOMINEE_3_NAME || '',
          nominee3Relationship: apiData.NOMINEE_3_RELATIONSHIP || '',
          nominee3ApplicablePercentage: apiData.NOMINEE_3_APPLICABLE_PERCENT || '',
          nominee3MinorFlag: apiData.NOMINEE_3_MINOR_FLAG || 'N',
          nominee3DOB: formatDateForInput(apiData.NOMINEE_3_DOB || ''),
          nominee3Guardian: apiData.NOMINEE_3_GUARDIAN || '',
          nominee3GuardianPAN: apiData.NOMINEE_3_GUARDIAN_PAN || '',
          nominee3IdentityType: apiData.NOMINEE_3_IDENTITY_TYPE || '',
          nominee3IdentityNo: apiData.NOMINEE_3_IDENTITY_NO || '',
          nominee3Email: apiData.NOMINEE_3_EMAIL || '',
          nominee3Mobile: apiData.NOMINEE_3_MOBILE || '',
          nominee3Address: isSame3 ? (apiData.NOMINEE_3_ADDRESS1 || hAddr?.address || '') : (apiData.NOMINEE_3_ADDRESS1 || ''),
          nominee3City: isSame3 ? (apiData.NOMINEE_3_CITY || hAddr?.city || '') : (apiData.NOMINEE_3_CITY || ''),
          nominee3Pincode: isSame3 ? (apiData.NOMINEE_3_PINCODE || hAddr?.pincode || '') : (apiData.NOMINEE_3_PINCODE || ''),
          nominee3Country: isSame3 ? (apiData.NOMINEE_3_COUNTRY || hAddr?.country || '') : (apiData.NOMINEE_3_COUNTRY || ''),
          nominee3AddressSameAsHolder: apiData.NOMINEE_3_ADDRESS_SAME_AS_HOLDER || 'N',
          nomineeNamesRegistrationStatus: apiData.NOMINEE_NAMES_STATUS || '',
          filler1: apiData.Filler1 || ''
        };
        
        setFormData(updatedFormData);
        // Correctly reflect nomination state for both 'Y' and 'N'
        setHasNominee(updatedFormData.nomination === 'Y');
      }
    } catch (error) {
      console.error('Error loading nominee details:', error);
      setApiError(`Failed to load nominee details: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    setHasNominee(formData.nomination === 'Y');
  }, [formData.nomination]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    
    // When changing nomination to 'N', clear all nominee details in state
    if (name === 'nomination' && value === 'N') {
      setFormData({
        nomination: 'N',
        authentication: '',
        nominee1Name: '',
        nominee1Relationship: '',
        nominee1ApplicablePercentage: '',
        nominee1MinorFlag: 'N',
        nominee1DOB: '',
        nominee1Guardian: '',
        nominee1GuardianPAN: '',
        nominee1IdentityType: '',
        nominee1IdentityNo: '',
        nominee1Email: '',
        nominee1Mobile: '',
        nominee1Address: '',
        nominee1City: '',
        nominee1Pincode: '',
        nominee1Country: '',
        nominee1AddressSameAsHolder: 'N',
        nominee2Name: '',
        nominee2Relationship: '',
        nominee2ApplicablePercentage: '',
        nominee2MinorFlag: 'N',
        nominee2DOB: '',
        nominee2Guardian: '',
        nominee2GuardianPAN: '',
        nominee2IdentityType: '',
        nominee2IdentityNo: '',
        nominee2Email: '',
        nominee2Mobile: '',
        nominee2Address: '',
        nominee2City: '',
        nominee2Pincode: '',
        nominee2Country: '',
        nominee2AddressSameAsHolder: 'N',
        nominee3Name: '',
        nominee3Relationship: '',
        nominee3ApplicablePercentage: '',
        nominee3MinorFlag: 'N',
        nominee3DOB: '',
        nominee3Guardian: '',
        nominee3GuardianPAN: '',
        nominee3IdentityType: '',
        nominee3IdentityNo: '',
        nominee3Email: '',
        nominee3Mobile: '',
        nominee3Address: '',
        nominee3City: '',
        nominee3Pincode: '',
        nominee3Country: '',
        nominee3AddressSameAsHolder: 'N',
        nomineeNamesRegistrationStatus: '',
        filler1: ''
      });
      setErrors({});
      return;
    }

    // When changing AddressSameAsHolder, copy primary holder address if 'Y', clear if 'N'
    if (name.includes('AddressSameAsHolder')) {
      const match = name.match(/nominee(\d+)AddressSameAsHolder/);
      if (match) {
        const num = match[1];
        if (value === 'Y') {
          setFormData(prev => ({
            ...prev,
            [name]: 'Y',
            [`nominee${num}Address`]: primaryHolderAddress.address,
            [`nominee${num}City`]: primaryHolderAddress.city,
            [`nominee${num}Pincode`]: primaryHolderAddress.pincode,
            [`nominee${num}Country`]: primaryHolderAddress.country
          }));
        } else {
          setFormData(prev => ({
            ...prev,
            [name]: 'N',
            [`nominee${num}Address`]: '',
            [`nominee${num}City`]: '',
            [`nominee${num}Pincode`]: '',
            [`nominee${num}Country`]: ''
          }));
        }
        if (errors[name]) {
          setErrors(prev => ({ ...prev, [name]: '' }));
        }
        return;
      }
    }

    // Check if this is an Identity Number field
    if (name.includes('IdentityNo')) {
      const nomineeNum = name.replace('nominee', '').replace('IdentityNo', '');
      const identityTypeField = `nominee${nomineeNum}IdentityType`;
      const identityType = formData[identityTypeField];
      
      // Check if Aadhar is selected
      if (isAadharType(identityType)) {
        // Only allow digits
        const digitsOnly = value.replace(/\D/g, '');
        // Limit to 4 digits
        const trimmedValue = digitsOnly.slice(0, 4);
        
        setFormData(prev => ({
          ...prev,
          [name]: trimmedValue
        }));
        
        // Clear error when exactly 4 digits are entered
        if (trimmedValue.length === 4 && errors[name]) {
          setErrors(prev => ({ ...prev, [name]: '' }));
        }
        return;
      }
    }
    
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: ''
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.nomination) {
      newErrors.nomination = 'Nomination selection is required';
    }
    
    if (hasNominee) {
      if (!formData.authentication) {
        newErrors.authentication = 'Authentication method is required for nomination';
      }
      
      // Validate nominee 1
      if (!formData.nominee1Name.trim()) newErrors.nominee1Name = 'Nominee 1 Name is required';
      if (!formData.nominee1Relationship) newErrors.nominee1Relationship = 'Nominee 1 Relationship is required';
      if (!formData.nominee1ApplicablePercentage) newErrors.nominee1ApplicablePercentage = 'Nominee 1 Percentage is required';
      if (formData.nominee1MinorFlag === 'Y' && !formData.nominee1Guardian.trim()) {
        newErrors.nominee1Guardian = 'Nominee 1 Guardian Name is required for minor';
      }
      if (!formData.nominee1Email.trim()) newErrors.nominee1Email = 'Nominee 1 Email is required';
      if (!formData.nominee1Mobile.trim()) newErrors.nominee1Mobile = 'Nominee 1 Mobile is required';
      
      if (formData.nominee1AddressSameAsHolder === 'N') {
        if (!formData.nominee1Address.trim()) newErrors.nominee1Address = 'Nominee 1 Address is required';
        if (!formData.nominee1City.trim()) newErrors.nominee1City = 'Nominee 1 City is required';
        if (!formData.nominee1Pincode.trim()) newErrors.nominee1Pincode = 'Nominee 1 Pincode is required';
        if (!formData.nominee1Country) newErrors.nominee1Country = 'Nominee 1 Country is required';
      }
      
      if (!formData.nominee1IdentityType) newErrors.nominee1IdentityType = 'Nominee 1 Identity Type is required';
      if (formData.nominee1IdentityType && !formData.nominee1IdentityNo?.trim()) {
        newErrors.nominee1IdentityNo = 'Nominee 1 Identity Number is required';
      }
      
      // Aadhar validation for nominee 1
      if (isAadharType(formData.nominee1IdentityType)) {
        const aadharNumber = formData.nominee1IdentityNo || '';
        if (!/^\d{4}$/.test(aadharNumber)) {
          newErrors.nominee1IdentityNo = 'Please enter last 4 digits of Aadhar number';
        }
      }
      
      // Validate nominee 2
      if (formData.nominee2Name.trim()) {
        if (!formData.nominee2Relationship) newErrors.nominee2Relationship = 'Nominee 2 Relationship is required';
        if (!formData.nominee2ApplicablePercentage) newErrors.nominee2ApplicablePercentage = 'Nominee 2 Percentage is required';
        if (formData.nominee2MinorFlag === 'Y' && !formData.nominee2Guardian.trim()) {
          newErrors.nominee2Guardian = 'Nominee 2 Guardian Name is required for minor';
        }
        if (!formData.nominee2Email.trim()) newErrors.nominee2Email = 'Nominee 2 Email is required';
        if (!formData.nominee2Mobile.trim()) newErrors.nominee2Mobile = 'Nominee 2 Mobile is required';
        
        if (!formData.nominee2IdentityType) newErrors.nominee2IdentityType = 'Nominee 2 Identity Type is required';
        if (formData.nominee2IdentityType && !formData.nominee2IdentityNo?.trim()) {
          newErrors.nominee2IdentityNo = 'Nominee 2 Identity Number is required';
        }
        
        if (isAadharType(formData.nominee2IdentityType)) {
          const aadharNumber = formData.nominee2IdentityNo || '';
          if (!/^\d{4}$/.test(aadharNumber)) {
            newErrors.nominee2IdentityNo = 'Please enter last 4 digits of Aadhar number';
          }
        }
      }
      
      // Validate nominee 3
      if (formData.nominee3Name.trim()) {
        if (!formData.nominee3Relationship) newErrors.nominee3Relationship = 'Nominee 3 Relationship is required';
        if (!formData.nominee3ApplicablePercentage) newErrors.nominee3ApplicablePercentage = 'Nominee 3 Percentage is required';
        if (formData.nominee3MinorFlag === 'Y' && !formData.nominee3Guardian.trim()) {
          newErrors.nominee3Guardian = 'Nominee 3 Guardian Name is required for minor';
        }
        if (!formData.nominee3Email.trim()) newErrors.nominee3Email = 'Nominee 3 Email is required';
        if (!formData.nominee3Mobile.trim()) newErrors.nominee3Mobile = 'Nominee 3 Mobile is required';
        
        if (!formData.nominee3IdentityType) newErrors.nominee3IdentityType = 'Nominee 3 Identity Type is required';
        if (formData.nominee3IdentityType && !formData.nominee3IdentityNo?.trim()) {
          newErrors.nominee3IdentityNo = 'Nominee 3 Identity Number is required';
        }
        
        if (isAadharType(formData.nominee3IdentityType)) {
          const aadharNumber = formData.nominee3IdentityNo || '';
          if (!/^\d{4}$/.test(aadharNumber)) {
            newErrors.nominee3IdentityNo = 'Please enter last 4 digits of Aadhar number';
          }
        }
      }
      
      // Check total percentage
      let totalPercentage = 0;
      totalPercentage += parseFloat(formData.nominee1ApplicablePercentage) || 0;
      if (formData.nominee2Name.trim()) totalPercentage += parseFloat(formData.nominee2ApplicablePercentage) || 0;
      if (formData.nominee3Name.trim()) totalPercentage += parseFloat(formData.nominee3ApplicablePercentage) || 0;
      
      if (totalPercentage > 100) {
        newErrors.totalPercentage = 'Total percentage across all nominees cannot exceed 100%';
      }
      
      if (totalPercentage === 0 && hasNominee) {
        newErrors.totalPercentage = 'At least one nominee must have a percentage allocation';
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleEdit = () => {
    setIsEditMode(true);
    setSuccessMessage('');
    setErrors({});
  };

  const handleCancel = () => {
    if (hasExistingData) {
      setIsEditMode(false);
      if (userId && token) {
        loadNomineeDetails(userId, token);
      }
    }
  };

  const handleClear = () => {
    if (window.confirm('Are you sure you want to clear all form data? This will not delete saved data from the database.')) {
      const emptyForm = {
        nomination: '',
        authentication: '',
        nominee1Name: '',
        nominee1Relationship: '',
        nominee1ApplicablePercentage: '',
        nominee1MinorFlag: 'N',
        nominee1DOB: '',
        nominee1Guardian: '',
        nominee1GuardianPAN: '',
        nominee1IdentityType: '',
        nominee1IdentityNo: '',
        nominee1Email: '',
        nominee1Mobile: '',
        nominee1Address: '',
        nominee1City: '',
        nominee1Pincode: '',
        nominee1Country: '',
        nominee1AddressSameAsHolder: 'N',
        nominee2Name: '',
        nominee2Relationship: '',
        nominee2ApplicablePercentage: '',
        nominee2MinorFlag: 'N',
        nominee2DOB: '',
        nominee2Guardian: '',
        nominee2GuardianPAN: '',
        nominee2IdentityType: '',
        nominee2IdentityNo: '',
        nominee2Email: '',
        nominee2Mobile: '',
        nominee2Address: '',
        nominee2City: '',
        nominee2Pincode: '',
        nominee2Country: '',
        nominee2AddressSameAsHolder: 'N',
        nominee3Name: '',
        nominee3Relationship: '',
        nominee3ApplicablePercentage: '',
        nominee3MinorFlag: 'N',
        nominee3DOB: '',
        nominee3Guardian: '',
        nominee3GuardianPAN: '',
        nominee3IdentityType: '',
        nominee3IdentityNo: '',
        nominee3Email: '',
        nominee3Mobile: '',
        nominee3Address: '',
        nominee3City: '',
        nominee3Pincode: '',
        nominee3Country: '',
        nominee3AddressSameAsHolder: 'N',
        nomineeNamesRegistrationStatus: '',
        filler1: ''
      };
      
      setFormData(emptyForm);
      setErrors({});
      setSuccessMessage('');
      setHasNominee(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    setSuccessMessage('');
    setErrors({});
    setApiError('');
    
    if (!validateForm()) {
      setTimeout(() => {
        const scrolled = scrollToErrorField();
        if (!scrolled) {
          scrollToMessages();
        }
      }, 100);
      return;
    }

    setIsSubmitting(true);
    
    try {
      const dataToSend = { ...formData };
      
      if (dataToSend.nomination === 'N') {
        dataToSend.authentication = '';
        dataToSend.nominee1Name = '';
        dataToSend.nominee1Relationship = '';
        dataToSend.nominee1ApplicablePercentage = '0';
        dataToSend.nominee1MinorFlag = 'N';
        dataToSend.nominee1DOB = '';
        dataToSend.nominee1Guardian = '';
        dataToSend.nominee1GuardianPAN = '';
        dataToSend.nominee1IdentityType = '';
        dataToSend.nominee1IdentityNo = '';
        dataToSend.nominee1Email = '';
        dataToSend.nominee1Mobile = '';
        dataToSend.nominee1Address = '';
        dataToSend.nominee1City = '';
        dataToSend.nominee1Pincode = '';
        dataToSend.nominee1Country = '';
        dataToSend.nominee1AddressSameAsHolder = 'N';
        dataToSend.nominee2Name = '';
        dataToSend.nominee2Relationship = '';
        dataToSend.nominee2ApplicablePercentage = '0';
        dataToSend.nominee2MinorFlag = 'N';
        dataToSend.nominee2DOB = '';
        dataToSend.nominee2Guardian = '';
        dataToSend.nominee2GuardianPAN = '';
        dataToSend.nominee2IdentityType = '';
        dataToSend.nominee2IdentityNo = '';
        dataToSend.nominee2Email = '';
        dataToSend.nominee2Mobile = '';
        dataToSend.nominee2Address = '';
        dataToSend.nominee2City = '';
        dataToSend.nominee2Pincode = '';
        dataToSend.nominee2Country = '';
        dataToSend.nominee2AddressSameAsHolder = 'N';
        dataToSend.nominee3Name = '';
        dataToSend.nominee3Relationship = '';
        dataToSend.nominee3ApplicablePercentage = '0';
        dataToSend.nominee3MinorFlag = 'N';
        dataToSend.nominee3DOB = '';
        dataToSend.nominee3Guardian = '';
        dataToSend.nominee3GuardianPAN = '';
        dataToSend.nominee3IdentityType = '';
        dataToSend.nominee3IdentityNo = '';
        dataToSend.nominee3Email = '';
        dataToSend.nominee3Mobile = '';
        dataToSend.nominee3Address = '';
        dataToSend.nominee3City = '';
        dataToSend.nominee3Pincode = '';
        dataToSend.nominee3Country = '';
        dataToSend.nominee3AddressSameAsHolder = 'N';
        dataToSend.nomineeNamesRegistrationStatus = '';
        dataToSend.filler1 = '';
      }
      
      if (dataToSend.nominee1ApplicablePercentage === '') dataToSend.nominee1ApplicablePercentage = '0';
      if (dataToSend.nominee2ApplicablePercentage === '') dataToSend.nominee2ApplicablePercentage = '0';
      if (dataToSend.nominee3ApplicablePercentage === '') dataToSend.nominee3ApplicablePercentage = '0';
      
      if (dataToSend.nominee1GuardianPAN) dataToSend.nominee1GuardianPAN = dataToSend.nominee1GuardianPAN.toUpperCase();
      if (dataToSend.nominee2GuardianPAN) dataToSend.nominee2GuardianPAN = dataToSend.nominee2GuardianPAN.toUpperCase();
      if (dataToSend.nominee3GuardianPAN) dataToSend.nominee3GuardianPAN = dataToSend.nominee3GuardianPAN.toUpperCase();
      
      const response = await fetch(`${NOMINEE_DETAILS_API}?action=save&user_id=${userId}`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(dataToSend)
      });
      
      const responseText = await response.text();
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error(`Invalid JSON response: ${responseText}`);
      }
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}, message: ${data.message || 'Unknown error'}`);
      }
      
      if (data.success) {
        setSuccessMessage('Nominee details saved successfully!');
        setHasExistingData(true);
        setIsEditMode(false);
        
        if (successTimeoutRef.current) {
          clearTimeout(successTimeoutRef.current);
        }
        
        successTimeoutRef.current = setTimeout(() => {
          setSuccessMessage('');
        }, 5000);
        
        await loadNomineeDetails(userId, token);
        
        setTimeout(() => {
          scrollToMessages();
        }, 200);
        
      } else {
        throw new Error(data.message || 'Failed to save details');
      }
      
    } catch (error) {
      console.error('Error saving nominee details:', error);
      setErrors({ 
        submit: `Failed to save details: ${error.message}` 
      });
      setTimeout(() => {
        scrollToMessages();
      }, 100);
    } finally {
      setIsSubmitting(false);
    }
  };

  const maskValue = (val) => {
    if (!val) return '';
    const str = String(val).trim();
    if (str === '' || str === '0000-00-00' || str === '0000-00-00 00:00:00' || str === 'null' || str === 'undefined') {
      return '';
    }
    if (str.length <= 4) return str;
    return '*****' + str.slice(-4);
  };

  // Helper function to render form fields
  const renderInputField = (label, name, type = 'text', options = [], placeholder = '', required = false, customProps = {}) => {
    const isGlobalDisabled = isSubmitting || isLoading || (!isEditMode && hasExistingData);
    const finalDisabled = isGlobalDisabled || Boolean(customProps.disabled);
    const { disabled: _customDisabled, ...restCustomProps } = customProps;
    const value = formData[name] || '';
    
    // Check if this is an Identity Number field and Aadhar is selected
    const isAadharField = name.includes('IdentityNo') && isAadharType(formData[name.replace('IdentityNo', 'IdentityType')]);
    
    if (type === 'select') {
      let selectOptions = [...options];
      let selectValue = value;

      if (value) {
        const matchingOption = options.find(opt => 
          String(opt.value).toUpperCase() === String(value).toUpperCase() ||
          String(opt.label).toUpperCase() === String(value).toUpperCase()
        );

        if (matchingOption) {
          selectValue = matchingOption.value;
        } else {
          // If value is not present in options list, dynamically add it so it renders cleanly in read/view mode
          selectOptions.push({ value: value, label: value });
          selectValue = value;
        }
      }

      return (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {label} {required && <span className="text-red-500">*</span>}
          </label>
          <select
            name={name}
            value={selectValue}
            onChange={handleChange}
            {...restCustomProps}
            disabled={finalDisabled}
            className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors[name] ? 'border-red-300' : 'border-gray-300'} ${finalDisabled ? 'bg-gray-100 cursor-not-allowed pointer-events-none' : ''}`}
            required={required}
          >
            <option value="">Select {label}</option>
            {selectOptions.map(option => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {errors[name] && (
            <p className="mt-1 text-xs text-red-600">{errors[name]}</p>
          )}
        </div>
      );
    }
    
    const isSensitive = [
      'nominee1DOB', 'nominee2DOB', 'nominee3DOB', 'guardianDOB',
      'nominee1IdentityNo', 'nominee2IdentityNo', 'nominee3IdentityNo', 'guardianIdentityNo',
      'nominee1GuardianPAN', 'nominee2GuardianPAN', 'nominee3GuardianPAN', 'guardianPAN'
    ].includes(name) || 
    name.toLowerCase().includes('pan') || 
    name.toLowerCase().includes('identityno') || 
    name.toLowerCase().includes('dob') ||
    name.toLowerCase().includes('email') ||
    name.toLowerCase().includes('mobile') ||
    name.toLowerCase().includes('phone');

    const shouldMask = isSensitive && (finalDisabled || !visibleFields[name]);
    const displayValue = shouldMask && finalDisabled ? maskValue(value) : value;
    const displayType = shouldMask && finalDisabled ? 'text' : (shouldMask ? 'password' : type);

    return (
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          {label} {required && <span className="text-red-500">*</span>}
          {isAadharField && (
            <span className="text-blue-600 text-xs ml-2 font-normal">(Last 4 digits only)</span>
          )}
        </label>
        <div className="relative">
          <input
            type={displayType}
            name={name}
            value={displayValue}
            onChange={handleChange}
            placeholder={placeholder}
            {...restCustomProps}
            disabled={finalDisabled}
            className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors[name] ? 'border-red-300' : 'border-gray-300'} ${finalDisabled ? 'bg-gray-100 cursor-not-allowed pointer-events-none' : ''} ${isSensitive && !finalDisabled ? 'pr-10' : ''}`}
            required={required}
            maxLength={isAadharField ? "4" : undefined}
            inputMode={isAadharField ? "numeric" : undefined}
            pattern={isAadharField ? "\\d{4}" : undefined}
          />
          {isSensitive && !finalDisabled && (
            <button
              type="button"
              onClick={() => toggleFieldVisibility(name)}
              className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
              title={visibleFields[name] ? "Hide details" : "Show details"}
            >
              {visibleFields[name] ? (
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              ) : (
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 4.5c4.756 0 8.757 3.856 9.965 7.178.07.207.07.421 0 .644-1.351 3.222-5.351 7.178-9.965 7.178-4.756 0-8.757-3.856-9.965-7.178Z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
              )}
            </button>
          )}
        </div>
        {errors[name] && (
          <p className="mt-1 text-xs text-red-600">{errors[name]}</p>
        )}
      </div>
    );
  };

  const renderIdentityNumberField = (nomineeNumber, isMandatory) => {
    const prefix = `nominee${nomineeNumber}`;
    const identityType = formData[`${prefix}IdentityType`];
    const isAadharSelected = isAadharType(identityType);
    
    return renderInputField(
      'Identity Number', 
      `${prefix}IdentityNo`, 
      'text', 
      [], 
      isAadharSelected ? 'Enter last 4 digits of Aadhar' : 'Enter identity number', 
      hasNominee && isMandatory,
      {
        maxLength: isAadharSelected ? "4" : undefined,
        pattern: isAadharSelected ? "\\d{4}" : undefined,
        title: isAadharSelected ? "Please enter exactly 4 digits (last 4 digits of Aadhar)" : undefined
      }
    );
  };

  const hasNomineeData = (nomineeNumber) => {
    const prefix = `nominee${nomineeNumber}`;
    return formData[`${prefix}Name`] && formData[`${prefix}Name`].trim() !== '';
  };

  const renderNomineeSection = (nomineeNumber) => {
    const prefix = `nominee${nomineeNumber}`;
    const title = nomineeNumber === 1 ? `Nominee ${nomineeNumber} Details (Mandatory)` : `Nominee ${nomineeNumber} Details (Optional)`;
    const isMandatory = nomineeNumber === 1;
    
    if (!isEditMode && hasExistingData && nomineeNumber > 1) {
      if (!hasNomineeData(nomineeNumber)) {
        return null;
      }
    }
    
    return (
      <div className="bg-blue-50 p-6 rounded-lg border border-blue-200 mb-4">
        <h3 className="font-semibold text-gray-900 mb-4">{title}</h3>
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Nominee Name (as per PAN)', 
              `${prefix}Name`, 
              'text', 
              [], 
              'Enter nominee name', 
              hasNominee && isMandatory
            )}
            
            {renderInputField(
              'Relationship', 
              `${prefix}Relationship`, 
              'select', 
              dropdownData.relationshipOptions, 
              '', 
              hasNominee && isMandatory
            )}
            
            {renderInputField(
              'Applicable %', 
              `${prefix}ApplicablePercentage`, 
              'number', 
              [], 
              '0-100', 
              hasNominee && isMandatory,
              { min: "0", max: "100", step: "0.01" }
            )}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Is Nominee Minor?', 
              `${prefix}MinorFlag`, 
              'select', 
              dropdownData.yesNoOptions, 
              '', 
              hasNominee && isMandatory
            )}
            
            {formData[`${prefix}MinorFlag`] === 'Y' && (
              <>
                {renderInputField(
                  'Guardian Name (as per PAN)', 
                  `${prefix}Guardian`, 
                  'text', 
                  [], 
                  'Enter guardian name', 
                  hasNominee && isMandatory
                )}
                
                {renderInputField(
                  'Guardian PAN', 
                  `${prefix}GuardianPAN`, 
                  'text', 
                  [], 
                  'Enter guardian PAN', 
                  false
                )}
              </>
            )}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Nominee Date of Birth', 
              `${prefix}DOB`, 
              'date', 
              [], 
              '', 
              false
            )}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Identity Type', 
              `${prefix}IdentityType`, 
              'select', 
              dropdownData.identityTypeOptions, 
              '', 
              hasNominee && isMandatory
            )}
            
            {renderIdentityNumberField(nomineeNumber, isMandatory)}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Email', 
              `${prefix}Email`, 
              'email', 
              [], 
              'Enter email', 
              hasNominee && isMandatory
            )}
            
            {renderInputField(
              'Mobile', 
              `${prefix}Mobile`, 
              'tel', 
              [], 
              'Enter mobile number', 
              hasNominee && isMandatory,
              { pattern: "\\d{10}", maxLength: "10" }
            )}
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {renderInputField(
              'Address Same As First Holder?', 
              `${prefix}AddressSameAsHolder`, 
              'select', 
              dropdownData.yesNoOptions, 
              '', 
              hasNominee && isMandatory
            )}
          </div>
          
          {(() => {
            const isSameAsHolder = formData[`${prefix}AddressSameAsHolder`] === 'Y';
            return (
              <>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {renderInputField(
                    'Address', 
                    `${prefix}Address`, 
                    'text', 
                    [], 
                    'Enter address', 
                    hasNominee && isMandatory && !isSameAsHolder,
                    { disabled: isSameAsHolder }
                  )}
                  
                  {renderInputField(
                    'City', 
                    `${prefix}City`, 
                    'text', 
                    [], 
                    'Enter city', 
                    hasNominee && isMandatory && !isSameAsHolder,
                    { disabled: isSameAsHolder }
                  )}
                  
                  {renderInputField(
                    'Pincode', 
                    `${prefix}Pincode`, 
                    'text', 
                    [], 
                    'Enter 6-digit pincode', 
                    hasNominee && isMandatory && !isSameAsHolder,
                    { maxLength: "6", disabled: isSameAsHolder }
                  )}
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {renderInputField(
                    'Country', 
                    `${prefix}Country`, 
                    'select', 
                    dropdownData.countryOptions, 
                    '', 
                    hasNominee && isMandatory && !isSameAsHolder,
                    { disabled: isSameAsHolder }
                  )}
                </div>
              </>
            );
          })()}
        </div>
      </div>
    );
  };

  if (!isInitialized || isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading nominee details...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          <TypedText text="Nominee Details" className="text-gray-900" />
        </h1>
        <p className="text-gray-600">
          Add nominee information for your account
        </p>
      </div>

      <div 
        ref={messageContainerRef} 
        className="scroll-mt-20"
        id="message-container"
      >
        {apiError && (
          <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg mb-4">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.408 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <span className="text-yellow-800">{apiError}</span>
            </div>
          </div>
        )}

        {successMessage && (
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg mb-4 transition-all duration-300">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <span className="text-green-800 font-medium">{successMessage}</span>
            </div>
          </div>
        )}

        {errors.submit && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg mb-4">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-red-800 font-medium">{errors.submit}</span>
            </div>
          </div>
        )}

        {errors.totalPercentage && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg mb-4">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span className="text-red-800 font-medium">{errors.totalPercentage}</span>
            </div>
          </div>
        )}
      </div>

      <form onSubmit={handleSubmit} className="space-y-8">
        <div className="bg-gray-50 p-6 rounded-lg border border-gray-200">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            {renderInputField('Nomination', 'nomination', 'select', dropdownData.yesNoOptions, '', true)}
            
            {hasNominee && (
              renderInputField('Authentication', 'authentication', 'select', dropdownData.authenticationOptions, '', true)
            )}
          </div>

          {hasNominee && (
            <div>
              <div className="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p className="text-sm text-yellow-800">
                  <span className="font-semibold">Note:</span> First nominee is mandatory. Second and third nominees are optional. If adding additional nominees, ensure all fields are completed.
                  <br />
                  <span className="font-semibold text-yellow-800">Important:</span> For Aadhar, enter only the <span className="font-bold">last 4 digits</span> in the Identity Number field.
                </p>
              </div>
              {renderNomineeSection(1)}
              {renderNomineeSection(2)}
              {renderNomineeSection(3)}
            </div>
          )}
        </div>

        <div className="flex justify-between space-x-4">
          <div>
            <button
              type="button"
              onClick={() => router.push('/dashboard/personal-details')}
              disabled={isSubmitting || isLoading}
              className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                  ? 'bg-gray-400 text-white cursor-not-allowed'
                  : 'bg-gray-600 hover:bg-gray-700 text-white'
                }`}
            >
              Back to Personal Details
            </button>
          </div>
          
          <div className="flex space-x-4">
            {hasExistingData && !isEditMode ? (
              <button
                type="button"
                onClick={handleEdit}
                disabled={isSubmitting || isLoading}
                className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                    ? 'bg-blue-400 text-white cursor-not-allowed'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                  }`}
              >
                Edit Nominee Details
              </button>
            ) : (
              <>
                <button
                  type="button"
                  onClick={handleClear}
                  disabled={isSubmitting || isLoading}
                  className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                      ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      : 'bg-gray-200 hover:bg-gray-300 text-gray-800'
                    }`}
                >
                  Clear Form
                </button>
                
                {hasExistingData && (
                  <button
                    type="button"
                    onClick={handleCancel}
                    disabled={isSubmitting || isLoading}
                    className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                        ? 'bg-gray-400 text-white cursor-not-allowed'
                        : 'bg-gray-600 hover:bg-gray-700 text-white'
                      }`}
                  >
                    Cancel
                  </button>
                )}
                
                <button
                  type="submit"
                  disabled={isSubmitting || isLoading}
                  className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                      ? 'bg-blue-400 text-white cursor-not-allowed'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                    }`}
                >
                  {isSubmitting ? 'Saving...' : hasExistingData ? 'Update Nominee Details' : 'Save Nominee Details'}
                </button>
              </>
            )}
          </div>
        </div>
      </form>
    </div>
  );
}