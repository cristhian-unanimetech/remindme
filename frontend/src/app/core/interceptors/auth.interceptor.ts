import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, switchMap, throwError } from 'rxjs';
import { API_BASE_URL, AUTH_BASE_URL } from '../config/api.config';
import { AuthService } from '../services/auth.service';

const SKIP_REFRESH_PATHS = ['/auth/login', '/auth/register', '/auth/refresh', '/auth/logout'];

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const authService = inject(AuthService);

  const isApiRequest = request.url.startsWith(API_BASE_URL);
  const isAuthRequest = request.url.startsWith(AUTH_BASE_URL);

  let outboundRequest = request;

  if (isAuthRequest) {
    outboundRequest = outboundRequest.clone({ withCredentials: true });
  }

  const accessToken = authService.getAccessToken();
  if (isApiRequest && accessToken !== null) {
    outboundRequest = outboundRequest.clone({
      setHeaders: { Authorization: `Bearer ${accessToken}` },
    });
  }

  return next(outboundRequest).pipe(
    catchError((error: HttpErrorResponse) => {
      if (!shouldAttemptRefresh(request.url, isApiRequest, error.status)) {
        return throwError(() => error);
      }

      return authService.refresh().pipe(
        switchMap((authResponse) => {
          const retriedRequest = request.clone({
            setHeaders: {
              Authorization: `Bearer ${authResponse.accessToken}`,
            },
          });

          return next(retriedRequest);
        }),
        catchError((refreshError) => {
          authService.clearSession();
          return throwError(() => refreshError);
        }),
      );
    }),
  );
};

function shouldAttemptRefresh(url: string, isApiRequest: boolean, status: number): boolean {
  if (!isApiRequest || status !== 401) {
    return false;
  }

  return SKIP_REFRESH_PATHS.every((path) => !url.endsWith(path));
}
