'use client';

import { useState } from 'react';
import ProfileSidebar from './ProfileSidebar';

export default function ProfilePage() {
  const [activeTab, setActiveTab] = useState('account-status');
  
  const user = {
    name: 'John Doe',
    email: 'john.doe@example.com',
    // UCC: 'UCC123456',
  };

  const getInitials = () => {
    return user.name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase();
  };

  return (
    <div className="flex min-h-screen bg-gray-100">
      {/* Sidebar */}
      <div className="w-80">
        <ProfileSidebar 
          user={user}
          activeTab={activeTab}
          setActiveTab={setActiveTab}
          getInitials={getInitials}
        />
      </div>
      
      {/* Main Content Area */}
      <div className="flex-1 p-8">
        <div className="bg-white rounded-xl shadow p-6">
          <h1 className="text-2xl font-bold text-gray-900 mb-4">
            {activeTab.split('-').map(word => 
              word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ')}
          </h1>
          <p className="text-gray-600">
            Content for {activeTab} will appear here.
          </p>
        </div>
      </div>
    </div>
  );
}