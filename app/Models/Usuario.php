<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario
{
    public const PERFIL_CADASTRADOR = 'cadastrador';
    public const PERFIL_GESTOR = 'gestor';
    public const PERFIL_ADMINISTRADOR = 'administrador';

    public const PERFIS = [
        self::PERFIL_CADASTRADOR,
        self::PERFIL_GESTOR,
        self::PERFIL_ADMINISTRADOR,
    ];
}
