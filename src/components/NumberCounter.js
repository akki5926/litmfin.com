'use client';

import { useEffect, useState } from 'react';

export default function NumberCounter({ targetValue, suffix = '', prefix = '', duration = 2000, className = '' }) {
  const [displayValue, setDisplayValue] = useState(0);

  useEffect(() => {
    let startTime;
    let animationFrameId;

    // Check if this is a rating (contains /)
    if (targetValue.toString().includes('/')) {
      // Extract decimal number and base from rating like "9.8/10" or "4.8/5"
      const ratingMatch = targetValue.toString().match(/(\d+\.?\d*)\/(\d+)/);
      const targetRating = parseFloat(ratingMatch ? ratingMatch[1] : '0');
      const ratingBase = ratingMatch ? ratingMatch[2] : '10';

      const animate = (currentTime) => {
        if (!startTime) startTime = currentTime;
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function for smooth animation
        const easeOutQuad = 1 - Math.pow(1 - progress, 2);
        const currentValue = (targetRating * easeOutQuad).toFixed(1);

        setDisplayValue(`${currentValue}/${ratingBase}`);

        if (progress < 1) {
          animationFrameId = requestAnimationFrame(animate);
        }
      };

      animationFrameId = requestAnimationFrame(animate);
      return () => cancelAnimationFrame(animationFrameId);
    }

    // For other numbers
    const numericValue = parseInt(targetValue.toString().replace(/[^\d]/g, '')) || 0;
    
    if (numericValue === 0) {
      setDisplayValue(targetValue);
      return;
    }

    const animate = (currentTime) => {
      if (!startTime) startTime = currentTime;
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);

      // Easing function for smooth animation
      const easeOutQuad = 1 - Math.pow(1 - progress, 2);
      const currentValue = Math.floor(numericValue * easeOutQuad);

      if (targetValue.includes('Cr')) {
        setDisplayValue(`₹${currentValue}Cr+`);
      } else if (targetValue.includes('+')) {
        setDisplayValue(`${currentValue}+`);
      } else {
        setDisplayValue(`${currentValue}${suffix}`);
      }

      if (progress < 1) {
        animationFrameId = requestAnimationFrame(animate);
      }
    };

    animationFrameId = requestAnimationFrame(animate);

    return () => cancelAnimationFrame(animationFrameId);
  }, [targetValue, duration, suffix]);

  return <span className={className}>{displayValue}</span>;
}
