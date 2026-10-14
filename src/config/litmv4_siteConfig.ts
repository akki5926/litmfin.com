// src/config/siteConfig.ts

const siteConfig = {
  // 🌐 APP IDENTITY
  siteName: "LITM",
  SITENAME_CAP: "LITM",
  domain: "LITM Financial Pvt Ltd",
  tagline: "Smart Financial Planning for Smart People",
  CompanyName: "Litm Financial Pvt Ltd",

  // 🖼 BRANDING & ASSETS
  branding: {
    logo: "/v4/logo.png",
    favicon: "/v4/aw_favicon.ico",
    bgLogo: "/v4/logo-bg.svg",
    footerLogo: "/v4/logo.png",
    assetAllocationImage: "/v4/asset-allocation.png",
  },

  // 👤 LEADERSHIP
  ceo: {
    name: "SAJI PULIKAN",
    imageUrl: "/v4/ceo.png",
  },

  // 🏢 OFFICE LOCATIONS
  offices: {
    corporate: {
      name: "Corporate Office",
      company: "Litm Financial Pvt Ltd",
      address: [
        "3488, 14th Main Rd,",
        "HAL 2nd Stage, Indiranagar,",
        "Bengaluru, Karnataka 560008"
      ],
      city: "Bengaluru",
      state: "Karnataka",
      pincode: "560008",
      mapsUrl: "https://maps.app.goo.gl/gCUXGSCjPTUFaFEG6?g_st=aw"
    }
  },

  // 📧 CONTACT & COMMUNICATION
  contact: {
    supportEmail: "support@litmfin.com",
    privacyEmail: "support@litmfin.com",
    phone: "9820724644",
    defaultLocation: "Bangalore, Karnataka, India",
    privacyAddress: "#783, 12th Main, 1st Cross, Indiranagar, Bangalore - 560008",
  },

  // 🎯 FEATURE FLAGS
  features: {
    SIF: true,
  },

  // 🌍 SOCIAL MEDIA
  social: {
    linkedin: "https://www.linkedin.com/company/litm-financial-pvt-ltd/",
    twitter: "https://x.com/FinancialLitm",
    instagram: "https://instagram.com/litmfin",
    facebook: "https://facebook.com/...",
    youtube: "https://youtube.com/..."
  },

  // 🔍 SEO & METADATA
  seo: {
    title: "LITM - Smart Investment Platform",
    description: "LITM - Smart Investment Platform offers personalized investment advice focusing on asset allocation. Start your financial journey with us today!",
    keywords: [
      "mutual funds",
      "investment",
      "asset allocation",
      "financial planning",
    ],
  },

  // 🏢 FOOTER CONTENT
  footer: {
    text: "AMFI Registered Mutual Fund and SIF Distributor with ARN no.166563. APMI Registered Portfolio Management Services Distributor with APRN00568.",
    description: "We empower individuals to make smart investment decisions through technology-driven solutions and expert guidance.",
  },

  // 📊 HOMEPAGE CONTENT
  homepage: {
    hero: {
      heading: "Investing Simplified",
      description: "Get simplified investment options with focus on Client Risk, Safety and Liquidity.",
    },
    stepsSection: {
      description: "Join hundreds of investors who have simplified their financial journey with {siteName}",
    },
    partnersSection: {
      heading: "Trusted And Loved By",
    },
    heroStats: {
      trustedInvestorsText: "Trusted by 250+ Families",
      assetsManaged: "₹300Cr+",
      happyInvestorsCount: "250+",
      happyInvestorsText: "Happy Families",
      customerRating: "200+",
      customerRatingText: "Products Empaneled",
      supportAvailability: "24/7",
    },
    investmentSteps: [
      {
        number: '01',
        title: 'Create Login',
        description: 'Register with your basic details in under 2 minutes',
        icon: '👤',
        color: 'from-blue-500 to-blue-600',
        href: '/login'
      },
      {
        number: '02',
        title: 'KYC & Risk Profiling',
        description: 'Complete paperless KYC and understand your risk appetite',
        icon: '📋',
        color: 'from-emerald-500 to-teal-600',
        href: '/dashboard/home'
      },
      {
        number: '03',
        title: 'Start Investing',
        description: 'Begin with as low as ₹500 and grow your wealth',
        icon: '📈',
        color: 'from-violet-500 to-purple-600',
        href: '/invest'
      }
    ],
    keyMetrics: {
      minInvestment: "₹500",
      accountFees: "₹0",
      onlineProcess: "100%",
      supportRatio: "1:1"
    },
    keyMetricsList: [
      { label: "Embedded Fees" },
      { label: "1:1 Support" },
      { label: "Monthly Tracking" },
      { label: "Quarterly Meetings" }
    ],
    benefitsTitle: "LITM Benefits",
    benefits: [
      'Customer Focus',
      'Multi Asset Offering',
      'Mapping Risk Profile with Financial Goals',
      '100% Online',
      'Embedded fees. No separate fees.',
      'No Demat A/c required.',
    ],
    ctaMessages: {
      stepsButtonText: "Begin Your Journey Now →",
      finalCtaTitle: "Ready to Transform Your Financial Future?",
      finalCtaDescription: "Join 500+ smart investors who trust {siteName} for their financial planning journey.",
      getStartedBtn: "Get Started Free",
      consultationBtn: "Book a Free Consultation",
    },
    fundCategoriesHeading: "Top Funds",
  },

  // 🏦 PARTNERSHIPS & FUNDS
  partners: {
    top: ['AMFI', 'APMI', 'NSE', 'BSE', 'Ticker', 'Morningstar'],
    mutualFunds: [
      'ADITYA BIRLA SUN LIFE', 'AXIS', 'BARODA', 'BNP PARIBAS',
      'BOI AXA', 'CANARA ROBECO', 'DSP', 'EDELWEISS', 'FRANKLIN TEMPLETON', 'HDFC',
      'HSBC', 'ICICI PRUDENTIAL', 'IDBI', 'IDFC', 'IIFL', 'INDIABULLS', 'INVESCO',
      'ITI', 'JM FINANCIAL', 'KOTAK MAHINDRA', 'L&T', 'LIC', 'MAHINDRA', 'MIRAE ASSET',
      'MOTILAL OSWAL', 'NIPPON INDIA', 'PGIM INDIA', 'PRINCIPAL', 'SBI', 'SHRIRAM',
      'SUNDARAM', 'TATA', 'TAURUS', 'UNION', 'UTI', 'YES'
    ]
  },

  // 📚 FUND CATEGORIES
  fundCategories: [
    {
      title: 'Equity',
      funds: [
        'Old Bridge Flexi Cap Fund',
        'Quant Value Fund',
        'HSBC Flexi Cap Fund',
        'Helios Flexi Cap Fund',
        'Kotak Flexicap Fund',
      ],
    },
    {
      title: 'Debt',
      funds: [
        'Aditya Birla Medium Term',
        'Kotak Medium Term',
        'DSP Credit Risk Fund',
        'Bandhan Gilt Fund',
        'Axis Gilt Fund',
      ],
    },
    {
      title: 'Hybrid',
      funds: [
        'Kotak Multi Asset Allocation',
        'DSP Multi Asset Allocation',
        'ICICIPru Equity & Debt',
        'Mahindra Aggressive Hybrid',
        'Bandhan Aggressive Hybrid',
      ],
    },
    {
      title: 'International',
      funds: [
        'Nippon India Taiwan Equity',
        'DSP World Gold Mining Overseas',
        'Mirae Asset NYSE FANG+ ETF',
        'Motilal Oswal Nasdaq 100',
        'Mirae Asset S&P 500 Top 50 ETF',
      ],
    },
  ],

  // 📺 MEDIA PAGE
  media: {
    hero: {
      heading: "Insights, Stories, and Expert Perspectives",
      subtitle: "Our thoughts and insights featured in leading financial publications",
    },
  },

  // 📝 BLOG PAGE
  blog: {
    hero: {
      heading: "Blog",
      subtitle: "Insights, stories, and expert perspectives from our team",
    },
  },

  // 🏢 OFFERINGS PAGE
  offerings: {
    hero: {
      heading: "Wealth & Investments",
      subtitle: "Expert guidance for diversified investments including Mutual Funds, Bonds, AIFs, PMS, and International opportunities",
    },
    services: [
      {
        title: 'Diversified Portfolios',
        icon: '📊',
        features: ['Mutual Funds & ETFs', 'Corporate & Government Bonds', 'International exposure']
      },
      {
        title: 'Alternative Investments',
        icon: '🏦',
        features: ['AIFs & PMS', 'SIF for special opportunities', 'Structured products']
      },
      {
        title: 'Constant Guidance',
        icon: '🎯',
        features: ['Action Plan', 'Risk assessment', 'Regular portfolio reviews']
      }
    ],
    description: "The Indian investment landscape has evolved significantly, offering diverse opportunities beyond traditional savings. From mutual funds to sophisticated alternative investments, we help clients navigate the complexity and build wealth through informed, strategic decisions tailored to their financial goals.",
    ourOfferingsHeading: "Our Offerings",
    whyChooseHeading: "Why Choose {siteName}?",
  },

  // 📄 ABOUT US PAGE
  aboutUs: {
    // Hero Section Content
    hero: {
      title: "About",
      introductionParagraphs: [
        {
          text: "LITM has been built by professionals who have been active for more than a decade in the Indian Investments and Banking sector. Our endeavour is to offer no-frills investment and banking solutions to our customers that will help them plan better for their future needs.",
        },
        {
          text: "Our proprietary tools help us build better and smarter Investments portfolios for our customers, with constant support coming from our Relationship Managers Virtual/Human to help navigate the financial journey in the Best interest of our customers and their families.",
        },
      ],
      secondTitle: "We Build Portfolio for you",
      portfolioText:
        "firmly believes that each customer has his own Risk Profile and Goals that are unique which needs to be connected with the Markets and Assets that can lead to his/her goals in LIFE.\nAgainst this backdrop, we continually monitor the portfolio progress towards stated goals peg market and economic cycles, thus creating long term value to our customer.",
    },

    // Mission & Vision Section
    missionVision: {
      mission: {
        icon: "🎯",
        title: "Our Mission",
        description:
          "To democratize investment opportunities and provide every Indian access to professional financial advice and investment products.",
        points: [
          "Make investing simple and transparent",
          "Provide personalized incidental advice.",
          "Educate and empower investors",
        ],
      },
      vision: {
        icon: "👁️",
        title: "Our Vision",
        description:
          "To become India's most trusted digital investment platform, helping 10 million Indians achieve their financial goals by 2030.",
        points: [
          "Build financial literacy across India",
          "Create wealth for middle-class families",
          "Promote long-term investment culture",
        ],
      },
    },

    // CEO Section
    ceoSection: {
      title: "From CEO's Desk:",
      subtitle: "Few Core Beliefs that I want to share with our customers:",
      beliefs: [
        {
          title: "Start Early:",
          description:
            "No Matter how much you earn always start early from your First Salary which could be your pocket money or intern stipends, India would have a large working population and this bulge would last upto atleast 2055 thus giving us more than 30 years of High productive and Hyper growth years – if channelized properly.",
        },
        {
          title: "Need to quickly learn to differentiate between Asset and Liability.",
          description:
            "We need to at an early age know the difference between Asset and Liability. Young go for instant gratification thus forgetting that a Car might look as a Asset on the Balance Sheet but might be a drain on your finances. Need to quickly learn to difference between Asset and Liability.",
        },
        {
          title: "Contingent Funds:",
          description:
            "Always believe in having a Plan B in Life and for that one needs to keep some funds always handy – CASH IS KING in business and in personal Life.",
        },
        {
          title: "Passive/Other Income:",
          description:
            "Strong Believer in creating one more line of income other than your active income which could be Salary/Business Income, this helps to tide over uncertainties/helps early retirement/Build for future opportunities.",
        },
        {
          title: "Debit Trap:",
          description:
            "Fancy Loans with attractive tag lines would tempt us to take loans which might not be wise, if these loans are not adding any economic value.",
        },
      ],
    },

    // Values Section
    values: {
      title: "Our Values",
      items: [
        {
          icon: "🤝",
          title: "Trust & Transparency",
          description: "We believe in complete transparency in all our dealings",
        },
        {
          icon: "🎓",
          title: "Customer Education",
          description: "We empower customers through financial literacy",
        },
        {
          icon: "🚀",
          title: "Innovation",
          description: "We continuously innovate to simplify investing",
        },
      ],
    },
  },
};

export default siteConfig;