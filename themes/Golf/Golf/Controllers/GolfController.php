<?php

namespace Themes\Golf\Golf\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Location\Models\Location;
use Modules\Location\Models\LocationCategory;
use Modules\Review\Models\Review;
use Modules\Core\Models\Attributes;
use DB;
use Themes\Golf\Golf\Models\Golf;

class GolfController extends Controller
{
    protected $goldClass;
    protected $locationClass;
    /**
     * @var string
     */
    private $locationCategoryClass;

    public function __construct(Golf $goldClass, Location $locationClass, LocationCategory $locationCategoryClass)
    {
        $this->goldClass = $goldClass;
        $this->locationClass = $locationClass;
        $this->locationCategoryClass = $locationCategoryClass;
    }

    public function callAction($method, $parameters)
    {
        if (!$this->goldClass::isEnable()) {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }

    public function index(Request $request)
    {
        $is_ajax = $request->query('_ajax');

        if (!empty($request->query('limit'))) {
            $limit = $request->query('limit');
        } else {
            $limit = !empty(setting_item("event_page_limit_item")) ? setting_item("event_page_limit_item") : 9;
        }

        $query = $this->goldClass->search($request->input());
        $list = $query->paginate($limit);

        $markers = [];
        if (!empty($list)) {
            foreach ($list as $row) {
                $markers[] = [
                    "id" => $row->id,
                    "title" => $row->title,
                    "lat" => (float)$row->map_lat,
                    "lng" => (float)$row->map_lng,
                    "gallery" => $row->getGallery(true),
                    "infobox" => view('Golf::frontend.layouts.search.loop-grid', ['row' => $row, 'disable_lazyload' => 1, 'wrap_class' => 'infobox-item'])->render(),
                    'marker' => get_file_url(setting_item("golf_icon_marker_map"), 'full') ?? url('images/icons/png/pin.png'),
                ];
            }
        }
        $limit_location = 15;
        if (empty(setting_item("golf_location_search_style")) or setting_item("golf_location_search_style") == "normal") {
            $limit_location = 1000;
        }
        $data = [
            'rows' => $list,
            'list_location' => $this->locationClass::where('status', 'publish')->limit($limit_location)->with(['translation'])->get()->toTree(),
            'golf_min_max_price' => $this->goldClass::getMinMaxPrice(),
            'markers' => $markers,
            "blank" => setting_item('search_open_tab') == "current_tab" ? 0 : 1,
            "seo_meta" => $this->goldClass::getSeoMetaForPageList()
        ];
        $layout = setting_item("golf_layout_search", 'normal');
        if ($request->query('_layout')) {
            $layout = $request->query('_layout');
        }
        $data['layout'] = $layout;
        if ($is_ajax) {
            return $this->sendSuccess([
                'html' => view('Golf::frontend.layouts.search-map.list-item', $data)->render(),
                "markers" => $data['markers']
            ]);
        }
        $data['attributes'] = Attributes::where('service', 'golf')->orderBy("position", "desc")->with(['terms' => function ($query) {
            $query->withCount('golf');
        }, 'translation'])->get();

        if ($layout == "map") {
            $data['body_class'] = 'has-search-map';
            $data['html_class'] = 'full-page';
            return view('Golf::frontend.search-map', $data);
        }
        return view('Golf::frontend.search', $data);
    }

    public function detail(Request $request, $slug)
    {
        $row = $this->goldClass::where('slug', $slug)->with(['location', 'translation', 'hasWishList'])->first();;
        if (empty($row) or !$row->hasPermissionDetailView()) {
            return redirect('/');
        }
        $translation = $row->translate();
        $golf_related = [];
        $location_id = $row->location_id;
        if (!empty($location_id)) {
            // $golf_related = $this->goldClass::where('location_id', $location_id)->where("status", "publish")->take(4)->whereNotIn('id', [$row->id])->with(['location', 'translation', 'hasWishList'])->get(); - only related city golf course by bookingcore
            
            //PPA added this code to get related golf course by country if the related city golf course hasn't 4 
            $locationCourses = $this->goldClass::where('location_id', $location_id)
            ->where('status', 'publish')
            ->take(4)
            ->whereNotIn('id', [$row->id])
            ->with(['location', 'translation', 'hasWishList'])
            ->get();
            // If there are less than 2 courses for the specified location, get additional courses from the same country
            $remainingCoursesCount = 4 - count($locationCourses);                
                if ($remainingCoursesCount > 0) {
                    $parentId = DB::table('bravo_locations')
                                ->where('id', $location_id)
                                ->value('parent_id');
                    $locationId=DB::table('bravo_locations')
                                ->where('parent_id', $parentId)
                                ->whereNotIn('id', [$location_id])
                                ->first();
                    $remainingCourses = $this->goldClass::where('location_id', $locationId->id)
                        ->where('status', 'publish')
                        ->take($remainingCoursesCount)
                        ->whereNotIn('id', [$row->id])
                        ->with(['location', 'translation', 'hasWishList'])
                        ->get();                
                    // Merge the courses from the location and country
                    $golf_related = $locationCourses->merge($remainingCourses);
                
                } else {
                    $golf_related = $locationCourses;
                }
        }
        $review_list = $row->getReviewList();
        $data = [
            'row' => $row,
            'translation' => $translation,
            'golf_related' => $golf_related,
            'location_category' => $this->locationCategoryClass::where("status", "publish")->with('location_category_translations')->get(),
            'booking_data' => $row->getBookingData(),
            'review_list' => $review_list,
            'seo_meta' => $row->getSeoMetaWithTranslation(app()->getLocale(), $translation),
            'body_class' => 'is_single',
            'breadcrumbs' => [
                [
                    'name' => __('Golf'),
                    'url' => route('golf.search'),
                ],
            ],
        ];
        $data['breadcrumbs'] = array_merge($data['breadcrumbs'], $row->locationBreadcrumbs());
        $data['breadcrumbs'][] = [
            'name' => $translation->title,
            'class' => 'active'
        ];
        $this->setActiveMenu($row);
        return view('Golf::frontend.detail', $data);
    }
}
