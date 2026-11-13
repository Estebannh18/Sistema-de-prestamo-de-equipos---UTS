<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    public const ROLES = ['administrador', 'funcionario', 'docente', 'estudiante'];

    /**
     * Valores por defecto de un usuario nuevo, iguales a los de la base de datos.
     * Sin esto, un usuario recién creado no tendría "activo" hasta volver a leerse.
     */
    protected $attributes = [
        'activo' => true,
        'tipo_documento' => 'CC',
    ];

    /** Tipos de documento aceptados: código => nombre. */
    public const TIPOS_DOCUMENTO = [
        'CC' => 'Cédula de ciudadanía',
        'TI' => 'Tarjeta de identidad',
        'CE' => 'Cédula de extranjería',
        'PA' => 'Pasaporte',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'documento',
        'telefono',
        'tipo_documento',
        'programa_id',
        'dependencia',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'administrador';
    }

    public function esFuncionario(): bool
    {
        return $this->rol === 'funcionario';
    }

    public function esDocente(): bool
    {
        return $this->rol === 'docente';
    }

    /** Cuántos administradores con la cuenta activa hay: nunca debe llegar a cero. */
    public static function administradoresActivos(): int
    {
        return self::where('rol', 'administrador')->where('activo', true)->count();
    }

    /** Préstamos que pidió y siguen abiertos: pendientes o aprobados sin devolver. */
    public function tienePrestamosActivos(): bool
    {
        return $this->solicitudes()->whereIn('estado', ['pendiente', 'aprobado'])->exists();
    }

    public function nombreTipoDocumento(): string
    {
        return self::TIPOS_DOCUMENTO[$this->tipo_documento] ?? $this->tipo_documento;
    }

    public function esEstudiante(): bool
    {
        return $this->rol === 'estudiante';
    }

    /**
     * Módulos que ve cada rol en el menú: etiqueta => nombre de la ruta.
     *
     * @return array<string, string>
     */
    public function modulos(): array
    {
        return match ($this->rol) {
            'administrador' => [
                'Usuarios' => 'usuarios.index',
                'Categorías' => 'categorias.index',
                'Equipos y salones' => 'equipos.index',
                'Préstamos' => 'prestamos.index',
                'Reporte' => 'reportes.prestamos',
                '2FA' => 'seguridad.2fa',
            ],
            'funcionario' => [
                'Equipos y salones' => 'equipos.index',
                'Préstamos' => 'prestamos.index',
                'Reporte' => 'reportes.prestamos',
                '2FA' => 'seguridad.2fa',
            ],
            default => [
                'Solicitud' => 'prestamos.create',
                'Equipos y salones' => 'equipos.index',
                'Mis préstamos' => 'prestamos.index',
                '2FA' => 'seguridad.2fa',
            ],
        };
    }

    /** Programa académico (solo estudiantes y docentes). */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'solicitante_id');
    }

    public function prestamosAtendidos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'funcionario_id');
    }
}
