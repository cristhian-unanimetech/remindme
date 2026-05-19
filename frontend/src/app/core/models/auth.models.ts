export interface User {
  id: number;
  name: string;
  email: string;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface AuthResponse {
  user: User;
  accessToken: string;
  expiresIn: number;
}

export interface MeResponse {
  user: User;
}

export interface ProfileResponse {
  user: User;
  memoriesCount: number;
}

export interface UpdateProfileRequest {
  name: string;
}

export interface ChangePasswordRequest {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}
