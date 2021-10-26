<html lang="en" class="" data-whatintent="mouse" data-whatinput="keyboard">
<head>
    <title>{{ $vendor_name ?? "{$current_product['group_mask']} {$current_product['product']}" }}</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="preload" href="https://cdn.s3stores.com/static/frontend/dist/js/main.59505752707b6418075e.js" as="script">
    <link rel="stylesheet" href="https://cdn.artistsupplysource.com/static/frontend/dist/css/styles.css?v=b1a8f3670596bb2855f936d393d40f80" as="style">

    <script>
      const dataProvider = {
        data: {  },

        get: function (key) {
            return this.data[key];
        },

        set: function(key, value) {
            return this.data[key] = value;
        },
      };

        window.app = {
            afterReady:[],
            assets: {
                'css': {
                    'styles.css': {
                        'loaded': false
                    }
                }
            },
            options: {
                'session_key': 'xid88',
                'urls': {
                    cart: {
                    }
                },
                'discount_minutes': 0,
                'order': null,
                currency: {
                    currency: "$",
                    symbol_prefix: "US",
                    after: "",
                },
                translates: '',
            },
        };
        window.parseUrl = function(href) { var a = document.createElement("a");a.href = href;return { 'href':href,'protocol': a.protocol,'host': a.host,'hostname': a.hostname,'port': a.port,'pathname': a.pathname,'hash': a.hash,'search': a.search,'origin': a.origin, 'document':a.pathname.split("/").pop(),};}
    </script>
</head>
<body>
    <div id="main_wrapper" class="off-canvas-wrapper">
        <div class="off-canvas-content" data-off-canvas-content="">
            <div id="content-wrapper">
                <div id="top-header-content" style="height: auto;">
                    <div id="top-header-menu">
                        <header id="top-header">
                            <div class="logo_menu">
                                <div class="row align-justify">
                                    <div class="columns small-3 medium-2">
                                        <a href="/feeds/visual/{{ $dx_code_url }}/products">
                                            <img src="/img/s3stores_footer.svg" alt="" class="show-for-large logo-big">
                                        </a>
                                    </div>
                                </div>
                                <div class="mobile-banner hide-for-medium">
                                    <div class="row align-justify">
                                        <div class="columns banner"> </div>
                                    </div>
                                </div>
                            </div>
                        </header>
                    </div>
                    <div class="shadow"></div>
                </div>
                <div id="content">
                    <div class="sticky-menu-container">

                        <div class="sticky def-zi2" style="width: 100%">
                            <div id="search_container" class="desktop_menu_search_cart show-for-large" data-toggler="show-for-large" data-show-for-large="gwdtax-show-for-large">
                                <div class="row">
                                    <div class="columns large-3 show-for-large"></div>
                                    <div class="columns small-12 large-7">
                                        <div class="search-form-container">
                                            <form action="/feeds/visual/{{ $dx_code_url }}/search" method="get">
                                                <input type="text" name="sku" class="search" placeholder="Search items" value="">
                                                <meta itemprop="target" content="/search?q={query}">
                                                <button class="button-search show-for-large"></button>
                                                <a class="button-clear "></a>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="columns large-2 show-for-large"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="before-content"></div>
                    <div class="content">
                        <section class="catalog-page default-content-page">
                            @yield('content')
                        </section>
                    </div>
                </div>
            </div>
            <footer><div class="footer-menu"></div></footer>
        </div>
        <div class="js-off-canvas-overlay is-overlay-fixed"></div>
    </div>

    <script async="" src="https://cdn.s3stores.com/static/frontend/dist/js/main.59505752707b6418075e.js"></script>
</body>
</html>