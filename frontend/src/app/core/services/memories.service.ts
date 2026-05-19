import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api.config';
import { MemoryDetail, MemorySummary } from '../models/memory.models';

interface ApiDataResponse<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class MemoriesService {
  private readonly http = inject(HttpClient);
  private readonly memoriesUrl = `${API_BASE_URL}/memories`;
  private readonly tagsUrl = `${API_BASE_URL}/tags`;

  list(filters: {
    search?: string;
    tag?: string;
    from?: string;
    to?: string;
  }): Observable<MemorySummary[]> {
    let params = new HttpParams();

    if (filters.search) {
      params = params.set('search', filters.search);
    }
    if (filters.tag) {
      params = params.set('tag', filters.tag);
    }
    if (filters.from) {
      params = params.set('from', filters.from);
    }
    if (filters.to) {
      params = params.set('to', filters.to);
    }

    return this.http
      .get<ApiDataResponse<MemorySummary[]>>(this.memoriesUrl, { params })
      .pipe(map((response) => response.data));
  }

  get(memoryId: number): Observable<MemoryDetail> {
    return this.http
      .get<ApiDataResponse<MemoryDetail>>(`${this.memoriesUrl}/${memoryId}`)
      .pipe(map((response) => response.data));
  }

  create(payload: FormData): Observable<MemoryDetail> {
    return this.http
      .post<ApiDataResponse<MemoryDetail>>(this.memoriesUrl, payload)
      .pipe(map((response) => response.data));
  }

  update(memoryId: number, payload: FormData): Observable<MemoryDetail> {
    return this.http
      .post<ApiDataResponse<MemoryDetail>>(`${this.memoriesUrl}/${memoryId}`, payload)
      .pipe(map((response) => response.data));
  }

  delete(memoryId: number): Observable<void> {
    return this.http.delete<{ message: string }>(`${this.memoriesUrl}/${memoryId}`).pipe(map(() => void 0));
  }

  listTags(): Observable<string[]> {
    return this.http.get<ApiDataResponse<string[]>>(this.tagsUrl).pipe(map((response) => response.data));
  }
}
