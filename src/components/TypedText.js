'use client';

import { useState, useEffect } from 'react';

export default function TypedText({ texts, text, className = '' }) {
  const [displayIndex, setDisplayIndex] = useState(0);
  const [charIndex, setCharIndex] = useState(0);
  
  const currentText = texts ? texts[displayIndex] : (text || '');
  
  useEffect(() => {
    if (charIndex < currentText.length) {
      const timer = setTimeout(() => {
        setCharIndex(charIndex + 1);
      }, 50);
      return () => clearTimeout(timer);
    } else if (texts && texts.length > 1) {
      // Wait 2 seconds then move to next text
      const timer = setTimeout(() => {
        setDisplayIndex((prev) => (prev + 1) % texts.length);
        setCharIndex(0);
      }, 2000);
      return () => clearTimeout(timer);
    }
  }, [charIndex, currentText, texts, displayIndex]);
  
  return (
    <span className={className}>
      {currentText.substring(0, charIndex)}
      {charIndex < currentText.length && (
        <span className="inline-block animate-pulse">|</span>
      )}
    </span>
  );
}