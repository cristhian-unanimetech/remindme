import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { catchError, finalize, map, Observable, of, shareReplay, tap, throwError } from 'rxjs';
import { AUTH_BASE_URL } from '../config/api.config';
import { AuthResponse, ChangePasswordRequest, LoginRequest, MeResponse, ProfileResponse, RegisterRequest, UpdateProfileRequest, User } from '../models/auth.models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly accessTokenState = signal<string | null>(null);
  private readonly userState = signal<User | null>(null);

  private refreshRequest$: Observable<AuthResponse> | null = null;

  readonly user = computed(() => this.userState());
  readonly isAuthenticated = computed(
    () => this.accessTokenState() !== null && this.userState() !== null,
  );

  getAccessToken(): string | null {
    return this.accessTokenState();
  }

  register(payload: RegisterRequest): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${AUTH_BASE_URL}/register`, payload, { withCredentials: true })
      .pipe(tap((response) => this.applyAuthResponse(response)));
  }

  login(payload: LoginRequest): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${AUTH_BASE_URL}/login`, payload, { withCredentials: true })
      .pipe(tap((response) => this.applyAuthResponse(response)));
  }

  refresh(): Observable<AuthResponse> {
    if (this.refreshRequest$ !== null) {
      return this.refreshRequest$;
    }

    this.refreshRequest$ = this.http
      .post<AuthResponse>(`${AUTH_BASE_URL}/refresh`, {}, { withCredentials: true })
      .pipe(
        tap((response) => this.applyAuthResponse(response)),
        finalize(() => {
          this.refreshRequest$ = null;
        }),
        shareReplay(1),
      );

    return this.refreshRequest$;
  }

  me(): Observable<MeResponse> {
    return this.http.get<MeResponse>(`${AUTH_BASE_URL}/me`).pipe(
      tap((response) => {
        this.userState.set(response.user);
      }),
    );
  }

  logout(): Observable<void> {
    return this.http.post<{ message: string }>(`${AUTH_BASE_URL}/logout`, {}, { withCredentials: true }).pipe(
      map(() => void 0),
      tap(() => this.clearSession()),
      catchError((error) => {
        this.clearSession();
        return throwError(() => error);
      }),
    );
  }

  getProfile(): Observable<ProfileResponse> {
    return this.http.get<ProfileResponse>(`${AUTH_BASE_URL}/profile`);
  }

  updateProfile(payload: UpdateProfileRequest): Observable<{ user: User; message: string }> {
    return this.http
      .put<{ user: User; message: string }>(`${AUTH_BASE_URL}/profile`, payload)
      .pipe(tap((response) => this.userState.set(response.user)));
  }

  changePassword(payload: ChangePasswordRequest): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${AUTH_BASE_URL}/password`, payload);
  }

  ensureAuthenticated(): Observable<boolean> {
    if (this.isAuthenticated()) {
      return of(true);
    }

    return this.refresh().pipe(
      map(() => true),
      catchError(() => {
        this.clearSession();
        return of(false);
      }),
    );
  }

  bootstrapSession(): Observable<void> {
    return this.ensureAuthenticated().pipe(map(() => void 0));
  }

  clearSession(): void {
    this.accessTokenState.set(null);
    this.userState.set(null);
  }

  private applyAuthResponse(response: AuthResponse): void {
    this.accessTokenState.set(response.accessToken);
    this.userState.set(response.user);
  }
}
