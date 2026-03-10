<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Http\Resources\CollectionResource;
use ProPhoto\Gallery\Models\GalleryCollection;

class CollectionController extends Controller
{
    /**
     * Get all collections accessible to the user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = GalleryCollection::with(['galleries', 'user', 'organization']);

        // Filter based on permissions
        if (!$user->hasRole('studio_user')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by_user_id', $user->id)
                    ->orWhere('organization_id', $user->organization_id)
                    ->orWhere('is_public', true);
            });
        }

        $collections = $query->paginate(20);

        return CollectionResource::collection($collections);
    }

    /**
     * Get a specific collection.
     */
    public function show(Request $request, GalleryCollection $collection)
    {
        if (!$request->user()->can('view', $collection)) {
            abort(403);
        }

        $collection->load(['galleries.images', 'user', 'organization']);

        return new CollectionResource($collection);
    }

    /**
     * Create a new collection.
     */
    public function store(Request $request)
    {
        if (!$request->user()->can(Permissions::CREATE_COLLECTION)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'studio_id' => 'required|exists:studios,id',
            'organization_id' => 'required|exists:organizations,id',
            'is_public' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $collection = GalleryCollection::create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
        ]);

        return new CollectionResource($collection);
    }

    /**
     * Update a collection.
     */
    public function update(Request $request, GalleryCollection $collection)
    {
        if (!$request->user()->can('update', $collection)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $collection->update($validated);

        return new CollectionResource($collection);
    }

    /**
     * Delete a collection.
     */
    public function destroy(Request $request, GalleryCollection $collection)
    {
        if (!$request->user()->can('delete', $collection)) {
            abort(403);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection deleted successfully']);
    }

    /**
     * Add galleries to a collection.
     */
    public function addGalleries(Request $request, GalleryCollection $collection)
    {
        if (!$request->user()->can('update', $collection)) {
            abort(403);
        }

        $validated = $request->validate([
            'gallery_ids' => 'required|array',
            'gallery_ids.*' => 'exists:galleries,id',
        ]);

        $collection->galleries()->attach($validated['gallery_ids']);

        return response()->json(['message' => 'Galleries added to collection']);
    }

    /**
     * Remove galleries from a collection.
     */
    public function removeGalleries(Request $request, GalleryCollection $collection)
    {
        if (!$request->user()->can('update', $collection)) {
            abort(403);
        }

        $validated = $request->validate([
            'gallery_ids' => 'required|array',
            'gallery_ids.*' => 'exists:galleries,id',
        ]);

        $collection->galleries()->detach($validated['gallery_ids']);

        return response()->json(['message' => 'Galleries removed from collection']);
    }
}
