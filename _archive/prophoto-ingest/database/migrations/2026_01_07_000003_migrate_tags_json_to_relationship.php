<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use ProPhoto\Ingest\Models\ProxyImage;
use ProPhoto\Ingest\Models\Tag;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing tags_json data to the new relationship table
        $proxies = ProxyImage::whereNotNull('tags_json')->get();
        
        foreach ($proxies as $proxy) {
            if (empty($proxy->tags_json) || !is_array($proxy->tags_json)) {
                continue;
            }
            
            $tagIds = [];
            foreach ($proxy->tags_json as $tagName) {
                if (empty($tagName)) {
                    continue;
                }
                
                // Find or create the tag (as normal type by default)
                $tag = Tag::findOrCreateByName($tagName);
                $tagIds[] = $tag->id;
            }
            
            if (!empty($tagIds)) {
                // Sync tags to the relationship
                $proxy->tags()->sync($tagIds);
            }
        }
    }

    public function down(): void
    {
        // Remove all proxy image tag relationships
        DB::table('ingest_proxy_image_tag')->truncate();
    }
};
