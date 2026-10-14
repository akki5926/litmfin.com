// src/app/blog/page.js
'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Image from 'next/image';
import TypedText from '@/components/TypedText';
import siteConfig from '@/config/siteConfig';

export default function BlogPage() {
  const router = useRouter();
  const [blogs, setBlogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [imageErrors, setImageErrors] = useState({});
  const [generatingBlogId, setGeneratingBlogId] = useState(null);
  const [generatedFiles, setGeneratedFiles] = useState(new Set()); // Track generated files

  // Get PHP server base URL from environment variable
  const PHP_BASE_URL = process.env.NEXT_PUBLIC_PHP_BASE_URL || 'http://localhost/litmfin.com';

  const fetchBlogs = async () => {
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL}/blog_api.php?action=all`);
      const data = await response.json();
      
      if (data.success) {
        setBlogs(data.data || []);
      } else {
        setError(data.error || 'Failed to fetch blogs');
      }
    } catch (err) {
      setError('Network error: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBlogs();
  }, []);

  // Handle scroll to top when coming back from blog detail page
  useEffect(() => {
    // Function to handle scroll to top when coming from blog detail
    const scrollToTopIfNeeded = () => {
      const backInfo = sessionStorage.getItem('blogBackInfo');
      if (backInfo) {
        try {
          const info = JSON.parse(backInfo);
          // Check if this is a recent back navigation (within last 5 seconds)
          if (info.shouldScrollToTop && (Date.now() - info.timestamp) < 5000) {
            window.scrollTo({ top: 0, behavior: 'instant' });
            // Clear the flag after scrolling
            sessionStorage.removeItem('blogBackInfo');
          }
        } catch (e) {
          console.error('Error parsing backInfo:', e);
          sessionStorage.removeItem('blogBackInfo');
        }
      }
    };

    // Check immediately when component mounts
    scrollToTopIfNeeded();

    // Listen for popstate event (browser back/forward buttons)
    const handlePopState = () => {
      scrollToTopIfNeeded();
    };

    // Handle pageshow event for bfcache (when page is restored from back/forward cache)
    const handlePageShow = (event) => {
      if (event.persisted) {
        // Page was restored from bfcache, check again
        scrollToTopIfNeeded();
      }
    };

    window.addEventListener('popstate', handlePopState);
    window.addEventListener('pageshow', handlePageShow);
    
    return () => {
      window.removeEventListener('popstate', handlePopState);
      window.removeEventListener('pageshow', handlePageShow);
    };
  }, []);

  const getCategoryIcon = (category) => {
    const icons = {
      "Beginner Guides": "📘",
      "Fund Education": "🎓",
      "Goal Planning": "🎯",
      "Tax Planning": "💰",
      "Investment Strategy": "📈",
      "Advanced Learning": "🧠",
      "Advanced Funds": "⭐",
      "Retirement": "🏖️",
      "Fund Comparison": "⚖️",
      "Thematic Funds": "🌿",
      "Mutual Funds": "📊",
      "Investment": "💹",
      "Personal Finance": "👥",
      "Market Insights": "📰"
    };
    return icons[category] || "📝";
  };

  const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const handleReadMore = (blog) => {
    // Navigate to details page with id as query parameter
    router.push(`/blog/details?id=${blog.id}`);
  };

  const handleImageError = (blogId) => {
    setImageErrors(prev => ({ ...prev, [blogId]: true }));
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
        <div className="container mx-auto px-4 py-12">
          <div className="text-center">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900"></div>
            <p className="mt-4 text-gray-600">Loading articles...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
        <div className="container mx-auto px-4 py-12">
          <div className="text-center">
            <p className="text-red-600 mb-4">Error: {error}</p>
            <button 
              onClick={fetchBlogs} 
              className="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      <div className="container mx-auto px-4 py-12">
        {/* Hero Section */}
        <div className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-bold text-blue-900 mb-6">
            <TypedText text={siteConfig.blog.hero.heading} className="text-blue-900" />
          </h1>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            {siteConfig.blog.hero.subtitle}
          </p>
        </div>

        {/* Blog Grid */}
        {blogs.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-gray-500">No blog posts found.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {blogs.map((blog) => (
              <article
                key={blog.id}
                className="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col group cursor-pointer"
                onClick={() => handleReadMore(blog)}
              >
                {/* Fixed Image Container with Next.js Image */}
                <div className="relative w-full overflow-hidden bg-gradient-to-br from-blue-100 to-blue-50" style={{ height: '250px' }}>
                  {blog.image_url && !imageErrors[blog.id] ? (
                    <Image
                      src={blog.image_url}
                      alt={blog.title || 'Blog post image'}
                      fill
                      sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                      className="object-cover transition-transform duration-500 group-hover:scale-110"
                      onError={() => handleImageError(blog.id)}
                      priority={false}
                    />
                  ) : (
                    <div className="absolute inset-0 flex items-center justify-center">
                      <span style={{ fontSize: '64px' }}>{getCategoryIcon(blog.topic)}</span>
                    </div>
                  )}
                </div>

                <div className="p-6 flex flex-col flex-grow">
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">
                      {blog.topic || "General"}
                    </span>
                    <span className="text-xs text-gray-400">
                      {blog.read_time || "5 min read"}
                    </span>
                  </div>

                  <h3 className="text-xl font-bold text-blue-800 mb-2 line-clamp-2 group-hover:text-blue-600 transition-colors">
                    {blog.title}
                  </h3>

                  <p className="text-gray-600 text-sm mb-4 line-clamp-3 leading-relaxed">
                    {blog.description}
                  </p>

                  <div className="flex items-center justify-between pt-3 border-t border-gray-100 mt-auto">
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <span className="font-medium">{formatDate(blog.date)}</span>
                    </div>
                  </div>

                  {/* Read More Button */}
                  <div className="mt-4 inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors group/link">
                    Read more 
                    <span className="group-hover/link:translate-x-1 transition-transform">→</span>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}