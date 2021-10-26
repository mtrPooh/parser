@extends('layouts.product_visual_layout')

@section('content')
    <div class="product-page default-content-page">
        <section class="product-title product-title-small">
            <div class="row">
                <div class="column large-12">
                    <h1>{!! "{$current_product['group_mask']} {$current_product['product']}" !!}</h1>
                    <div class="row align-justify align-middle">
                        <div class="column shrink sku">
                            <span class="value">
                                SKU: <span class="style">{{ $current_product['is_group'] ? "$dx_code_url-GROUP-{$current_product['hash_product']}" : $current_product['productcode'] }}</span>
                            </span>
                        </div>
                        <div class="column shrink notifications hide-for-ml product_notifications">
                            <div class="notifications-info small-collapse">
                                <div class="column shrink"></div>
                            </div>
                        </div>
                    </div>
                    <span class="clearfix"></span>
                </div>
            </div>
        </section>
        <section class="images_prices">
            <div class="row">
                <div class="column small-12 ml-6 large-6 block__image">
                    <div class="product__images-slider">
                        <div class="images-slider">
                            <div class="slider-thumbs" style="">
                                <div
                                    class="swiper-container swiper-container-initialized swiper-container-vertical swiper-container-pointer-events product-thumbs-slider"
                                    style="margin-bottom: 10px;">
                                </div>
                            </div>
                            <div
                                class="swiper-container swiper-container-initialized swiper-container-horizontal swiper-container-pointer-events product-images-slider"
                                style="margin-bottom: 10px;">
                                <div class="swiper-wrapper" style="transition-duration: 0ms; transform: translate3d(-542px, 0px, 0px);">
                                    @if($current_product['is_group'])
                                        @foreach($current_product['child_products'] as $child)
                                            @php($image = array_first($child['images']))
                                            <div
                                                class="swiper-slide swiper-slide-duplicate swiper-slide-prev"
                                                data-swiper-slide-index="1"
                                                style="background-image: url({!! $image !!}); width: 492px; margin-right: 50px;">
                                            </div>
                                        @endforeach
                                    @else
                                        @if($current_product['images'])
                                            @foreach($current_product['images'] as $image)
                                                <div
                                                    class="swiper-slide swiper-slide-duplicate swiper-slide-prev"
                                                    data-swiper-slide-index="1"
                                                    style="background-image: url({!! $image !!}); width: 492px; margin-right: 50px;">
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="not-avail-thumb"> <p>Image not available</p> </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        <datalist>
                            @if($current_product['is_group'])
                                @foreach($current_product['child_products'] as $child)
                                    @php($image = array_first($child['images']))
                                    <option
                                        value="{!! $image !!}"
                                        data-thumb="{!! str_replace(['(', ')'], ['\(', '\)'], $image) !!}"
                                        data-preview="{!! str_replace(['(', ')'], ['\(', '\)'], $image) !!}"
                                        data-id="4423691" type="image">
                                    </option>
                                @endforeach
                            @else
                                @foreach($current_product['images'] as $image)
                                    <option
                                        value="{!! $image !!}"
                                        data-thumb="{!! str_replace(['(', ')'], ['\(', '\)'], $image) !!}"
                                        data-preview="{!! str_replace(['(', ')'], ['\(', '\)'], $image) !!}"
                                        data-id="4423691" type="image">
                                    </option>
                                @endforeach
                            @endif
                        </datalist>
                    </div>
                </div>
                <div class="column small-12 ml-6 large-6 block__title_price">
                    {!! $current_product['descr'] !!}
                    <div class="notifications show-for-ml product_notifications">
                        <div class="row align-middle ml-collapse notifications-info">
                            <div class="column shrink ">
                                @if($current_product['r_avail'] === 0 && !$current_product['is_group'])
                                    <div class="product-label product_label fill product-label__out-of-stock ">
                                        <i class="product-label-icon product-label-icon__out-of-stock"></i>
                                        <div class="product-label-text">Out of stock</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if($current_product['is_group'])
                    <div class="full_line__group_root buttons">
                        <a
                            onclick="$('html, body').animate({scrollTop: $('#products').offset().top}, 1000);"
                            class="button yellow waves waves-orange waves-effect default-style">
                            Full product line
                        </a>
                        <div class="info" style="margin-top: 15px">
                            <a target="_blank" href="{{ $current_product['supplier_internal_id'] }}">Click here to see product in distributor site</a>
                        </div>
                    </div>
                    @else
                        <div class="prices">
                            <div class="prices__container">
                                <div class="row align-justify">
                                    <div class="price-section columns small-12">
                                        <div class="product-quantity">
                                            <div class="column small-12">
                                                <div class="table table__prices table__prices--top product-quantity-row__title">
                                                    <div class="title column small-4 product-quantity-title">Unit Price</div>
                                                    <div class="title column small-4 product-quantity-title">Quantity</div>
                                                    <div class="title column small-4 product-quantity-title">Subtotal</div>
                                                </div>
                                                <div class="table table__prices table__prices--top product-quantity-row__price_discount">
                                                    <div class="column product-table-prices_price-column column-price small-4">
                                                        <div class="value product-quantity-one-price product-quantity-one-price__discount">
                                                            US
                                                            <span> $ </span>
                                                            <span class="price">{{ number_format($current_product['cost_to_us'], 2, '.', ',') }}</span>
                                                            <span> </span>
                                                        </div>
                                                        @if($current_product['list_price'])
                                                            <div class="value product-quantity-old-price">
                                                                US <span> $ </span>
                                                                <span class="price product-quantity-old-price">{{ number_format($current_product['list_price'], 2, '.', ',') }}</span>
                                                                <span> </span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="column quantity small-4">
                                                        <div class="value">
                                                            <div class="quantity-group">
                                                                <input class="quantity-group-input" type="number" name="quantity" value="{{ $current_product['min_amount'] }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="column product-table-prices_price-column column-extended small-4">
                                                        <div class="product-quantity-extended-price">
                                                            US$ <span class="price">{{ number_format($current_product['cost_to_us'], 2, '.', ',') }}</span>&nbsp;
                                                        </div>
                                                        @if($current_product['list_price'])
                                                        <div class="value product-quantity-old-price">
                                                            US <span> $ </span>
                                                            <span class="price product-quantity-old-price">{{ number_format($current_product['list_price'], 2, '.', ',') }}</span>
                                                            <span> </span>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="table table__prices table__prices--down price-row-width hidden"> </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="link__group_root">
                                <a target="_blank" href="{{ $current_product['supplier_internal_id'] }}">Click here to see product in distributor site</a>
                            </div>
                            @if(!empty($hash_group_product))
                                <br>
                                <div class="link__group_root">
                                    <a href="/feeds/visual/{{ $dx_code_url }}/product/{{ $hash_group_product }}"> Full Black Dahlia Culottes Jumpsuit product line </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>
        <section class="info_tabs">
            <ul class="tabs" data-responsive-accordion-tabs="tabs small-accordion large-tabs"
                data-allow-all-closed="true"
                data-multi-expand="true" id="product_tabs" role="tablist" data-tabs="r8y9nz-tabs">
                <li class="tabs-title is-active" role="presentation">
                    <a href="#description" aria-selected="true"
                       role="tab"
                       aria-controls="description"
                       id="description-label"
                       tabindex="0">Description</a>
                </li>
                <li class="tabs-title" role="presentation">
                    <a href="#shipping" aria-selected="false"
                       role="tab"
                       aria-controls="shipping"
                       id="shipping-label"
                       tabindex="-1">Shipping
                    </a>
                </li>
            </ul>
            <div class="tabs-content" data-tabs-content="product_tabs">
                <div class="tabs-panel is-active" id="description" role="tabpanel" aria-labelledby="description-label">
                    <div class="tab-description tab-content">
                        <div class="row">
                            @if($current_product['attributes'] || $current_product['brand_name'] || $current_product['product_files'])
                                <div class="column small-12 large-5 block">
                                    <div class="options">
                                        <div class="h2 title">Options</div>
                                        <div class="content">
                                            @if($current_product['brand_name'])
                                                <div class="option">
                                                    <div class="title option-title">Brand</div>
                                                    <div class="value"><span>{{ $current_product['brand_name'] }}</span></div>
                                                </div>
                                            @endif

                                            @if($current_product['attributes'])
                                                @foreach($current_product['attributes'] as $key => $value)
                                                    <div class="option">
                                                        <div class="title option-title">{!! $key !!}</div>
                                                        <div class="value"><span>{!! $value !!}</span></div>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if($current_product['product_files'])
                                                @foreach($current_product['product_files'] as $file)
                                                    <div class="option">
                                                        <div class="title option-title">{!! $file['name'] !!}</div>
                                                        <div class="value">
                                                            <span>
                                                                <div class="row margin-0">
                                                                    <div class="columns option-file-icon shrink">
                                                                        <img class="icon" src="https://www.artistsupplysource.com/static/frontend/dist/images/icons/file_format/pdf.svg" alt="">
                                                                    </div>
                                                                    <div class="columns padding-0 option-file_description">
                                                                        <a href="{{ $file['link'] }}">
                                                                            {!! $file['name'] !!}.pdf
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="column small-12 large-7 block">
                                <div class="description">
                                    <div class="h2 title">Description</div>
                                    <div class="content" style="height: auto">{!! $current_product['fulldescr'] ?: '' !!}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tabs-panel" id="shipping" role="tabpanel" aria-labelledby="shipping-label"
                     aria-hidden="true">
                    <div class="tab-shipping tab-content">
                        <div class="tab-shipping">
                            <div class="row">
                                <div class="columns small-12 large-12 block">
                                    <div class="row">
                                        <div class="columns small-12 large-4 block">
                                            <div class="h2 title">Shipping specs</div>
                                            <div class="options">
                                                <div class="content">

                                                    @if($current_product['weight'])
                                                        <div class="option">
                                                            <div class="title option-title">Weight</div>
                                                            <div class="value"> <span>{{ $current_product['weight'] }}</span> </div>
                                                        </div>
                                                    @endif

                                                    @if($current_product['shipping_weight'])
                                                        <div class="option">
                                                            <div class="title option-title">Shipping Weight</div>
                                                            <div class="value"> <span>{{ $current_product['shipping_weight'] }}</span> </div>
                                                        </div>
                                                    @endif

                                                    @if($current_product['dim_x'] || $current_product['dim_y'] || $current_product['dim_z'])
                                                    <div class="option">
                                                        <div class="title option-title">Dimensions</div>
                                                        <div class="value">
                                                            <span>
                                                                {{ $current_product['dim_x'] ?? 0 }}" x
                                                                {{ $current_product['dim_y'] ?? 0 }}" x
                                                                {{ $current_product['dim_z'] ?? 0 }}"
                                                            </span>
                                                        </div>
                                                    </div>
                                                    @endif

                                                    @if($current_product['shipping_dim_x'] || $current_product['shipping_dim_y'] || $current_product['shipping_dim_z'])
                                                        <div class="option">
                                                            <div class="title option-title">Shipping Dimensions</div>
                                                            <div class="value">
                                                                <span>
                                                                    {{ $current_product['shipping_dim_x'] ?? 0 }}" x
                                                                    {{ $current_product['shipping_dim_y'] ?? 0 }}" x
                                                                    {{ $current_product['shipping_dim_z'] ?? 0 }}"
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endif

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section>
            <div class="row"><br></div>
            <div class="row">
                    <div class="catalog">
                        @if($current_product['is_group'])
                            <div class="products-state-line_catalog products-state-line pcont">
                                <div class="state-line-counter padding-left-1">
                                    <span class="state-line-title">Product line</span>
                                    <span class="page_count">
                                    <span class="count">{{ count($current_product['child_products']) }}</span>
                                    <span> / </span>
                                    <span class="full">{{ count($current_product['child_products']) }}</span>
                                    <span> items shown</span>
                                </span>
                                </div>
                            </div>
                        @endif
                            @if(!empty($other_products))
                                <div class="products-state-line_catalog products-state-line pcont">
                                    <div class="state-line-counter padding-left-1">
                                        <span class="state-line-title">Other products</span>
                                    </div>
                                </div>
                            @endif
                        <div class="product-items tile-view product-items__tile" id="products">
                            @foreach($current_product['child_products'] as $product)
                                @include('../templates/product', compact('dx_code_url', 'product'))
                            @endforeach

                            @if(isset($other_products))
                                @foreach($other_products as $product)
                                    @include('../templates/product', compact('dx_code_url', 'product'))
                                @endforeach
                            @endif

                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                            <div class="out_of_stock catalog-product__tile catalog-product_tile catalog-product item"></div>
                        </div>
                </div>
            </div>
        </section>
    </div>
@endsection