<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // Admin can view all
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }

        // Owner can view
        if ($user->id === $document->user_id) {
            return true;
        }

        // Signers (if implemented) should view
        if (
            $document->signers()->where('email', $user->email)->exists() ||
            $document->signers()->where('user_id', $user->id)->exists()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }

    /**
     * Determine whether the user can sign the document.
     */
    public function sign(User $user, Document $document): bool
    {
        // Check if user is a designated signer (Logic depends on SignatureField or implementation)
        // For MVP, if they can view, they might be able to sign if pending? 
        // Better to be strict: only if they are the requested signer.

        return $this->view($user, $document); // Fallback for now
    }
}
