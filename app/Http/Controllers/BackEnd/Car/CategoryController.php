<?php

namespace App\Http\Controllers\BackEnd\Car;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\Car\Category;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        // first, get the language info from db
        $language = Language::where('code', $request->language)->firstOrFail();
        $information['language'] = $language;

        // then, get the equipment categories of that language from db
        $information['categories'] = $language->carCategory()->orderByDesc('id')->get();

        // also, get all the languages from db
        $information['langs'] = Language::all();

        return view('backend.car.category.index', $information);
    }

    public function store(Request $request)
    {
        $img = $request->file('image');
        $basicInfo = Basic::select('theme_version')->first();
        if ($basicInfo->theme_version == 1) {
            $width = '360';
            $height = '160';
        } elseif ($basicInfo->theme_version == 2) {
            $width = '290';
            $height = '158';
        } else {
            $width = '245';
            $height = '185';
        }

        $rules = [
            'language_id' => 'required',
            'name' => [
                'required',
                Rule::unique('categories')->where(function ($query) use ($request) {
                    return $query->where('language_id', $request->input('language_id'));
                }),
                'max:255',
            ],
            'image' => "required|",
            'image' => [
                "required",
                "dimensions:width=$width,height=$height",
            ],
            'status' => 'required|numeric',
            'serial_number' => 'required|numeric'
        ];

        $message = [
            'language_id.required' => 'The language field is required.'
        ];

        $validator = Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            return Response::json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $filename = uniqid() . '.jpg';
        $directory = public_path('assets/admin/img/car-category/');
        @mkdir($directory, 0775, true);
        $img->move($directory, $filename);

        $in = $request->all();
        $in['image'] = $filename;
        $in['slug'] = createSlug($request->name);

        Category::create($in);

        Session::flash('success', 'New Car category added successfully!');

        return Response::json(['status' => 'success'], 200);
    }

    public function update(Request $request)
    {
        $basicInfo = Basic::select('theme_version')->first();
        if ($basicInfo->theme_version == 1) {
            $width = '360';
            $height = '160';
        } elseif ($basicInfo->theme_version == 2) {
            $width = '290';
            $height = '158';
        } else {
            $width = '245';
            $height = '185';
        }
        $rules = [
            'name' => [
                'required',
                Rule::unique('categories')->where(function ($query) use ($request) {
                    return $query->where('language_id', $request->input('language_id'));
                })->ignore($request->id, 'id'),
                'max:255',
            ],
            'status' => 'required|numeric',
            'serial_number' => 'required|numeric'
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = "dimensions:width=$width,height=$height";
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return Response::json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $category = Category::find($request->id);

        $in = $request->all();

        if ($request->hasFile('image')) {
            @unlink(public_path('assets/admin/img/car-category/') . $category->image);
            $img = $request->file('image');
            $filename = uniqid() . '.jpg';
            $directory = public_path('assets/admin/img/car-category/');
            @mkdir($directory, 0775, true);
            $img->move($directory, $filename);
            $in['image'] = $filename;
        }


        $in['slug'] = createSlug($request->name);

        $category->update($in);

        Session::flash('success', 'Car category updated successfully!');

        return Response::json(['status' => 'success'], 200);
    }

    public function destroy($id)
    {
        $category = Category::find($id);
        $car_contents = $category->car_contents()->get();
        foreach ($car_contents as $car_content) {
            $car_content->delete();
        }
        @unlink(public_path('assets/admin/img/car-category/') . $category->image);
        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully!');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->ids;

        foreach ($ids as $id) {
            $category = Category::find($id);
            $car_contents = $category->car_contents()->get();
            foreach ($car_contents as $car_content) {
                $car_content->delete();
            }
            @unlink(public_path('assets/admin/img/car-category/') . $category->image);
            $category->delete();
        }
        Session::flash('success', 'Car categories deleted successfully!');

        return Response::json(['status' => 'success'], 200);
    }
}
