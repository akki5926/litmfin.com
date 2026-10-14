/* eslint-disable @next/next/no-img-element */
import siteConfig from "@/config/siteConfig";
import TypedText from '@/components/TypedText';

export default function AboutUs() {
  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      <div className="container mx-auto px-4 pt-0">
        
        {/* Original Content Section (Desktop View) - From Angular - NOW AS HERO */}
        <section className="hidden lg:block bg-[#FAFAFA] rounded-2xl overflow-hidden shadow-lg mt-0 mb-8">
          <div className="container-fluid p-4">
            <div className="relative pt-6 pb-12">
              <div className="row">
                <div className="col-lg-3 col-md-12 col-sm-12">&nbsp;</div>
                <div 
                  className="col-lg-6 col-md-12 col-sm-12 aos-init aos-animate"
                  data-aos="fade-up-right"
                  data-aos-duration="3000"
                  data-aos-once="true"
                >
                  <div className="absolute inset-0 flex items-center justify-center opacity-15">
                    <img 
                      src={siteConfig.branding.bgLogo} 
                      alt={siteConfig.siteName} 
                      className="w-96 h-96"
                    />
                  </div>
                </div>
                <div className="col-lg-3 col-md-12 col-sm-12">&nbsp;</div>
              </div>
              <div className="row">
                <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
                <div className="col-lg-8 col-md-12 col-sm-12">
                  <div className="row">
                    <div className="col-lg-12 col-md-12 col-sm-12">
                      <h5 className="text-4xl md:text-5xl text-[#04047e] text-center mb-8">
                        <TypedText text={`About ${siteConfig.domain?.replace('www.', '')}`} className="text-[#04047e]" />
                      </h5>
                    </div>
                    <div className="col-lg-12 col-md-12 col-sm-12">
                      {siteConfig.aboutUs.hero.introductionParagraphs.map((para, idx) => (
                        <p key={idx} className="text-sm md:text-base text-justify mb-6">
                          {para.text}
                        </p>
                      ))}
                      <h5 className="text-4xl md:text-5xl text-[#04047e] text-center mb-8">
                        {siteConfig.aboutUs.hero.secondTitle}
                      </h5>
                      <p className="text-sm md:text-base text-justify pt-5">
                        {siteConfig.siteName} {siteConfig.aboutUs.hero.portfolioText}
                      </p>
                    </div>
                  </div>
                </div>
                <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
              </div>
            </div>
          </div>
        </section>

        {/* Original Content Section (Mobile/Tablet View) - From Angular */}
        <section className="lg:hidden bg-[#FAFAFA] rounded-2xl overflow-hidden shadow-lg mt-0 mb-8">
          <div className="container-fluid p-4">
            <div className="relative pt-6 pb-12">
              <div className="row">
                <div className="col-lg-3 col-md-12 col-sm-12">&nbsp;</div>
                <div 
                  className="col-lg-6 col-md-12 col-sm-12 aos-init aos-animate"
                  data-aos="fade-up-right"
                  data-aos-duration="3000"
                  data-aos-once="true"
                >
                  <div className="absolute inset-0 flex items-center justify-center opacity-15">
                    <img 
                      src={siteConfig.branding.bgLogo} 
                      alt={siteConfig.siteName}
                      className="w-80 h-80"
                    />
                  </div>
                </div>
                <div className="col-lg-3 col-md-12 col-sm-12">&nbsp;</div>
              </div>
              <div className="row">
                <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
                <div className="col-lg-8 col-md-12 col-sm-12">
                  <div className="row">
                    <div className="col-lg-12 col-md-12 col-sm-12">
                      <h5 className="text-3xl md:text-4xl text-[#04047e] text-center mb-8">
                        About {siteConfig.domain?.replace('www.', '')}
                      </h5>
                    </div>
                    <div className="col-lg-12 col-md-12 col-sm-12">
                      {siteConfig.aboutUs.hero.introductionParagraphs.map((para, idx) => (
                        <p key={idx} className="text-sm md:text-base text-justify mx-5 mb-6">
                          {para.text}
                        </p>
                      ))}
                      <h5 className="text-3xl md:text-4xl text-[#04047e] text-center mb-8">
                        {siteConfig.aboutUs.hero.secondTitle}
                      </h5>
                      <p className="text-sm md:text-base text-justify pt-5 mx-5">
                        {siteConfig.siteName} {siteConfig.aboutUs.hero.portfolioText}
                      </p>
                    </div>
                  </div>
                </div>
                <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
              </div>
            </div>
          </div>
        </section>

        {/* Mission & Vision - Desktop only */}
        <div className="hidden lg:grid grid-cols-1 md:grid-cols-2 gap-12 mb-16">
          <div className="bg-white rounded-2xl shadow-xl p-8">
            <div className="text-4xl mb-6">{siteConfig.aboutUs.missionVision.mission.icon}</div>
            <h2 className="text-2xl font-bold text-blue-800 mb-4">{siteConfig.aboutUs.missionVision.mission.title}</h2>
            <p className="text-gray-700 mb-4">
              {siteConfig.aboutUs.missionVision.mission.description}
            </p>
            <ul className="space-y-2">
              {siteConfig.aboutUs.missionVision.mission.points.map((point, idx) => (
                <li key={idx} className="flex items-center">
                  <span className="text-blue-500 mr-3">•</span>
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>
          
          <div className="bg-gradient-to-br from-blue-900 to-blue-800 text-white rounded-2xl p-8">
            <div className="text-4xl mb-6">{siteConfig.aboutUs.missionVision.vision.icon}</div>
            <h2 className="text-2xl font-bold mb-4">{siteConfig.aboutUs.missionVision.vision.title}</h2>
            <p className="text-blue-100 mb-4">
              {siteConfig.aboutUs.missionVision.vision.description}
            </p>
            <ul className="space-y-2">
              {siteConfig.aboutUs.missionVision.vision.points.map((point, idx) => (
                <li key={idx} className="flex items-center">
                  <span className="text-blue-300 mr-3">•</span>
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* CEO Section from Angular (Mobile/Tablet View) */}
        <div className="container-fluid row lg:hidden m-0 bg-white rounded-2xl shadow-lg mb-12 p-6">
          <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
          <div className="col-lg-8 col-md-12 col-sm-12">
            <h3 className="text-2xl md:text-3xl font-bold text-[#04047e] mb-8">{siteConfig.aboutUs.ceoSection.title}</h3>
            <div className="row">
              <div className="col-lg-4 col-md-4 col-sm-12 mb-6">
                <img 
                  src={siteConfig.ceo.imageUrl} 
                  alt={siteConfig.ceo.name}
                  className="w-90 mx-auto block rounded-lg shadow-md"
                />
                <figcaption className="text-[#04047e] font-bold mt-4 text-center text-lg">
                  {siteConfig.ceo.name}
                </figcaption>
              </div>
              <div className="col-lg-8 col-md-8 col-sm-12">
                <p className="text-sm md:text-base text-justify mb-4">
                  <strong className="text-[#04047e]">{siteConfig.aboutUs.ceoSection.subtitle}</strong>
                  <br />
                </p>
                <ul className="pl-5 text-justify space-y-4 list-disc">
                  {siteConfig.aboutUs.ceoSection.beliefs.map((belief, idx) => (
                    <li key={idx} className="text-sm md:text-base">
                      <strong>{belief.title}</strong> {belief.description}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
          <div className="col-lg-2 col-md-12 col-sm-12">&nbsp;</div>
        </div>

        {/* CEO Section from Angular (Desktop View) */}
        <div className="hidden lg:block container-fluid m-0 bg-white rounded-2xl shadow-lg mb-12 p-12">
          <div className="max-w-7xl mx-auto">
            <h3 className="text-2xl md:text-3xl font-bold text-[#04047e] mb-8 text-center">{siteConfig.aboutUs.ceoSection.title}</h3>
            
            {/* Two-column layout */}
            <div className="flex flex-col lg:flex-row gap-8 items-start">
              
              {/* Left Column - Image */}
              <div className="lg:w-1/3 flex flex-col items-center">
                <div className="w-full max-w-xs">
                  <img 
                    src={siteConfig.ceo.imageUrl}
                    alt={siteConfig.ceo.name}
                    className="w-full rounded-lg shadow-md"
                  />
                  <figcaption className="text-[#04047e] font-bold mt-4 text-center text-lg">
                    {siteConfig.ceo.name}
                  </figcaption>
                </div>
              </div>
              
              {/* Right Column - Content */}
              <div className="lg:w-2/3">
                <p className="text-sm md:text-base text-justify mb-6">
                  <strong className="text-[#04047e] text-lg">{siteConfig.aboutUs.ceoSection.subtitle}</strong>
                </p>
                <ul className="space-y-4 text-justify">
                  {siteConfig.aboutUs.ceoSection.beliefs.map((belief, idx) => (
                    <li key={idx} className="text-sm md:text-base p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                      <strong className="text-blue-700">{belief.title}</strong> {belief.description}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </div>

        {/* Values - Desktop only */}
        <div className="hidden lg:block bg-gradient-to-r from-blue-100 to-blue-50 rounded-2xl p-8">
          <h2 className="text-3xl font-bold text-center text-blue-900 mb-8">{siteConfig.aboutUs.values.title}</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {siteConfig.aboutUs.values.items.map((value, idx) => (
              <div key={idx} className="text-center">
                <div className="text-4xl mb-4">{value.icon}</div>
                <h3 className="text-xl font-bold mb-3">{value.title}</h3>
                <p className="text-gray-700">{value.description}</p>
              </div>
            ))}
          </div>
        </div>

      </div>
    </div>
  );
}