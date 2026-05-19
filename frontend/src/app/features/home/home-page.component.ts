import { CommonModule } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnDestroy, OnInit, QueryList, ViewChild, ViewChildren, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { Router, RouterLink } from '@angular/router';
import { Subscription, finalize } from 'rxjs';
import { API_BASE_URL } from '../../core/config/api.config';
import { MemoryDetail, MemoryImage, MemorySummary } from '../../core/models/memory.models';
import { AuthService } from '../../core/services/auth.service';
import { AiAction, AiService } from '../../core/services/ai.service';
import { MemoriesService } from '../../core/services/memories.service';

@Component({
  selector: 'app-home-page',
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './home-page.component.html',
  styleUrl: './home-page.component.css',
})
export class HomePageComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChildren('memoryCard') private memoryCards!: QueryList<ElementRef<HTMLElement>>;
  @ViewChild('bentoGrid') private bentoGrid!: ElementRef<HTMLElement>;

  private readonly defaultMoodColor = '#ffffff';
  private readonly masonryRowHeight = 4;
  private readonly masonryGap = 14;
  private readonly authService = inject(AuthService);
  private readonly memoriesService = inject(MemoriesService);
  private readonly aiService = inject(AiService);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly apiOrigin = API_BASE_URL.replace('/api/v1', '');
  private cardsChangesSub?: Subscription;
  private masonryObserver: ResizeObserver | null = null;
  private isMasonryScheduled = false;

  readonly user = this.authService.user;

  memories: MemorySummary[] = [];
  availableTags: string[] = [];

  showIntro = true;
  cardsEntering = false;

  isLoading = false;
  isLoggingOut = false;
  isCapturing = false;
  isSavingMemory = false;
  isDeletingMemory = false;
  errorMessage: string | null = null;

  toasts: { id: number; message: string; type: 'success' | 'error' }[] = [];
  private toastIdCounter = 0;
  isDeleteConfirmOpen = false;
  private pendingDeleteId: number | null = null;
  readonly skeletonItems = new Array(6).fill(0);

  search = '';
  selectedTag = '';
  fromDate = '';
  toDate = '';
  sortBy = 'date_desc';

  get hasActiveFilters(): boolean {
    return !!(this.search || this.selectedTag || this.fromDate || this.toDate);
  }

  isFormOpen = false;
  isEditMode = false;
  editingMemoryId: number | null = null;
  selectedFiles: File[] = [];
  existingImages: MemoryImage[] = [];
  removeImageIds: number[] = [];

  isDetailOpen = false;
  detailMemory: MemoryDetail | null = null;
  detailEmbedSafeUrl: SafeResourceUrl | null = null;
  isImagePreviewOpen = false;
  imagePreviewUrl: string | null = null;

  tagsArray: string[] = [];
  tagInputValue = '';

  isAiLoading = false;
  aiSuggestion: string | null = null;
  aiSuggestionTarget: AiAction | null = null;

  readonly memoryForm = this.fb.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(150)]],
    content: ['', [Validators.required]],
    memoryDate: ['', [Validators.required]],
    moodColor: [this.defaultMoodColor],
    spotifyUrl: [''],
  });

  ngOnInit(): void {
    this.reloadAll();
    setTimeout(() => (this.showIntro = false), 1600);
    setTimeout(() => (this.cardsEntering = true), 1800);
    setTimeout(() => (this.cardsEntering = false), 3600);
  }

  ngAfterViewInit(): void {
    this.cardsChangesSub = this.memoryCards.changes.subscribe(() => {
      this.attachMasonryObserver();
      this.scheduleMasonryLayout();
    });

    this.attachMasonryObserver();
    this.scheduleMasonryLayout();
  }

  ngOnDestroy(): void {
    this.cardsChangesSub?.unsubscribe();
    this.masonryObserver?.disconnect();
    this.masonryObserver = null;
  }

  reloadAll(): void {
    this.loadMemories();
    this.loadTags();
  }

  loadMemories(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.memoriesService
      .list({
        search: this.search.trim() || undefined,
        tag: this.selectedTag || undefined,
        from: this.fromDate || undefined,
        to: this.toDate || undefined,
      })
      .pipe(finalize(() => (this.isLoading = false)))
      .subscribe({
        next: (memories) => {
          this.memories = this.sortMemories(memories);
          this.scheduleMasonryLayout();
        },
        error: (error) => {
          this.errorMessage = this.formatApiError(error, 'No se pudieron cargar los recuerdos.');
        },
      });
  }

  loadTags(): void {
    this.memoriesService.listTags().subscribe({
      next: (tags) => {
        this.availableTags = tags;
      },
    });
  }

  applyFilters(): void {
    this.loadMemories();
  }

  clearFilters(): void {
    this.search = '';
    this.selectedTag = '';
    this.fromDate = '';
    this.toDate = '';
    this.sortBy = 'date_desc';
    this.loadMemories();
  }

  onSortChange(value: string): void {
    this.sortBy = value;
    this.memories = this.sortMemories([...this.memories]);
    this.scheduleMasonryLayout();
  }

  requestAiAssist(action: AiAction): void {
    const raw = this.memoryForm.getRawValue();
    this.isAiLoading = true;
    this.aiSuggestion = null;
    this.aiSuggestionTarget = null;

    this.aiService
      .assist({ action, title: raw.title.trim(), content: raw.content.trim() })
      .pipe(finalize(() => (this.isAiLoading = false)))
      .subscribe({
        next: (res) => {
          this.aiSuggestion = res.suggestion;
          this.aiSuggestionTarget = action;
        },
        error: (error) => {
          this.showToast(this.formatApiError(error, 'El asistente de IA no está disponible.'), 'error');
        },
      });
  }

  acceptAiSuggestion(): void {
    if (!this.aiSuggestion || !this.aiSuggestionTarget) return;

    if (this.aiSuggestionTarget === 'improve_text') {
      this.memoryForm.controls.content.setValue(this.aiSuggestion);
    } else if (this.aiSuggestionTarget === 'improve_title') {
      this.memoryForm.controls.title.setValue(this.aiSuggestion);
    } else if (this.aiSuggestionTarget === 'suggest_tags') {
      const incoming = this.aiSuggestion
        .split(',')
        .map((t) => t.trim().toLowerCase())
        .filter((t) => t.length > 0 && !this.tagsArray.includes(t));
      this.tagsArray = [...this.tagsArray, ...incoming];
    }

    this.aiSuggestion = null;
    this.aiSuggestionTarget = null;
  }

  discardAiSuggestion(): void {
    this.aiSuggestion = null;
    this.aiSuggestionTarget = null;
  }

  openCreateForm(): void {
    this.isFormOpen = true;
    this.isEditMode = false;
    this.editingMemoryId = null;
    this.selectedFiles = [];
    this.existingImages = [];
    this.removeImageIds = [];
    this.errorMessage = null;
    this.aiSuggestion = null;
    this.aiSuggestionTarget = null;

    this.tagsArray = [];
    this.tagInputValue = '';
    this.memoryForm.reset({
      title: '',
      content: '',
      memoryDate: this.today(),
      moodColor: this.defaultMoodColor,
      spotifyUrl: '',
    });
  }

  openEditForm(event: Event, memoryId: number): void {
    event.stopPropagation();
    this.errorMessage = null;
    this.isFormOpen = true;
    this.isEditMode = true;
    this.editingMemoryId = memoryId;
    this.selectedFiles = [];
    this.removeImageIds = [];

    this.memoriesService.get(memoryId).subscribe({
      next: (memory) => {
        this.existingImages = memory.images;
        this.tagsArray = [...memory.tags];
        this.tagInputValue = '';
        this.memoryForm.reset({
          title: memory.title,
          content: memory.content,
          memoryDate: memory.memoryDate,
          moodColor: memory.moodColor ?? this.defaultMoodColor,
          spotifyUrl: memory.spotify.spotifyUrl ?? '',
        });
      },
      error: (error) => {
        this.errorMessage = this.formatApiError(error, 'No se pudo cargar el recuerdo para editar.');
        this.closeForm();
      },
    });
  }

  closeForm(): void {
    this.isFormOpen = false;
    this.isEditMode = false;
    this.editingMemoryId = null;
    this.selectedFiles = [];
    this.existingImages = [];
    this.removeImageIds = [];
    this.aiSuggestion = null;
    this.aiSuggestionTarget = null;
  }

  onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (!files) {
      this.selectedFiles = [];
      return;
    }

    this.selectedFiles = Array.from(files);
  }

  toggleRemoveExistingImage(imageId: number): void {
    if (this.removeImageIds.includes(imageId)) {
      this.removeImageIds = this.removeImageIds.filter((id) => id !== imageId);
      return;
    }

    this.removeImageIds = [...this.removeImageIds, imageId];
  }

  submitMemory(): void {
    if (this.memoryForm.invalid) {
      this.memoryForm.markAllAsTouched();
      return;
    }

    this.isSavingMemory = true;
    this.errorMessage = null;

    const wasEditMode = this.isEditMode;
    const payload = this.buildFormDataPayload();
    const request$ =
      this.isEditMode && this.editingMemoryId !== null
        ? this.memoriesService.update(this.editingMemoryId, payload)
        : this.memoriesService.create(payload);

    request$.pipe(finalize(() => (this.isSavingMemory = false))).subscribe({
      next: () => {
        this.closeForm();
        this.showToast(wasEditMode ? 'Recuerdo actualizado' : 'Recuerdo guardado');
        this.reloadAll();
      },
      error: (error) => {
        this.errorMessage = this.formatApiError(error, 'No se pudo guardar el recuerdo.');
      },
    });
  }

  openDetail(memoryId: number): void {
    this.errorMessage = null;
    this.isDetailOpen = true;
    this.detailMemory = null;
    this.detailEmbedSafeUrl = null;

    this.memoriesService.get(memoryId).subscribe({
      next: (memory) => {
        this.detailMemory = memory;
        this.detailEmbedSafeUrl = this.toSafeEmbedUrl(memory.spotify.embedUrl);
      },
      error: (error) => {
        this.errorMessage = this.formatApiError(error, 'No se pudo cargar el detalle del recuerdo.');
        this.closeDetail();
      },
    });
  }

  closeDetail(): void {
    this.isDetailOpen = false;
    this.detailMemory = null;
    this.detailEmbedSafeUrl = null;
    this.closeImagePreview();
  }

  openImagePreview(url: string): void {
    const value = url.trim();
    if (!value) {
      return;
    }

    this.imagePreviewUrl = value;
    this.isImagePreviewOpen = true;
  }

  closeImagePreview(): void {
    this.isImagePreviewOpen = false;
    this.imagePreviewUrl = null;
  }

  openDeleteConfirm(event: Event, memoryId: number): void {
    event.stopPropagation();
    this.pendingDeleteId = memoryId;
    this.isDeleteConfirmOpen = true;
  }

  cancelDelete(): void {
    this.isDeleteConfirmOpen = false;
    this.pendingDeleteId = null;
  }

  confirmDelete(): void {
    if (this.pendingDeleteId === null) {
      return;
    }

    const memoryId = this.pendingDeleteId;
    this.isDeleteConfirmOpen = false;
    this.pendingDeleteId = null;

    this.isDeletingMemory = true;
    this.errorMessage = null;

    this.memoriesService
      .delete(memoryId)
      .pipe(finalize(() => (this.isDeletingMemory = false)))
      .subscribe({
        next: () => {
          if (this.detailMemory?.id === memoryId) {
            this.closeDetail();
          }
          this.showToast('Recuerdo eliminado');
          this.reloadAll();
        },
        error: (error) => {
          this.errorMessage = this.formatApiError(error, 'No se pudo eliminar el recuerdo.');
        },
      });
  }

  showToast(message: string, type: 'success' | 'error' = 'success'): void {
    const id = ++this.toastIdCounter;
    this.toasts = [...this.toasts, { id, message, type }];
    setTimeout(() => this.dismissToast(id), 3500);
  }

  dismissToast(id: number): void {
    this.toasts = this.toasts.filter((t) => t.id !== id);
  }

  async captureBoard(): Promise<void> {
    if (this.isCapturing || !this.bentoGrid) return;
    this.isCapturing = true;

    try {
      const domToImage = (await import('dom-to-image-more')).default;
      const dataUrl = await domToImage.toPng(this.bentoGrid.nativeElement, {
        bgcolor: '#ffffff',
        scale: 2,
        cacheBust: true,
      });

      const link = document.createElement('a');
      link.download = `remindme-mural-${new Date().toISOString().slice(0, 10)}.png`;
      link.href = dataUrl;
      link.click();
    } catch (err) {
      this.showToast('No se pudo capturar el mural.', 'error');
    } finally {
      this.isCapturing = false;
    }
  }

  logout(): void {
    this.errorMessage = null;
    this.isLoggingOut = true;

    this.authService
      .logout()
      .pipe(finalize(() => (this.isLoggingOut = false)))
      .subscribe({
        next: () => {
          this.router.navigate(['/login']);
        },
        error: (error) => {
          this.errorMessage = this.formatApiError(error, 'No se pudo cerrar sesión.');
        },
      });
  }

  imageUrl(path: string | null): string {
    if (!path) {
      return '';
    }

    return `${this.apiOrigin}${path}`;
  }

  cardTint(moodColor: string | null): string {
    return this.buildSolidGradient(moodColor, 0.62, 72);
  }

  detailCardTint(moodColor: string | null): string {
    return this.buildSolidGradient(moodColor, 0.5, 70);
  }

  get userInitials(): string {
    const name = this.user()?.name ?? '';
    return name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
  }

  get selectedMoodColor(): string {
    return this.normalizeSolidColor(this.memoryForm.controls.moodColor.value).toUpperCase();
  }

  isMarkedForRemoval(imageId: number): boolean {
    return this.removeImageIds.includes(imageId);
  }

  trackByMemoryId(_: number, memory: MemorySummary): number {
    return memory.id;
  }

  addTag(value: string): void {
    const tag = value.trim().toLowerCase();
    if (tag && !this.tagsArray.includes(tag)) {
      this.tagsArray = [...this.tagsArray, tag];
    }
    this.tagInputValue = '';
  }

  removeTag(tag: string): void {
    this.tagsArray = this.tagsArray.filter((t) => t !== tag);
  }

  onTagKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
      event.preventDefault();
      this.addTag(this.tagInputValue);
    }
    if (event.key === 'Backspace' && this.tagInputValue === '' && this.tagsArray.length > 0) {
      this.tagsArray = this.tagsArray.slice(0, -1);
    }
  }

  private sortMemories(memories: MemorySummary[]): MemorySummary[] {
    return memories.slice().sort((a, b) => {
      switch (this.sortBy) {
        case 'date_asc':  return a.memoryDate.localeCompare(b.memoryDate);
        case 'title_asc': return a.title.localeCompare(b.title);
        case 'title_desc': return b.title.localeCompare(a.title);
        default:          return b.memoryDate.localeCompare(a.memoryDate);
      }
    });
  }

  private buildFormDataPayload(): FormData {
    const raw = this.memoryForm.getRawValue();
    const payload = new FormData();

    payload.append('title', raw.title.trim());
    payload.append('content', raw.content.trim());
    payload.append('memory_date', raw.memoryDate);
    payload.append('mood_color', raw.moodColor.trim());
    payload.append('spotify_url', raw.spotifyUrl.trim());
    payload.append('tags', this.tagsArray.join(', '));

    this.selectedFiles.forEach((file) => {
      payload.append('images[]', file);
    });

    if (this.removeImageIds.length > 0) {
      payload.append('remove_image_ids', JSON.stringify(this.removeImageIds));
    }

    return payload;
  }

  private formatApiError(error: any, fallback: string): string {
    const errors = error?.error?.errors;
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors)[0];
      if (typeof first === 'string' && first.trim() !== '') {
        return first;
      }
    }

    const message = error?.error?.message;
    if (typeof message === 'string' && message.trim() !== '') {
      return message;
    }

    return fallback;
  }

  private today(): string {
    return new Date().toISOString().slice(0, 10);
  }

  private toSafeEmbedUrl(url: string | null): SafeResourceUrl | null {
    const value = (url ?? '').trim();
    if (!value) {
      return null;
    }

    return this.sanitizer.bypassSecurityTrustResourceUrl(value);
  }

  private normalizeSolidColor(moodColor: string | null): string {
    const raw = (moodColor ?? '').trim();
    const normalized = raw.replace('#', '');

    if (/^[0-9a-fA-F]{6}$/.test(normalized)) {
      return `#${normalized}`;
    }

    return this.defaultMoodColor;
  }

  private buildSolidGradient(moodColor: string | null, whiteMix: number, whiteStopPercent: number): string {
    const base = this.normalizeSolidColor(moodColor);
    const soft = this.blendHexWithWhite(base, whiteMix);

    return `linear-gradient(165deg, ${soft} 0%, #ffffff ${whiteStopPercent}%)`;
  }

  private blendHexWithWhite(hex: string, whiteMix: number): string {
    const normalized = hex.replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
      return '#ffffff';
    }

    const ratio = Math.min(1, Math.max(0, whiteMix));
    const r = Number.parseInt(normalized.slice(0, 2), 16);
    const g = Number.parseInt(normalized.slice(2, 4), 16);
    const b = Number.parseInt(normalized.slice(4, 6), 16);

    const mixedR = Math.round(r * (1 - ratio) + 255 * ratio);
    const mixedG = Math.round(g * (1 - ratio) + 255 * ratio);
    const mixedB = Math.round(b * (1 - ratio) + 255 * ratio);

    return `#${this.toHex(mixedR)}${this.toHex(mixedG)}${this.toHex(mixedB)}`;
  }

  private toHex(value: number): string {
    return value.toString(16).padStart(2, '0');
  }

  private attachMasonryObserver(): void {
    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    this.masonryObserver?.disconnect();
    this.masonryObserver = new ResizeObserver(() => {
      this.scheduleMasonryLayout();
    });

    this.memoryCards.forEach((cardRef) => {
      this.masonryObserver?.observe(cardRef.nativeElement);
    });
  }

  private scheduleMasonryLayout(): void {
    if (this.isMasonryScheduled) {
      return;
    }

    this.isMasonryScheduled = true;

    const runLayout = () => {
      this.isMasonryScheduled = false;
      this.applyMasonrySpans();
    };

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(() => runLayout());
      return;
    }

    setTimeout(runLayout, 0);
  }

  private applyMasonrySpans(): void {
    if (!this.memoryCards || this.memoryCards.length === 0) {
      return;
    }

    this.memoryCards.forEach((cardRef) => {
      const card = cardRef.nativeElement;
      const height = card.getBoundingClientRect().height;
      const span = Math.max(1, Math.ceil((height + this.masonryGap) / (this.masonryRowHeight + this.masonryGap)));

      card.style.setProperty('--row-span', String(span));
    });
  }
}





