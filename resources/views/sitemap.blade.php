<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ $baseUrl }}/</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
    <url><loc>{{ $baseUrl }}/services</loc><changefreq>monthly</changefreq><priority>0.9</priority></url>
    <url><loc>{{ $baseUrl }}/portfolio</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
    <url><loc>{{ $baseUrl }}/blog</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
    <url><loc>{{ $baseUrl }}/guides</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ $baseUrl }}/shop</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ $baseUrl }}/about</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    <url><loc>{{ $baseUrl }}/contact</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    <url><loc>{{ $baseUrl }}/faq</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>

    @foreach($publications as $pub)
    <url>
        <loc>{{ $baseUrl }}/blog/{{ $pub->slug }}</loc>
        @if ($pub->updated_at)
        <lastmod>{{ $pub->updated_at->toAtomString() }}</lastmod>
        @endif
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach

    @foreach($guides as $guide)
    <url>
        <loc>{{ $baseUrl }}/guides/{{ $guide->slug }}</loc>
        @if ($guide->updated_at)
        <lastmod>{{ $guide->updated_at->toAtomString() }}</lastmod>
        @endif
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach

    @foreach($products as $product)
    <url>
        <loc>{{ $baseUrl }}/shop/{{ $product->slug }}</loc>
        @if ($product->updated_at)
        <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
        @endif
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
</urlset>
