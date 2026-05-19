import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api.config';

export type AiAction = 'improve_text' | 'improve_title' | 'suggest_tags';

export interface AiAssistRequest {
  action: AiAction;
  content?: string;
  title?: string;
}

export interface AiAssistResponse {
  suggestion: string;
}

@Injectable({ providedIn: 'root' })
export class AiService {
  private readonly http = inject(HttpClient);
  private readonly url = `${API_BASE_URL}/ai/assist`;

  assist(payload: AiAssistRequest): Observable<AiAssistResponse> {
    return this.http.post<AiAssistResponse>(this.url, payload);
  }
}
