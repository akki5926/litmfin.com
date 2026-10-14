"use client";

import { useState, useEffect } from 'react';
import siteConfig from "@/config/siteConfig";

export default function Testimonials() {
  const [testimonials, setTestimonials] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTestimonials = async () => {
      try {
        const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/site/api';
        const res = await fetch(`${API_BASE_URL}/testimonials.php`);
        if (!res.ok) throw new Error('Network response was not ok');
        
        const json = await res.json();
        if (json.success && json.data.length > 0) {
          setTestimonials(json.data);
        } else {
          setFallbackTestimonials();
        }
      } catch (error) {
        console.error("Error fetching testimonials:", error);
        setFallbackTestimonials();
      } finally {
        setLoading(false);
      }
    };

    const setFallbackTestimonials = () => {
      setTestimonials([
        {
          name: 'Ashish',
          role: 'MNC professional, Bangalore',
          text: `${siteConfig.SITENAME_CAP} is an excellent platform that has solved the big issue of getting a proper asset allocation for my portfolio. I'm very satisfied with your investment services. Keep up the good work...`,
          image: null,
        },
        {
          name: 'Reshma',
          role: 'Data Analyst, Kochi',
          text: `I work hard to make money but I never invested properly. I have been wrongly given financial advice multiple times and I lost money. This is first time I am happy with an investment that happened through ${siteConfig.SITENAME_CAP}. I believe that team ${siteConfig.SITENAME_CAP} is honest with their advice and suggest option based on our needs.`,
          image: null,
        },
        {
          name: 'K. Bhagat',
          role: 'Bangalore',
          text: `The user interface is extremely simple and very user-friendly. Even for a beginner like me, it made quite easier to explore and invest. I liked most is your predefined recommended fund which helped me to select and invest based on my requirements and risk category.`,
          image: null,
        },
      ]);
    };

    fetchTestimonials();
  }, []);

  if (loading) {
    return (
      <section className="py-16 bg-gray-50 flex justify-center items-center">
        <div className="animate-pulse text-gray-500 font-medium">Loading testimonials...</div>
      </section>
    );
  }

  return (
    <section className="py-16 bg-gray-50">
      <div className="container mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-12 text-blue-600">{siteConfig.homepage.partnersSection.heading}</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {testimonials.map((testimonial, index) => (
            <div key={index} className="bg-white p-6 rounded-lg shadow-md">
              <p className="text-gray-600 italic mb-4">
                &quot;{testimonial.text
                  .replace(/{SITENAME_CAP}/g, siteConfig.SITENAME_CAP)
                  .replace(/{siteName}/g, siteConfig.siteName)}&quot;
              </p>
              
              {/* Author Info & Image */}
              <div className="flex items-center mt-6">
                {testimonial.image ? (
                  <img 
                    src={testimonial.image.startsWith('http') || testimonial.image.startsWith('data:')
                      ? testimonial.image 
                      : `${(process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/site/api').replace(/\/api\/?$/, '')}/uploads/testimonials/${testimonial.image}`} 
                    alt={testimonial.name} 
                    className="w-12 h-12 rounded-full object-cover mr-4 shadow-sm" 
                    onError={(e) => {
                      e.target.onerror = null;
                      e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(testimonial.name || 'U')}&background=DBEAFE&color=1D4ED8`;
                    }}
                  />
                ) : (
                  <div className="w-12 h-12 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center font-bold text-xl mr-4 flex-shrink-0 shadow-sm border border-blue-200">
                    {testimonial.name ? testimonial.name.charAt(0).toUpperCase() : 'U'}
                  </div>
                )}
                <div>
                  <h4 className="font-bold text-gray-900">{testimonial.name}</h4>
                  <p className="text-gray-500 text-sm">{testimonial.role}</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}