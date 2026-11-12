<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class ContentController extends Controller
{
    public function index()
    {
        abort_unless(class_exists(Entry::class), 503, 'The CMS is not installed.');
        $entries = fn ($collection) => Entry::query()->where('collection', $collection)->where('published', true)->get();
        $settings = GlobalSet::findByHandle('website')?->inDefaultSite();

        return response()->json(['data' => [
            'hero_title' => $settings?->get('hero_title'), 'hero_subtitle' => $settings?->get('hero_subtitle'),
            'faqs' => $entries('faqs')->map(fn ($e) => ['question' => $e->get('title'), 'answer' => $e->get('answer')])->values(),
            'announcements' => $entries('announcements')->map(fn ($e) => ['title' => $e->get('title'), 'content' => $e->get('content')])->values(),
            'services' => $entries('services')->sortBy(fn ($entry) => (int) ($entry->get('display_order') ?? 0))
                ->map(fn ($entry) => ['slug' => $entry->slug(), 'title' => $entry->get('title'), 'summary' => $entry->get('summary'), 'content' => $entry->get('content')])->values(),
            'pages' => $entries('pages')->map(fn ($e) => ['slug' => $e->slug(), 'title' => $e->get('title'), 'content' => $e->get('content')])->values(),
        ]]);
    }
}
