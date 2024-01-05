@php
    $translation = $row->translate();
@endphp
<div class="row x-gap-20 y-gap-20">
    <div class="col-md-auto">
        <div class="cardImage ratio ratio-1:1 w-250 md:w-1/1 rounded-4">
            <div class="cardImage__content">
                <a href="{{ $row->getDetailUrl() }}">
                    @if($row->image_url)
                        @if(!empty($disable_lazyload))
                            <img  src="{{$row->image_url}}" class="rounded-4 col-12 js-lazy" alt="{{ $translation->title ?? 'image' }}">
                        @else
                            {!! get_image_tag($row->image_id,'medium',['class'=>'rounded-4 col-12 js-lazy','alt'=>$translation->title]) !!}
                        @endif
                    @endif
                </a>
            </div>
            <div class="service-wishlist {{$row->isWishList()}}" data-id="{{ $row->id }}" data-type="{{ $row->type }}">
                <div class="cardImage__wishlist">
                    <button class="button -blue-1 bg-white size-30 rounded-full shadow-2">
                        <i class="icon-heart text-12"></i>
                    </button>
                </div>
            </div>
            <div class="cardImage__leftBadge">
                @if($row->is_featured == "1")
                    <div class="py-5 px-15 rounded-right-4 text-12 lh-16 fw-500 uppercase bg-yellow-1 text-dark-1">
                        {{__("Featured")}}
                    </div>
                @endif
                @if($row->discount_percent)
                    <div class="py-5 px-15 rounded-right-4 text-12 lh-16 fw-500 uppercase bg-blue-1 text-white mt-5">
                        {{__("Sale off :number",['number'=>$row->discount_percent])}}
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md">
        <div class="d-flex flex-column h-full justify-between">
            <div class="">
                @if(!empty($row->location->name))
                    @php $location =  $row->location->translate() @endphp
                @endif
                <h3 class="text-18 lh-16 fw-500">
                    <a href="{{ $row->getDetailUrl() }}">{{ $translation->title }}</a>
                    @if($row->star_rate)
                        <div class="star-rate d-inline-block ml-10">
                            @for ($star = 1 ;$star <= $row->star_rate ; $star++)
                                <i class="icon-star text-10 text-yellow-2"></i>
                            @endfor
                        </div>
                    @endif
                </h3>
                @if(!empty($location))
                    <p class="text-14 lh-14 mb-5">{{ $location->name }}</p>
                @endif
                @if(!empty($attribute = $row->getAttributeInListingPage()))
                    @php
                        $translate_attribute =  $attribute->translate();
                        $termsByAttribute = $row->termsByAttributeInListingPage
                    @endphp
                    <div class="terms">
                        <div class="g-attributes">
                            <span class="attr-title"><i class="icofont-medal"></i> {{$translate_attribute->name ?? ""}}: </span>
                            @foreach($termsByAttribute as $term )
                                @php $translate_term = $term->translate() @endphp
                                <span class="item {{$term->slug}} term-{{$term->id}}">{{$translate_term->name}}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @php $terms_ids = $row->terms->pluck('term_id');
                 $attributes = \Modules\Core\Models\Terms::getTermsById($terms_ids);
                 $terms = [];
            @endphp
            @if(!empty($attributes))
                @foreach($attributes as $attr)
                    @php $terms = array_merge($terms,$attr['child']) @endphp
                @endforeach
            @endif
            @if(!empty($terms))
                <div class="row x-gap-10 y-gap-10 pt-20">
                    @foreach($terms as $k => $term)
                        @php if($k > 3) continue;
                            $translate_term = $term->translate()
                        @endphp
                        <div class="col-auto">
                            <div class="border-light rounded-100 py-5 px-20 text-14 lh-14">{{ $translate_term->name }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-auto text-right md:text-left">
        @if(setting_item('space_enable_review'))
            <?php $reviewData = $row->getScoreReview(); $score_total = $reviewData['score_total'];?>
            <div class="row x-gap-10 y-gap-10 justify-end items-center md:justify-start">
                <div class="col-auto">
                    <div class="text-14 lh-14 fw-500">{{ $reviewData['review_text'] }}</div>
                    <div class="text-14 lh-14 text-light-1">@if($reviewData['total_review'] > 1)
                            {{ __(":number Reviews",["number"=>$reviewData['total_review'] ]) }}
                        @else
                            {{ __(":number Review",["number"=>$reviewData['total_review'] ]) }}
                        @endif
                    </div>
                </div>
                <div class="col-auto">
                    <div class="flex-center text-white fw-600 text-14 size-40 rounded-4 bg-blue-1">{{ $reviewData['score_total'] }}</div>
                </div>
            </div>
        @endif
        <div class="text-14 text-light-1 mt-40 md:mt-20">{{__('From')}}</div>
        <div class="d-flex justify-content-md-end align-baseline mt-5">
            <div class="text-16 text-red-1 line-through mr-5">{{ $row->display_sale_price }}</div>
            <div class="text-22 lh-12 fw-600">{{ $row->display_price }}</div>
        </div>
        <div class="text-14 text-light-1 mt-5">{{ __('/night') }}</div>
        <a href="{{ $row->getDetailUrl() }}" class="button -md -dark-1 bg-blue-1 text-white mt-24">
            {{ __('See Availability') }} <div class="icon-arrow-top-right ml-15"></div>
        </a>
    </div>
</div>
