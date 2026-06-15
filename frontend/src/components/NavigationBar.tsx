// F:\WebDevelopment\Mytherra\frontend\src\components\NavigationBar.tsx
import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';

const NavigationBar: React.FC = () => {
  const location = useLocation();
  const { isAdmin } = useAuth();
  const baseTabs = [
    { id: 'events', label: 'Events', icon: '📜', path: '/' },
    { id: 'world', label: 'World Map', icon: '🗺️', path: '/world-map' },
    { id: 'heroes', label: 'Heroes', icon: '⚔️', path: '/heroes' },
    { id: 'artifacts', label: 'Artifacts', icon: '◆', path: '/artifacts' },
    { id: 'weather', label: 'Weather', icon: '☁', path: '/weather' },
    { id: 'omens', label: 'Omens', icon: '◷', path: '/omens' },
    { id: 'magic', label: 'Magic', icon: '✦', path: '/magic' },
    { id: 'myths', label: 'Myths', icon: '✧', path: '/myths' },
    { id: 'civilization', label: 'Civilization', icon: '◇', path: '/civilization' },
    { id: 'pantheon', label: 'Pantheon', icon: '♛', path: '/pantheon' },
    { id: 'betting', label: 'Divine Betting', icon: '⚡', path: '/betting' },
    { id: 'eras', label: 'Eras', icon: '⏳', path: '/eras' },
    { id: 'dashboard', label: 'Statistics', icon: '📊', path: '/dashboard' },
  ] as const;
  const tabs = isAdmin()
    ? [...baseTabs, { id: 'admin', label: 'Admin Tools', icon: '⚙', path: '/admin/world-editor' }]
    : baseTabs;

  const getActiveTab = (): string => {
    if (location.pathname.includes('/heroes')) return 'heroes';
    if (location.pathname.includes('/artifacts')) return 'artifacts';
    if (location.pathname.includes('/weather')) return 'weather';
    if (location.pathname.includes('/omens')) return 'omens';
    if (location.pathname.includes('/magic')) return 'magic';
    if (location.pathname.includes('/myths')) return 'myths';
    if (location.pathname.includes('/civilization')) return 'civilization';
    if (location.pathname.includes('/pantheon')) return 'pantheon';
    if (location.pathname.includes('/betting')) return 'betting';
    if (location.pathname.includes('/eras')) return 'eras';
    if (location.pathname.includes('/admin')) return 'admin';
    if (location.pathname.includes('/world-map')) return 'world';
    if (location.pathname.includes('/dashboard')) return 'dashboard';
    return 'events';
  };

  const activeTab = getActiveTab();

  return (
    <nav className="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
      <div className="container mx-auto px-4 py-3">
        <div className="flex justify-center space-x-1 md:space-x-4">
          {tabs.map(tab => (
            <Link
              key={tab.id}
              to={tab.path}
              className={`px-3 md:px-4 py-2 rounded-lg font-medium transition-all duration-200 flex items-center ${
                activeTab === tab.id
                  ? 'bg-yellow-500 text-gray-900 shadow-lg scale-105'
                  : 'bg-gray-700 text-gray-200 hover:bg-gray-600 hover:scale-102'
              }`}
            >
              <span className="mr-1 md:mr-2 text-lg">{tab.icon}</span>
              <span className="hidden sm:inline text-sm md:text-base">{tab.label}</span>
              <span className="sm:hidden text-xs">{tab.label.split(' ')[0]}</span>
            </Link>
          ))}
        </div>
      </div>
    </nav>
  );
};

export default NavigationBar;
