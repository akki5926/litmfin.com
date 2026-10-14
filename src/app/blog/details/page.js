// src/app/blog/details/page.js
'use client';

import { useState, useEffect, useCallback, Suspense } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import Image from 'next/image';
import Link from 'next/link';

function BlogDetailContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const [blog, setBlog] = useState(null);
  const [latestBlogs, setLatestBlogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [imageError, setImageError] = useState(false);
  const [latestImageErrors, setLatestImageErrors] = useState({});

  const blogId = searchParams?.get('id');

  const fetchBlogDetail = useCallback(async () => {
    try {
      setLoading(true);
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL}/blog_api.php?action=single&id=${blogId}`);
      const data = await response.json();
      
      if (data.success) {
        setBlog(data.data);
      } else {
        setError(data.error || 'Failed to fetch blog details');
      }
    } catch (err) {
      setError('Network error: ' + err.message);
    } finally {
      setLoading(false);
    }
  }, [blogId]);

  const fetchLatestBlogs = useCallback(async () => {
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL}/blog_api.php?action=latest&limit=3`);
      const data = await response.json();
      
      if (data.success) {
        // Filter out current blog if it appears in latest
        const filtered = (data.data || []).filter(b => b.id != blogId);
        setLatestBlogs(filtered.slice(0, 3));
      }
    } catch (err) {
      console.error('Failed to fetch latest blogs:', err);
    }
  }, [blogId]);

  useEffect(() => {
    if (blogId) {
      fetchBlogDetail();
      fetchLatestBlogs();
    } else {
      setError('No blog ID specified');
      setLoading(false);
    }
  }, [blogId, fetchBlogDetail, fetchLatestBlogs]);

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

  const formatShortDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  // Function to render HTML content safely
  const createMarkup = (htmlContent) => {
    return { __html: htmlContent };
  };

  const handleLatestImageError = (blogId) => {
    setLatestImageErrors(prev => ({ ...prev, [blogId]: true }));
  };

  const goBack = () => {
    // Store back info in sessionStorage before going back (matching blog/page.js expectations)
    const backInfo = {
      fromBlogId: blogId,
      timestamp: Date.now(),
      shouldScrollToTop: true
    };
    sessionStorage.setItem('blogBackInfo', JSON.stringify(backInfo));
    router.back();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="mx-auto px-4 py-20" style={{ width: '80%', maxWidth: '1200px' }}>
          <div className="text-center">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900"></div>
            <p className="mt-4 text-gray-500">Loading article...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-white">
        <div className="mx-auto px-4 py-20" style={{ width: '80%', maxWidth: '1200px' }}>
          <div className="text-center">
            <p className="text-red-600 mb-4">Error: {error}</p>
            <button 
              onClick={() => router.push('/blog')} 
              className="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors"
            >
              Back to All Blogs
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!blog) {
    return (
      <div className="min-h-screen bg-white">
        <div className="mx-auto px-4 py-20" style={{ width: '80%', maxWidth: '1200px' }}>
          <div className="text-center">
            <p className="text-gray-500 mb-4">Blog post not found.</p>
            <Link 
              href="/blog" 
              className="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors"
            >
              Back to All Blogs
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Back Button - Top of page */}
      <div className="sticky top-0 bg-white/80 backdrop-blur-sm z-10 border-b border-gray-100">
        <div className="mx-auto px-4 py-4" style={{ width: '80%', maxWidth: '1200px' }}>
          <button
            onClick={goBack}
            className="inline-flex items-center gap-2 text-gray-600 hover:text-blue-900 transition-colors text-sm font-medium group"
          >
            <svg 
              className="w-4 h-4 transition-transform group-hover:-translate-x-1" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>
        </div>
      </div>

      {/* Hero Section - Title and meta - 80% width */}
      <div className="mx-auto px-4 pt-12 pb-8" style={{ width: '80%', maxWidth: '1200px' }}>
        <div className="mb-6 flex items-center gap-3 text-sm">
          <span className="text-gray-400">{formatDate(blog.date)}</span>
          <span className="text-gray-300">•</span>
          <span className="text-blue-700 font-medium">{blog.topic || "Insights"}</span>
        </div>
        
        <h1 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
          {blog.title}
        </h1>
      </div>

      {/* Featured Image - Full width */}
      <div className="w-full my-8">
        <div className="relative w-full" style={{ width: '100%', maxWidth: '100%', height: '500px' }}>
          {blog.image_url && !imageError ? (
            <Image
              src={blog.image_url}
              alt={blog.title}
              fill
              sizes="100vw"
              className="object-cover"
              onError={() => setImageError(true)}
              priority
              style={{ objectFit: 'cover' }}
            />
          ) : (
            <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-blue-50 to-gray-100">
              <span style={{ fontSize: '80px' }}>{getCategoryIcon(blog.topic)}</span>
            </div>
          )}
        </div>
      </div>

      {/* Description and Content - 80% width */}
      <div className="mx-auto px-4" style={{ width: '80%', maxWidth: '1200px' }}>
        {/* Description as pull-quote after image */}
        <div className="my-10">
          <p className="text-xl text-gray-500 leading-relaxed border-l-4 border-blue-200 pl-6 italic">
            {blog.description}
          </p>
        </div>

        {/* Blog Content - Clean typography, spacious layout */}
        <article className="py-8">
          <div 
            className="prose prose-lg prose-gray max-w-none
              prose-headings:text-gray-900 prose-headings:font-bold prose-headings:tracking-tight
              prose-h1:text-4xl prose-h1:mt-12 prose-h1:mb-6
              prose-h2:text-2xl prose-h2:mt-10 prose-h2:mb-4 prose-h2:font-semibold
              prose-h3:text-xl prose-h3:mt-8 prose-h3:mb-3 prose-h3:text-gray-800
              prose-p:text-gray-600 prose-p:leading-relaxed prose-p:mb-6 prose-p:text-lg
              prose-a:text-blue-700 prose-a:no-underline hover:prose-a:underline
              prose-strong:text-gray-900 prose-strong:font-semibold
              prose-em:text-gray-500
              prose-ul:list-disc prose-ul:pl-6 prose-ul:mb-6
              prose-ol:list-decimal prose-ol:pl-6 prose-ol:mb-6
              prose-li:text-gray-600 prose-li:mb-2 prose-li:text-lg
              prose-blockquote:border-l-4 prose-blockquote:border-blue-300 prose-blockquote:pl-6 prose-blockquote:italic prose-blockquote:text-gray-500 prose-blockquote:font-light prose-blockquote:text-lg
              prose-img:rounded-xl prose-img:shadow-md prose-img:my-8
              prose-hr:my-12 prose-hr:border-gray-100
            "
            // dangerouslySetInnerHTML={createMarkup(blog.content || blog.body || '<p>Content coming soon...</p>')}
          />
        </article>
      </div>

      {/* Latest Blogs Section - Full width background, content 80% */}
      {latestBlogs.length > 0 && (
        <div className="bg-gray-50 mt-20 py-16">
          <div className="mx-auto px-4" style={{ width: '80%', maxWidth: '1200px' }}>
            <div className="text-center mb-12">
              <h2 className="text-3xl font-bold text-gray-900 mb-3">Latest Blogs</h2>
              <p className="text-gray-500">Continue your reading journey with our recent posts</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {latestBlogs.map((latestBlog) => (
                <article
                  key={latestBlog.id}
                  onClick={() => router.push(`/blog/details?id=${latestBlog.id}`)}
                  className="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col group cursor-pointer"
                >
                  {/* Image Container */}
                  <div className="relative w-full overflow-hidden bg-gradient-to-br from-gray-100 to-gray-50" style={{ height: '200px' }}>
                    {latestBlog.image_url && !latestImageErrors[latestBlog.id] ? (
                      <Image
                        src={latestBlog.image_url}
                        alt={latestBlog.title || 'Blog post image'}
                        fill
                        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                        className="object-cover transition-transform duration-500 group-hover:scale-110"
                        onError={() => handleLatestImageError(latestBlog.id)}
                      />
                    ) : (
                      <div className="absolute inset-0 flex items-center justify-center">
                        <span style={{ fontSize: '48px' }}>{getCategoryIcon(latestBlog.topic)}</span>
                      </div>
                    )}
                  </div>

                  <div className="p-5">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded">
                        {latestBlog.topic || "General"}
                      </span>
                      <span className="text-xs text-gray-400">
                        {formatShortDate(latestBlog.date)}
                      </span>
                    </div>

                    <h3 className="text-lg font-bold text-gray-800 mb-2 line-clamp-2 group-hover:text-blue-600 transition-colors">
                      {latestBlog.title}
                    </h3>

                    <p className="text-gray-500 text-sm line-clamp-2">
                      {latestBlog.description}
                    </p>

                    <div className="mt-3 inline-flex items-center gap-1 text-sm text-blue-600 font-medium">
                      Read more 
                      <span className="group-hover:translate-x-1 transition-transform">→</span>
                    </div>
                  </div>
                </article>
              ))}
            </div>

            {/* View All Blogs Button */}
            <div className="text-center mt-12">
              <Link 
                href="/blog"
                className="inline-flex items-center gap-2 px-6 py-3 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors font-medium"
              >
                View All Blogs
                <span>→</span>
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Simple Footer */}
      <div className="border-t border-gray-100">
        <div className="mx-auto px-4 py-12" style={{ width: '80%', maxWidth: '1200px' }}>
          <div className="flex justify-center items-center">
            <button
              onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
              className="text-gray-400 hover:text-blue-900 transition-colors text-sm"
            >
              {/* Back to top ↑ */}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function BlogDetailPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-white">
        <div className="mx-auto px-4 py-20" style={{ width: '80%', maxWidth: '1200px' }}>
          <div className="text-center">
            <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900"></div>
            <p className="mt-4 text-gray-500">Loading article...</p>
          </div>
        </div>
      </div>
    }>
      <BlogDetailContent />
    </Suspense>
  );
}