export interface User {
  id: number | string;
  auth_user_id: number | string | null;
  email: string;
  username: string;
  display_name: string;
  divine_influence: number;
  divine_favor: number;
  betting_stats: Record<string, unknown>;
  game_preferences: Record<string, unknown>;
  role: string;
  roles?: string[];
  is_active: boolean;
  is_guest?: boolean;
  auth_type?: 'frontpage' | 'guest';
  guest_user_id?: string | null;
}

export interface AuthResponse {
  success: boolean;
  message: string;
  data?: {
    token: string;
    user: User;
  };
}

export interface LoginUrlResponse {
  success: boolean;
  data: {
    login_url: string;
  };
}

export interface RegisterUrlResponse {
  success: boolean;
  data: {
    register_url: string;
  };
}
