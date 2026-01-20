<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Str;

class FolderController extends Controller
{
    /**
     * List user's folders.
     */
    public function index(Request $request)
    {
        $parentId = $request->query('parent_id');

        $query = Folder::where('user_id', $request->user()->id)
            ->withCount('documents')
            ->withCount('children');

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id'); // Root folders only
        }

        $folders = $query->orderBy('name')->get();

        // If viewing a subfolder, get parent info for breadcrumbs
        $parent = null;
        if ($parentId) {
            $parent = Folder::where('id', $parentId)
                ->where('user_id', $request->user()->id)
                ->first();
        }

        return response()->json([
            'folders' => $folders,
            'parent' => $parent,
            'breadcrumbs' => $parent ? $parent->path : [],
        ]);
    }

    /**
     * Create a new folder.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|uuid|exists:folders,id',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
        ]);

        // Verify parent belongs to user if specified
        if (isset($validated['parent_id'])) {
            $parent = Folder::where('id', $validated['parent_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $folder = Folder::create([
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($folder, 201);
    }

    /**
     * Get folder details.
     */
    public function show(Request $request, $id)
    {
        $folder = Folder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->withCount('documents')
            ->with([
                'children' => function ($q) {
                    $q->withCount('documents');
                }
            ])
            ->firstOrFail();

        // Get documents in this folder
        $documents = Document::where('folder_id', $id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'folder' => $folder,
            'documents' => $documents,
            'breadcrumbs' => $folder->path,
        ]);
    }

    /**
     * Update folder.
     */
    public function update(Request $request, $id)
    {
        $folder = Folder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'string|max:255',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|uuid',
        ]);

        // Prevent moving folder into itself or its children
        if (isset($validated['parent_id']) && $validated['parent_id'] !== $folder->parent_id) {
            if ($validated['parent_id'] === $folder->id) {
                return response()->json(['message' => 'Cannot move folder into itself'], 422);
            }

            // Check if new parent is not a child of this folder
            $parent = Folder::find($validated['parent_id']);
            if ($parent) {
                $checkParent = $parent;
                while ($checkParent) {
                    if ($checkParent->id === $folder->id) {
                        return response()->json(['message' => 'Cannot move folder into its own subfolder'], 422);
                    }
                    $checkParent = $checkParent->parent;
                }
            }
        }

        $folder->update($validated);

        return response()->json($folder);
    }

    /**
     * Delete folder (moves documents to parent or root).
     */
    public function destroy(Request $request, $id)
    {
        $folder = Folder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Move documents to parent folder (or root)
        Document::where('folder_id', $id)->update([
            'folder_id' => $folder->parent_id,
        ]);

        // Move child folders to parent
        Folder::where('parent_id', $id)->update([
            'parent_id' => $folder->parent_id,
        ]);

        $folder->delete();

        return response()->json(['message' => 'Folder deleted']);
    }

    /**
     * Move documents to a folder.
     */
    public function moveDocuments(Request $request, $id)
    {
        $folder = null;
        if ($id !== 'root') {
            $folder = Folder::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'uuid|exists:documents,id',
        ]);

        // Update documents
        Document::whereIn('id', $validated['document_ids'])
            ->where('user_id', $request->user()->id)
            ->update(['folder_id' => $folder?->id]);

        return response()->json([
            'message' => 'Documents moved successfully',
            'count' => count($validated['document_ids']),
        ]);
    }

    /**
     * Download folder as ZIP.
     */
    public function download(Request $request, $id)
    {
        $folder = Folder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Get all documents in folder and subfolders
        $documents = $this->getDocumentsRecursive($folder, $request->user()->id);

        if ($documents->isEmpty()) {
            return response()->json(['message' => 'Folder is empty'], 404);
        }

        // Create ZIP
        $zipPath = $this->createFolderZip($folder, $documents);

        if (!$zipPath) {
            return response()->json(['message' => 'Failed to create ZIP file'], 500);
        }

        return response()->download($zipPath, Str::slug($folder->name) . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Get documents from folder and all subfolders.
     */
    protected function getDocumentsRecursive(Folder $folder, string $userId)
    {
        $folderIds = [$folder->id];

        // Get all child folder IDs recursively
        $this->collectChildFolderIds($folder, $folderIds);

        return Document::whereIn('folder_id', $folderIds)
            ->where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->get();
    }

    /**
     * Collect all child folder IDs recursively.
     */
    protected function collectChildFolderIds(Folder $folder, array &$ids): void
    {
        foreach ($folder->children as $child) {
            $ids[] = $child->id;
            $this->collectChildFolderIds($child, $ids);
        }
    }

    /**
     * Create ZIP file for folder.
     */
    protected function createFolderZip(Folder $folder, $documents): ?string
    {
        $tempPath = sys_get_temp_dir() . '/' . Str::random(16) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($documents as $doc) {
            if (!$doc->file_path || !Storage::disk('minio')->exists($doc->file_path)) {
                continue;
            }

            $content = Storage::disk('minio')->get($doc->file_path);
            $filename = Str::slug($doc->title) . '_' . substr($doc->id, 0, 8) . '.pdf';

            // Add folder path structure
            $folderPath = '';
            if ($doc->folder_id && $doc->folder_id !== $folder->id) {
                $docFolder = Folder::find($doc->folder_id);
                if ($docFolder) {
                    $path = $docFolder->path;
                    // Find index of current folder in path
                    $startIndex = 0;
                    foreach ($path as $i => $p) {
                        if ($p['id'] === $folder->id) {
                            $startIndex = $i + 1;
                            break;
                        }
                    }
                    // Build subfolder path
                    for ($i = $startIndex; $i < count($path); $i++) {
                        $folderPath .= Str::slug($path[$i]['name']) . '/';
                    }
                }
            }

            $zip->addFromString($folderPath . $filename, $content);
        }

        $zip->close();

        return file_exists($tempPath) ? $tempPath : null;
    }
}
