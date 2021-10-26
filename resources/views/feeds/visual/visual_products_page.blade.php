@extends('layouts.product_visual_layout')

@section('content')
    <div class="row">
        <div class="columns large-2 show-for-large">
        </div>
        <div class="columns large-10">
            <h1 class="title">{{ $vendor_name }}</h1>
            <div class="description show-for-medium">
                <div class="row">
                    <div class="columns large-10 must-show-less">
                        <div class="relative">
                            <div class="gradient collapse-gradient"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("click", function(e) {
            if (e.target.className=="accordion-title") {
                var id = e.target.getAttribute('id');
                document.querySelector('div[aria-labelledby="'+id+'"]').style.display = "block";
            }
        });
    </script>

    <div class="row">
        <div class="columns large-2 show-for-large" itemscope="">
            <div class="firm_cont">
                <form action="/category/72024/new-products/" method="get" id="filter_form" data-ajax-send="off">
                    <div class="filters_section advanced"> </div>
                    <div class="filters_section default">
                        <div class="filter_container">
                            <div class="filter-block" id="all_filter">
                                <div class="block-title"> Validation </div>
                                <ul class="accordion" data-accordion="cys2zh-accordion" data-allow-all-closed="true" data-multi-expand="true" role="tablist">
                                    <li class="accordion-item " data-accordion-item="" role="presentation" style="text-align: center; font-weight: bold; border-bottom: 1px solid #ddd; padding: 7px">
                                        <a href="/feeds/visual/{{ $dx_code_url }}/products">
                                            All Products
                                        </a>
                                    </li>

                                    @if(array_key_exists('valid', $filters) && count($filters['valid']))
                                        <li class="accordion-item " data-accordion-item="" role="presentation" style="text-align: center; font-weight: bold; padding: 7px">
                                            <a href="/feeds/visual/{{ $dx_code_url }}/products/valid" >
                                                Valid Products
                                            </a>
                                        </li>
                                        @php
                                          unset($filters['valid']);
                                        @endphp
                                    @endif
                                    @foreach($filters as $name_filter => $filter )
                                        <li class="accordion-item " data-accordion-item="" role="presentation">
                                            <a class="accordion-title" role="tab" id="{{ $name_filter }}" aria-expanded="false" aria-selected="false">
                                                {{ ucfirst($name_filter) }} Errors
                                            </a>

                                            @if(isset($general_type) && $general_type === $name_filter)
                                                <div class="accordion-content" role="tabpanel" aria-labelledby="{{ $name_filter }}" aria-hidden="true" style="display: block">
                                            @else
                                                <div class="accordion-content" role="tabpanel" aria-labelledby="{{ $name_filter }}" aria-hidden="true">
                                            @endif
                                                <div class="filter_list">
                                                    <ul class="no-bullet filter short" >
                                                        @foreach($filter as $error => $info)
                                                            <li>
                                                                @php
                                                                    $raw_error_url = explode('/', $info['page_link']);
                                                                    $type_error = array_pop($raw_error_url);
                                                                @endphp

                                                                @if(isset($type) && $type === $type_error)
                                                                    <a href="/feeds/visual/{{ $dx_code_url }}/products/{{ $info['page_link'] }}" style="font-size: 13px; font-weight: bold">
                                                                        {{ $error }} <span class="count"> ({{ $info['total_products'] }})</span>
                                                                    </a>
                                                                @else
                                                                    <a href="/feeds/visual/{{ $dx_code_url }}/products/{{ $info['page_link'] }}" style="font-size: 13px">
                                                                        {{ $error }} <span class="count">({{ $info['total_products'] }})</span>
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="columns large-10">
            <div class="catalog-component">
                <div class="catalog">
                    <div class="products-state-line_catalog products-state-line pcont" style="display: block;">
                        @php
                            if ( isset($general_type, $type) ){
                                if ($general_type !== 'valid'){
                                    $general_type = "errors/$general_type";
                                }
                                $path = isset($general_type, $type) ?  "$general_type/$type" : '';
                            }else{
                                $path = '';
                            }
                        @endphp

                        <div class="row">
                            <div class="columns large-3 show-for-large">
                                <div class="page_count_wrap">
                                    <span class="page_count">
                                        <span class="count">{{ (int)$page === $products['total_pages'] ? $products['total_products'] : count($products['products']) * $page }}</span>
                                        <span> / </span>
                                        <span class="full">{{ $products['total_products'] }}</span><span> items shown</span>
                                    </span>
                                </div>
                            </div>
                            <div class="columns large-3 show-for-large">
                            @if($page > 1)
                                <div class="page_count_wrap">
                                    <a href="/feeds/visual/{{ $dx_code_url }}/products/{{ $path }}?page={{ $page - 1 }}">Prev page</a>
                                </div>
                            @endif
                            </div>

                            <div class="columns large-3 show-for-large">
                                <div class="page_count_wrap">
                                    <form class="page_count">
                                        <label class="count">Page <input name="page" type="text" value="{{ $page }}"> Of</label>
                                        <span class="full">{{ $products['total_pages'] }}</span>
                                    </form>
                                </div>
                            </div>

                            <div class="columns large-3 show-for-large">
                            @if($page < $products['total_pages'])
                                <div class="page_count_wrap">
                                    <a href="/feeds/visual/{{ $dx_code_url }}/products/{{$path}}?page={{ $page + 1 }}">Next page</a>
                                </div>
                            @endif
                            </div>
                        </div>
                    </div>
                    <div class="product-items tile-view product-items__tile">
                        @foreach($products['products'] as $product)
                            @include('../templates/product', compact('dx_code_url', 'product'))
                        @endforeach
                    </div>
                    <div class="products-state-line_catalog products-state-line pcont" style="display: block;">
                        <div class="row">
                            <div class="columns large-3 show-for-large">
                                <div class="page_count_wrap">
                                    <span class="page_count">
                                        <span class="count">{{ (int)$page === $products['total_pages'] ? $products['total_products'] : count($products['products']) * $page }}</span>
                                        <span> / </span>
                                        <span class="full">{{ $products['total_products'] }}</span><span> items shown</span>
                                    </span>
                                </div>
                            </div>
                            <div class="columns large-3 show-for-large">
                                @if($page > 1)
                                    <div class="page_count_wrap">
                                        <a href="/feeds/visual/{{ $dx_code_url }}/products/{{ $path }}?page={{ $page - 1 }}">Prev page</a>
                                    </div>
                                @endif
                            </div>

                            <div class="columns large-3 show-for-large">
                                <div class="page_count_wrap">
                                    <form class="page_count">
                                        <label class="count">Page <input name="page" type="text" value="{{ $page }}"> Of</label>
                                        <span class="full">{{ $products['total_pages'] }}</span>
                                    </form>
                                </div>
                            </div>

                            <div class="columns large-3 show-for-large">
                                @if($page < $products['total_pages'])
                                    <div class="page_count_wrap">
                                        <a href="/feeds/visual/{{ $dx_code_url }}/products/{{$path}}?page={{ $page + 1 }}">Next page</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection