import React from 'react';
import { useAuth } from '../contexts/useAuth';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requireAdmin?: boolean;
  fallback?: React.ReactNode;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children, requireAdmin = false, fallback }) => {
  const { isAuthenticated, isLoading, user, isAdmin, login, continueAsGuest } = useAuth();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-900">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
          <p className="text-gray-300">Loading...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    if (fallback) return <>{fallback}</>;
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-900 px-4">
        <div className="bg-gray-800 p-8 rounded-lg shadow-lg text-center max-w-md">
          <h2 className="text-2xl font-bold text-white mb-4">Enter Mytherra</h2>
          <p className="text-gray-300 mb-6">
            Start instantly as a guest or sign in with your WebHatchery account and link the session later.
          </p>
          <div className="grid gap-3">
            <button onClick={() => { void continueAsGuest(); }} className="bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold py-2 px-6 rounded transition duration-200">
              Continue as Guest
            </button>
            <button onClick={() => login()} className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition duration-200">
              Login with WebHatchery
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (requireAdmin && !isAdmin()) {
    if (fallback) return <>{fallback}</>;
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-900">
        <div className="bg-gray-800 p-8 rounded-lg shadow-lg text-center max-w-md">
          <h2 className="text-2xl font-bold text-white mb-4">Access Denied</h2>
          <p className="text-gray-300 mb-6">You need administrator privileges to access this area.</p>
          <p className="text-sm text-gray-400">Current role: {user.role}</p>
        </div>
      </div>
    );
  }

  return <>{children}</>;
};

export default ProtectedRoute;
