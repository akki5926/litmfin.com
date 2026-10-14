// src/config/siteConfig.ts

const siteConfig = {
  // 🌐 APP IDENTITY
  siteName: "AutoWealth",
  SITENAME_CAP: "AUTOWEALTH",
  domain: "autowealth.in",
  tagline: "Smart Investments for Smart People",
  CompanyName: "AutoWealth.in",

  // 🖼 BRANDING & ASSETS
  branding: {
    logo: "/v4/aw_logo.png",
    favicon: "/v4/aw_favicon.ico",
    bgLogo: "/v4/aw_logo-bg.svg",
    footerLogo: "/v4/aw_logo_footer.png",
    assetAllocationImage: "/v4/asset-allocation.png",
  },

  // 👤 LEADERSHIP
  ceo: {
    name: "Rajesh Kumar Singh", 
    imageUrl: "/rks.jpg",
  },

  // 🏢 OFFICE LOCATIONS
  offices: {
    registered: {
      name: "Registered Office",
      company: "AutoWealth.in",
      address: [
        "303,Sri Lorven Homes,",
        "21st Cross, 2A Main,",
        "Vignan Nagar,",
        "Bangalore - 560075"
      ],
      city: "Bangalore",
      state: "Karnataka",
      pincode: "560075"
    },
    corporate: {
      name: "Corporate Office",
      company: "AutoWealth.in",
      address: [
        "#5BC-938,1st Floor, KLP Arcade,",
        "2nd Cross Rd, Babusapalya,",
        "Bangalore - 560043"
      ],
      city: "Bangalore",
      state: "Karnataka",
      pincode: "560043"
    }
  },

  // 📧 CONTACT & COMMUNICATION
  contact: {
    supportEmail: "support@autowealth.in",
    privacyEmail: "support@autowealth.in",
    phone: "9341059619",
    defaultLocation: "Bangalore, Karnataka, India",
    privacyAddress: "#5BC-938,1st Floor, KLP Arcade, 2nd Cross Rd, Babusapalya, Bangalore - 560043",
  },

  // 🎯 FEATURE FLAGS
  features: {
    SIF: false,
  },

  // 🌍 SOCIAL MEDIA
  social: {
    linkedin: "https://www.linkedin.com/company/autowealth-india/",
    twitter: "https://x.com/autowealth_in",
    instagram: "https://instagram.com/autowealth_official",
    facebook: "https://facebook.com/autowealth.in",
    youtube: "https://youtube.com/@autowealth"
  },

  // 🔍 SEO & METADATA
  seo: {
    title: "AutoWealth – AI-Powered Investment Platform",
    description: "AutoWealth is India's AI-powered mutual fund investment platform. Invest from ₹250 with smart recommendations, zero fees, and 24/7 support.",
    keywords: [
      "mutual funds",
      "investment platform",
      "robo advisor",
      "wealth management",
      "automated investing",
    ],
  },

  // 🏢 FOOTER CONTENT
  footer: {
    text: "AMFI Registered Mutual Fund Distributor with ARN no.249603.",
    description: "AutoWealth uses AI and automation to simplify investing. Invest smarter, grow faster with personalized recommendations and zero bias.",
  },

  // 📊 HOMEPAGE CONTENT
  homepage: {
    hero: {
      heading: "Invest Like Never Before",
      description: "AI-powered recommendations meet zero-fee investing. Let smart technology work for your wealth.",
      bannerImage: "/v4/aw_hero_banner.png",
    },
    stepsSection: {
      description: "Join 50+ smart investors who use AI to make better investment decisions with {siteName}",
    },
    partnersSection: {
      heading: "Backed By Industry Leaders",
    },
    heroStats: {
      trustedInvestorsText: "Trusted by 50+ Investors",
      assetsManaged: "₹2Cr+",
      happyInvestorsCount: "50+",
      customerRating: "9.7/10",
      customerRatingText: "Ratings",
      supportAvailability: "24/7",
    },
    investmentSteps: [
      {
        number: '01',
        title: 'Open Account',
        description: 'Quick 5-minute registration with minimal documents',
        icon: '📱',
        color: 'from-indigo-500 to-blue-600',
        href: '/login'
      },
      {
        number: '02',
        title: 'Complete Verification',
        description: 'Digital KYC and risk assessment in one step',
        icon: '✓',
        color: 'from-green-500 to-emerald-600',
        href: '/dashboard/home'
      },
      {
        number: '03',
        title: 'Invest & Grow',
        description: 'Start investing from ₹250 with expert guidance',
        icon: '💰',
        color: 'from-amber-500 to-orange-600',
        href: '/invest'
      }
    ],
    keyMetrics: {
      minInvestment: "₹250",
      accountFees: "Completely Free",
      onlineProcess: "100% Digital",
      supportRatio: "24/7 Chatbot"
    },
    keyMetricsList: [
      { value: "₹250", label: "Min Investment" },
      { value: "Completely Free", label: "Account Fees" },
      { value: "100% Digital", label: "Online Process" }
    ],
    benefits: [
      'AI-Powered Investment Recommendations',
      'Real-time Portfolio Monitoring',
      'Automated Rebalancing',
      'Tax Optimization Tools',
      'Zero Hidden Charges',
      'Instant Fund Transfers',
    ],
    ctaMessages: {
      stepsButtonText: "Start Smart Investing →",
      finalCtaTitle: "Let AI Power Your Investments",
      finalCtaDescription: "Experience automated wealth building with {siteName}. Technology, simplified.",
      getStartedBtn: "Get Started Free",
      consultationBtn: "Talk to AI Advisor",
    },
    fundCategoriesHeading: "Top Funds",
  },

  // 🏦 PARTNERSHIPS & FUNDS
  partners: {
    top: [],
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
      title: 'Large Cap Growth',
      funds: [
        'HDFC Large Cap Fund',
        'Axis Bluechip Fund',
        'Mirae Asset Large Cap Fund',
        'ICICI Prudential Bluechip Fund',
        'SBI Bluechip Fund',
      ],
    },
    {
      title: 'Balanced Returns',
      funds: [
        'HDFC Balanced Advantage Fund',
        'ICICI Prudential Balanced Advantage',
        'Axis Balanced Advantage Fund',
        'Parag Parikh Long Term Equity Fund',
        'Aditya Birla Sun Life Balanced Advantage',
      ],
    },
    {
      title: 'Income & Safety',
      funds: [
        'ICICI Prudential Banking & PSU Debt',
        'HDFC Income Fund',
        'Axis Dynamic Bond Fund',
        'IDFC Dynamic Bond Fund',
        'UTI Dynamic Bond Fund',
      ],
    },
    {
      title: 'Long Term Wealth',
      funds: [
        'Canara Robeco Equity Tax Saver',
        'Axis Long Term Equity Fund',
        'HDFC Long Term Advantage Fund',
        'Kotak Tax Saver Fund',
        'DSP Tax Saver Fund',
      ],
    },
  ],

  // 📺 MEDIA PAGE
  media: {
    hero: {
      heading: "AI Insights & Market Analysis",
      subtitle: "Data-driven perspectives and intelligent market insights from our AI research team",
    },
  },

  // 📝 BLOG PAGE
  blog: {
    hero: {
      heading: "Blog",
      subtitle: "AI-powered insights, market analysis, and smart investing strategies",
    },
  },

  // 🏢 OFFERINGS PAGE
  offerings: {
    hero: {
      heading: "Smart Investment Solutions Powered by AI",
      subtitle: "Intelligent recommendations and automated portfolio management tailored to your financial goals",
    },
    services: [
      {
        title: 'AI-Powered Insights',
        icon: '🤖',
        features: ['Data-driven fund selection', 'Smart rebalancing', 'Real-time market analysis']
      },
      {
        title: 'Diverse Investments',
        icon: '📈',
        features: ['Mutual Funds & ETFs', 'International exposure', 'Direct equities']
      },
      {
        title: 'Zero-Fee Investing',
        icon: '💰',
        features: ['No advisory charges', 'No hidden costs', 'Transparent pricing']
      }
    ],
    description: "Leverage artificial intelligence to make smarter investment decisions. Our AI algorithms analyze market trends, optimize your portfolio, and help you achieve your financial goals with minimal fees and maximum transparency.",
    ourOfferingsHeading: "Our Smart Investment Solutions",
    whyChooseHeading: "Why Choose {siteName}?",
  },

  // 📄 ABOUT US PAGE
  aboutUs: {
    // Hero Section Content
    hero: {
      title: "About",
      introductionParagraphs: [
        {
          text: "is an AI-powered investment platform designed to democratize wealth creation for every Indian. Born from the vision of making sophisticated investing accessible to everyone, we combine cutting-edge artificial intelligence with financial expertise to deliver intelligent, fee-free investment solutions.",
        },
        {
          text: "Our proprietary AI engine analyzes thousands of data points, market trends, and economic indicators to provide personalized investment recommendations. Whether you're a first-time investor or a seasoned trader, we eliminate complexity and bias, helping you build wealth with confidence and clarity.",
        },
      ],
      secondTitle: "Technology Meets Finance",
      portfolioText:
        "believes that financial markets shouldn't be complicated. Every investor deserves access to institutional-quality portfolio management without the institutional price tag. Our automated systems continuously monitor, rebalance, and optimize your portfolio based on real-time market data and your personal financial goals.\nWe've removed the middleman, slashed the fees, and put intelligent technology at your fingertips. The result? Smarter investing, faster wealth growth, and complete peace of mind.",
    },

    // Mission & Vision Section
    missionVision: {
      mission: {
        icon: "🎯",
        title: "Our Mission",
        description:
          "To revolutionize investing in India by harnessing AI and technology to provide intelligent, affordable, and accessible wealth management for every individual.",
        points: [
          "Remove complexity from investing through AI",
          "Deliver institutional-quality portfolios at zero cost",
          "Empower every Indian to build generational wealth",
        ],
      },
      vision: {
        icon: "👁️",
        title: "Our Vision",
        description:
          "To become India's most trusted AI-powered investment platform, enabling 1 million Indians to grow their wealth smartly and achieve their financial dreams by 2028.",
        points: [
          "Lead the fintech revolution in India",
          "Transform millions into confident investors",
          "Create generational wealth through automation and intelligence",
        ],
      },
    },

    // CEO Section
    ceoSection: {
      title: "Founder's Vision:",
      subtitle: "How AI is Reshaping Wealth Creation in India:",
      beliefs: [
        {
          title: "Democratize Financial Intelligence:",
          description:
            "For decades, sophisticated portfolio management was reserved for the wealthy. We believe every individual deserves access to world-class investment strategies. Our AI levels the playing field, providing personalized recommendations that rival top financial advisors.",
        },
        {
          title: "Eliminate Human Bias:",
          description:
            "Emotions drive poor investment decisions. Fear and greed lead to panic selling and euphoric buying. Our AI removes emotions from investing, making decisions based purely on data, logic, and your financial goals.",
        },
        {
          title: "Transparency is Non-Negotiable:",
          description:
            "Every recommendation, every rebalance, every charge – we show you everything. Zero hidden fees, zero hidden agendas. You know exactly why your money is invested the way it is, and you can change it anytime.",
        },
        {
          title: "Time is Your Most Valuable Asset:",
          description:
            "Instead of spending hours researching funds or analyzing portfolios, let our AI work 24/7 for you. Invest once, let technology handle the rest. Monitor your growth, celebrate your wins, and focus on what matters in life.",
        },
        {
          title: "Start Small, Dream Big:",
          description:
            "You don't need ₹1 lakh to begin investing. With AutoWealth, start from just ₹250. Every rupee grows through the power of compound returns and intelligent portfolio management. Your journey to wealth begins today.",
        },
      ],
    },

    // Values Section
    values: {
      title: "Our Core Values",
      items: [
        {
          icon: "🤖",
          title: "AI-First Approach",
          description: "Technology drives every decision, delivering unbiased, data-driven investment strategies",
        },
        {
          icon: "🔓",
          title: "Complete Transparency",
          description: "Full visibility into fees, strategies, and recommendations with no hidden charges",
        },
        {
          icon: "🚀",
          title: "Constant Innovation",
          description: "We continuously improve our AI algorithms to adapt to market changes and investor needs",
        },
      ],
    },
  },
};

export default siteConfig;