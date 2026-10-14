// src/app/media/page.js

'use client';

import { useState, useEffect } from 'react';
import Image from 'next/image';
import TypedText from '@/components/TypedText';
import siteConfig from '@/config/siteConfig';

export default function MediaPage() {
  const [mediaArticles, setMediaArticles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [failedImages, setFailedImages] = useState({});

  useEffect(() => {
    fetchMediaData();
  }, []);

  const fetchMediaData = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL}/media_api.php?action=all`);
      
      if (!response.ok) {
        throw new Error('Failed to fetch media data');
      }
      
      const result = await response.json();
      
      if (result.success && result.data) {
        const articles = result.data.data || [];
        setMediaArticles(articles);
      } else {
        setError(result.message || 'No media data found');
      }
    } catch (err) {
      console.error('Error fetching media data:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleImageError = (articleId) => {
    setFailedImages(prev => ({ ...prev, [articleId]: true }));
  };

  // Loading skeleton
  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
        <div className="container mx-auto px-4 py-12">
          <div className="text-center mb-12">
            <h1 className="text-4xl md:text-5xl font-bold text-blue-900 mb-6">
              <TypedText text={siteConfig.media.hero.heading} className="text-blue-900" />
            </h1>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              {siteConfig.media.hero.subtitle}
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <div key={i} className="bg-white rounded-xl shadow-lg overflow-hidden animate-pulse">
                <div className="h-48 bg-gray-200"></div>
                <div className="p-6">
                  <div className="h-4 bg-gray-200 rounded mb-2 w-24"></div>
                  <div className="h-6 bg-gray-200 rounded mb-2"></div>
                  <div className="h-4 bg-gray-200 rounded mb-1 w-full"></div>
                  <div className="h-4 bg-gray-200 rounded mb-1 w-5/6"></div>
                  <div className="h-4 bg-gray-200 rounded w-32"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
        <div className="container mx-auto px-4 py-12 text-center">
          <div className="text-red-600 mb-4">
            <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-xl font-semibold text-gray-800 mb-2">Unable to load media articles</h3>
          <p className="text-gray-600 mb-4">{error}</p>
          <button onClick={fetchMediaData} className="bg-blue-900 text-white px-6 py-2 rounded-lg">
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!mediaArticles || mediaArticles.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
        <div className="container mx-auto px-4 py-12 text-center">
          <h1 className="text-4xl font-bold text-blue-900 mb-6">Media</h1>
          <p className="text-gray-500 text-lg">No media articles found.</p>
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
            <TypedText text={siteConfig.media.hero.heading} className="text-blue-900" />
          </h1>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            {siteConfig.media.hero.subtitle}
          </p>
        </div>

        {/* Media Articles Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {mediaArticles.map((article) => {
            const hasImage = article.image_url && !failedImages[article.id];
            const displayIcon = article.default_icon || '📰';
            
            return (
              <article
                key={article.id}
                className="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col group"
              >
                {/* Fixed Image Container with Next.js Image */}
                <div className="relative w-full overflow-hidden bg-gradient-to-br from-blue-100 to-blue-50" style={{ height: '250px' }}>
                  {hasImage ? (
                    <Image
                      src={article.image_url}
                      alt={article.title || 'Media article'}
                      fill
                      sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
                      className="object-cover transition-transform duration-500 group-hover:scale-110"
                      onError={() => handleImageError(article.id)}
                      priority={false}
                    />
                  ) : (
                    <div className="absolute inset-0 flex items-center justify-center">
                      <span style={{ fontSize: '64px' }}>{displayIcon}</span>
                    </div>
                  )}
                  
                  {/* Category Badge */}
                  {article.category && (
                    <div className="absolute top-3 left-3 z-10">
                      <span className="text-xs font-semibold text-blue-600 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full shadow-sm">
                        {article.category}
                      </span>
                    </div>
                  )}
                </div>

                <div className="p-6 flex flex-col flex-grow">
                  {/* Source and Date */}
                  <div className="flex items-center justify-between mb-3">
                    {article.source && (
                      <span className="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-1 rounded">
                        {article.source}
                      </span>
                    )}
                    {article.formatted_date && (
                      <span className="text-xs text-gray-400">
                        {article.formatted_date}
                      </span>
                    )}
                  </div>

                  {/* Title */}
                  <h3 className="text-xl font-bold text-blue-800 mb-3 line-clamp-2">
                    <a
                      href={article.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="hover:text-blue-600 transition-colors"
                    >
                      {article.title}
                    </a>
                  </h3>

                  {/* Description */}
                  {article.description && (
                    <p className="text-gray-600 text-sm mb-4 line-clamp-3">
                      {article.description}
                    </p>
                  )}

                  {/* Read More Link */}
                  <div className="mt-auto pt-3 border-t border-gray-100">
                    <a
                      href={article.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors group/link"
                    >
                      Read full article
                      <svg className="w-4 h-4 transition-transform group-hover/link:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                    </a>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </div>
  );
}