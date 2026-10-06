<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Facade;

/**
 * Facade tipis untuk SeoMeta supaya controller cukup menulis:
 *
 *     use App\Support\Seo\Seo;
 *
 *     Seo::set([
 *         'title'       => $job['posisi'],
 *         'description' => $job['ringkasan'],
 *         'type'        => 'article',
 *     ]);
 *
 * @method static \App\Support\Seo\SeoMeta set(array $values)
 * @method static \App\Support\Seo\SeoMeta title(string $title)
 * @method static \App\Support\Seo\SeoMeta description(string $description)
 * @method static array resolve(\Illuminate\Http\Request $request)
 * @method static string composeTitle(string $pageTitle, ?string $siteName = null, ?string $separator = null)
 *
 * @see \App\Support\Seo\SeoMeta
 */
class Seo extends Facade
{
}
