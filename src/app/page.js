import Banner from '@/components/Banner';
import Testimonials from '@/components/Testimonials';
import Partners from '@/components/Partners';
import Benefits from '@/components/Benefits';
import FundCategories from '@/components/FundCategories';
import TypedText from '@/components/TypedText';
import NumberCounter from '@/components/NumberCounter';
import siteConfig from "@/config/siteConfig";
import Link from 'next/link';

export default function Home() {
  return (
    <div className="overflow-hidden">
      {/* Hero Section */}
      <section 
        className="relative py-12 md:py-20 px-4 bg-cover bg-center bg-no-repeat overflow-hidden"
        style={{
          backgroundImage: siteConfig.homepage.hero.bannerImage 
            ? `url(${siteConfig.homepage.hero.bannerImage})` 
            : undefined
        }}
      >
        {/* Overlay when background image is present */}
        {siteConfig.homepage.hero.bannerImage ? (
          <div className="absolute inset-0 bg-gradient-to-b from-slate-950/40 via-slate-950/20 to-slate-950/50"></div>
        ) : (
          <div className="absolute inset-0 bg-gradient-to-b from-gray-50 to-white"></div>
        )}

        <div className="relative container mx-auto max-w-5xl text-center z-10">
          {/* Trusted by badge */}
          <div className="mb-4">
            <span className={`inline-block px-6 py-2 rounded-full text-sm font-semibold border ${
              siteConfig.homepage.hero.bannerImage 
                ? "bg-black/30 text-blue-200 border-white/30 backdrop-blur-sm shadow-md" 
                : "bg-blue-50 text-blue-700 border-blue-100"
            }`}>
              {siteConfig.homepage.heroStats.trustedInvestorsText}
            </span>
          </div>

          {/* Main heading */}
          <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold mb-4">
            <span className={siteConfig.homepage.hero.bannerImage ? "text-white [text-shadow:_0_2px_8px_rgba(0,0,0,0.9)]" : "text-gray-900"}>
              {siteConfig.homepage.hero.heading}
            </span>
            <span className="block mt-2">
              <TypedText 
                texts={[
                  "Multiple Assets",
                  "Risk profile and Research-Based",
                  "Goal-based approach",
                  "Long-Term Thought Process"
                ]}
                className={siteConfig.homepage.hero.bannerImage ? "text-yellow-300 [text-shadow:_0_2px_8px_rgba(0,0,0,0.9)]" : "text-blue-600"}
              />
            </span>
          </h1>

          {/* Description */}
          <p className={`text-lg md:text-xl max-w-4xl mx-auto mb-6 leading-relaxed animate-fade-in ${
            siteConfig.homepage.hero.bannerImage ? "text-white font-medium [text-shadow:_0_1px_4px_rgba(0,0,0,0.9)]" : "text-gray-600"
          }`}>
            {siteConfig.homepage.hero.description}
          </p>

          {/* Stats Section */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 max-w-4xl mx-auto mt-12">
            <div className={`text-center animate-fade-in hover:shadow-lg hover:-translate-y-2 transition-all duration-300 p-4 rounded-xl ${
              siteConfig.homepage.hero.bannerImage
                ? "bg-slate-900/40 backdrop-blur-sm border border-white/20 text-white hover:bg-slate-900/60 shadow-lg"
                : "p-4 rounded-lg"
            }`}>
              <div className={`text-3xl md:text-4xl font-bold mb-1 ${
                siteConfig.homepage.hero.bannerImage ? "text-yellow-300 [text-shadow:_0_2px_4px_rgba(0,0,0,0.8)]" : "text-blue-700"
              }`}>
                <NumberCounter targetValue={siteConfig.homepage.heroStats.assetsManaged} duration={2500} className="" />
              </div>
              <div className={siteConfig.homepage.hero.bannerImage ? "text-gray-200 font-medium text-sm [text-shadow:_0_1px_2px_rgba(0,0,0,0.8)]" : "text-gray-600 font-medium text-sm"}>
                Assets Managed
              </div>
            </div>

            <div className={`text-center animate-fade-in hover:shadow-lg hover:-translate-y-2 transition-all duration-300 p-4 rounded-xl ${
              siteConfig.homepage.hero.bannerImage
                ? "bg-slate-900/40 backdrop-blur-sm border border-white/20 text-white hover:bg-slate-900/60 shadow-lg"
                : "p-4 rounded-lg"
            }`} style={{ animationDelay: '0.1s' }}>
              <div className={`text-3xl md:text-4xl font-bold mb-1 ${
                siteConfig.homepage.hero.bannerImage ? "text-yellow-300 [text-shadow:_0_2px_4px_rgba(0,0,0,0.8)]" : "text-blue-700"
              }`}>
                <NumberCounter targetValue={siteConfig.homepage.heroStats.happyInvestorsCount} duration={2000} className="" />
              </div>
              <div className={siteConfig.homepage.hero.bannerImage ? "text-gray-200 font-medium text-sm [text-shadow:_0_1px_2px_rgba(0,0,0,0.8)]" : "text-gray-600 font-medium text-sm"}>
                {siteConfig.homepage.heroStats.happyInvestorsText ?? "Happy Families"}
              </div>
            </div>

            <div className={`text-center animate-fade-in hover:shadow-lg hover:-translate-y-2 transition-all duration-300 p-4 rounded-xl ${
              siteConfig.homepage.hero.bannerImage
                ? "bg-slate-900/40 backdrop-blur-sm border border-white/20 text-white hover:bg-slate-900/60 shadow-lg"
                : "p-4 rounded-lg"
            }`} style={{ animationDelay: '0.2s' }}>
              <div className={`text-3xl md:text-4xl font-bold mb-1 ${
                siteConfig.homepage.hero.bannerImage ? "text-yellow-300 [text-shadow:_0_2px_4px_rgba(0,0,0,0.8)]" : "text-blue-700"
              }`}>
                <NumberCounter targetValue={siteConfig.homepage.heroStats.customerRating} duration={2000} className="" />
              </div>
              <div className={siteConfig.homepage.hero.bannerImage ? "text-gray-200 font-medium text-sm [text-shadow:_0_1px_2px_rgba(0,0,0,0.8)]" : "text-gray-600 font-medium text-sm"}>
                {siteConfig.homepage.heroStats.customerRatingText}
              </div>
            </div>

            <div className={`text-center animate-fade-in hover:shadow-lg hover:-translate-y-2 transition-all duration-300 p-4 rounded-xl ${
              siteConfig.homepage.hero.bannerImage
                ? "bg-slate-900/40 backdrop-blur-sm border border-white/20 text-white hover:bg-slate-900/60 shadow-lg"
                : "p-4 rounded-lg"
            }`} style={{ animationDelay: '0.3s' }}>
              <div className={`text-3xl md:text-4xl font-bold mb-1 ${
                siteConfig.homepage.hero.bannerImage ? "text-yellow-300 [text-shadow:_0_2px_4px_rgba(0,0,0,0.8)]" : "text-blue-700"
              }`}>
                {siteConfig.homepage.heroStats.supportAvailability}
              </div>
              <div className={siteConfig.homepage.hero.bannerImage ? "text-gray-200 font-medium text-sm [text-shadow:_0_1px_2px_rgba(0,0,0,0.8)]" : "text-gray-600 font-medium text-sm"}>
                Support
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 3 Easy Steps Section */}
      <section className="py-8 md:py-12 px-4 relative">
        <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-50"></div>
        <div className="container mx-auto px-4 max-w-7xl">
          <div className="text-center mb-8">
            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 mb-2">
              GET STARTED
            </span>
            <h2 className="text-3xl md:text-4xl font-bold mb-4 bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">
              Start Your Financial Planning Journey in <span className="text-blue-700">3 Easy Steps</span>
            </h2>
            <p className="text-gray-600 max-w-2xl mx-auto">
              {siteConfig.homepage.stepsSection.description.replace('{siteName}', siteConfig.siteName)}
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {siteConfig.homepage.investmentSteps.map((step, index) => (
              <Link href={step.href} key={index} className="relative group animate-fade-in block" style={{ animationDelay: `${index * 0.15}s` }}>
                <div className="bg-white rounded-xl shadow-lg p-5 border border-blue-100 hover:border-blue-300 transition-all duration-500 hover:scale-105 hover:shadow-2xl cursor-pointer">
                  <div className="absolute -top-3 -left-3 w-14 h-14 bg-gradient-to-br from-blue-100 to-white rounded-2xl flex items-center justify-center shadow-lg animate-float">
                    <span className="text-2xl">{step.icon}</span>
                  </div>
                  <div className="mt-6">
                    <div className={`text-4xl font-bold mb-2 ${index === 0 ? 'text-blue-600' : index === 1 ? 'text-emerald-600' : 'text-purple-600'}`}>
                      {step.number}
                    </div>
                    <h3 className="text-xl font-bold mb-2 text-gray-800 hover:text-blue-700 transition-colors">{step.title}</h3>
                    <p className="text-gray-600 text-sm">{step.description}</p>
                  </div>
                  <div className="absolute bottom-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div className="w-7 h-7 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                      <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                    </div>
                  </div>
                </div>
              </Link>
            ))}
          </div>

          {/* CTA Button */}
          <div className="text-center mt-8 animate-fade-in" style={{ animationDelay: '0.4s' }}>
            <Link href="/login">
              <button className="bg-gradient-to-r from-blue-700 to-blue-900 text-white px-10 py-3 rounded-lg font-bold text-lg hover:from-blue-800 hover:to-blue-950 transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 transform">
                {siteConfig.homepage.ctaMessages.stepsButtonText}
              </button>
            </Link>
          </div>
        </div>
      </section>

      <div className="h-px bg-gradient-to-r from-transparent via-blue-300 to-transparent my-8"></div>

      <Testimonials />

      <div className="h-px bg-gradient-to-r from-transparent via-blue-300 to-transparent my-8"></div>

      <Partners />

      <div className="h-px bg-gradient-to-r from-transparent via-blue-300 to-transparent my-8"></div>

      <Benefits />

      <div className="h-px bg-gradient-to-r from-transparent via-blue-300 to-transparent my-8"></div>

      <FundCategories />

      {/* Final CTA Section */}
      <section className="relative overflow-hidden bg-gradient-to-br from-blue-900 to-blue-800 text-white py-12 px-4">
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0" style={{
            backgroundImage: `radial-gradient(circle at 1px 1px, white 1px, transparent 0)`,
            backgroundSize: '40px 40px'
          }}></div>
        </div>

        <div className="relative container mx-auto px-4 max-w-7xl">
          <div className="py-12">
            <div className="max-w-4xl mx-auto text-center">
              <h2 className="text-3xl md:text-4xl font-bold mb-4">
                {siteConfig.homepage.ctaMessages.finalCtaTitle}
              </h2>
              <p className="text-lg text-blue-100 mb-6 max-w-2xl mx-auto">
                {siteConfig.homepage.ctaMessages.finalCtaDescription.replace('{siteName}', siteConfig.siteName)}
              </p>

              <div className="flex flex-col sm:flex-row gap-3 justify-center">
                <Link href="/login">
                  <button className="bg-white text-blue-900 px-8 py-3 rounded-xl font-bold text-lg hover:bg-blue-50 transition-all duration-300 shadow-xl hover:shadow-2xl">
                    {siteConfig.homepage.ctaMessages.getStartedBtn}
                  </button>
                </Link>
                <Link href="/contact">
                  <button className="border-2 border-white text-white px-8 py-3 rounded-xl font-bold text-lg hover:bg-white/10 transition-all duration-300">
                    {siteConfig.homepage.ctaMessages.consultationBtn}
                  </button>
                </Link>
              </div>

              <div className="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                {siteConfig.homepage.keyMetricsList.map((metric, index) => (
                  <div
                    key={index}
                    className="text-center bg-white/10 backdrop-blur-sm px-4 py-3.5 rounded-xl border border-white/20 hover:bg-white hover:scale-105 hover:shadow-2xl transition-all duration-300 transform cursor-pointer group"
                  >
                    <div className="text-blue-100 group-hover:text-blue-900 font-semibold text-base transition-colors duration-300">
                      {metric.label}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}