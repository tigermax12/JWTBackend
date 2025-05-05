<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Peticione;
use App\Models\User;

class PeticionePolicy
{

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Peticione $peticion): bool
    {
        return $user->role_id === 1 || ($user->role_id === 2 && $user->id === $peticion->user_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Peticione $peticion): bool
    {
        return $user->role_id === 1 || ($user->role_id === 2 && $user->id === $peticion->user_id);
    }
    public function firmar(User $user, Peticione $peticion)
    {
        // Verificar si el usuario ya ha firmado la petición
        return !$peticion->firmas()->where('user_id', $user->id)->exists();
    }

    public function cambiarEstado(User $user, Peticione $peticione): bool
    {
        return $user->role_id === 1;
    }

}
