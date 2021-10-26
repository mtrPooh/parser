<div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item">
    <div class="product-card-image product-card-image__catalog-tile grid-catalog-product-image">
        <a href="/feeds/visual/{{ $dx_code_url }}/product/{{ $product['productcode'] ?: $product['hash_product'] }}" class="product-image__catalog-tile product-image-link">
            @if(count($product['images']))
                <img src="{{ array_shift($product['images']) }}" alt="" class="products-slider-image">
            @elseif($product['is_group'])
                @php($child = array_first($product['child_products']))
                <img  src="{{ array_shift($child['images']) }}" alt="" class="products-slider-image">
            @else
                <div class="product-no-image__catalog-tile product-card-no-image">
                    <span>Image not available</span>
                </div>
            @endif
        </a>
    </div>
    <div class="container grid-catalog-product-info product-card-info product-card-info__tile">
        <h4 class="product-card-title__catalog product-card-title__catalog-tile">
            <a href="/feeds/visual/{{ $dx_code_url }}/product/{{ $product['productcode'] ?: $product['hash_product'] }}" class="product-card-title-link">
                <span>{!! "{$product['group_mask']} {$product['product']}" !!}</span>
            </a>
        </h4>
    </div>
    <div class="grid-catalog-product-price product-card-price__catalog">
        <div class="price_container product-card-price product-card-price__tile">
            <div class="price_container product-card-price product-card-price__tile">
                @if((isset($child) && $child['list_price']) || $product['list_price'])
                    <div class="old">
                        <span class="show-for-medium product-card-price-caption__tile">List Price: </span>
                        <span class="products-slider-old-price">
                            US$ <span class="price-number">
                                @if($product['list_price'])
                                    {{ number_format($product['list_price'], 2, '.', ',') }}
                                @elseif(isset($child))
                                    {{ number_format($child['list_price'], 2, '.', ',') }}
                                @endif
                            </span>
                        </span>
                    </div>
                @endif
                <div class="current">
                    <span class="products-slider-current-price">
                        US$ <span class="price-number">
                            @if($product['cost_to_us'])
                                {{ number_format($product['cost_to_us'], 2, '.', ',') }}
                            @elseif(isset($child))
                                {{ number_format($child['cost_to_us'], 2, '.', ',') }}
                            @endif
                    </span>
                </div>
            </div>
        </div>
        @if($product['is_group'])
            <div class="product-card-price-info product-price-advanced__tile">
                <div class="price-attributes price-attributes__tile">
                    <div class="info-container info-container__tile">
                        <a class="button waves waves-orange yellow-white see-other" href="/feeds/visual/{{ $dx_code_url }}/product/{{ $product['hash_product'] }}">
                            <span class="text">See {{ count($product['child_products']) }} products variation</span>
                        </a>
                    </div>
                </div>
                <div class="info-container info-container__tile"></div>
            </div>
        @else
            <div class="product-card-price-info product-price-advanced__tile">
                <div class="price-attributes price-attributes__tile">
                    @if($product['r_avail'] === 0)
                        <div class="p-label out-of-stock product-card__label">
                            <i></i>
                            <span class="text p-label-text_out-of-stock">Out of stock</span>
                        </div>
                    @else
                        <div class="cart-quantity cart-quantity__tile">
                            <div class="quantity-group">
                                <span class="quantity-group-btn quantity-group-btn_dec"></span>
                                <input class="quantity-group-input" type="number" name="quantity" min="1" max="59" value="{{ $product['min_amount'] }}">
                                <span class="quantity-group-btn quantity-group-btn_inc quantity-group-btn_active"></span>
                            </div>
                        </div>
                        <div class="cart-add product-card-button catalog-tile_product-card-button cart-add__tile product-card-button__tile">
                            <div class="add-to-cart-button add-to-cart-button_catalog">
                                <a class="add button yellow wait-button add-to-cart-button-add add-to-cart-button-add__simple add-to-cart-button-add__catalog add-to-cart-button-add__catalog-tile">
                                    <span class="button-text add-to-cart-text__catalog add-to-cart-text__catalog-tile">Add to cart</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="info-container info-container__tile"></div>
            </div>
        @endif
    </div>
</div>