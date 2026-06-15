// src/App.tsx
import React from 'react';
import { BrowserRouter as Router, Route, Routes, Navigate } from 'react-router-dom';
import EventsPage from './pages/EventsPage';
import EventDetailPage from './pages/EventDetailPage';
import WorldMapPage from './pages/WorldMapPage';
import HeroesPage from './pages/HeroesPage';
import ArtifactsPage from './pages/ArtifactsPage';
import WeatherPage from './pages/WeatherPage';
import TemporalOmensPage from './pages/TemporalOmensPage';
import MagicDiscoveryPage from './pages/MagicDiscoveryPage';
import MythologyPage from './pages/MythologyPage';
import CivilizationPage from './pages/CivilizationPage';
import PantheonPage from './pages/PantheonPage';
import BettingPage from './pages/BettingPage';
import ErasPage from './pages/ErasPage';
import AdminWorldEditorPage from './pages/AdminWorldEditorPage';
import { Dashboard } from './pages/Dashboard';
import { RegionProvider } from './contexts/RegionProvider';
import { AuthProvider } from './contexts/AuthProvider';
import ProtectedRoute from './components/ProtectedRoute';

const App: React.FC = () => {
  const getBasename = () => {
    const basePath = import.meta.env.BASE_URL;
    if (basePath && basePath !== '/') {
      return basePath;
    }

    return '/';
  };

  return (
    <AuthProvider>
      <RegionProvider>
        <Router basename={getBasename()}>
          <Routes>
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <EventsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute>
                  <Dashboard />
                </ProtectedRoute>
              }
            />
            <Route
              path="/events"
              element={
                <ProtectedRoute>
                  <EventsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/events/:id"
              element={
                <ProtectedRoute>
                  <EventDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/world-map"
              element={
                <ProtectedRoute>
                  <WorldMapPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/heroes"
              element={
                <ProtectedRoute>
                  <HeroesPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/artifacts"
              element={
                <ProtectedRoute>
                  <ArtifactsPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/weather"
              element={
                <ProtectedRoute>
                  <WeatherPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/omens"
              element={
                <ProtectedRoute>
                  <TemporalOmensPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/magic"
              element={
                <ProtectedRoute>
                  <MagicDiscoveryPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/myths"
              element={
                <ProtectedRoute>
                  <MythologyPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/civilization"
              element={
                <ProtectedRoute>
                  <CivilizationPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/pantheon"
              element={
                <ProtectedRoute>
                  <PantheonPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/betting"
              element={
                <ProtectedRoute>
                  <BettingPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/eras"
              element={
                <ProtectedRoute>
                  <ErasPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/world-editor"
              element={
                <ProtectedRoute>
                  <AdminWorldEditorPage />
                </ProtectedRoute>
              }
            />
            <Route path="*" element={<Navigate to="/" />} />
          </Routes>
        </Router>
      </RegionProvider>
    </AuthProvider>
  );
};

export default App;
