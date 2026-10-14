'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import siteConfig from '@/config/siteConfig';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;
const FAQ_API_ENDPOINT = `${API_BASE_URL}/master_data.php`;

// Separate component for animated header to isolate re-renders
const AnimatedHeader = () => {
  const [text, setText] = useState('');
  const [index, setIndex] = useState(0);
  const fullText = "Frequently Asked Questions";

  useEffect(() => {
    if (index < fullText.length) {
      const timeout = setTimeout(() => {
        setText(prev => prev + fullText[index]);
        setIndex(prev => prev + 1);
      }, 50);
      return () => clearTimeout(timeout);
    }
  }, [index, fullText]);

  return (
    <div className="text-center mb-12">
      <h1 className="text-4xl font-bold text-gray-900 mb-4">
        {text || fullText}
      </h1>
      <p className="text-xl text-gray-600">
        Find answers to common questions about {siteConfig.siteName}, investing, accounts, and transactions
      </p>
    </div>
  );
};

// Extracted as a proper component — not a useMemo — to avoid stale closures and phantom re-mounts
const CategoryTabs = ({ categories, activeCategory, categoryLoading, onCategoryChange }) => {
  if (categories.length === 0) return null;

  return (
    <div className="flex flex-wrap justify-center gap-3 mb-8">
      {categories.map(category => (
        <button
          key={category.id}
          onClick={() => onCategoryChange(category.page)}
          disabled={categoryLoading}
          className={`px-6 py-3 rounded-lg font-medium transition-colors ${
            categoryLoading && activeCategory !== category.page ? 'opacity-50 cursor-not-allowed' : ''
          } ${
            activeCategory === category.page
              ? 'bg-blue-600 text-white'
              : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
          }`}
        >
          {category.name} ({category.count})
        </button>
      ))}
    </div>
  );
};

