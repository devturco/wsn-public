<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\MiscellaneousController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MyfatoorahController;
use App\Http\Controllers\FrontEnd\PaymentGateway\XenditController;
use App\Http\Controllers\Payment\MyfatoorahController as PaymentMyfatoorahController;
use App\Http\Controllers\Payment\XenditController as PaymentXenditController;
use App\Models\BasicSettings\Basic;
use App\Models\Car;
use App\Models\Car\Brand;
use App\Models\Car\CarCondition;
use App\Models\Car\CarContent;
use App\Models\Car\CarModel;
use App\Models\Car\Category;
use App\Models\CounterSection;
use App\Models\HomePage\Banner;
use App\Models\HomePage\CategorySection;
use App\Models\HomePage\Section;
use App\Models\Journal\Blog;
use App\Models\HomePage\Partner;
use App\Models\Prominence\FeatureSection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Session;

class HomeController extends Controller
{
  public function index()
  {
    $themeVersion = Basic::query()->pluck('theme_version')->first();

    $secInfo = Section::query()->first();

    $misc = new MiscellaneousController();

    $language = $misc->getLanguage();

    $queryResult['language'] = $language;

    $queryResult['car_categories'] = Category::where('language_id', $language->id)->where('status', 1)->orderBy('serial_number', 'asc')->get();

    $queryResult['seoInfo'] = $language->seoInfo()->select('meta_keyword_home', 'meta_description_home')->first();


    $queryResult['sliderInfos'] = $language->sliderInfo()->orderByDesc('id')->get();

    if ($secInfo->about_section_status == 1) {
      $queryResult['aboutSectionImage'] = Basic::query()->pluck('about_section_image')->first();
      $queryResult['aboutSecInfo'] = $language->aboutSection()->first();
    }
    if ($themeVersion == 2) {
      $queryResult['categorySectionImage'] = Basic::query()->pluck('category_section_background')->first();
    }
    $queryResult['catgorySecInfo'] = CategorySection::where('language_id', $language->id)->first();
    $queryResult['featuredSecInfo'] = FeatureSection::where('language_id', $language->id)->first();

    if ($themeVersion == 1) {
      $queryResult['banners'] = Banner::where('language_id', $language->id)->get();
    }

    if ($secInfo->work_process_section_status == 1 && $themeVersion == 2) {
      $queryResult['workProcessSecInfo'] = $language->workProcessSection()->first();
      $queryResult['processes'] = $language->workProcess()->orderBy('serial_number', 'asc')->get();
    }

    if ($secInfo->counter_section_status == 1) {
      $queryResult['counterSectionImage'] = Basic::query()->pluck('counter_section_image')->first();
      $queryResult['counterSectionInfo'] = CounterSection::where('language_id', $language->id)->first();
      $queryResult['counters'] = $language->counterInfo()->orderByDesc('id')->get();
    }

    $queryResult['currencyInfo'] = $this->getCurrencyInfo();

    $min = Car::min('price');
    $max = Car::max('price');

    $queryResult['min'] = intval($min);
    $queryResult['max'] = intval($max);

    if ($secInfo->testimonial_section_status == 1) {
      $queryResult['testimonialSecInfo'] = $language->testimonialSection()->first();
      $queryResult['testimonials'] = $language->testimonial()->orderByDesc('id')->get();
      $queryResult['testimonialSecImage'] = Basic::query()->pluck('testimonial_section_image')->first();
    }

    if ($themeVersion != 1 && $secInfo->call_to_action_section_status == 1) {
      $queryResult['callToActionSectionImage'] = Basic::query()->pluck('call_to_action_section_image')->first();
      $queryResult['callToActionSecInfo'] = $language->callToActionSection()->first();
    }

    if ($secInfo->blog_section_status == 1) {
      $queryResult['blogSecInfo'] = $language->blogSection()->first();

      $queryResult['blogs'] = Blog::query()->join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
        ->join('blog_categories', 'blog_categories.id', '=', 'blog_informations.blog_category_id')
        ->where('blog_informations.language_id', '=', $language->id)
        ->select('blogs.image', 'blog_categories.name AS categoryName', 'blog_categories.slug AS categorySlug', 'blog_informations.title', 'blog_informations.slug', 'blog_informations.author', 'blogs.created_at', 'blog_informations.content')
        ->orderBy('blogs.serial_number', 'desc')
        ->limit(3)
        ->get();
    }

    $queryResult['cars'] = Car::join('car_contents', 'car_contents.car_id', 'cars.id')
      ->join('memberships', 'cars.vendor_id', '=', 'memberships.vendor_id')
      ->join('vendors', 'cars.vendor_id', '=', 'vendors.id')
      ->where([
        ['memberships.status', '=', 1],
        ['memberships.start_date', '<=', Carbon::now()->format('Y-m-d')],
        ['memberships.expire_date', '>=', Carbon::now()->format('Y-m-d')]
      ])
      ->where([['vendors.status', 1], ['cars.is_featured', 1], ['cars.status', 1]])
      ->where('car_contents.language_id', $language->id)
      ->inRandomOrder()
      ->limit(8)
      ->select('cars.*', 'car_contents.slug', 'car_contents.title', 'car_contents.car_model_id', 'car_contents.brand_id')
      ->get();

    $queryResult['car_conditions'] = CarCondition::where('language_id', $language->id)->where('status', 1)->orderBy('serial_number', 'asc')->get();

    $categories = Category::has('car_contents')->where('language_id', $language->id)->where('status', 1)->orderBy('serial_number', 'asc')->get();
    $queryResult['categories'] = $categories;


    $queryResult['brands'] = Brand::where('language_id', $language->id)->where('status', 1)->orderBy('serial_number', 'asc')->get();

    $queryResult['secInfo'] = $secInfo;

    if ($themeVersion == 1) {
      return view('frontend.home.index-v1', $queryResult);
    } elseif ($themeVersion == 2) {
      return view('frontend.home.index-v2', $queryResult);
    } elseif ($themeVersion == 3) {
      return view('frontend.home.index-v3', $queryResult);
    }
  }

