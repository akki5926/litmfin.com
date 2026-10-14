'use client';

import { useState, useRef, useEffect } from 'react';

export default function CustomSelect({ 
  name, 
  value, 
  onChange, 
  options = [], 
  placeholder = 'Select an option',
  disabled = false,
  className = '',
  required = false,
  ...props 
}) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef(null);

  // Get the label of selected option
  const selectedLabel = options.find(opt => opt.value === value)?.label || placeholder;

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  const handleSelect = (optionValue) => {
    onChange({
      target: {
        name,
        value: optionValue
      }
    });
    setIsOpen(false);
  };

  return (
    <div className="relative" ref={containerRef}>
      {/* Hidden native select for form submission */}
      <select
        name={name}
        value={value}
        onChange={onChange}
        className="hidden"
        required={required}
        {...props}
      >
        <option value="">{placeholder}</option>
        {options.map(opt => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>

      {/* Custom Select Button */}
      <button
        type="button"
        onClick={() => !disabled && setIsOpen(!isOpen)}
        disabled={disabled}
        className={`w-full px-4 py-2.5 border border-gray-300 rounded-lg text-left bg-white text-gray-700 flex justify-between items-center transition-all duration-200 hover:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
          disabled ? 'bg-gray-50 cursor-not-allowed opacity-50' : 'cursor-pointer hover:brightness-105'
        } ${className}`}
      >
        <span>{selectedLabel}</span>
        <svg 
          className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setIsOpen(false)} />
          <div className="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-300 rounded-lg shadow-2xl z-40 max-h-64 overflow-y-auto">
            {options.length > 0 ? (
              options.map(option => (
                <div
                  key={option.value}
                  className={`dropdown-item flex items-center px-4 py-3 text-sm transition-all duration-200 group rounded-lg mx-2 cursor-pointer ${
                    value === option.value
                      ? 'text-blue-700 font-semibold bg-blue-100'
                      : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'
                  }`}
                  onClick={() => handleSelect(option.value)}
                  onMouseDown={(e) => e.preventDefault()}
                  role="option"
                  aria-selected={value === option.value}
                  tabIndex="0"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      handleSelect(option.value);
                    }
                  }}
                >
                  <span className="flex-1">{option.label}</span>
                  {value === option.value && (
                    <svg className="w-5 h-5 text-blue-600 ml-2" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
                    </svg>
                  )}
                </div>
              ))
            ) : (
              <div className="px-4 py-3 text-gray-500 text-sm">No options available</div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
