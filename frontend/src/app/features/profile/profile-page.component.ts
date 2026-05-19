import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile-page',
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './profile-page.component.html',
  styleUrl: './profile-page.component.css',
})
export class ProfilePageComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  readonly user = this.authService.user;
  memoriesCount: number | null = null;
  isLoadingProfile = true;

  isSavingProfile = false;
  profileSuccess: string | null = null;
  profileError: string | null = null;

  isSavingPassword = false;
  passwordSuccess: string | null = null;
  passwordError: string | null = null;

  readonly profileForm = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(100)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(150)]],
  });

  readonly passwordForm = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(8)]],
    newPasswordConfirmation: ['', [Validators.required]],
  });

  ngOnInit(): void {
    this.authService
      .getProfile()
      .pipe(finalize(() => (this.isLoadingProfile = false)))
      .subscribe({
        next: (response) => {
          this.memoriesCount = response.memoriesCount;
          this.profileForm.patchValue({ name: response.user.name, email: response.user.email });
        },
        error: () => {
          const current = this.user();
          if (current) {
            this.profileForm.patchValue({ name: current.name, email: current.email });
          }
        },
      });
  }

  saveProfile(): void {
    if (this.profileForm.invalid) {
      this.profileForm.markAllAsTouched();
      return;
    }

    this.isSavingProfile = true;
    this.profileSuccess = null;
    this.profileError = null;

    const { name } = this.profileForm.getRawValue();

    this.authService
      .updateProfile({ name })
      .pipe(finalize(() => (this.isSavingProfile = false)))
      .subscribe({
        next: () => {
          this.profileSuccess = 'Perfil actualizado correctamente.';
        },
        error: (error) => {
          this.profileError = this.formatError(error, 'No se pudo actualizar el perfil.');
        },
      });
  }

  savePassword(): void {
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const raw = this.passwordForm.getRawValue();
    if (raw.newPassword !== raw.newPasswordConfirmation) {
      this.passwordError = 'Las contraseñas no coinciden.';
      return;
    }

    this.isSavingPassword = true;
    this.passwordSuccess = null;
    this.passwordError = null;

    this.authService
      .changePassword({
        current_password: raw.currentPassword,
        new_password: raw.newPassword,
        new_password_confirmation: raw.newPasswordConfirmation,
      })
      .pipe(finalize(() => (this.isSavingPassword = false)))
      .subscribe({
        next: () => {
          this.passwordSuccess = 'Contraseña actualizada correctamente.';
          this.passwordForm.reset();
        },
        error: (error) => {
          this.passwordError = this.formatError(error, 'No se pudo cambiar la contraseña.');
        },
      });
  }

  get userInitials(): string {
    const name = this.user()?.name ?? '';
    return name
      .split(' ')
      .map((w) => w[0])
      .join('')
      .slice(0, 2)
      .toUpperCase();
  }

  get memberSince(): string {
    const createdAt = this.user()?.createdAt;
    if (!createdAt) return '';
    return new Date(createdAt).toLocaleDateString('es-ES', { year: 'numeric', month: 'long' });
  }

  private formatError(error: unknown, fallback: string): string {
    const err = error as { error?: { errors?: Record<string, string>; message?: string } };
    const errors = err?.error?.errors;
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors)[0];
      if (typeof first === 'string') return first;
    }
    return err?.error?.message ?? fallback;
  }
}