// Extracted as a proper component to prevent useMemo re-evaluation cascades
const FAQItems = ({ faqData, categoryLoading, openItems, onToggle, cleanHtmlToText }) => {
  if (categoryLoading) {
    return (
      <div className="text-center py-12">
        <div className="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
        <p className="mt-4 text-gray-600">Loading FAQs...</p>
      </div>
    );
  }

  if (faqData && faqData.length > 0) {
    return (
      <div className="space-y-4">
        {faqData.map((item, index) => (
          <div key={item.faq_id || index} className="bg-white rounded-xl shadow-lg overflow-hidden">
            <button
              onClick={() => onToggle(index)}
              className="w-full px-6 py-5 text-left flex justify-between items-center hover:bg-gray-50 transition-colors"
            >
              <span className="text-lg font-medium text-gray-900">
                {cleanHtmlToText(item.faq_question)}
              </span>
              <span className="text-gray-500 text-xl">
                {openItems.includes(index) ? '−' : '+'}
              </span>
            </button>

            {openItems.includes(index) && (
              <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div
                  className="text-gray-700 prose max-w-none"
                  dangerouslySetInnerHTML={{ __html: item.faq_ans }}
                />
              </div>
            )}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="text-center py-12">
      <p className="text-gray-500 text-lg">No FAQs found.</p>
    </div>
  );
};

// Extracted as a proper component — no longer a useMemo that re-evaluates on every parent render
const SupportSection = ({ onContactSupport, onWhatsApp }) => (
  <div className="mt-12 bg-gradient-to-r from-blue-500 to-blue-700 rounded-2xl p-8 text-white text-center">
    <h2 className="text-2xl font-bold mb-4">Still Have Questions?</h2>
    <p className="text-blue-100 mb-6">
      Can&apos;t find the answer you&apos;re looking for? Our support team is here to help.
    </p>
    <div className="flex justify-center gap-4">
      <button
        onClick={onContactSupport}
        className="px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
      >
        Contact Support
      </button>
      <button
        onClick={onWhatsApp}
        className="px-6 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white/10 transition-colors flex items-center gap-2 justify-center"
      >
        {/* <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12.032 12.031c.1-.1.232-.263.395-.49.163-.228.28-.406.352-.535.071-.129.107-.252.107-.37 0-.117-.036-.24-.107-.37-.072-.129-.189-.307-.352-.535-.163-.227-.295-.39-.395-.49-.1-.1-.223-.182-.37-.246-.146-.064-.303-.096-.47-.096-.168 0-.324.032-.47.096-.147.064-.27.146-.37.246-.1.1-.232.263-.395.49-.163.228-.28.406-.352.535-.072.129-.107.252-.107.37 0 .118.035.241.107.37.072.129.189.307.352.535.163.227.295.39.395.49.1.1.223.182.37.246.146.064.303.096.47.096.168 0 .324-.032.47-.096.147-.064.27-.146.37-.246.1-.1.232-.263.395-.49.163-.228.28-.406.352-.535.071-.129.107-.252.107-.37 0-.117-.036-.24-.107-.37-.072-.129-.189-.307-.352-.535-.163-.227-.295-.39-.395-.49-.1-.1-.223-.182-.37-.246-.146-.064-.303-.096-.47-.096-.168 0-.324.032-.47.096-.147.064-.27-.146-.37-.246z" />
        </svg> */}
        Live Chat
      </button>
    </div>
  </div>
);

export default function FAQPage() {
  const router = useRouter();
  const [activeCategory, setActiveCategory] = useState('general');
  const [openItems, setOpenItems] = useState([]);
  const [faqData, setFaqData] = useState([]);
  const [categories, setCategories] = useState([]);
  const [categoryLoading, setCategoryLoading] = useState(false);
  const [apiStatus, setApiStatus] = useState('checking');
  const [allFaqs, setAllFaqs] = useState([]);
  // null = not yet hydrated on client (prevents SSR/hydration flash)
  // true = hydrated, fetch in progress
  // false = fetch complete
  const [initialLoading, setInitialLoading] = useState(null);

  // Static derived value — useMemo is fine here
  const WHATSAPP_NUMBER = useMemo(() => {
    let phoneNumber = siteConfig.contact.phone || '';
    phoneNumber = phoneNumber.replace(/\D/g, '');
    if (phoneNumber.length === 10) {
      phoneNumber = '91' + phoneNumber;
    }
    return phoneNumber;
  }, []);

  const cleanHtmlToText = useCallback((html = '') => {
    if (typeof html !== 'string') return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
  }, []);

  useEffect(() => {
    fetchAllFAQs();
  }, []);

  const fetchAllFAQs = async () => {
    try {
      setInitialLoading(true); // now explicitly "loading" after hydration
      const response = await fetch(`${FAQ_API_ENDPOINT}?action=faqs`, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data) && result.data.length > 0) {
          const freshFaqs = result.data;
          setAllFaqs(freshFaqs);

          const pageCounts = {};
          freshFaqs.forEach(faq => {
            const page = faq.faq_page || 'general';
            pageCounts[page] = (pageCounts[page] || 0) + 1;
          });

          const extractedCategories = Object.keys(pageCounts).map(page => ({
            id: page,
            name: page.charAt(0).toUpperCase() + page.slice(1).replace(/_/g, ' '),
            page: page,
            count: pageCounts[page]
          }));

          setCategories(extractedCategories);

          if (extractedCategories.length > 0) {
            const firstPage = extractedCategories[0].page;
            setActiveCategory(firstPage);
            const firstCategoryFaqs = freshFaqs.filter(faq => (faq.faq_page || 'general') === firstPage);
            setFaqData(firstCategoryFaqs);
          }

          setApiStatus('online');
        } else {
          setApiStatus('offline');
        }
      } else {
        setApiStatus('offline');
      }
    } catch (error) {
      console.error('Failed to fetch FAQs:', error);
      setApiStatus('offline');
    } finally {
      setInitialLoading(false);
    }
  };

  const fetchFAQsByCategory = useCallback(async (page) => {
    const categoryFaqs = allFaqs.filter(faq => (faq.faq_page || 'general') === page);

    if (categoryFaqs.length > 0) {
      setFaqData(categoryFaqs);
      setCategoryLoading(false);
      return;
    }

    if (apiStatus === 'online') {
      try {
        const response = await fetch(`${FAQ_API_ENDPOINT}?action=faqs&page=${encodeURIComponent(page)}`, {
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        });

        if (response.ok) {
          const result = await response.json();
          if (result.success && result.data && result.data.length > 0) {
            setFaqData(result.data);
            setAllFaqs(prev => [...prev, ...result.data]);
          }
        }
      } catch (error) {
        console.error('Error fetching category FAQs:', error);
      } finally {
        setCategoryLoading(false);
      }
    } else {
      setCategoryLoading(false);
    }
  }, [allFaqs, apiStatus]);

  const handleCategoryChange = useCallback((page) => {
    if (page === activeCategory) return;

    setActiveCategory(page);
    setOpenItems([]);
    setCategoryLoading(true);

    const categoryFaqs = allFaqs.filter(faq => (faq.faq_page || 'general') === page);

    if (categoryFaqs.length > 0) {
      setFaqData(categoryFaqs);
      setCategoryLoading(false);
    } else {
      fetchFAQsByCategory(page);
    }
  }, [activeCategory, allFaqs, fetchFAQsByCategory]);

  const toggleItem = useCallback((index) => {
    setOpenItems(prev =>
      prev.includes(index)
        ? prev.filter(i => i !== index)
        : [...prev, index]
    );
  }, []);

  const openWhatsApp = useCallback(() => {
    const message = encodeURIComponent(`Hello! I need assistance with my ${siteConfig.siteName} account. Can you please help me?`);
    window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${message}&ll=en`, '_blank');
  }, [WHATSAPP_NUMBER]);

  const handleContactSupport = useCallback(() => {
    router.push('/contact');
  }, [router]);

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <AnimatedHeader />

        {/* null = not yet hydrated, true = fetching — both show spinner, nothing else renders */}
        {initialLoading !== false ? (
          <div className="text-center py-12">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
            <p className="mt-4 text-gray-600">Loading FAQs...</p>
          </div>
        ) : (
          // fade-in ensures even a 1-frame hydration blip is invisible
          <div style={{ animation: 'faqFadeIn 0.25s ease-in both' }}>
            <style>{`@keyframes faqFadeIn { from { opacity: 0; } to { opacity: 1; } }`}</style>

            <CategoryTabs
              categories={categories}
              activeCategory={activeCategory}
              categoryLoading={categoryLoading}
              onCategoryChange={handleCategoryChange}
            />

            {apiStatus !== 'online' && apiStatus !== 'checking' && (
              <div className="mb-6 p-4 rounded-lg bg-yellow-50 border border-yellow-200">
                <p className="font-medium text-yellow-700">
                  ⚠️ Unable to fetch latest data. Showing available content.
                </p>
              </div>
            )}

            <div className="min-h-[400px]">
              <FAQItems
                faqData={faqData}
                categoryLoading={categoryLoading}
                openItems={openItems}
                onToggle={toggleItem}
                cleanHtmlToText={cleanHtmlToText}
              />
            </div>

            <SupportSection
              onContactSupport={handleContactSupport}
              onWhatsApp={openWhatsApp}
            />
          </div>
        )}
      </div>
    </div>
  );
}