// app/dashboard/personal-details/page.js
'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

export default function PersonalDetailsPage() {
  const router = useRouter();
  const [isInitialized, setIsInitialized] = useState(false);
  const [visibleFields, setVisibleFields] = useState({});
  const toggleFieldVisibility = (fieldName) => {
    setVisibleFields(prev => ({ ...prev, [fieldName]: !prev[fieldName] }));
  };
  const [formData, setFormData] = useState({
    // Basic Information
    primaryHolderName: '',
    taxStatus: '',
    gender: '',
    primaryHolderDOB: '',
    occupationCode: '',
    holdingNature: '',

    // Secondary Holder Information
    secondHolderName: '',
    secondHolderDOB: '',

    // Third Holder Information
    thirdHolderName: '',
    thirdHolderDOB: '',
    thirdHolderEmail: '',
    thirdHolderMobile: '',

    // Guardian Information
    guardianFirstName: '',
    guardianDOB: '',

    // PAN Information - Only for second and third holders
    secondHolderPAN: '',
    thirdHolderPAN: '',

    // Guardian PAN (if minor)
    guardianPAN: '',

    // Address Information (Single line address)
    address: '',
    pincode: '',
    city: '',
    state: '',
    country: 'INDIA',
    email: '',

    // Foreign Address (for NRI)
    foreignAddress: '',
    foreignAddressPincode: '',
    foreignAddressState: '',
    foreignAddressCountry: '',

    // Contact Information
    indianMobileNo: '',
    secondHolderEmail: '',
    secondHolderMobile: '',

    // Guardian Details
    guardianRelationship: '',
  });

  const [errors, setErrors] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isMinor, setIsMinor] = useState(false);
  const [isJointAccount, setIsJointAccount] = useState(false);
  const [isNRI, setIsNRI] = useState(false);

  // Add these state variables
  const [isEditMode, setIsEditMode] = useState(false);
  const [hasExistingData, setHasExistingData] = useState(false);
  const [userId, setUserId] = useState(null);
  const [token, setToken] = useState('');
  const [saveSuccess, setSaveSuccess] = useState(false); // Add success state

  // Add ref for message container
  const messageContainerRef = useRef(null);

  // State for primary holder PAN (from signup)
  const [primaryHolderPAN, setPrimaryHolderPAN] = useState('');

  // State for dropdown data
  const [dropdownData, setDropdownData] = useState({
    taxStatusOptions: [],
    occupationOptions: [],
    genderOptions: [],
    holdingNatureOptions: [],
    stateOptions: [],
    countryOptions: [],
    relationshipOptions: []
  });

  const [isLoading, setIsLoading] = useState(true);
  const [apiError, setApiError] = useState('');

  // API base URL from environment
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

  // Add after API_BASE_URL declaration
  const PERSONAL_DETAILS_API = `${API_BASE_URL}/personal_details_api.php`;

  // Helper to resolve string code/name/alias to matching dropdown option value
  const resolveOptionValue = (options, val) => {
    if (!val || !options || options.length === 0) return val || '';
    const trimmedVal = String(val).trim().toUpperCase();
    if (!trimmedVal) return '';

    // 1. Direct exact match by value
    const byValue = options.find(opt => opt.value && String(opt.value).trim().toUpperCase() === trimmedVal);
    if (byValue) return byValue.value;

    // 2. Exact match by label
    const byLabel = options.find(opt => opt.label && String(opt.label).trim().toUpperCase() === trimmedVal);
    if (byLabel) return byLabel.value;

    // 3. Special handling for Bihar state aliases (BH <-> BR <-> BIHAR)
    if (trimmedVal === 'BR' || trimmedVal === 'BH' || trimmedVal === 'BIHAR') {
      const biharOpt = options.find(opt => {
        const v = String(opt.value || '').trim().toUpperCase();
        const l = String(opt.label || '').trim().toUpperCase();
        return v === 'BH' || v === 'BR' || l === 'BIHAR';
      });
      if (biharOpt) return biharOpt.value;
    }

    // 4. Special handling for India country aliases (IN <-> IND <-> INDIA)
    if (trimmedVal === 'IN' || trimmedVal === 'IND' || trimmedVal === 'INDIA') {
      const indiaOpt = options.find(opt => {
        const v = String(opt.value || '').trim().toUpperCase();
        const l = String(opt.label || '').trim().toUpperCase();
        return v === 'IN' || v === 'IND' || l === 'INDIA';
      });
      if (indiaOpt) return indiaOpt.value;
    }

    return val;
  };

  // Improved scroll function with retry logic
  const scrollToMessages = () => {
    if (messageContainerRef.current) {
      // Immediate scroll
      messageContainerRef.current.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      // Try again after a short delay to ensure DOM is fully updated
      setTimeout(() => {
        if (messageContainerRef.current) {
          messageContainerRef.current.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      }, 100);

      // And again after 300ms for any async DOM updates
      setTimeout(() => {
        if (messageContainerRef.current) {
          messageContainerRef.current.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      }, 300);
    } else {
      // If ref is not available, try to find by data attribute
      const messageContainer = document.querySelector('[data-message-container="true"]');
      if (messageContainer) {
        messageContainer.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    }
  };

  // Add this useEffect to check authentication and load existing data
  useEffect(() => {
    const loadUserAndData = async () => {
      try {
        // console.log('Loading user data...');

        // Get token from localStorage
        const storedToken = localStorage.getItem('site_token');
        const storedUser = localStorage.getItem('site_user');

        // console.log('Stored token exists:', !!storedToken);
        // console.log('Stored user exists:', !!storedUser);

        if (!storedToken || !storedUser) {
          console.error('No authentication found');
          setIsInitialized(true);
          setIsEditMode(true); // Allow editing for new users
          return;
        }

        const user = JSON.parse(storedUser);
        // console.log('User ID:', user.id || user.user_id);

        // Get primary holder PAN from user data
        if (user.pan) {
          setPrimaryHolderPAN(user.pan);
        }

        setToken(storedToken);
        setUserId(user.id || user.user_id);

        // Check if user has existing data
        const hasData = await checkExistingPersonalDetails(user.id, storedToken);
        // console.log('Has existing data:', hasData);
        setHasExistingData(hasData);

        if (hasData) {
          // Load existing data
          await loadPersonalDetails(user.id, storedToken);
          setIsEditMode(false); // View mode for existing data
        } else {
          // No existing data, allow editing
          setIsEditMode(true);
        }

        setIsInitialized(true);
      } catch (error) {
        console.error('Error loading user data:', error);
        setIsEditMode(true);
        setIsInitialized(true);
      }
    };

    loadUserAndData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Function to check if user has existing personal details
  const checkExistingPersonalDetails = async (userId, token) => {
    try {
      // console.log('Checking existing token:', token);

      const response = await fetch(PERSONAL_DETAILS_API, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });

      // console.log('Check existing data response status:', response.status);

      if (!response.ok) {
        console.error('Check existing data failed:', response.status);
        return false;
      }

      const data = await response.json();
      // console.log('Check existing data response:', data);

      if (data.success && data.data) {
        //console.log('Existing data found:', data.data);
        return true;
      }

      return false;
    } catch (error) {
      console.error('Error checking existing data:', error);
      return false;
    }
  };

  // Function to load personal details from API - IMPROVED VERSION
  const loadPersonalDetails = async (userId, token) => {
    try {
      setIsLoading(true);
      setApiError('');

      // console.log('Loading personal details for user:', userId);

      const response = await fetch(PERSONAL_DETAILS_API, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        }
      });

      // console.log('Load data response status:', response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error('HTTP error:', errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      // console.log('Load data API response:', data);

      if (data.success && data.data) {
        // Map API response to formData structure
        const apiData = data.data;

        // Helper function to format date for input[type="date"]
        const formatDateForInput = (dateString) => {
          if (!dateString || dateString === '0000-00-00') return '';
          // Convert from MySQL format (YYYY-MM-DD) to input format (YYYY-MM-DD)
          if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
            return dateString;
          }
          // Try to parse other formats
          const date = new Date(dateString);
          if (!isNaN(date.getTime())) {
            return date.toISOString().split('T')[0];
          }
          return '';
        };

        // Create updated form data
        const updatedFormData = {
          // Basic Information
          primaryHolderName: apiData.PRIMARY_HOLDER_NAME || apiData.primaryHolderName || '',
          taxStatus: apiData.TAX_STATUS || apiData.taxStatus || '',
          gender: apiData.GENDER || apiData.gender || '',
          primaryHolderDOB: formatDateForInput(apiData.primaryHolderDOB || apiData.PRIMARY_HOLDER_DOB || ''),
          occupationCode: apiData.OCCUPATION_CODE || apiData.occupationCode || '',
          holdingNature: apiData.HOLDING_NATURE || apiData.holdingNature || '',

          // Secondary Holder Information
          secondHolderName: apiData.SECOND_HOLDER_NAME || apiData.secondHolderName || '',
          secondHolderDOB: formatDateForInput(apiData.secondHolderDOB || apiData.SECOND_HOLDER_DOB || ''),
          secondHolderEmail: apiData.SECOND_HOLDER_EMAIL || apiData.secondHolderEmail || '',
          secondHolderMobile: apiData.SECOND_HOLDER_MOBILE || apiData.secondHolderMobile || '',
          secondHolderPAN: apiData.SECOND_HOLDER_PAN || apiData.secondHolderPAN || '',

          // Third Holder Information
          thirdHolderName: apiData.THIRD_HOLDER_NAME || apiData.thirdHolderName || '',
          thirdHolderDOB: formatDateForInput(apiData.thirdHolderDOB || apiData.THIRD_HOLDER_DOB || ''),
          thirdHolderEmail: apiData.THIRD_HOLDER_EMAIL || apiData.thirdHolderEmail || '',
          thirdHolderMobile: apiData.THIRD_HOLDER_MOBILE || apiData.thirdHolderMobile || '',
          thirdHolderPAN: apiData.THIRD_HOLDER_PAN || apiData.thirdHolderPAN || '',

          // Guardian Information
          guardianFirstName: apiData.GUARDIAN_FIRST_NAME || apiData.guardianFirstName || '',
          guardianDOB: formatDateForInput(apiData.guardianDOB || apiData.GUARDIAN_DOB || ''),
          guardianRelationship: apiData.guardianRelationship || apiData.GUARDIAN_RELATIONSHIP || '',
          guardianPAN: apiData.GUARDIAN_PAN || apiData.guardianPAN || '',

          // Address Information
          address: apiData.address || apiData.ADDRESS_1 || '',
          pincode: apiData.PINCODE || apiData.pincode || '',
          city: apiData.CITY || apiData.city || '',
          state: apiData.STATE || apiData.state || '',
          country: apiData.COUNTRY || apiData.country || '',
          email: apiData.EMAIL || apiData.email || '',

          // Foreign Address
          foreignAddress: apiData.foreignAddress || apiData.FOREIGN_ADDRESS_1 || '',
          foreignAddressPincode: apiData.foreignAddressPincode || apiData.FOREIGN_ADDRESS_PINCODE || '',
          foreignAddressState: apiData.foreignAddressState || apiData.FOREIGN_ADDRESS_STATE || '',
          foreignAddressCountry: apiData.foreignAddressCountry || apiData.FOREIGN_ADDRESS_COUNTRY || '',

          // Contact Information
          indianMobileNo: apiData.INDIAN_MOBILE_NO || apiData.indianMobileNo || '',
        };

        // console.log('Setting form data:', updatedFormData);
        setFormData(updatedFormData);

        // Update conditional flags based on loaded data
        const taxStatus = apiData.TAX_STATUS || apiData.taxStatus || '';
        const holdingNature = apiData.HOLDING_NATURE || apiData.holdingNature || '';

        setIsMinor(taxStatus === '02' || taxStatus === '28');
        setIsJointAccount(['JO', 'AS'].includes(holdingNature));
        setIsNRI(['11', '21', '24', '26', '27', '28', '29', '61', '62', '70', '77', '78'].includes(taxStatus));
      } else {
        console.log('No data found or API returned failure');
      }
    } catch (error) {
      console.error('Error loading personal details:', error);
      setApiError(`Failed to load personal details: ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  // Fetch master data from API
  useEffect(() => {
    const fetchMasterData = async () => {
      try {
        setIsLoading(true);
        setApiError('');

        // console.log('Fetching master data from:', `${API_BASE_URL}/master_data.php`);

        // Fetch all master data
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

        // console.log('API Response for get_all_tables:', data);

        // Check if the API returned success
        if (data.success) {
          processMasterData(data.data || data);
        } else {
          throw new Error(data.message || 'Failed to fetch master data');
        }

      } catch (error) {
        console.error('Error fetching master data:', error);
        setApiError(`Failed to load dropdown data: ${error.message}. Dropdowns will be empty.`);

        // Set empty dropdown data
        setDropdownData({
          taxStatusOptions: [],
          occupationOptions: [],
          genderOptions: [],
          holdingNatureOptions: [],
          stateOptions: [],
          countryOptions: [],
          relationshipOptions: []
        });
      } finally {
        setIsLoading(false);
      }
    };

    // Function to process master data
    const processMasterData = (masterData) => {
      // Helper function to transform data
      const transformData = (dataArray, idKey = 'code', labelKey = 'description') => {
        if (!Array.isArray(dataArray)) return [];

        return dataArray.map(item => ({
          value: item[idKey] || item.id || item.value,
          label: item[labelKey] || item.name || item.label || item.value
        })).filter(item => item.value && item.label);
      };

      // Transform specific data structures
      const taxStatusOptions = transformData(masterData.tax_status || [], 'code', 'tax_status');
      const occupationOptions = transformData(masterData.occupation_code || [], 'code', 'details');
      const holdingNatureOptions = transformData(masterData.client_holding || [], 'code', 'details');
      const relationshipOptions = transformData(masterData.nominee_relation || [], 'code', 'value');

      // For country options, ensure unique keys by using code if available
      let countryOptions = [];
      if (masterData.country && Array.isArray(masterData.country)) {
        // Create a Set to track unique values
        const seen = new Set();
        countryOptions = masterData.country
          .map(item => {
            // Use code if available, otherwise use details
            const code = item.code || item.details || item.value;
            const label = item.details || item.label || item.value || '';

            return {
              value: code,
              label: label
            };
          })
          .filter(item => {
            // Filter out duplicates and empty items
            if (!item.value || !item.label) return false;
            if (seen.has(item.value)) {
              console.warn(`Duplicate country code found: ${item.value} - ${item.label}`);
              return false; // Skip duplicates
            }
            seen.add(item.value);
            return true;
          });
      }

      const stateOptions = transformData(masterData.state || [], 'code', 'details');

      // Static options (not from DB)
      const genderOptions = [
        { value: 'M', label: 'Male' },
        { value: 'F', label: 'Female' },
        { value: 'O', label: 'Other' }
      ];

      setDropdownData({
        taxStatusOptions,
        occupationOptions,
        holdingNatureOptions,
        relationshipOptions,
        countryOptions,
        stateOptions,
        genderOptions
      });

      // console.log('Processed dropdown data:', {
      //   taxStatusCount: taxStatusOptions.length,
      //   occupationCount: occupationOptions.length,
      //   holdingNatureCount: holdingNatureOptions.length,
      //   relationshipCount: relationshipOptions.length,
      //   countryCount: countryOptions.length,
      //   stateCount: stateOptions.length
      // });
    };

    fetchMasterData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Effect to auto-fill state and country based on pincode
  useEffect(() => {
    const fetchLocationByPincode = async () => {
      // Only fetch if we have a 6-digit pincode AND we're in edit mode
      if (formData.pincode && formData.pincode.length === 6 && isEditMode) {
        try {
          // console.log('Fetching pincode info for:', formData.pincode);
          const response = await fetch(
            `${API_BASE_URL}/master_data.php?action=pincode_info&pincode=${formData.pincode}`
          );

          if (response.ok) {
            const data = await response.json();
            console.log('Pincode API response:', data);

            if (data.success && data.data) {
              const location = data.data;

              // Extract raw values from API response
              let rawStateValue = location.state_code || location.state || location.STATE || '';
              const cityValue = location.city || location.CITY || '';
              const countryValue = location.country_code || location.country || location.COUNTRY || '';

              console.log('Raw state value from API:', rawStateValue);
              console.log('Available state options:', dropdownData.stateOptions);

              // Log all available state options for debugging
              if (dropdownData.stateOptions && dropdownData.stateOptions.length > 0) {
                console.log('State options details:');
                dropdownData.stateOptions.forEach(opt => {
                  console.log(`  Value: "${opt.value}", Label: "${opt.label}"`);
                });
              }

              let finalStateValue = resolveOptionValue(dropdownData.stateOptions, rawStateValue);
              let finalCountryValue = resolveOptionValue(dropdownData.countryOptions, countryValue);

              setFormData(prev => ({
                ...prev,
                city: cityValue || prev.city,
                state: finalStateValue || prev.state, // Only update if finalStateValue is not empty
                country: finalCountryValue || prev.country
              }));

              // Clear errors for these fields
              setErrors(prev => ({
                ...prev,
                city: '',
                state: finalStateValue ? '' : prev.state, // Only clear error if state was actually updated
                country: finalCountryValue ? '' : prev.country
              }));
            } else {
              console.warn('API returned success false or no data:', data);
            }
          } else {
            console.error('API returned non-ok status:', response.status);
            const errorText = await response.text();
            console.error('Error response:', errorText);
          }
        } catch (error) {
          console.error('Error fetching location by pincode:', error);
        }
      }
    };

    // Debounce the API call
    const timer = setTimeout(() => {
      fetchLocationByPincode();
    }, 500);

    return () => clearTimeout(timer);
  }, [formData.pincode, API_BASE_URL, isEditMode, dropdownData.stateOptions, dropdownData.countryOptions]);

  useEffect(() => {
    // Set conditional flags based on form data
    setIsMinor(formData.taxStatus === '02' || formData.taxStatus === '28');
    setIsJointAccount(['JO', 'AS'].includes(formData.holdingNature));
    setIsNRI(['11', '21', '24', '26', '27', '28', '29', '61', '62', '70', '77', '78'].includes(formData.taxStatus));
  }, [
    formData.taxStatus,
    formData.holdingNature
  ]);

  const handleChange = (e) => {
    const { name, value } = e.target;

    // console.log(`Field changed: ${name} = ${value}`);

    // If pincode is changed
    if (name === 'pincode') {
      setFormData(prev => ({
        ...prev,
        [name]: value,
        // Clear city, state, country if pincode length is not 6
        ...(value.length !== 6 ? {
          city: '',
          state: '',
          country: ''
        } : {})
      }));
    }
    // For all other fields
    else {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }));
    }

    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: ''
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    // console.log('Starting form validation');

    // Mandatory validations
    if (!formData.primaryHolderName.trim()) newErrors.primaryHolderName = 'Primary Holder Name is required';
    if (!formData.taxStatus) newErrors.taxStatus = 'Tax Status is required';
    if (!formData.primaryHolderDOB) newErrors.primaryHolderDOB = 'Date of Birth is required';
    if (!formData.occupationCode) newErrors.occupationCode = 'Occupation Code is required';
    if (!formData.holdingNature) newErrors.holdingNature = 'Holding Nature is required';

    // Conditional validations
    if (isMinor || formData.gender) {
      if (!formData.gender) newErrors.gender = 'Gender is required';
    }

    if (isJointAccount) {
      if (!formData.secondHolderName.trim()) newErrors.secondHolderName = 'Second Holder Name is required';
      if (!formData.secondHolderDOB) newErrors.secondHolderDOB = 'Second Holder DOB is required';
      if (!formData.secondHolderEmail.trim()) newErrors.secondHolderEmail = 'Second Holder Email is required';
      if (!formData.secondHolderMobile.trim()) newErrors.secondHolderMobile = 'Second Holder Mobile is required';

      // Validate second holder PAN
      if (formData.secondHolderName.trim()) {
        const panNumber = formData.secondHolderPAN || '';
        if (!panNumber.trim()) {
          newErrors.secondHolderPAN = 'Second Holder PAN is required';
        } else if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNumber.toUpperCase())) {
          newErrors.secondHolderPAN = 'Invalid PAN format (e.g., ABCDE1234F)';
        }
      }

      // Validate third holder if name is provided
      if (formData.thirdHolderName.trim() || formData.thirdHolderEmail.trim() || formData.thirdHolderMobile.trim()) {
        if (!formData.thirdHolderName.trim()) newErrors.thirdHolderName = 'Third Holder Name is required';
        if (!formData.thirdHolderDOB) newErrors.thirdHolderDOB = 'Third Holder DOB is required';
        if (!formData.thirdHolderEmail.trim()) newErrors.thirdHolderEmail = 'Third Holder Email is required';
        if (!formData.thirdHolderMobile.trim()) newErrors.thirdHolderMobile = 'Third Holder Mobile is required';

        // Validate third holder PAN
        if (formData.thirdHolderName.trim()) {
          const panNumber = formData.thirdHolderPAN || '';
          if (!panNumber.trim()) {
            newErrors.thirdHolderPAN = 'Third Holder PAN is required';
          } else if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNumber.toUpperCase())) {
            newErrors.thirdHolderPAN = 'Invalid PAN format (e.g., ABCDE1234F)';
          }
        }
      }
    }

    if (isMinor) {
      if (!formData.guardianFirstName.trim()) newErrors.guardianFirstName = 'Guardian First Name is required';
      if (!formData.guardianDOB) newErrors.guardianDOB = 'Guardian DOB is required';
      if (!formData.guardianRelationship) newErrors.guardianRelationship = 'Guardian Relationship is required';

      // Validate guardian PAN
      if (formData.guardianFirstName.trim()) {
        const panNumber = formData.guardianPAN || '';
        if (!panNumber.trim()) {
          newErrors.guardianPAN = 'Guardian PAN is required';
        } else if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNumber.toUpperCase())) {
          newErrors.guardianPAN = 'Invalid PAN format (e.g., ABCDE1234F)';
        }
      }
    }

    // Address validations (single line address)
    if (!formData.address.trim()) newErrors.address = 'Address is required';
    if (!formData.pincode) newErrors.pincode = 'Pincode is required';
    if (!formData.city.trim()) newErrors.city = 'City is required';
    if (!formData.state) newErrors.state = 'State is required';
    if (!formData.country) newErrors.country = 'Country is required';

    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Invalid email format';
    }

    if (isNRI) {
      if (!formData.foreignAddress.trim()) newErrors.foreignAddress = 'Foreign Address is required';
      if (!formData.foreignAddressCountry) newErrors.foreignAddressCountry = 'Foreign Country is required';
    }

    if (!formData.indianMobileNo.trim()) {
      newErrors.indianMobileNo = 'Indian Mobile Number is required';
    } else if (!/^\d{10}$/.test(formData.indianMobileNo)) {
      newErrors.indianMobileNo = 'Invalid mobile number (10 digits required)';
    }

    // console.log('Validation errors:', newErrors);
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleEdit = () => {
    // console.log('Edit button clicked, setting edit mode to true');
    setIsEditMode(true);
  };

  const handleCancel = () => {
    if (hasExistingData) {
      setIsEditMode(false);
      // Reload the original data
      if (userId && token) {
        loadPersonalDetails(userId, token);
      }
    }
  };

  const handleClear = () => {
    if (window.confirm('Are you sure you want to clear all form data? This will not delete saved data from the database.')) {
      // Reset form to empty state
      const emptyForm = {
        // Basic Information
        primaryHolderName: '',
        taxStatus: '',
        gender: '',
        primaryHolderDOB: '',
        occupationCode: '',
        holdingNature: '',

        // Secondary Holder Information
        secondHolderName: '',
        secondHolderDOB: '',

        // Third Holder Information
        thirdHolderName: '',
        thirdHolderDOB: '',
        thirdHolderEmail: '',
        thirdHolderMobile: '',

        // Guardian Information
        guardianFirstName: '',
        guardianDOB: '',

        // PAN Information
        secondHolderPAN: '',
        thirdHolderPAN: '',
        guardianPAN: '',

        // Address Information
        address: '',
        pincode: '',
        city: '',
        state: '',
        country: 'IN',
        email: '',

        // Foreign Address
        foreignAddress: '',
        foreignAddressPincode: '',
        foreignAddressState: '',
        foreignAddressCountry: '',

        // Contact Information
        indianMobileNo: '',
        secondHolderEmail: '',
        secondHolderMobile: '',

        // Guardian Details
        guardianRelationship: '',
      };

      setFormData(emptyForm);
      setErrors({});

      // Also clear from localStorage
      localStorage.removeItem('site_personal_details');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    // console.log('Form submission started');

    // Clear success message
    setSaveSuccess(false);

    // Validate form
    if (!validateForm()) {
      console.log('Form validation failed');
      // Scroll to error messages if validation fails
      setTimeout(() => {
        scrollToMessages();
      }, 100);
      return;
    }

    setIsSubmitting(true);
    setErrors({});

    try {
      // Create data object with ALL parameters that the backend SQL query expects
      const dataToSend = {
        // User ID
        userId: userId,

        // Basic Information
        primaryHolderName: formData.primaryHolderName.trim(),
        taxStatus: formData.taxStatus,
        gender: formData.gender,
        primaryHolderDOB: formData.primaryHolderDOB || null,
        occupationCode: formData.occupationCode,
        holdingNature: formData.holdingNature,

        // Secondary Holder Information
        secondHolderName: formData.secondHolderName.trim(),
        secondHolderDOB: formData.secondHolderDOB || null,
        secondHolderEmail: formData.secondHolderEmail.trim(),
        secondHolderMobile: formData.secondHolderMobile.trim(),
        secondHolderPAN: formData.secondHolderPAN ? formData.secondHolderPAN.toUpperCase() : '',

        // Third Holder Information
        thirdHolderName: formData.thirdHolderName.trim(),
        thirdHolderDOB: formData.thirdHolderDOB || null,
        thirdHolderEmail: formData.thirdHolderEmail.trim(),
        thirdHolderMobile: formData.thirdHolderMobile.trim(),
        thirdHolderPAN: formData.thirdHolderPAN ? formData.thirdHolderPAN.toUpperCase() : '',

        // Guardian Information
        guardianFirstName: formData.guardianFirstName.trim(),
        guardianDOB: formData.guardianDOB || null,
        guardianRelationship: formData.guardianRelationship,
        guardianPAN: formData.guardianPAN ? formData.guardianPAN.toUpperCase() : '',

        // Address Information
        address: formData.address.trim(),
        pincode: formData.pincode,
        city: formData.city.trim(),
        state: formData.state,
        country: formData.country,
        email: formData.email.trim(),

        // Foreign Address
        foreignAddress: formData.foreignAddress.trim(),
        foreignAddressPincode: formData.foreignAddressPincode,
        foreignAddressState: formData.foreignAddressState,
        foreignAddressCountry: formData.foreignAddressCountry,

        // Contact Information
        indianMobileNo: formData.indianMobileNo.trim(),
        secondHolderEmail: formData.secondHolderEmail.trim(),
        secondHolderMobile: formData.secondHolderMobile.trim(),

        // Primary holder PAN (from signup - not editable)
        primaryHolderPAN: primaryHolderPAN || '',
      };

      // console.log('Data to send (ALL parameters):', dataToSend);

      // Save to API
      const response = await fetch(PERSONAL_DETAILS_API, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(dataToSend)
      });

      const responseText = await response.text();
      // console.log('Raw response:', responseText);

      let data;
      try {
        data = JSON.parse(responseText);
        // console.log('Parsed response data:', data);
      } catch (parseError) {
        console.error('Failed to parse JSON response:', parseError);
        throw new Error(`Invalid JSON response: ${responseText}`);
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}, message: ${data.message || 'Unknown error'}`);
      }

      if (data.success) {
        setHasExistingData(true);
        setIsEditMode(false); // Switch to view mode after saving
        setSaveSuccess(true); // Set success message
        setApiError(''); // Clear any API errors

        // Scroll to success message
        setTimeout(() => {
          scrollToMessages();
        }, 100);

        // Auto-hide success message after 5 seconds
        setTimeout(() => {
          setSaveSuccess(false);
        }, 5000);

        // Also save to localStorage as backup
        localStorage.setItem('site_personal_details', JSON.stringify(formData));

        // Reload data to ensure UI is updated
        if (userId && token) {
          await loadPersonalDetails(userId, token);
          // Scroll again after data reload
          setTimeout(() => {
            scrollToMessages();
          }, 200);
        }
      } else {
        // Handle specific database errors
        if (data.message && data.message.includes('SQLSTATE[HY093]')) {
          throw new Error('Database error: Parameter mismatch. Please contact support.');
        }
        throw new Error(data.message || 'Failed to save details');
      }

    } catch (error) {
      console.error('Error saving personal details:', error);
      setErrors({
        submit: `Failed to save details: ${error.message}`
      });
      // Scroll to error message
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

  // Helper function to render form fields with proper edit/view mode handling
  const renderInputField = (label, name, type = 'text', options = [], placeholder = '', required = false) => {
    const isDisabled = isSubmitting || isLoading || (!isEditMode && hasExistingData);
    const value = formData[name] || '';

    if (type === 'select') {
      const resolvedValue = resolveOptionValue(options, value);
      return (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {label} {required && <span className="text-red-500">*</span>}
          </label>
          <select
            name={name}
            value={resolvedValue}
            onChange={handleChange}
            className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors[name] ? 'border-red-300' : 'border-gray-300'} ${isDisabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
            required={required}
            disabled={isDisabled}
          >
            <option value="">Select {label}</option>
            {options.map(option => (
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
      'primaryHolderDOB',
      'secondHolderDOB',
      'thirdHolderDOB',
      'guardianDOB',
      'secondHolderPAN',
      'thirdHolderPAN',
      'guardianPAN'
    ].includes(name) ||
      name.toLowerCase().includes('pan') ||
      name.toLowerCase().includes('dob') ||
      name.toLowerCase().includes('email') ||
      name.toLowerCase().includes('mobile') ||
      name.toLowerCase().includes('phone');

    const shouldMask = isSensitive && (isDisabled || !visibleFields[name]);
    const displayValue = shouldMask && isDisabled ? maskValue(value) : value;
    const displayType = shouldMask && isDisabled ? 'text' : (shouldMask ? 'password' : type);

    return (
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          {label} {required && <span className="text-red-500">*</span>}
        </label>
        <div className="relative">
          <input
            type={displayType}
            name={name}
            value={displayValue}
            onChange={handleChange}
            placeholder={placeholder}
            className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors[name] ? 'border-red-300' : 'border-gray-300'} ${isDisabled ? 'bg-gray-100 cursor-not-allowed' : ''} ${isSensitive && !isDisabled ? 'pr-10' : ''}`}
            required={required}
            disabled={isDisabled}
          />
          {isSensitive && !isDisabled && (
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

  // Function to navigate to nominee details page
  const handleNavigateToNominee = () => {
    router.push('/dashboard/nominee');
  };

  if (!isInitialized || isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading form data...</p>
        </div>
      </div>
    );
  }

  const isDisabled = isSubmitting || isLoading || (!isEditMode && hasExistingData);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          <TypedText text="Personal Details" className="text-gray-900" />
        </h1>
        <p className="text-gray-600">
          Complete your personal information for account verification
        </p>
      </div>

      {/* Message Container - Added ref and data attribute for scrolling */}
      <div
        ref={messageContainerRef}
        data-message-container="true"
        className="scroll-mt-20"
      >
        {/* Success Message */}
        {saveSuccess && (
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg mb-4 transition-all duration-300">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <span className="text-green-800 font-medium">
                Personal details saved successfully!
              </span>
            </div>
          </div>
        )}

        {/* API Error Message */}
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

        {/* Error Message */}
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
      </div>

      <form onSubmit={handleSubmit} className="space-y-8">
        {/* Section 1: Basic Information */}
        <div className="bg-gray-50 p-6 rounded-lg border border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Primary Holder Name (as per PAN) <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="primaryHolderName"
                value={formData.primaryHolderName || ''}
                onChange={handleChange}
                placeholder="Enter full name as per PAN"
                className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.primaryHolderName ? 'border-red-300' : 'border-gray-300'} ${(!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                required={true}
                disabled={isSubmitting || isLoading || (!isEditMode && hasExistingData)}
              />
              {errors.primaryHolderName && (
                <p className="mt-1 text-xs text-red-600">{errors.primaryHolderName}</p>
              )}
              {primaryHolderPAN && (
                <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md">
                  <p className="text-sm text-blue-800">
                    <span className="font-medium">Primary Holder PAN:</span>{' '}
                    {maskValue(primaryHolderPAN)}
                  </p>
                </div>
              )}
            </div>
            {renderInputField('Tax Status', 'taxStatus', 'select', dropdownData.taxStatusOptions, '', true)}
            {renderInputField('Gender', 'gender', 'select', dropdownData.genderOptions, '', isMinor)}
            {renderInputField('Date of Birth/Incorporation', 'primaryHolderDOB', 'date', [], 'DD/MM/YYYY', true)}
            {renderInputField('Occupation Code', 'occupationCode', 'select', dropdownData.occupationOptions, '', true)}
            {renderInputField('Holding Nature', 'holdingNature', 'select', dropdownData.holdingNatureOptions, '', true)}
          </div>
        </div>

        {/* Section 2: Secondary Holder Information (Conditional) */}
        {isJointAccount && (
          <div className="bg-blue-50 p-6 rounded-lg border border-blue-200">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Joint Holder Information</h2>
            <div className="space-y-6">
              {/* Second Holder */}
              <div>
                <h3 className="font-medium text-gray-900 mb-3">Second Holder Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {renderInputField('Second Holder Name (as per PAN)', 'secondHolderName', 'text', [], 'Enter full name as per PAN', true)}
                  {renderInputField('Second Holder DOB', 'secondHolderDOB', 'date', [], 'DD/MM/YYYY', true)}
                  {renderInputField('Second Holder Email', 'secondHolderEmail', 'email', [], 'Enter email', true)}
                  {renderInputField('Second Holder Mobile', 'secondHolderMobile', 'tel', [], 'Enter mobile number', true)}
                  {renderInputField('Second Holder PAN', 'secondHolderPAN', 'text', [], 'Enter PAN number (e.g., ABCDE1234F)', true)}
                </div>
              </div>

              {/* Third Holder (Optional) */}
              <div>
                <h3 className="font-medium text-gray-900 mb-3">Third Holder Details (Optional)</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {renderInputField('Third Holder Name (as per PAN)', 'thirdHolderName', 'text', [], 'Enter full name as per PAN', false)}
                  {renderInputField('Third Holder DOB', 'thirdHolderDOB', 'date', [], 'DD/MM/YYYY', false)}
                  {renderInputField('Third Holder Email', 'thirdHolderEmail', 'email', [], 'Enter email', false)}
                  {renderInputField('Third Holder Mobile', 'thirdHolderMobile', 'tel', [], 'Enter mobile number', false)}
                  {renderInputField('Third Holder PAN', 'thirdHolderPAN', 'text', [], 'Enter PAN number (e.g., ABCDE1234F)', false)}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Section 3: Guardian Information (Conditional) */}
        {isMinor && (
          <div className="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Guardian Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {renderInputField('Guardian Relationship', 'guardianRelationship', 'select', dropdownData.relationshipOptions, '', true)}
              {renderInputField('Guardian First Name (as per PAN)', 'guardianFirstName', 'text', [], 'Enter guardian name as per PAN', true)}
              {renderInputField('Guardian DOB', 'guardianDOB', 'date', [], 'DD/MM/YYYY', true)}
              {renderInputField('Guardian PAN', 'guardianPAN', 'text', [], 'Enter PAN number (e.g., ABCDE1234F)', true)}
            </div>
          </div>
        )}

        {/* Section 4: Address Information */}
        <div className="bg-gray-50 p-6 rounded-lg border border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Address Information</h2>
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Pincode <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  name="pincode"
                  value={formData.pincode || ''}
                  onChange={handleChange}
                  placeholder="Enter 6-digit pincode"
                  maxLength="6"
                  className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.pincode ? 'border-red-300' : 'border-gray-300'} ${(!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                  required={true}
                  disabled={isSubmitting || isLoading || (!isEditMode && hasExistingData)}
                />
                {errors.pincode && (
                  <p className="mt-1 text-xs text-red-600">{errors.pincode}</p>
                )}

              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Address <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="address"
                value={formData.address || ''}
                onChange={handleChange}
                placeholder="Enter your complete address"
                className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.address ? 'border-red-300' : 'border-gray-300'} ${(!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                required={true}
                disabled={isSubmitting || isLoading || (!isEditMode && hasExistingData)}
              />
              {errors.address && (
                <p className="mt-1 text-xs text-red-600">{errors.address}</p>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  City <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="city"
                  value={formData.city || ''}
                  onChange={handleChange}
                  placeholder="Enter city"
                  className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.city ? 'border-red-300' : 'border-gray-300'} ${(!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                  required={true}
                  disabled={isSubmitting || isLoading || (!isEditMode && hasExistingData)}
                />
                {errors.city && (
                  <p className="mt-1 text-xs text-red-600">{errors.city}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  State <span className="text-red-500">*</span>
                </label>
                <select
                  name="state"
                  value={resolveOptionValue(dropdownData.stateOptions, formData.state)}
                  onChange={handleChange}
                  className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.state ? 'border-red-300' : 'border-gray-300'} ${isNRI || (!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                  required={!isNRI}
                  disabled={isSubmitting || isLoading || isNRI || (!isEditMode && hasExistingData)}
                >
                  <option value="">Select State</option>
                  {dropdownData.stateOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {errors.state && (
                  <p className="mt-1 text-xs text-red-600">{errors.state}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Country <span className="text-red-500">*</span>
                </label>
                <select
                  name="country"
                  value={resolveOptionValue(dropdownData.countryOptions, formData.country)}
                  onChange={handleChange}
                  className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.country ? 'border-red-300' : 'border-gray-300'} ${isNRI || (!isEditMode && hasExistingData) ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                  required={!isNRI}
                  disabled={isSubmitting || isLoading || isNRI || (!isEditMode && hasExistingData)}
                >
                  <option value="">Select Country</option>
                  {dropdownData.countryOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {errors.country && (
                  <p className="mt-1 text-xs text-red-600">{errors.country}</p>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type={isDisabled ? 'text' : (visibleFields['email'] ? 'email' : 'password')}
                    name="email"
                    value={isDisabled ? maskValue(formData.email) : (formData.email || '')}
                    onChange={handleChange}
                    placeholder="Enter email"
                    className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.email ? 'border-red-300' : 'border-gray-300'} ${isDisabled ? 'bg-gray-100 cursor-not-allowed' : ''} ${!isDisabled ? 'pr-10' : ''}`}
                    required={true}
                    disabled={isDisabled}
                  />
                  {!isDisabled && (
                    <button
                      type="button"
                      onClick={() => toggleFieldVisibility('email')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                      title={visibleFields['email'] ? "Hide Email" : "Show Email"}
                    >
                      {visibleFields['email'] ? (
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
                {errors.email && (
                  <p className="mt-1 text-xs text-red-600">{errors.email}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Indian Mobile No. <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type={isDisabled ? 'text' : (visibleFields['indianMobileNo'] ? 'tel' : 'password')}
                    name="indianMobileNo"
                    value={isDisabled ? maskValue(formData.indianMobileNo) : (formData.indianMobileNo || '')}
                    onChange={handleChange}
                    placeholder="10-digit mobile number"
                    className={`w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${errors.indianMobileNo ? 'border-red-300' : 'border-gray-300'} ${isDisabled ? 'bg-gray-100 cursor-not-allowed' : ''} ${!isDisabled ? 'pr-10' : ''}`}
                    required={true}
                    disabled={isDisabled}
                  />
                  {!isDisabled && (
                    <button
                      type="button"
                      onClick={() => toggleFieldVisibility('indianMobileNo')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                      title={visibleFields['indianMobileNo'] ? "Hide Mobile" : "Show Mobile"}
                    >
                      {visibleFields['indianMobileNo'] ? (
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
                {errors.indianMobileNo && (
                  <p className="mt-1 text-xs text-red-600">{errors.indianMobileNo}</p>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Section 5: Foreign Address (Conditional) */}
        {isNRI && (
          <div className="bg-purple-50 p-6 rounded-lg border border-purple-200">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Foreign Address Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {renderInputField('Foreign Address', 'foreignAddress', 'text', [], 'Enter foreign address', true)}
              {renderInputField('Foreign Pincode', 'foreignAddressPincode', 'text', [], 'Enter pincode', true)}
              {renderInputField('Foreign State', 'foreignAddressState', 'text', [], 'Enter state', true)}
              {renderInputField('Foreign Country', 'foreignAddressCountry', 'select', dropdownData.countryOptions, '', true)}
            </div>
          </div>
        )}

        {/* Update the form submission section */}
        <div className="flex justify-between space-x-4">
          <div>
            <button
              type="button"
              onClick={handleNavigateToNominee}
              disabled={isSubmitting || isLoading}
              className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                ? 'bg-green-400 text-white cursor-not-allowed'
                : 'bg-green-600 hover:bg-green-700 text-white'
                }`}
            >
              Go to Nominee Details
            </button>
          </div>

          <div className="flex space-x-4">
            {/* Show different buttons based on state */}
            {hasExistingData && !isEditMode ? (
              // View mode with existing data - show Edit button only
              <button
                type="button"
                onClick={handleEdit}
                disabled={isSubmitting || isLoading}
                className={`px-6 py-3 rounded-lg font-medium transition-colors ${isSubmitting || isLoading
                  ? 'bg-blue-400 text-white cursor-not-allowed'
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
                  }`}
              >
                Edit Personal Details
              </button>
            ) : (
              // Edit mode - show Save and Cancel buttons
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
                  {isSubmitting ? 'Saving...' : hasExistingData ? 'Update Personal Details' : 'Save Personal Details'}
                </button>
              </>
            )}
          </div>
        </div>
      </form>

      {/* Information Section - Updated with red asterisk */}
      <div className="mt-8 p-6 bg-gray-50 rounded-lg border border-gray-200">
        <h3 className="font-bold text-gray-900 mb-3">Important Information</h3>
        <ul className="space-y-2 text-sm text-gray-600">
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Fields marked with <span className="text-red-500 font-bold">*</span> are mandatory</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Conditional mandatory fields become required based on your selections</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Please ensure all name fields are entered exactly as per PAN card</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Primary holder PAN is taken from your signup details and cannot be changed here</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>If nominee is minor, guardian details are mandatory</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Enter 6-digit pincode to automatically fill city, state, and country</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Click &quot;Go to Nominee Details&quot; to add nominee information</span>
          </li>
        </ul>
      </div>
    </div>
  );
}