// app/dashboard/fatca/page.js
'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import TypedText from '@/components/TypedText';

export default function FATCAPage() {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [visibleFields, setVisibleFields] = useState({});
  const toggleFieldVisibility = (fieldName) => {
    setVisibleFields(prev => ({ ...prev, [fieldName]: !prev[fieldName] }));
  };
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState(null);
  const [isEditMode, setIsEditMode] = useState(false);
  const [hasData, setHasData] = useState(false);
  const [isIndividual, setIsIndividual] = useState(true);
  const [isHUF, setIsHUF] = useState(false);
  const [isEntity, setIsEntity] = useState(false);

  // Refs for scrolling
  const headerRef = useRef(null);

  // Form state matching your table structure
  const [formData, setFormData] = useState({
    // Primary Details
    pan_rp: '',
    // pekrn: '', // REMOVED - No longer needed
    inv_name: '',
    dob: '',
    fr_name: '',
    sp_name: '',
    tax_status_us: '',
    data_src: 'P', // Changed default to 'P' for Physical Request
    addr_type: '',
    po_bir_inc: '',
    co_bir_inc: 'IN',

    // TIN Details (1-4)
    tax_res1: '',
    tpin1: '',
    id1_type: '',
    tax_res2: '',
    tpin2: '',
    id2_type: '',
    tax_res3: '',
    tpin3: '',
    id3_type: '',
    tax_res4: '',
    tpin4: '',
    id4_type: '',

    // Additional Details
    srce_wealt: '',
    corp_servs: '',
    inc_slab: '',
    net_worth: '',
    nw_date: '',
    pep_flag: '',
    occ_code: '',
    occ_type: '',
    exemp_code: '',
    ffi_drnfe: '',
    giin_no: '',
    spr_entity: '',
    giin_na: '',
    giin_exemc: '',
    nffe_catg: '',
    act_nfe_sc: '',
    nature_bus: '',
    rel_listed: '',
    exch_name: 'O',

    // UBO Details (for single UBO in main table)
    ubo_appl: 'N',
    ubo_count: '0',
    ubo_name: '',
    ubo_pan: '',
    ubo_nation: '',
    ubo_add1: '',
    ubo_add2: '',
    ubo_add3: '',
    ubo_city: '',
    ubo_pin: '',
    ubo_state: '',
    ubo_cntry: '',
    ubo_add_type: '',
    ubo_ctr: '',
    ubo_tin: '',
    ubo_id_ty: '',
    ubo_cob: '',
    ubo_dob: '',
    ubo_gender: '',
    ubo_fr_nam: '',
    ubo_occ: '',
    ubo_occ_ty: '',
    ubo_tel: '',
    ubo_mobile: '',
    ubo_code: '',
    ubo_hol_pc: '',
    ubo_exch: '',
    ubo_isin: '',
    ubo_rel_li: '',
    ubo_categ: '',
    ubo_pep_fl: '',
    ubo_email: '',
    ubo_smo_de: '',

    // Other Flags
    sdf_flag: 'Y',
    ubo_df: 'N',
    aadhaar_rp: '',
    new_cha: 'N',
    log_name: '',
    filler1: '',
    filler2: '',

    // NPO Details
    npo_form: 'N',
    npo_dcl: '',
    npo_rgno: '',
  });

  // Master data
  const [masterData, setMasterData] = useState({
    // Dropdown options
    taxStatusOptions: [],
    addressTypeOptions: [],
    countryOptions: [],
    idTypeOptions: [],
    wealthSourceOptions: [],
    corporateServicesOptions: [],
    incomeSlabOptions: [],
    pepFlagOptions: [],
    occupationCodeOptions: [],
    occupationTypeOptions: [],
    exemptionCodeOptions: [],
    ffiOptions: [],
    giinNAOptions: [],
    giinExemptionOptions: [],
    nfeCategoryOptions: [],
    nfeSubCategoryOptions: [],
    uboCodeOptions: [],
    stateOptions: [],
    genderOptions: [
      { value: 'M', label: 'Male' },
      { value: 'F', label: 'Female' },
      { value: 'O', label: 'Other' }
    ],
    dataSourceOptions: [
      { value: 'E', label: 'Electronically' },
      { value: 'P', label: 'Physical Request' }
    ],
    selfDeclarationFlagOptions: [
      { value: 'Y', label: 'Yes' },
      { value: 'N', label: 'No' }
    ],
    uboDFOptions: [
      { value: 'Y', label: 'Yes' },
      { value: 'N', label: 'No' }
    ],
    npoFormOptions: [
      { value: 'Y', label: 'Yes' },
      { value: 'N', label: 'No' }
    ],
    npoDCLOptions: [
      { value: 'Y', label: 'Yes' },
      { value: 'N', label: 'No' }
    ],
    exchangeOptions: [
      { value: 'B', label: 'BSE' },
      { value: 'N', label: 'NSE' },
      { value: 'O', label: 'Others' }
    ],
    uboCategoryOptions: [
      { value: 'UBO', label: 'Ultimate Beneficial Owner' },
      { value: 'SMO', label: 'Senior Managing Official' }
    ],
  });

  // Store occupation mapping for auto-setting occ_type
  const [occupationMapping, setOccupationMapping] = useState({});

  // API base URL
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

  // Check if user is logged in and load data
  useEffect(() => {
    const loadData = async () => {
      const storedUser = localStorage.getItem('site_user');
      const authStatus = localStorage.getItem('site_auth');

      if (!storedUser || authStatus !== 'true') {
        router.push('/login');
        return;
      }

      try {
        const userData = JSON.parse(storedUser);
        //console.log('User data from localStorage:', userData);
        setUser(userData);

        // Load master data and FATCA profile in parallel
        await Promise.all([
          loadMasterData(),
          loadFatcaProfile(userData.id)
        ]);

      } catch (error) {
        router.push('/login');
      } finally {
        setIsLoading(false);
      }
    };

    loadData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [router]);

  // Function to scroll to top
  const scrollToTop = () => {
    if (headerRef.current) {
      headerRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  // Load master data from API
  const loadMasterData = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/master_data.php?action=get_fatca_tables`);
      const responseData = await response.json();

      if (responseData.success) {
        // Create occupation mapping object
        const occupationMap = {};
        if (responseData.data.fatca_occupation) {
          responseData.data.fatca_occupation.forEach(item => {
            occupationMap[item.code] = item.type || '';
          });
        }
        setOccupationMapping(occupationMap);

        setMasterData(prev => ({
          ...prev,
          taxStatusOptions: responseData.data.fatca_tax_status?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          addressTypeOptions: responseData.data.fatca_add_type?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          countryOptions: responseData.data.country?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          idTypeOptions: responseData.data.fatca_identity_doc_type?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          wealthSourceOptions: responseData.data.fatca_wealth_sc?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          corporateServicesOptions: responseData.data.fatca_corp_serv_sec?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          incomeSlabOptions: responseData.data.fatca_income_range?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          pepFlagOptions: [
            { value: 'Y', label: 'Yes - Politically Exposed Person' },
            { value: 'N', label: 'No' },
            { value: 'R', label: 'Relative of PEP' }
          ],

          occupationCodeOptions: responseData.data.fatca_occupation?.map(item => ({
            value: item.code,
            label: item.details,
            type: item.type || ''
          })) || [],

          occupationTypeOptions: [...new Set(
            responseData.data.fatca_occupation?.map(item => item.type).filter(Boolean)
          )].map(type => ({
            value: type,
            label: type
          })) || [],

          exemptionCodeOptions: responseData.data.fatca_exempt_code?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          ffiOptions: [
            { value: 'FFI', label: 'FFI' },
            { value: 'DRNFE', label: 'DRNFE' },
            { value: 'NA', label: 'NA' }
          ],

          giinNAOptions: [
            { value: 'AF', label: 'Applied for' },
            { value: 'NR', label: 'Not required to apply for' },
            { value: 'NO', label: 'Not obtained - Non-participating FI' }
          ],

          giinExemptionOptions: responseData.data.fatca_giin_exempt?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          nfeCategoryOptions: responseData.data.fatca_act_nfe_sub_cat?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          nfeSubCategoryOptions: responseData.data.fatca_act_nfe_sub_cat?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          uboCodeOptions: responseData.data.fatca_ubo_code?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],

          stateOptions: responseData.data.state?.map(item => ({
            value: item.code,
            label: item.details
          })) || [],
        }));
      }
    } catch (error) {
    }
  };

  // Load FATCA profile from API
  const loadFatcaProfile = async (userId) => {
    try {
      const token = localStorage.getItem('site_token');
      const storedUser = localStorage.getItem('site_user');

      if (!storedUser) {
        console.error('No user data in localStorage');
        router.push('/login');
        return;
      }

      const userData = JSON.parse(storedUser);
      //console.log('User data from localStorage:', userData);

      // Step 1: Try to load user details from database API
      let userPan = userData.pan || '';
      let userName = userData.name || '';
      let userDob = userData.dob || '';

      try {
        const userDetailsResponse = await fetch(`${API_BASE_URL}/user_details_api.php?action=get_user_details`, {
          method: "GET",
          headers: {
            "Authorization": `Bearer ${token}`,
            "Content-Type": 'application/json',
          }
        });

        const responseText = await userDetailsResponse.text();
        //console.log('Raw user details response:', responseText);

        try {
          const userDetails = JSON.parse(responseText);
          //console.log('User details API response:', userDetails);

          if (userDetails.success === true && userDetails.data) {
            userPan = userDetails.data.PRIMARY_HOLDER_PAN || userPan;
            userName = userDetails.data.PRIMARY_HOLDER_NAME || userName;
            userDob = userDetails.data.PRIMARY_HOLDER_DOB || userDob;
          } else {
            console.warn('User details API returned unsuccessful:', userDetails.message);
          }
        } catch (parseError) {
          console.error('Failed to parse user details response as JSON:', parseError, 'Raw response:', responseText);
        }
      } catch (error) {
        console.error('Error fetching user details:', error);
      }

      //console.log('Final user data to use:', { userPan, userName, userDob });

      // Step 2: Load FATCA profile
      const response = await fetch(`${API_BASE_URL}/fatca_details_api.php?action=get_fatca_profile`, {
        method: "GET",
        headers: {
          "Authorization": `Bearer ${token}`,
          "Content-Type": "application/json"
        }
      });

      const data = await response.json();

      if (data.success && data.data) {
        // Create a complete merged object with ALL fields
        const fatcaData = data.data;

        // Ensure read-only/auto-filled fields are preserved from database
        const mergedData = {
          // Start with current form data
          ...formData,
          // Override with API data
          ...fatcaData,
          // Force set read-only fields from user profile
          pan_rp: userPan,
          inv_name: userName,
          dob: userDob ? formatDateForInput(userDob) : '',
          data_src: 'P', // Always 'P' for Physical request
          exch_name: 'O', // Always 'O' for Others
          // Auto-set log_name from email
          log_name: userData.email || '',

          // Format dates properly
          nw_date: fatcaData.nw_date ? formatDateForInput(fatcaData.nw_date) : '',
          ubo_dob: fatcaData.ubo_dob ? formatDateForInput(fatcaData.ubo_dob) : '',

          // Ensure occupation type is set properly (mapping single-letter DB codes to full labels if needed)
          occ_type: (fatcaData.occ_type === 'S' ? 'Service' : (fatcaData.occ_type === 'B' ? 'Business' : (fatcaData.occ_type === 'O' ? 'Others' : fatcaData.occ_type))) || (fatcaData.occ_code ? occupationMapping[fatcaData.occ_code] : ''),
          ubo_occ_ty: (fatcaData.ubo_occ_ty === 'S' ? 'Service' : (fatcaData.ubo_occ_ty === 'B' ? 'Business' : (fatcaData.ubo_occ_ty === 'O' ? 'Others' : fatcaData.ubo_occ_ty))) || (fatcaData.ubo_occ ? occupationMapping[fatcaData.ubo_occ] : '')
        };

        // console.log('Merged form data with protected fields:', {
        //   pan_rp: mergedData.pan_rp,
        //   inv_name: mergedData.inv_name,
        //   dob: mergedData.dob,
        //   data_src: mergedData.data_src,
        //   exch_name: mergedData.exch_name,
        //   occ_type: mergedData.occ_type,
        //   occ_code: mergedData.occ_code
        // });

        setFormData(mergedData);
        setHasData(true);
        setIsEditMode(false);

        // Check user type - preserve ubo_appl from database for existing data
        const taxStatus = mergedData.tax_status_us || '';
        checkUserType(taxStatus, true, mergedData.ubo_appl);
      } else {
        // No FATCA data exists
        setIsEditMode(true);
        setHasData(false);

        // Set up initial form with read-only fields
        setFormData(prev => ({
          ...prev,
          // Auto-filled fields (read-only)
          pan_rp: userPan,
          inv_name: userName,
          dob: userDob ? formatDateForInput(userDob) : '',
          data_src: 'P', // Default to 'P' for Physical request
          exch_name: 'O',
          log_name: userData.email || '',

          // Default values
          co_bir_inc: 'IN', // Default Country of Birth/Incorporation to India
          ubo_appl: isIndividual ? 'N' : 'Y',
          ubo_df: isIndividual ? 'N' : 'Y',
          sdf_flag: 'Y',
          npo_form: 'N',
          new_cha: 'N',
          ubo_count: '0'
        }));
      }
    } catch (error) {
      console.error('Error loading FATCA profile:', error);
      setIsEditMode(true);

      // Don't reset form if there's an error - keep existing data
      const storedUser = localStorage.getItem('site_user');
      if (storedUser) {
        const userData = JSON.parse(storedUser);
        setFormData(prev => ({
          ...prev,
          // Set read-only fields
          pan_rp: userData.pan || '',
          inv_name: userData.name || '',
          dob: userData.dob ? formatDateForInput(userData.dob) : '',
          data_src: 'P', // Default to 'P' for Physical request
          exch_name: 'O',
          co_bir_inc: 'IN',
          log_name: userData.email || ''
        }));
      }
    }
  };

  // Check user type based on tax status
  const checkUserType = (taxStatus, preserveUboAppl = false, currentUboAppl = null) => {
    const individualTaxStatuses = ['1', '01']; // 01 = Individual
    const hufStatuses = ['2', '02'];           // 02 = HUF

    // Individual (01)
    if (individualTaxStatuses.includes(taxStatus)) {
      setIsIndividual(true);
      setIsHUF(false);
      setIsEntity(false);

      setFormData(prev => ({
        ...prev, // Keep existing data
        ubo_appl: preserveUboAppl ? currentUboAppl : 'N',
        ubo_df: preserveUboAppl ? prev.ubo_df : 'N',
        sdf_flag: preserveUboAppl && prev.sdf_flag ? prev.sdf_flag : 'Y',
        pep_flag: prev.pep_flag || '', // Keep existing PEP flag if set
      }));
      return;
    }

    // HUF (02)
    if (hufStatuses.includes(taxStatus)) {
      setIsHUF(true);
      setIsIndividual(false);
      setIsEntity(false);

      setFormData(prev => ({
        ...prev, // Keep existing data
        ubo_appl: preserveUboAppl ? currentUboAppl : 'Y',
        ubo_df: preserveUboAppl ? prev.ubo_df : 'Y',
        pep_flag: '', // clear PEP for non-individual
        sdf_flag: preserveUboAppl && prev.sdf_flag ? prev.sdf_flag : 'Y',
      }));
      return;
    }

    // Entity (Company / Trust / LLP / Others)
    setIsEntity(true);
    setIsIndividual(false);
    setIsHUF(false);

    setFormData(prev => ({
      ...prev, // Keep existing data
      ubo_appl: preserveUboAppl ? currentUboAppl : 'Y',
      ubo_df: preserveUboAppl ? prev.ubo_df : 'Y',
      sdf_flag: preserveUboAppl && prev.sdf_flag ? prev.sdf_flag : 'Y',
      pep_flag: '', // clear PEP for non-individual
    }));
  };

  // Effect to auto-fill UBO state and city based on pincode (for INDIA nationality)
  useEffect(() => {
    const fetchLocationByUboPincode = async () => {
      // Only fetch if:
      // 1. We have a 6-digit pincode for UBO
      // 2. UBO Nationality is set to INDIA or IN
      // 3. We're in edit mode
      if (formData.ubo_pin && formData.ubo_pin.length === 6 && (formData.ubo_nation === 'INDIA' || formData.ubo_nation === 'IN') && isEditMode) {
        try {
          // console.log('Fetching pincode info for UBO pincode:', formData.ubo_pin);
          const response = await fetch(
            `${API_BASE_URL}/master_data.php?action=pincode_info&pincode=${formData.ubo_pin}`
          );

          if (response.ok) {
            const data = await response.json();
            // console.log('UBO Pincode API response:', data);

            if (data.success && data.data) {
              const location = data.data;

              // Extract raw values from API response
              let rawStateValue = location.state_code || location.state || location.STATE || '';
              const cityValue = location.city || location.CITY || '';

              // console.log('Raw state value from API:', rawStateValue);
              // console.log('Available state options:', masterData.stateOptions);

              // Try to find matching state in dropdown options
              let finalStateValue = '';

              if (masterData.stateOptions && masterData.stateOptions.length > 0) {
                // First try exact match by value (state code)
                const exactMatch = masterData.stateOptions.find(
                  opt => opt.value && opt.value.toUpperCase() === rawStateValue.toUpperCase()
                );

                if (exactMatch) {
                  finalStateValue = exactMatch.value;
                  // console.log('✓ Found exact state match by value:', exactMatch);
                } else {
                  console.log('✗ No exact match by value for:', rawStateValue);

                  // Try to match by label (state name)
                  const labelMatch = masterData.stateOptions.find(
                    opt => opt.label && opt.label.toUpperCase() === rawStateValue.toUpperCase()
                  );

                  if (labelMatch) {
                    finalStateValue = labelMatch.value;
                    // console.log('✓ Found state match by label:', labelMatch);
                  } else {
                    console.log('✗ No match by label for:', rawStateValue);

                    // Try substring matching for state name
                    const nameSubstringMatch = masterData.stateOptions.find(
                      opt => opt.label && opt.label.toUpperCase().includes(rawStateValue.toUpperCase())
                    );

                    if (nameSubstringMatch) {
                      finalStateValue = nameSubstringMatch.value;
                      // console.log('✓ Found state match by substring:', nameSubstringMatch);
                    } else {
                      console.log('✗ No substring match in labels for:', rawStateValue);

                      // Last resort: check if API returned a full state name
                      const reverseSubstringMatch = masterData.stateOptions.find(
                        opt => rawStateValue.toUpperCase().includes(opt.label.toUpperCase())
                      );

                      if (reverseSubstringMatch) {
                        finalStateValue = reverseSubstringMatch.value;
                        // console.log('✓ Found state match by reverse substring:', reverseSubstringMatch);
                      } else {
                        console.warn('✗ No matching state option found for UBO pincode:', rawStateValue);
                      }
                    }
                  }
                }
              } else {
                console.warn('State options are empty or not loaded yet');
              }

              // console.log('Final state value to set:', finalStateValue || '(empty - will not change current value)');

              setFormData(prev => ({
                ...prev,
                ubo_city: cityValue || prev.ubo_city,
                ubo_state: finalStateValue || prev.ubo_state // Only update if finalStateValue is not empty
              }));
            } else {
              console.warn('API returned success false or no data:', data);
            }
          }
        } catch (error) {
          console.error('Error fetching UBO location by pincode:', error);
        }
      }
    };

    // Debounce the API call
    const timer = setTimeout(() => {
      fetchLocationByUboPincode();
    }, 500);

    return () => clearTimeout(timer);
  }, [formData.ubo_pin, formData.ubo_nation, API_BASE_URL, isEditMode, masterData.stateOptions]);


  // Handle input changes
  const handleInputChange = (e) => {
    if (!isEditMode) return;

    const { name, value } = e.target;

    // Prevent changes to auto-filled and read-only fields
    const readOnlyFields = [
      'pan_rp',
      'inv_name',
      'dob',
      'data_src',
      'exch_name',
      'occ_type',
      'log_name'  // Also make log_name read-only since it's auto-filled from email
    ];

    if (readOnlyFields.includes(name)) {
      return;
    }

    // If tax status changes, update user type
    if (name === 'tax_status_us') {
      checkUserType(value);
    }

    // If occupation code changes, auto-set occupation type
    if (name === 'occ_code') {
      const occupationType = occupationMapping[value] || '';
      setFormData(prev => ({
        ...prev,
        [name]: value,
        occ_type: occupationType
      }));
      return;
    }

    // Handle date fields
    if (name === 'ubo_dob') {
      setFormData(prev => ({
        ...prev,
        ubo_dob: value || ''
      }));
      return;
    }

    // Handle nw_date separately if needed
    if (name === 'nw_date') {
      setFormData(prev => ({
        ...prev,
        nw_date: value || ''
      }));
      return;
    }

    // Handle UBO nationality change - clear location fields if not INDIA
    if (name === 'ubo_nation') {
      if (value !== 'INDIA' && value !== 'IN') {
        // Clear pincode, city, state, and address when nationality is changed to non-India
        setFormData(prev => ({
          ...prev,
          ubo_nation: value,
          ubo_pin: '',
          ubo_city: '',
          ubo_state: '',
          ubo_add1: ''
        }));
      } else {
        // Just update the nationality if it's INDIA
        setFormData(prev => ({
          ...prev,
          ubo_nation: value
        }));
      }
      return;
    }

    // For all other fields
    setFormData(prev => ({
      ...prev,
      [name]: value,
    }));
  };

  // Validate PAN format
  const validatePAN = (pan) => {
    if (!pan) return false;
    const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
    return panRegex.test(pan);
  };

  // Validate all mandatory fields
  const validateForm = () => {
    const errors = [];

    // PAN validation
    if (!formData.pan_rp) {
      errors.push('PAN is mandatory');
    } else if (!validatePAN(formData.pan_rp)) {
      errors.push('Invalid PAN format (should be ABCDE1234F)');
    }

    // Basic mandatory fields
    if (!formData.inv_name?.trim()) errors.push('Name (As per PAN) is mandatory');
    if (!formData.tax_status_us) errors.push('Tax Status is mandatory');
    if (!formData.data_src) errors.push('Data Source is mandatory');
    if (!formData.addr_type) errors.push('Address Type is mandatory');
    if (!formData.po_bir_inc?.trim()) errors.push('Place of Birth/Incorporation is mandatory');
    if (!formData.co_bir_inc) errors.push('Country of Birth/Incorporation is mandatory');

    // TIN Detail 1 validation
    if (!formData.tax_res1) errors.push('Residence Country (TAX_RES1) is mandatory');
    if (!formData.tpin1?.trim()) errors.push('Tax Payer Identification Number / Other Equivalent Number (TPIN1) is mandatory');
    if (!formData.id1_type) errors.push('ID Type (ID1_TYPE) is mandatory');

    // Additional mandatory fields
    if (!formData.srce_wealt) errors.push('Wealth Source is mandatory');
    if (!formData.inc_slab) errors.push('Income Slab is mandatory');
    if (!formData.occ_code) errors.push('Occupation Code is mandatory');
    // Note: occ_type is now auto-set, not manually selected

    /*if (!formData.exch_name) errors.push('Exchange Name is mandatory');*/

    // Corporate fields for non-individuals
    if (isEntity || isHUF) {
      if (!formData.corp_servs && formData.corp_servs !== '') errors.push('Corporate Services is mandatory for non-individuals');
      if (!formData.net_worth && formData.net_worth !== '') errors.push('Net Worth is mandatory for non-individuals');
      if (!formData.nw_date) errors.push('Net Worth Date is mandatory for non-individuals');
      if (!formData.exemp_code) errors.push('Exemption Code is mandatory for non-individuals');
      if (!formData.ffi_drnfe) errors.push('FFI/DRNFE is mandatory for non-individuals');
      if (!formData.nffe_catg) errors.push('NFE Category is mandatory for HUF & non-individuals');

      if (formData.ffi_drnfe === 'FFI' || formData.ffi_drnfe === 'DRNFE') {
        if (!formData.giin_no?.trim()) errors.push('GIIN Number is mandatory for FFI/DRNFE');
      }
    }

    // PEP flag for individuals
    if (isIndividual && !formData.pep_flag) {
      errors.push('PEP Flag is mandatory for individuals');
    }

    // UBO validation if applicable
    if (formData.ubo_appl === 'Y') {
      if (!formData.ubo_name?.trim()) errors.push('UBO Name is mandatory when UBO_APPL is Y');
      if (!formData.ubo_nation) errors.push('UBO Nationality is mandatory when UBO_APPL is Y');
      if (!formData.ubo_add1?.trim()) errors.push('UBO Address Line 1 is mandatory when UBO_APPL is Y');
      if (!formData.ubo_city?.trim()) errors.push('UBO City is mandatory when UBO_APPL is Y');
      if (!formData.ubo_state) errors.push('UBO State is mandatory when UBO_APPL is Y');
      if (!formData.ubo_ctr) errors.push('UBO Country of Tax Residency is mandatory when UBO_APPL is Y');
      if (!formData.ubo_code) errors.push('UBO Code is mandatory when UBO_APPL is Y');

      // UBO PAN or TIN validation
      if (!formData.ubo_pan?.trim() && !formData.ubo_tin?.trim()) {
        errors.push('Either UBO PAN or TIN is mandatory when UBO_APPL is Y');
      }

      // ID Type if UBO_APPL is Y
      if (!formData.ubo_id_ty) errors.push('UBO ID Type is mandatory when UBO_APPL is Y');
      if (!formData.ubo_cob) errors.push('UBO Country of Birth is mandatory when UBO_APPL is Y');
    }

    // SDF Flag validation
    if (!formData.sdf_flag) {
      if (isEntity) {
        errors.push('Self Declaration Flag is mandatory for entities');
      }
    }

    // UBO DF validation
    if (!formData.ubo_df) {
      errors.push('UBO Declaration Flag is mandatory');
    }

    // NPO validation
    if (!formData.npo_form) errors.push('NPO Form is mandatory');

    if (formData.npo_form === 'Y') {
      if (!formData.pan_rp) errors.push('PAN is mandatory when NPO_FORM is Y');
      if (!formData.npo_dcl) errors.push('NPO Declaration is mandatory when NPO_FORM is Y');
      if (!formData.npo_rgno?.trim()) errors.push('NPO Registration Number is mandatory when NPO_FORM is Y');
    }

    // Log Name validation for electronic data source
    if (formData.data_src === 'E' && !formData.log_name?.trim()) {
      errors.push('Log Name is mandatory when Data Source is Electronic');
    }

    return errors;
  };

  // Save FATCA profile
  const saveFatcaProfile = async () => {
    setIsSubmitting(true);

    try {
      // Validate form
      const validationErrors = validateForm();
      if (validationErrors.length > 0) {
        alert('Please fix the following errors:\n\n' + validationErrors.join('\n'));
        setIsSubmitting(false);
        return;
      }

      // Get user token/ID
      const storedUser = localStorage.getItem('site_user');
      const userData = JSON.parse(storedUser);
      const token = localStorage.getItem('site_token');


      // Prepare data with user ID
      const dataToSave = {
        ...formData,
        user_id: userData.id,
        email: userData.email,
        updated_by: userData.id,
        updated_at: new Date().toISOString()
      };


      // Submit to API
      const response = await fetch(`${API_BASE_URL}/fatca_details_api.php?action=save_fatca_profile`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(dataToSave)
      });

      const result = await response.json();

      if (result.success) {
        alert('FATCA profile saved successfully!');
        setHasData(true);
        setIsEditMode(false);

        // Scroll to top after successful save
        scrollToTop();

        // Refresh data
        await loadFatcaProfile(userData.id);
      } else {
        alert(result.message);
      }

    } catch (error) {
      alert('Failed to save FATCA profile. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Clear FATCA profile
  const clearFatcaProfile = async () => {
    if (!window.confirm('Are you sure you want to clear the FATCA profile? This action cannot be undone.')) {
      return;
    }

    try {
      const storedUser = localStorage.getItem('site_user');
      const userData = JSON.parse(storedUser);
      const token = localStorage.getItem('site_token');

      // Get user details from database
      let userPan = '';
      let userName = '';
      let userDob = '';

      try {
        const userDetailsResponse = await fetch(`${API_BASE_URL}/user_details_api.php?action=get_user_details`, {
          method: "GET",
          headers: {
            "Authorization": `Bearer ${token}`,
            "Content-Type": "application/json"
          }
        });

        const userDetails = await userDetailsResponse.json();
        userPan = userDetails.data?.PRIMARY_HOLDER_PAN || userData.pan || '';
        userName = userDetails.data?.PRIMARY_HOLDER_NAME || userData.name || '';
        userDob = userDetails.data?.PRIMARY_HOLDER_DOB || userData.dob || '';
      } catch (error) {
        userPan = userData.pan || '';
        userName = userData.name || '';
        userDob = userData.dob || '';
      }

      // Clear the profile via API
      const response = await fetch(`${API_BASE_URL}/fatca_details_api.php?action=clear_fatca_profile`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ user_id: userData.id })
      });

      const result = await response.json();

      if (result.success) {
        alert('FATCA profile cleared successfully!');

        // Reset form with auto-filled data from signup
        setFormData({
          // Auto-filled from signup (read-only)
          pan_rp: userPan,
          inv_name: userName,
          dob: userDob ? formatDateForInput(userDob) : '',

          // Empty other fields
          // pekrn: '', // REMOVED
          fr_name: '',
          sp_name: '',
          tax_status_us: '',
          data_src: 'P',
          addr_type: '',
          po_bir_inc: '',
          co_bir_inc: 'IN',
          tax_res1: '',
          tpin1: '',
          id1_type: '',
          tax_res2: '',
          tpin2: '',
          id2_type: '',
          tax_res3: '',
          tpin3: '',
          id3_type: '',
          tax_res4: '',
          tpin4: '',
          id4_type: '',
          srce_wealt: '',
          corp_servs: '',
          inc_slab: '',
          net_worth: '',
          nw_date: '',
          pep_flag: '',
          occ_code: '',
          occ_type: '',
          exemp_code: '',
          ffi_drnfe: '',
          giin_no: '',
          spr_entity: '',
          giin_na: '',
          giin_exemc: '',
          nffe_catg: '',
          act_nfe_sc: '',
          nature_bus: '',
          rel_listed: '',
          exch_name: 'O',
          ubo_appl: isIndividual ? 'N' : 'Y',
          ubo_count: '0',
          ubo_name: '',
          ubo_pan: '',
          ubo_nation: '',
          ubo_add1: '',
          ubo_add2: '',
          ubo_add3: '',
          ubo_city: '',
          ubo_pin: '',
          ubo_state: '',
          ubo_cntry: '',
          ubo_add_type: '',
          ubo_ctr: '',
          ubo_tin: '',
          ubo_id_ty: '',
          ubo_cob: '',
          ubo_dob: '', // Clear UBO DOB
          ubo_gender: '',
          ubo_fr_nam: '',
          ubo_occ: '',
          ubo_occ_ty: '',
          ubo_tel: '',
          ubo_mobile: '',
          ubo_code: '',
          ubo_hol_pc: '',
          ubo_exch: '',
          ubo_isin: '',
          ubo_rel_li: '',
          ubo_categ: '',
          ubo_pep_fl: '',
          ubo_email: '',
          ubo_smo_de: '',
          sdf_flag: isEntity ? 'Y' : 'Y',
          ubo_df: isIndividual ? 'N' : 'Y',
          aadhaar_rp: '',
          new_cha: 'N',
          log_name: userData.email || '',
          filler1: '',
          filler2: '',
          npo_form: 'N',
          npo_dcl: '',
          npo_rgno: '',
        });

        setHasData(false);
        setIsEditMode(true);
      } else {
        alert('Failed to clear FATCA profile: ' + result.message);
      }
    } catch (error) {
      alert('Failed to clear FATCA profile. Please try again.');
    }
  };

  // Toggle edit mode
  const toggleEditMode = () => {
    if (isEditMode) {
      saveFatcaProfile();
    } else {
      setIsEditMode(true);
    }
  };

  // Format date for display
  const formatDateForInput = (dateString) => {
    if (!dateString || dateString === '0000-00-00') return '';
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) {
        // Try parsing different date formats
        const parts = dateString.split(/[-/]/);
        if (parts.length === 3) {
          const day = parts[0];
          const month = parts[1];
          const year = parts[2];
          const parsedDate = new Date(`${year}-${month}-${day}`);
          if (!isNaN(parsedDate.getTime())) {
            return parsedDate.toISOString().split('T')[0];
          }
        }
        return '';
      }
      return date.toISOString().split('T')[0];
    } catch (error) {
      return '';
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

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading FATCA profile...</p>
        </div>
      </div>
    );
  }

  // Calculate disabled state
  const isDisabled = isSubmitting || isLoading || (!isEditMode && hasData);

  return (
    <div className="space-y-6">
      {/* Header with ref for scrolling */}
      <div ref={headerRef} className="flex flex-col md:flex-row md:items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            <TypedText text="FATCA Declaration" className="text-gray-900" />
          </h1>
          <p className="text-gray-600">
            Foreign Account Tax Compliance Act - Complete all mandatory fields marked with <span className="text-red-500">*</span>
          </p>
          <div className="mt-2 flex space-x-4">
            <span className={`px-3 py-1 rounded-full text-sm ${isIndividual ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}`}>
              {isIndividual ? 'Individual' : isHUF ? 'HUF' : 'Entity'}
            </span>
            {hasData && (
              <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                Profile Exists
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Form */}
      <form onSubmit={(e) => { e.preventDefault(); saveFatcaProfile(); }} className="space-y-8">
        {/* Section 1: Primary Details */}
        <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
            1. Primary Details
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                PAN <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <input
                  type="text"
                  name="pan_rp"
                  value={visibleFields['pan_rp'] ? formData.pan_rp : maskValue(formData.pan_rp)}
                  className={`w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg uppercase ${isDisabled ? 'cursor-not-allowed' : ''} text-gray-600 ${!isDisabled ? 'pr-12' : ''}`}
                  placeholder="ABCDE1234F"
                  maxLength="10"
                  required
                  readOnly={true}
                  disabled={isDisabled}
                />
                {!isDisabled && (
                  <button
                    type="button"
                    onClick={() => toggleFieldVisibility('pan_rp')}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                    title={visibleFields['pan_rp'] ? "Hide PAN" : "Show PAN"}
                  >
                    {visibleFields['pan_rp'] ? (
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                      </svg>
                    ) : (
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                    )}
                  </button>
                )}
              </div>
            </div>

            {/* PEKRN field REMOVED */}

            {/* Name - Auto-filled and read-only */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Name (As per PAN) <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="inv_name"
                value={formData.inv_name}
                className={`w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-600 ${isDisabled ? 'cursor-not-allowed' : ''}`}
                maxLength="70"
                required
                readOnly={true}
                disabled={isDisabled}
              />

            </div>

            {/* Date of Birth - Auto-filled and read-only */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Date of Birth/Incorporation
              </label>
              <div className="relative">
                <input
                  type="text"
                  name="dob"
                  value={visibleFields['dob'] ? formatDateForInput(formData.dob) : maskValue(formatDateForInput(formData.dob))}
                  className={`w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-600 ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                  readOnly={true}
                  disabled={isDisabled}
                />
                {!isDisabled && (
                  <button
                    type="button"
                    onClick={() => toggleFieldVisibility('dob')}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                    title={visibleFields['dob'] ? "Hide DOB" : "Show DOB"}
                  >
                    {visibleFields['dob'] ? (
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
            </div>

            {/* Father's Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Father&apos;s Name
              </label>
              <input
                type="text"
                name="fr_name"
                value={formData.fr_name}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                maxLength="70"
                readOnly={!isEditMode}
                disabled={isDisabled}
              />
            </div>

            {/* Spouse Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Spouse Name
              </label>
              <input
                type="text"
                name="sp_name"
                value={formData.sp_name}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                maxLength="70"
                readOnly={!isEditMode}
                disabled={isDisabled}
              />
            </div>

            {/* Tax Status */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Tax Status <span className="text-red-500">*</span>
              </label>
              <select
                name="tax_status_us"
                value={formData.tax_status_us}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Tax Status</option>
                {masterData.taxStatusOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Data Source - Set to Physical and read-only */}
            <div className="hidden">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Data Source <span className="text-red-500">*</span>
              </label>
              <input
                type="hidden"
                name="data_src"
                value="P"
                className="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-600 cursor-not-allowed"
                required
                readOnly
                disabled
              />
            </div>

            {/* Address Type */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Address Type <span className="text-red-500">*</span>
              </label>
              <select
                name="addr_type"
                value={formData.addr_type}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Address Type</option>
                {masterData.addressTypeOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Place of Birth */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Place of Birth/Incorporation <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="po_bir_inc"
                value={formData.po_bir_inc}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                maxLength="60"
                required
                readOnly={!isEditMode}
                disabled={isDisabled}
              />
            </div>

            {/* Country of Birth */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Country of Birth/Incorporation <span className="text-red-500">*</span>
              </label>
              <select
                name="co_bir_inc"
                value={formData.co_bir_inc}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Country</option>
                {masterData.countryOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Section 2: TIN Details */}
        <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
            2. Tax Identification Number (TIN) Details
          </h2>

          <div className="space-y-6">
            {/* First TIN Detail - Mandatory */}
            <div className="border border-blue-100 rounded-lg p-6 bg-blue-50">
              <h3 className="text-md font-bold text-gray-900 mb-4">First TIN Detail <span className="text-red-500">*</span></h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Residence Country */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Residence Country <span className="text-red-500">*</span>
                  </label>
                  <select
                    name="tax_res1"
                    value={formData.tax_res1}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    required
                    disabled={isDisabled}
                  >
                    <option value="">Select Country</option>
                    {masterData.countryOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Tax Payer Identification Number / Other Equivalent Number */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tax Payer Identification Number / Other Equivalent Number <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={isDisabled ? 'text' : (visibleFields['tpin1'] ? 'text' : 'password')}
                      name="tpin1"
                      value={isDisabled ? maskValue(formData.tpin1) : formData.tpin1}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                      maxLength="20"
                      required
                      readOnly={!isEditMode}
                      disabled={isDisabled}
                    />
                    {!isDisabled && (
                      <button
                        type="button"
                        onClick={() => toggleFieldVisibility('tpin1')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                        title={visibleFields['tpin1'] ? "Hide TPIN" : "Show TPIN"}
                      >
                        {visibleFields['tpin1'] ? (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                          </svg>
                        ) : (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        )}
                      </button>
                    )}
                  </div>
                </div>

                {/* ID Type */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ID Document Type <span className="text-red-500">*</span>
                  </label>
                  <select
                    name="id1_type"
                    value={formData.id1_type}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    required
                    disabled={isDisabled}
                  >
                    <option value="">Select Document Type</option>
                    {masterData.idTypeOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Second TIN Detail - Optional */}
            <div className="border border-gray-200 rounded-lg p-6 bg-gray-50">
              <h3 className="text-md font-bold text-gray-900 mb-4">Second TIN Detail (Optional)</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Residence Country
                  </label>
                  <select
                    name="tax_res2"
                    value={formData.tax_res2}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Country</option>
                    {masterData.countryOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tax Payer Identification Number / Other Equivalent Number
                  </label>
                  <div className="relative">
                    <input
                      type={isDisabled ? 'text' : (visibleFields['tpin2'] ? 'text' : 'password')}
                      name="tpin2"
                      value={isDisabled ? maskValue(formData.tpin2) : formData.tpin2}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                      maxLength="20"
                      readOnly={!isEditMode}
                      disabled={isDisabled}
                    />
                    {!isDisabled && (
                      <button
                        type="button"
                        onClick={() => toggleFieldVisibility('tpin2')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                        title={visibleFields['tpin2'] ? "Hide TPIN" : "Show TPIN"}
                      >
                        {visibleFields['tpin2'] ? (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                          </svg>
                        ) : (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        )}
                      </button>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ID Document Type
                  </label>
                  <select
                    name="id2_type"
                    value={formData.id2_type}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Document Type</option>
                    {masterData.idTypeOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Third TIN Detail - Optional */}
            <div className="border border-gray-200 rounded-lg p-6 bg-gray-50">
              <h3 className="text-md font-bold text-gray-900 mb-4">Third TIN Detail (Optional)</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Residence Country
                  </label>
                  <select
                    name="tax_res3"
                    value={formData.tax_res3}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Country</option>
                    {masterData.countryOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tax Payer Identification Number / Other Equivalent Number
                  </label>
                  <div className="relative">
                    <input
                      type={isDisabled ? 'text' : (visibleFields['tpin3'] ? 'text' : 'password')}
                      name="tpin3"
                      value={isDisabled ? maskValue(formData.tpin3) : formData.tpin3}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                      maxLength="20"
                      readOnly={!isEditMode}
                      disabled={isDisabled}
                    />
                    {!isDisabled && (
                      <button
                        type="button"
                        onClick={() => toggleFieldVisibility('tpin3')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                        title={visibleFields['tpin3'] ? "Hide TPIN" : "Show TPIN"}
                      >
                        {visibleFields['tpin3'] ? (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                          </svg>
                        ) : (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        )}
                      </button>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ID Document Type
                  </label>
                  <select
                    name="id3_type"
                    value={formData.id3_type}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Document Type</option>
                    {masterData.idTypeOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Fourth TIN Detail - Optional */}
            <div className="border border-gray-200 rounded-lg p-6 bg-gray-50">
              <h3 className="text-md font-bold text-gray-900 mb-4">Fourth TIN Detail (Optional)</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Residence Country
                  </label>
                  <select
                    name="tax_res4"
                    value={formData.tax_res4}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Country</option>
                    {masterData.countryOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tax Payer Identification Number / Other Equivalent Number
                  </label>
                  <div className="relative">
                    <input
                      type={isDisabled ? 'text' : (visibleFields['tpin4'] ? 'text' : 'password')}
                      name="tpin4"
                      value={isDisabled ? maskValue(formData.tpin4) : formData.tpin4}
                      onChange={handleInputChange}
                      className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                      maxLength="20"
                      readOnly={!isEditMode}
                      disabled={isDisabled}
                    />
                    {!isDisabled && (
                      <button
                        type="button"
                        onClick={() => toggleFieldVisibility('tpin4')}
                        className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                        title={visibleFields['tpin4'] ? "Hide TPIN" : "Show TPIN"}
                      >
                        {visibleFields['tpin4'] ? (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                          </svg>
                        ) : (
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        )}
                      </button>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ID Document Type
                  </label>
                  <select
                    name="id4_type"
                    value={formData.id4_type}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    disabled={isDisabled}
                  >
                    <option value="">Select Document Type</option>
                    {masterData.idTypeOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Section 3: Financial & Occupation Details */}
        <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
            3. Financial & Occupation Details
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Wealth Source */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Wealth Source <span className="text-red-500">*</span>
              </label>
              <select
                name="srce_wealt"
                value={formData.srce_wealt}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Wealth Source</option>
                {masterData.wealthSourceOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Corporate Services (for entities) */}
            {(isEntity || isHUF) && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Corporate Services <span className="text-red-500">*</span>
                </label>
                <select
                  name="corp_servs"
                  value={formData.corp_servs}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Service</option>
                  {masterData.corporateServicesOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Income Slab */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Income Slab <span className="text-red-500">*</span>
              </label>
              <select
                name="inc_slab"
                value={formData.inc_slab}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Income Slab</option>
                {masterData.incomeSlabOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Net Worth (for entities) */}
            {(isEntity || isHUF) && (
              <>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Net Worth (INR in Lacs) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    name="net_worth"
                    value={formData.net_worth}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    step="0.01"
                    required
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Net Worth Date <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="date"
                    name="nw_date"
                    value={formatDateForInput(formData.nw_date)}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    required
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                </div>
              </>
            )}

            {/* PEP Flag (for individuals) */}
            {isIndividual && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Politically Exposed Person (PEP) <span className="text-red-500">*</span>
                </label>
                <select
                  name="pep_flag"
                  value={formData.pep_flag}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'
                    } rounded-lg ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  disabled={isDisabled}
                  required
                >
                  <option value="">Select PEP Status</option>
                  {masterData.pepFlagOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}


            {/* Occupation Code */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Occupation Code <span className="text-red-500">*</span>
              </label>
              <select
                name="occ_code"
                value={formData.occ_code}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Occupation Code</option>
                {masterData.occupationCodeOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              {/* Hidden input for occ_type that will be auto-filled */}
              <input
                type="hidden"
                name="occ_type"
                value={formData.occ_type}
              />
            </div>

            {/* Exemption Code (for entities) */}
            {(isEntity || isHUF) && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Exemption Code <span className="text-red-500">*</span>
                </label>
                <select
                  name="exemp_code"
                  value={formData.exemp_code}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Exemption Code</option>
                  {masterData.exemptionCodeOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* FFI/DRNFE (for entities) */}
            {(isEntity || isHUF) && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  FFI/DRNFE <span className="text-red-500">*</span>
                </label>
                <select
                  name="ffi_drnfe"
                  value={formData.ffi_drnfe}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Option</option>
                  {masterData.ffiOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* GIIN Number (conditional) */}
            {(formData.ffi_drnfe === 'FFI' || formData.ffi_drnfe === 'DRNFE') && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  GIIN Number <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="giin_no"
                  value={formData.giin_no}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="19"
                  required
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>
            )}

            {/* NFE Category (for HUF & entities) */}
            {(isEntity || isHUF) && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  NFE Category <span className="text-red-500">*</span>
                </label>
                <select
                  name="nffe_catg"
                  value={formData.nffe_catg}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select NFE Category</option>
                  {masterData.nfeCategoryOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}


            {/* Exchange Name - Fixed to 'Others' */}
            <div className="hidden">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Exchange Name <span className="text-red-500">*</span>
              </label>
              <input
                type="hidden"
                name="exch_name"
                value="Others"
                className="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-600 cursor-not-allowed"
                required
                readOnly
                disabled
              />

            </div>

            {/* Aadhaar */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Aadhaar Number
              </label>
              <input
                type="text"
                name="aadhaar_rp"
                value={formData.aadhaar_rp}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                maxLength="30"
                readOnly={!isEditMode}
                disabled={isDisabled}
              />
            </div>
          </div>
        </div>

        {/* Section 4: UBO & Declaration Flags */}
        <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
            4. Declaration Flags
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {/* Self Declaration Flag */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Self Declaration Flag <span className="text-red-500">*</span>
              </label>
              <select
                name="sdf_flag"
                value={formData.sdf_flag}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required={isEntity}
                disabled={isDisabled}
              >
                <option value="">Select Option</option>
                {masterData.selfDeclarationFlagOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-500">
                {isEntity ? 'Mandatory for entities' : 'Optional for individuals'}
              </p>
            </div>

            {/* UBO Declaration Flag */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                UBO Declaration Flag <span className="text-red-500">*</span>
              </label>
              <select
                name="ubo_df"
                value={formData.ubo_df}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Option</option>
                {masterData.uboDFOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* UBO Applicable */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                UBO Applicable <span className="text-red-500">*</span>
              </label>
              <select
                name="ubo_appl"
                value={formData.ubo_appl}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Option</option>
                {masterData.uboDFOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* NPO Form */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                NPO Form <span className="text-red-500">*</span>
              </label>
              <select
                name="npo_form"
                value={formData.npo_form}
                onChange={handleInputChange}
                className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                required
                disabled={isDisabled}
              >
                <option value="">Select Option</option>
                {masterData.npoFormOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-500">
                PAN mandatory when set to Y
              </p>
            </div>

            {/* NPO Declaration (conditional) */}
            {formData.npo_form === 'Y' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  NPO Declaration <span className="text-red-500">*</span>
                </label>
                <select
                  name="npo_dcl"
                  value={formData.npo_dcl}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Option</option>
                  {masterData.npoDCLOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* NPO Registration Number (conditional) */}
            {formData.npo_form === 'Y' && formData.npo_dcl === 'Y' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  NPO Registration Number <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="npo_rgno"
                  value={formData.npo_rgno}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="50"
                  required
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>
            )}
          </div>
        </div>

        {/* Section 5: UBO Details (Conditional) - ONLY SHOW WHEN UBO_APPL IS 'Y' */}
        {formData.ubo_appl === 'Y' && (
          <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
              5. Ultimate Beneficial Owner (UBO) Details
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Basic UBO Details */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  UBO Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="ubo_name"
                  value={formData.ubo_name}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="70"
                  required
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  UBO PAN
                </label>
                <div className="relative">
                  <input
                    type={isDisabled ? 'text' : (visibleFields['ubo_pan'] ? 'text' : 'password')}
                    name="ubo_pan"
                    value={isDisabled ? maskValue(formData.ubo_pan) : formData.ubo_pan}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors uppercase ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                    maxLength="10"
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                  {!isDisabled && (
                    <button
                      type="button"
                      onClick={() => toggleFieldVisibility('ubo_pan')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                      title={visibleFields['ubo_pan'] ? "Hide UBO PAN" : "Show UBO PAN"}
                    >
                      {visibleFields['ubo_pan'] ? (
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
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  UBO Nationality <span className="text-red-500">*</span>
                </label>
                <select
                  name="ubo_nation"
                  value={formData.ubo_nation}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Nationality</option>
                  {masterData.countryOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  UBO Code <span className="text-red-500">*</span>
                </label>
                <select
                  name="ubo_code"
                  value={formData.ubo_code}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select UBO Code</option>
                  {masterData.uboCodeOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* Address Details */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Address Line 1 <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="ubo_add1"
                  value={formData.ubo_add1}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="50"
                  required
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  City <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="ubo_city"
                  value={formData.ubo_city}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="50"
                  required
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  State <span className="text-red-500">*</span>
                </label>
                {formData.ubo_nation === 'INDIA' || formData.ubo_nation === 'IN' ? (
                  // Show dropdown for India
                  <select
                    name="ubo_state"
                    value={formData.ubo_state}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    required
                    disabled={isDisabled}
                  >
                    <option value="">Select State</option>
                    {masterData.stateOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                ) : (
                  // Show text input for non-India countries
                  <input
                    type="text"
                    name="ubo_state"
                    value={formData.ubo_state}
                    onChange={handleInputChange}
                    placeholder="Enter state/province"
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                    maxLength="50"
                    required
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  PIN Code
                </label>
                <input
                  type="text"
                  name="ubo_pin"
                  value={formData.ubo_pin}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="6"
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>

              {/* Additional UBO Fields */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Country of Tax Residency <span className="text-red-500">*</span>
                </label>
                <select
                  name="ubo_ctr"
                  value={formData.ubo_ctr}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Country</option>
                  {masterData.countryOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  UBO TIN (If PAN not provided)
                </label>
                <div className="relative">
                  <input
                    type={isDisabled ? 'text' : (visibleFields['ubo_tin'] ? 'text' : 'password')}
                    name="ubo_tin"
                    value={isDisabled ? maskValue(formData.ubo_tin) : formData.ubo_tin}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                    maxLength="20"
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                  {!isDisabled && (
                    <button
                      type="button"
                      onClick={() => toggleFieldVisibility('ubo_tin')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                      title={visibleFields['ubo_tin'] ? "Hide UBO TIN" : "Show UBO TIN"}
                    >
                      {visibleFields['ubo_tin'] ? (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                      ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                      )}
                    </button>
                  )}
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  ID Document Type <span className="text-red-500">*</span>
                </label>
                <select
                  name="ubo_id_ty"
                  value={formData.ubo_id_ty}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Document Type</option>
                  {masterData.idTypeOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Country of Birth <span className="text-red-500">*</span>
                </label>
                <select
                  name="ubo_cob"
                  value={formData.ubo_cob}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  required
                  disabled={isDisabled}
                >
                  <option value="">Select Country</option>
                  {masterData.countryOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Date of Birth
                </label>
                <div className="relative">
                  <input
                    type={isDisabled ? 'text' : (visibleFields['ubo_dob'] ? 'date' : 'password')}
                    name="ubo_dob"
                    value={isDisabled ? maskValue(formData.ubo_dob) : (formData.ubo_dob || '')}
                    onChange={handleInputChange}
                    className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''} ${!isDisabled ? 'pr-12' : ''}`}
                    readOnly={!isEditMode}
                    disabled={isDisabled}
                  />
                  {!isDisabled && (
                    <button
                      type="button"
                      onClick={() => toggleFieldVisibility('ubo_dob')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none pointer-events-auto"
                      title={visibleFields['ubo_dob'] ? "Hide UBO DOB" : "Show UBO DOB"}
                    >
                      {visibleFields['ubo_dob'] ? (
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
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Gender
                </label>
                <select
                  name="ubo_gender"
                  value={formData.ubo_gender}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  disabled={isDisabled}
                >
                  <option value="">Select Gender</option>
                  {masterData.genderOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Email
                </label>
                <input
                  type="email"
                  name="ubo_email"
                  value={formData.ubo_email}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  maxLength="30"
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Holding Percentage
                </label>
                <input
                  type="number"
                  name="ubo_hol_pc"
                  value={formData.ubo_hol_pc}
                  onChange={handleInputChange}
                  className={`w-full px-4 py-2.5 border ${isEditMode ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-600'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors ${isDisabled ? 'cursor-not-allowed' : ''}`}
                  min="0"
                  max="100"
                  step="0.01"
                  readOnly={!isEditMode}
                  disabled={isDisabled}
                />
              </div>
            </div>
          </div>
        )}

        {/* Button Section at Bottom */}
        <div className="mt-8 pt-6 border-t border-gray-200">
          <div className="flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
            <div className="text-sm text-gray-600">
              <p>All fields marked with <span className="text-red-500">*</span> are mandatory</p>
              <p className="mt-1">Make sure all information is accurate before saving</p>
            </div>

            <div className="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
              <button
                type="button"
                onClick={clearFatcaProfile}
                className="px-4 py-2.5 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded-lg transition-colors flex items-center justify-center"
              >
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Clear Profile
              </button>

              <button
                type="button"
                onClick={toggleEditMode}
                disabled={isSubmitting}
                className={`px-6 py-2.5 font-medium rounded-lg transition-colors flex items-center justify-center ${isEditMode
                    ? 'bg-blue-600 hover:bg-blue-700 text-white'
                    : 'bg-green-600 hover:bg-green-700 text-white'
                  } ${isSubmitting ? 'opacity-75 cursor-not-allowed' : ''}`}
              >
                {isSubmitting ? (
                  <span className="flex items-center">
                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                  </span>
                ) : isEditMode ? (
                  <span className="flex items-center">
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    Save FATCA Profile
                  </span>
                ) : (
                  <span className="flex items-center">
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit FATCA Profile
                  </span>
                )}
              </button>
            </div>
          </div>
        </div>
      </form>

      {/* Instructions */}
      <div className="mt-8 p-6 bg-yellow-50 rounded-lg border border-yellow-200">
        <h3 className="font-bold text-gray-900 mb-3">Important Instructions</h3>
        <ul className="space-y-2 text-sm text-gray-600">
          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>Fields marked with <span className="text-red-500">*</span> are mandatory</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>PAN, Name, and Date of Birth are auto-filled from your database profile and cannot be changed. If you need to update these, please update your main profile first.</span>
          </li>

          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>Name should match exactly as per PAN card</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>First TIN Detail section fields are mandatory</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>NPO: PAN is mandatory when NPO_FORM is &apos;Y&apos;</span>
          </li>
          <li className="flex items-start">
            <svg className="w-4 h-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.698-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span>UBO Details: Either PAN or TIN must be provided for each UBO</span>
          </li>

        </ul>
      </div>
    </div>
  );
}