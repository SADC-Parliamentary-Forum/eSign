<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'color',
        'description',
    ];

    /**
     * Get the user who owns this folder.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent folder.
     */
    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Get child folders.
     */
    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Get all nested children (recursive).
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Get documents in this folder.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get document count including nested folders.
     */
    public function getTotalDocumentCountAttribute(): int
    {
        $count = $this->documents()->count();

        foreach ($this->children as $child) {
            $count += $child->total_document_count;
        }

        return $count;
    }

    /**
     * Get the full path of the folder (for breadcrumbs).
     */
    public function getPathAttribute(): array
    {
        $path = [['id' => $this->id, 'name' => $this->name]];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, ['id' => $parent->id, 'name' => $parent->name]);
            $parent = $parent->parent;
        }

        return $path;
    }
}