  public function get_model(Request $request)
  {
    $slug = $request->id;

    $misc = new MiscellaneousController();
    $language = $misc->getLanguage();

    $car_brand = Brand::where([['slug', $slug], ['language_id', $language->id]])->first();
    if ($car_brand) {
      $models = CarModel::where([['brand_id', $car_brand->id], ['status', 1], ['language_id', $language->id]])->orderBy('serial_number', 'asc')->get();
      return $models;
    } else {
      return [];
    }
  }
  //about
  public function about()
  {
    $misc = new MiscellaneousController();

    $language = $misc->getLanguage();

    $queryResult['seoInfo'] = $language->seoInfo()->select('meta_keywords_about_page', 'meta_description_about_page')->first();

    $queryResult['pageHeading'] = $misc->getPageHeading($language);

    $queryResult['bgImg'] = $misc->getBreadcrumb();
    $secInfo = Section::query()->first();
    $queryResult['secInfo'] = $secInfo;

    if ($secInfo->work_process_section_status == 1) {
      $queryResult['workProcessSecInfo'] = $language->workProcessSection()->first();
      $queryResult['processes'] = $language->workProcess()->orderBy('serial_number', 'asc')->get();
    }

    if ($secInfo->testimonial_section_status == 1) {
      $queryResult['testimonialSecInfo'] = $language->testimonialSection()->first();
      $queryResult['testimonials'] = $language->testimonial()->orderByDesc('id')->get();
      $queryResult['testimonialSecImage'] = Basic::query()->pluck('testimonial_section_image')->first();
    }

    if ($secInfo->counter_section_status == 1) {
      $queryResult['counterSectionImage'] = Basic::query()->pluck('counter_section_image')->first();
      $queryResult['counterSectionInfo'] = CounterSection::where('language_id', $language->id)->first();
      $queryResult['counters'] = $language->counterInfo()->orderByDesc('id')->get();
    }

    return view('frontend.about', $queryResult);
  }

  //offline
  public function offline()
  {
    return view('frontend.offline');
  }

  public function myfatoorah_callback(Request $request)
  {
    $type = Session::get('myfatoorah_payment_type');
    if ($type == 'buy_plan') {
      $data = new PaymentMyfatoorahController();
      $data = $data->successCallback($request);
      // return redirect($data);
      Session::forget('myfatoorah_payment_type');
      if ($data['status'] == 'success') {
        return redirect()->route('success.page');
      } else {
        $cancel_url = Session::get('cancel_url');
        return redirect($cancel_url);
      }
    } elseif ($type == 'shop') {
      $data = new MyfatoorahController();
      $data = $data->successCallback($request);
      Session::forget('myfatoorah_payment_type');
      if ($data['status'] == 'success') {
        return redirect()->route('shop.purchase_product.complete');
      } else {
        return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment failed']);
      }
    }
  }

  public function myfatoorah_cancel(Request $request)
  {
    $type = Session::get('myfatoorah_payment_type');
    if ($type == 'buy_plan') {
      return redirect()->route('membership.cancel');
    } elseif ($type == 'shop') {
      return redirect()->route('shop.checkout')->with(['alert-type' => 'error', 'message' => 'Payment failed']);
    }
  }

  public function xendit_callback(Request $request)
  {
    return $request->all();
    if (Session::get('xendit_payment_type') == 'buy_plan') {
      $data = new PaymentXenditController();
      $data->callback($request);
    } elseif (Session::get('xendit_payment_type') == 'shop') {
      $data = new XenditController();
      $data->callback($request);
    }
  }
}
