<section class="tour-route-explorer mt-8" data-tour-route-map data-road-route-url="{{ route('itinerary.road-route') }}" data-map-poi-url="{{ route('tours.map-places', $tour) }}">
    <div class="tour-route-heading">
        <div>
            <p class="eyebrow">B&#x1EA2;N &#x110;&#x1ED2; H&#xC0;NH TR&#xCC;NH</p>
            <h3 class="mt-2 font-serif text-3xl leading-tight sm:text-4xl">Theo d&#x1EA5;u h&#xE0;nh tr&#xEC;nh, t&#x1EEB;ng &#x111;i&#x1EC3;m ch&#x1EA1;m.</h3>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-muted">Kh&#xE1;m ph&#xE1; cung &#x111;&#x1B0;&#x1EDD;ng theo t&#x1EEB;ng ng&#xE0;y. Ch&#x1ECD;n m&#x1ED9;t ng&#xE0;y &#x111;&#x1EC3; xem c&#xE1;c &#x111;i&#x1EC3;m d&#x1EEB;ng v&#xE0; th&#x1EE9; t&#x1EF1; di chuy&#x1EC3;n.</p>
        </div>
        <span class="map-live-badge"><span></span> B&#x1EA3;n &#x111;&#x1ED3; t&#x1B0;&#x1A1;ng t&#xE1;c</span>
    </div>

    <div class="map-journey-stats" aria-label="T&#xF3;m t&#x1EAF;t l&#x1ECB;ch tr&#xEC;nh">
        <div class="map-stat"><span class="map-stat-icon">&#x25CE;</span><span><strong data-map-days>0</strong><small>ng&#xE0;y kh&#xE1;m ph&#xE1;</small></span></div>
        <div class="map-stat"><span class="map-stat-icon">&#x2316;</span><span><strong data-map-stops>0</strong><small>&#x111;i&#x1EC3;m d&#x1EEB;ng</small></span></div>
        <div class="map-stat map-stat-note"><span class="map-stat-icon">&#x27A4;</span><span><strong>L&#x1ED9; tr&#xEC;nh</strong><small>C&#xE1;c &#x111;i&#x1EC3;m n&#x1ED1;i theo th&#x1EE9; t&#x1EF1;</small></span></div>
    </div>

    <div class="map-explorer-shell">
        <div class="map-explorer-main">
            <div class="map-toolbar">
                <div><p class="map-toolbar-kicker">CH&#x1ECC;N G&#xD3;C NH&#xCC;N</p><div class="map-filter-list" data-map-filters aria-label="L&#x1ECD;c ng&#xE0;y tr&#xEA;n b&#x1EA3;n &#x111;&#x1ED3;"></div></div>
                <div class="map-toolbar-actions">
                    <div><button type="button" class="map-poi-button" data-map-search-pois>&#x2315; T&#xEC;m qu&#xE1;n quanh h&#xE0;nh tr&#xEC;nh</button><small data-map-poi-status role="status" aria-live="polite">T&#x1EF1; &#x111;&#x1ED9;ng t&#xEC;m khi b&#x1EA3;n &#x111;&#x1ED3; hi&#x1EC7;n ra; k&#x1EBF;t qu&#x1EA3; l&#x1B0;u t&#x1EA1;m 1 gi&#x1EDD;.</small></div>
                    <button type="button" class="map-islands-button" data-map-islands>&#x25C6; Ho&#xE0;ng Sa &amp; Tr&#x1B0;&#x1EDD;ng Sa</button>
                </div>
            </div>
            <p class="map-poi-hint">Ph&#xF3;ng to b&#x1EA3;n &#x111;&#x1ED3; &#x111;&#x1EC3; xem t&#xEA;n qu&#xE1;n c&#xE0; ph&#xEA;, nh&#xE0; h&#xE0;ng v&#xE0; &#x111;i&#x1EC3;m &#x103;n u&#x1ED1;ng.</p>
            <div class="tour-route-map-canvas" data-map-canvas role="application" aria-label="B&#x1EA3;n &#x111;&#x1ED3; t&#x1B0;&#x1A1;ng t&#xE1;c c&#xE1;c &#x111;i&#x1EC3;m trong l&#x1ECB;ch tr&#xEC;nh"></div>
            <div class="map-caption"><p data-map-status role="status" aria-live="polite"></p><span class="map-route-legend"><i></i> Tuy&#x1EBF;n theo &#x111;&#x1B0;&#x1EDD;ng th&#x1EF1;c t&#x1EBF;</span></div>
            <div class="map-provider-attribution"><span>D&#x1EEF; li&#x1EC7;u b&#x1EA3;n &#x111;&#x1ED3; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">&#xA9; OpenStreetMap contributors</a></span><span>Ch&#x1EC9; &#x111;&#x1B0;&#x1EDD;ng b&#x1EDFi; <a href="https://www.graphhopper.com/" target="_blank" rel="noreferrer">GraphHopper API</a></span></div>
        </div>
        <aside class="map-route-panel" aria-label="C&#xE1;c &#x111;i&#x1EC3;m theo ng&#xE0;y" data-map-route-list></aside>
    </div>
    <div class="map-sovereignty-note"><span>&#x2726;</span><p><strong>Bi&#x1EC3;n &#x111;&#x1EA3;o Vi&#x1EC7;t Nam</strong> <span>Ho&#xE0;ng Sa v&#xE0; Tr&#x1B0;&#x1EDD;ng Sa &#x111;&#x1B0;&#x1EE3;c ch&#xFA; th&#xED;ch tr&#xEA;n b&#x1EA3;n &#x111;&#x1ED3; b&#x1EB1;ng ti&#x1EBF;ng Vi&#x1EC7;t.</span></p></div>
</section>
